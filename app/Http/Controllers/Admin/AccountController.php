<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Admin\Flash;
use App\Http\Request;
use App\Http\Response;
use App\Security\AdminAuth;
use App\Security\PasswordPolicy;
use App\Security\RateLimiter;
use App\Security\ReauthGuard;
use App\Service\AccountService;

/**
 * The Account page (password, "close all devices", recent accesses) and the "confirm your password" page used
 * by sensitive actions. Everything here sits behind the three guards of the /admin prefix.
 *
 * Passwords are read with rawInput() (never altered) and are never written to the history, the logs or a message.
 */
final class AccountController extends BasePage
{
    public function index(Request $request): Response
    {
        return $this->accountPage();
    }

    public function changePassword(Request $request): Response
    {
        $auth = new AdminAuth($this->app->db());
        $service = new AccountService($this->app->db());
        $adminId = $auth->adminId() ?? 0;
        $info = $auth->accessInfo();

        $current = $request->rawInput('current_password');
        $new = $request->rawInput('new_password');
        $repeat = $request->rawInput('new_password_confirm');

        // The new password is checked first: a form with an obvious mistake does not spend an attempt of the limit.
        $errors = [];
        if ($new === '') {
            $errors['new_password'] = 'Scrivi la nuova password.';
        } else {
            $problems = PasswordPolicy::problems($new, $info['username'], $service->currentHash($adminId));
            if ($problems !== []) {
                $errors['new_password'] = implode(' ', $problems);
            }
        }
        if ($repeat !== $new) {
            $errors['new_password_confirm'] = 'Le due password non coincidono.';
        }
        if ($current === '') {
            $errors['current_password'] = 'Scrivi la password attuale.';
        }
        if ($errors !== []) {
            return $this->accountPage($errors, 422);
        }

        $result = $this->reauth()->confirm($current, $request->ip());
        if ($result !== ReauthGuard::OK) {
            return $this->wrongPasswordPage($result);
        }

        $service->changePassword($adminId, $new);
        $auth->refreshCurrentSession(); // this session stays valid with the new password; every other one ends
        $this->app->logger->info('Admin password changed');
        Flash::ok('Password cambiata. Gli altri dispositivi collegati sono stati disconnessi.');
        return $this->redirect('/admin/account');
    }

    public function closeAllSessions(Request $request): Response
    {
        $auth = new AdminAuth($this->app->db());
        $current = $request->rawInput('close_current_password');
        if ($current === '') {
            return $this->accountPage(['close_current_password' => 'Scrivi la password attuale.'], 422);
        }

        $result = $this->reauth()->confirm($current, $request->ip());
        if ($result !== ReauthGuard::OK) {
            return $this->wrongPasswordPage($result, 'close_current_password');
        }

        (new AccountService($this->app->db()))->closeAllSessions($auth->adminId() ?? 0);
        $auth->refreshCurrentSession();
        $this->app->logger->info('Admin sessions closed');
        Flash::ok('Tutte le altre sessioni sono state chiuse. La password non è cambiata.');
        return $this->redirect('/admin/account');
    }

    // === Confirm the password before a sensitive action ======================

    public function confirmForm(Request $request): Response
    {
        $target = ReauthGuard::safeTarget($request->query('to'));
        if ($this->reauth()->isFresh()) {
            return $this->redirect($target); // already confirmed in the last five minutes: nothing to ask
        }
        return $this->confirmPage($target);
    }

    public function confirm(Request $request): Response
    {
        $target = ReauthGuard::safeTarget($request->input('to'));
        $password = $request->rawInput('password');
        if ($password === '') {
            return $this->confirmPage($target, 'Scrivi la tua password.', 422);
        }

        $result = $this->reauth()->confirm($password, $request->ip());
        if ($result === ReauthGuard::LIMITED) {
            $this->app->logger->warning('Admin password confirmation rate limited');
            return $this->confirmPage($target, 'Troppi tentativi non riusciti. Riprova tra qualche minuto.', 429);
        }
        if ($result === ReauthGuard::WRONG) {
            $this->app->logger->warning('Admin password confirmation failed');
            return $this->confirmPage($target, 'La password non è corretta.', 422);
        }
        return $this->redirect($target);
    }

    // === Pages ================================================================

    private function reauth(): ReauthGuard
    {
        $db = $this->app->db();
        return new ReauthGuard(new AdminAuth($db), new RateLimiter($db));
    }

    private function wrongPasswordPage(string $result, string $field = 'current_password'): Response
    {
        if ($result === ReauthGuard::LIMITED) {
            $this->app->logger->warning('Admin password confirmation rate limited');
            return $this->accountPage([$field => 'Troppi tentativi non riusciti. Riprova tra qualche minuto.'], 429);
        }
        $this->app->logger->warning('Admin password confirmation failed');
        return $this->accountPage([$field => 'La password attuale non è corretta.'], 422);
    }

    /** @param array<string, string> $errors messages by field */
    private function accountPage(array $errors = [], int $status = 200): Response
    {
        $db = $this->app->db();
        return $this->render('admin/account/index', [
            'title' => 'Account',
            'errors' => $errors,
            'info' => (new AdminAuth($db))->accessInfo(),
            'failedRecent' => (new RateLimiter($db))->countRecent(AdminAuth::FAILED_BUCKET, 86400),
            'minLength' => PasswordPolicy::MIN_LENGTH,
        ], $status);
    }

    private function confirmPage(string $target, ?string $error = null, int $status = 200): Response
    {
        return $this->render('admin/account/reauth', [
            'title' => 'Conferma la password',
            'target' => $target,
            'error' => $error,
            'minutes' => (int) (ReauthGuard::VALID_SECONDS / 60),
        ], $status);
    }
}
