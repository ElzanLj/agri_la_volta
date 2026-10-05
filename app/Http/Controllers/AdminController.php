<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Admin\Flash;
use App\Http\Controllers\Admin\BasePage;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\AdminAuth;
use App\Security\RateLimiter;

/**
 * Login, logout and dashboard. Login and logout are reached through the prefix guards like every
 * other admin route: POSTs are CSRF-checked before this code runs; only GET/POST /admin/login are
 * exempt from the authentication guard.
 */
final class AdminController extends BasePage
{
    private const LOGIN_BUCKET = 'admin_login';
    private const LOGIN_MAX_FAILURES = 5;
    private const LOGIN_WINDOW_SECONDS = 900;

    public function dashboard(Request $request): Response
    {
        return $this->render('admin/dashboard', [
            'title' => 'Area amministrativa',
            'counts' => $this->queries()->dashboardCounts($this->today()),
        ]);
    }

    public function loginForm(Request $request): Response
    {
        if ((new AdminAuth($this->app->db()))->isAuthenticated()) {
            return $this->redirect('/admin');
        }
        return $this->loginPage();
    }

    public function login(Request $request): Response
    {
        $username = trim($request->input('username'));

        $limiter = new RateLimiter($this->app->db());
        $client = $request->ip();
        if ($limiter->tooManyAttempts(self::LOGIN_BUCKET, $client, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_SECONDS)) {
            $this->app->logger->warning('Admin login rate limited');
            return $this->loginPage('Troppi tentativi non riusciti. Riprova tra qualche minuto.', $username, 429);
        }

        $password = $request->input('password');
        $auth = new AdminAuth($this->app->db());
        if ($username === '' || $password === '' || strlen($password) > 1024 || !$auth->attempt($username, $password)) {
            $limiter->hit(self::LOGIN_BUCKET, $client);
            $this->app->logger->warning('Admin login failed');
            return $this->loginPage('Nome utente o password non corretti.', $username, 422);
        }

        $limiter->clear(self::LOGIN_BUCKET, $client);
        $this->app->logger->info('Admin login succeeded');
        return $this->redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        AdminAuth::logout();
        return $this->redirect('/admin/login');
    }

    private function loginPage(?string $error = null, string $username = '', int $status = 200): Response
    {
        return View::render('admin/login', [
            'title' => 'Accesso amministratore',
            'error' => $error,
            'username' => $username,
            'loggedIn' => false,
            'flash' => [],
        ], $status, 'admin/layout');
    }
}
