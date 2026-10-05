<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

/**
 * Renders plain PHP templates from templates/. Templates must escape output with e().
 */
final class View
{
    private const ERROR_MESSAGES = [
        400 => ['Richiesta non valida', 'La richiesta non può essere elaborata.'],
        401 => ['Accesso richiesto', 'Devi accedere come amministratore per eseguire questa operazione.'],
        403 => ['Richiesta non consentita', 'Il modulo è scaduto o non è valido. Ricarica la pagina e riprova.'],
        404 => ['Pagina non trovata', 'La pagina richiesta non esiste o è stata spostata.'],
        405 => ['Metodo non consentito', 'Questa operazione non è consentita su questa pagina.'],
        413 => ['Richiesta troppo grande', 'I dati inviati superano la dimensione consentita.'],
        500 => ['Errore del server', 'Si è verificato un errore imprevisto. Riprova più tardi.'],
    ];

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], int $status = 200, ?string $layout = 'layout'): Response
    {
        $html = self::capture($template, $data);
        if ($layout !== null) {
            $html = self::capture($layout, ['content' => $html] + $data);
        }
        return Response::html($html, $status);
    }

    public static function error(int $status, string $layout = 'layout'): Response
    {
        [$title, $message] = self::ERROR_MESSAGES[$status] ?? self::ERROR_MESSAGES[500];
        return self::render('error', ['title' => $title, 'message' => $message, 'noindex' => true, 'loggedIn' => false, 'isAdminError' => $layout === 'admin/layout'], $status, $layout);
    }

    /** @param array<string, mixed> $data */
    public static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/templates/' . $template . '.php';
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $render($file, $data);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
