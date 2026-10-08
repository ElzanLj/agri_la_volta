<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\App;
use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Response;
use App\Http\View;
use App\Repository\AdminQueryRepository;
use App\Security\AdminAuth;
use App\Security\RateLimiter;
use App\Security\ReauthGuard;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Shared helpers for the admin controllers. Authorization and CSRF are NOT handled here:
 * they are guards on the /admin prefix (see app/routes_admin.php), so no controller can skip them.
 */
abstract class BasePage
{
    public function __construct(protected App $app)
    {
    }

    protected function queries(): AdminQueryRepository
    {
        return new AdminQueryRepository($this->app->db());
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return View::render($template, $data + ['loggedIn' => true, 'flash' => Flash::pull()], $status, 'admin/layout');
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    /**
     * For a sensitive action: null when the admin confirmed the password in the last five minutes, otherwise the
     * redirect to the "confirm your password" page, which brings the person back to $returnTo (the page that
     * holds the action's form). See App\Security\ReauthGuard.
     */
    protected function needsReauth(string $returnTo): ?Response
    {
        $db = $this->app->db();
        if ((new ReauthGuard(new AdminAuth($db), new RateLimiter($db)))->isFresh()) {
            return null;
        }
        return $this->redirect('/admin/conferma-password?to=' . rawurlencode(ReauthGuard::safeTarget($returnTo)));
    }

    protected function notFound(): Response
    {
        return View::error(404, 'admin/layout');
    }

    /** Positive integer route parameter, or null (=> 404). @param array<string, string> $params */
    protected function id(array $params, string $key = 'id'): ?int
    {
        $value = $params[$key] ?? '';
        return preg_match('/^[1-9]\d{0,9}$/', $value) ? (int) $value : null;
    }

    protected function defaultCountryCode(): string
    {
        return $this->app->config->string('WHATSAPP_DEFAULT_COUNTRY_CODE', '39');
    }

    protected function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
    }

    /**
     * Runs a service call and turns its business errors into a flash message and a redirect.
     *
     * @param callable(): mixed $action
     * @param string|callable(mixed): string $successMessage
     * @param string|callable(mixed): string $successUrl
     */
    protected function attempt(callable $action, string|callable $successMessage, string|callable $successUrl, string $failureUrl): Response
    {
        try {
            $result = $action();
        } catch (ConflictException $e) {
            Flash::error(ErrorMessages::conflict($e));
            return $this->redirect($failureUrl);
        } catch (StateException $e) {
            Flash::error(ErrorMessages::forCode($e->reason));
            return $this->redirect($failureUrl);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', ErrorMessages::forFields($e->errors())));
            return $this->redirect($failureUrl);
        } catch (BusyException) {
            Flash::error('Il sistema è occupato: riprova tra qualche istante.');
            return $this->redirect($failureUrl);
        }

        Flash::ok(is_callable($successMessage) ? $successMessage($result) : $successMessage);
        return $this->redirect(is_callable($successUrl) ? $successUrl($result) : $successUrl);
    }

    /** @return array{page: int, pages: int, total: int} */
    protected function pagination(int $page, int $total, int $perPage): array
    {
        return ['page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'total' => $total];
    }
}
