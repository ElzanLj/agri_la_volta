<?php

declare(strict_types=1);

namespace Tests\Support;

use App\App;
use App\Mail\MailTransport;
use App\Mail\MessageBuilder;
use App\Mail\NotificationService;
use App\Mail\PostCommitNotifier;
use App\Service\BookingService;
use App\Support\Logger;

/** Wiring helpers for tests that need the mail pipeline with a chosen transport. */
trait MailTestHelpers
{
    private ?string $mailLogDir = null;
    /** @var array<string, string|false> */
    private array $savedEnv = [];

    /** Fixed mail-related settings so tests do not depend on the developer's .env. @param array<string, string> $extra */
    protected function setMailEnv(array $extra = []): void
    {
        $values = $extra + [
            'MAIL_ADMIN_ADDRESS' => 'gestore@example.test',
            'MAIL_FROM_ADDRESS' => 'info@example.test',
            'MAIL_FROM_NAME' => 'Agriturismo La Volta',
            'APP_URL' => 'https://sito.example.test',
        ];
        foreach ($values as $key => $value) {
            $this->savedEnv[$key] ??= getenv($key);
            putenv($key . '=' . $value);
        }
    }

    protected function restoreMailEnv(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            $value === false ? putenv($key) : putenv($key . '=' . $value);
        }
        $this->savedEnv = [];
    }

    /** Notification service on the test database with the given transport. @param list<string> $secrets */
    protected function notifications(MailTransport $transport, array $secrets = []): NotificationService
    {
        $config = App::current()->config;
        return new NotificationService(
            $this->db,
            $transport,
            new MessageBuilder($this->db, $config),
            $this->testLogger(),
            $secrets,
        );
    }

    /** A BookingService whose queued e-mails are sent after each commit through $transport. */
    protected function bookingServiceWithMail(MailTransport $transport, array $secrets = []): BookingService
    {
        $service = new BookingService($this->db, null, static fn (): string => '2027-01-10');
        $logger = $this->testLogger();
        $service->afterCommit(new PostCommitNotifier($this->notifications($transport, $secrets), $logger, false), $logger);
        return $service;
    }

    protected function testLogger(): Logger
    {
        $this->mailLogDir ??= sys_get_temp_dir() . '/lavolta-mail-test-logs-' . bin2hex(random_bytes(4));
        if (!is_dir($this->mailLogDir)) {
            mkdir($this->mailLogDir, 0775, true);
        }
        return new Logger($this->mailLogDir);
    }

    /** Everything the test logger wrote, as one string. */
    protected function loggedText(): string
    {
        $text = '';
        foreach (glob(($this->mailLogDir ?? '/nonexistent') . '/*.log') ?: [] as $file) {
            $text .= (string) file_get_contents($file);
        }
        return $text;
    }

    /** @return list<array<string, mixed>> */
    protected function outboxRows(string $where = '1=1', array $params = []): array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_outbox WHERE {$where} ORDER BY id");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
