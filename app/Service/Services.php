<?php

declare(strict_types=1);

namespace App\Service;

use App\App;
use App\Mail\MailTransport;
use App\Mail\MailTransportFactory;
use App\Mail\MessageBuilder;
use App\Mail\NotificationService;
use App\Mail\PostCommitNotifier;
use App\Repository\PricingRepository;

/**
 * Composition root for the services that need wiring (mail, notifications). Controllers and CLI
 * scripts ask it for a ready BookingService instead of building one, so the "send after commit"
 * hook is always attached.
 */
final class Services
{
    private ?MailTransport $transport;
    private ?NotificationService $notifications = null;

    public function __construct(private App $app, ?MailTransport $transport = null)
    {
        $this->transport = $transport;
    }

    public function transport(): MailTransport
    {
        return $this->transport ??= MailTransportFactory::fromConfig($this->app->config, BASE_PATH . '/storage');
    }

    public function notifications(): NotificationService
    {
        $config = $this->app->config;

        return $this->notifications ??= new NotificationService(
            $this->app->db(),
            $this->transport(),
            new MessageBuilder($this->app->db(), $config),
            $this->app->logger,
            [$config->string('SMTP_PASSWORD'), $config->string('SMTP_USERNAME')],
        );
    }

    /** Price list quoter used for the quotes shown to visitors (same one BookingService stores). */
    public function priceQuoter(): ConfiguredPriceQuoter
    {
        return new ConfiguredPriceQuoter(new PricingRepository($this->app->db()));
    }

    /** BookingService whose queued e-mails are sent after each commit. */
    public function bookingService(): BookingService
    {
        $service = new BookingService($this->app->db());
        $service->afterCommit(new PostCommitNotifier($this->notifications(), $this->app->logger), $this->app->logger);
        return $service;
    }
}
