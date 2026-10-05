<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;

final class MailTransportFactory
{
    /**
     * MAIL_TRANSPORT=smtp (default) | log (development only).
     * A misconfiguration never throws here: it yields a transport that fails clearly, so queued
     * messages wait until the settings are fixed.
     */
    public static function fromConfig(Config $config, string $storageDir): MailTransport
    {
        $kind = strtolower($config->string('MAIL_TRANSPORT', 'smtp'));

        return match ($kind) {
            'smtp' => $config->string('SMTP_HOST') === ''
                ? new NotConfiguredTransport('SMTP_HOST non configurato: le email restano in coda.')
                : SmtpTransport::fromConfig($config),
            'log' => $config->isProduction()
                ? new NotConfiguredTransport('MAIL_TRANSPORT=log non è ammesso in produzione: le email restano in coda.')
                : new LogTransport($config->string('MAIL_LOG_DIR', $storageDir . '/mail')),
            default => new NotConfiguredTransport('MAIL_TRANSPORT non valido (usa smtp o log).'),
        };
    }
}
