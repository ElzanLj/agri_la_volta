<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/**
 * Real SMTP delivery through PHPMailer. Credentials come from the environment only.
 * Every failure is turned into a MailTransportException with a category and a retry hint;
 * the exception text never contains the password or e-mail addresses.
 */
final class SmtpTransport implements MailTransport
{
    public function __construct(
        private string $host,
        private int $port,
        private string $encryption, // tls (STARTTLS) | ssl (implicit TLS) | none
        private string $username,
        private string $password,
        private int $timeoutSeconds,
        private string $fromAddress,
        private string $fromName,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->string('SMTP_HOST'),
            $config->int('SMTP_PORT', 587),
            strtolower($config->string('SMTP_ENCRYPTION', 'tls')),
            $config->string('SMTP_USERNAME'),
            $config->string('SMTP_PASSWORD'),
            max(1, $config->int('SMTP_TIMEOUT', 10)),
            $config->string('MAIL_FROM_ADDRESS'),
            $config->string('MAIL_FROM_NAME'),
        );
    }

    public function send(MailMessage $message): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new MailTransportException(MailTransportException::NOT_CONFIGURED, 'PHPMailer non installato: manca la cartella vendor/.', false);
        }
        if ($this->host === '') {
            throw new MailTransportException(MailTransportException::NOT_CONFIGURED, 'SMTP_HOST non configurato.', false);
        }
        if ($this->fromAddress === '') {
            throw new MailTransportException(MailTransportException::NOT_CONFIGURED, 'MAIL_FROM_ADDRESS non configurato.', false);
        }

        $mail = new PHPMailer(true);
        $started = microtime(true);
        /** @var list<int> $replies numeric reply codes the server sent, in order (never their text) */
        $replies = [];

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = $this->username !== '';
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            // PHPMailer clears its own error state with a clean-up RSET after a failure, so the server's
            // reply codes are captured while the conversation happens. Only the 3-digit codes are kept.
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = static function (string $line, int $level) use (&$replies): void {
                if (preg_match('/SERVER -> CLIENT: (\d{3})/', $line, $m) === 1) {
                    $replies[] = (int) $m[1];
                }
            };
            $mail->Timeout = $this->timeoutSeconds;
            $mail->getSMTPInstance()->Timelimit = $this->timeoutSeconds;
            $mail->SMTPKeepAlive = false;
            $mail->XMailer = ' '; // a blank (not empty) value suppresses the X-Mailer banner
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->isHTML(false);

            match ($this->encryption) {
                'ssl' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS,
                'none' => $this->disableTls($mail),
                default => $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS,
            };

            $mail->setFrom($this->fromAddress, MailMessage::oneLine($this->fromName), false);
            $mail->Sender = $this->fromAddress;
            $mail->addAddress($message->to);
            if ($message->replyTo !== null && $message->replyTo !== '') {
                $mail->addReplyTo($message->replyTo, (string) $message->replyToName);
            }
            $mail->Subject = $message->subject;
            $mail->Body = $message->body;

            $mail->send();
        } catch (PHPMailerException $e) {
            throw $this->classify($e->getMessage(), $replies, microtime(true) - $started);
        } catch (MailTransportException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Warnings raised by the stream layer on a broken connection arrive as ErrorException.
            $network = $e instanceof \ErrorException && preg_match('/fwrite|fread|stream|socket|broken pipe|timed out|connection/i', $e->getMessage()) === 1;
            throw new MailTransportException(
                $network ? MailTransportException::CONNECTION : MailTransportException::UNKNOWN,
                $network ? 'Connessione al server SMTP interrotta.' : 'Errore imprevisto durante l\'invio (' . $e::class . ').',
                true,
            );
        }
    }

    private function disableTls(PHPMailer $mail): void
    {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }

    /**
     * Maps a failure to a category using the last error reply (4xx/5xx) the server sent.
     * No reply at all means the conversation never worked: a timeout if we waited the whole
     * time, otherwise a connection problem. Server texts are never copied (they can contain
     * addresses): only the numeric code is reported.
     *
     * @param list<int> $replies
     */
    private function classify(string $phpMailerMessage, array $replies, float $elapsed): MailTransportException
    {
        $errors = array_values(array_filter($replies, static fn (int $c): bool => $c >= 400));
        $code = $errors === [] ? 0 : $errors[count($errors) - 1];
        $recipientsFailed = stripos($phpMailerMessage, 'recipients failed') !== false;

        if ($code === 0) {
            if (stripos($phpMailerMessage, 'invalid address') !== false) {
                return new MailTransportException(MailTransportException::RECIPIENT_REJECTED, 'Indirizzo del destinatario non valido.', false);
            }
            if ($elapsed >= $this->timeoutSeconds * 0.8) {
                return new MailTransportException(MailTransportException::TIMEOUT, 'Il server SMTP non ha risposto entro ' . $this->timeoutSeconds . ' secondi.', true);
            }
            return new MailTransportException(MailTransportException::CONNECTION, 'Connessione al server SMTP non riuscita o interrotta.', true);
        }

        if (in_array($code, [530, 534, 535, 538], true) || stripos($phpMailerMessage, 'authenticate') !== false) {
            return new MailTransportException(MailTransportException::AUTH_FAILED, 'Autenticazione SMTP rifiutata (codice ' . $code . ').', false);
        }
        if ($code < 500) {
            return new MailTransportException(MailTransportException::TEMPORARY, 'Il server SMTP ha chiesto di riprovare più tardi (codice ' . $code . ').', true);
        }
        if ($recipientsFailed) {
            return new MailTransportException(MailTransportException::RECIPIENT_REJECTED, 'Il server SMTP ha rifiutato il destinatario (codice ' . $code . ').', false);
        }
        return new MailTransportException(MailTransportException::REJECTED, 'Il server SMTP ha rifiutato il messaggio (codice ' . $code . ').', false);
    }
}
