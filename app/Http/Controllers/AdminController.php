<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\AdminAuth;
use App\Security\Csrf;
use App\Security\RateLimiter;

final class AdminController
{
    private const LOGIN_BUCKET = 'admin_login';
    private const LOGIN_MAX_FAILURES = 5;
    private const LOGIN_WINDOW_SECONDS = 900;

    public function __construct(private App $app)
    {
    }

    public function dashboard(Request $request): Response
    {
        if (!AdminAuth::check()) {
            return self::private(Response::redirect(url('/admin/login')));
        }

        $db = $this->app->db();
        return $this->page('admin/dashboard', [
            'title' => 'Area amministrativa',
            'pendingRequests' => (int) $db->query("SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'")->fetchColumn(),
            'apartments' => (int) $db->query('SELECT COUNT(*) FROM apartments')->fetchColumn(),
        ]);
    }

    public function loginForm(Request $request): Response
    {
        if (AdminAuth::check()) {
            return self::private(Response::redirect(url('/admin')));
        }
        return $this->loginPage();
    }

    public function login(Request $request): Response
    {
        $username = trim($request->input('username'));

        if (!Csrf::isValid($request->input('_csrf'))) {
            return $this->loginPage('La sessione è scaduta. Ricarica la pagina e riprova.', $username, 400);
        }

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
        return self::private(Response::redirect(url('/admin')));
    }

    public function logout(Request $request): Response
    {
        if (Csrf::isValid($request->input('_csrf'))) {
            AdminAuth::logout();
        }
        return self::private(Response::redirect(url('/admin/login')));
    }

    private function loginPage(?string $error = null, string $username = '', int $status = 200): Response
    {
        return $this->page('admin/login', [
            'title' => 'Accesso amministratore',
            'error' => $error,
            'username' => $username,
        ], $status);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data, int $status = 200): Response
    {
        $data['loggedIn'] = AdminAuth::check();
        return self::private(View::render($template, $data, $status, 'admin/layout'));
    }

    /** Admin responses must never be indexed or cached. */
    private static function private(Response $response): Response
    {
        return $response
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'no-store');
    }
}
