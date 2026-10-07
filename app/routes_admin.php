<?php

declare(strict_types=1);

use App\App;
use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\RequestController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\VerifyCsrf;
use App\Http\Router;

/*
 * Everything under /admin. Security does not depend on the individual routes below:
 * three guards cover the whole prefix, unknown URLs included (default deny).
 *
 *   1. PrivateResponse  every response is no-store / noindex
 *   2. RequireAdmin     authentication (only the login page is exempt)
 *   3. VerifyCsrf       token + Origin check on every non-GET/HEAD request (no exemptions)
 *
 * Rules for new routes: state changes are POST only; GET never modifies anything.
 */
return static function (Router $router, App $app): void {
    $router->guard('/admin', new PrivateResponse());
    $router->guard('/admin', new RequireAdmin($app), except: ['GET /admin/login', 'POST /admin/login']);
    $router->guard('/admin', new VerifyCsrf($app));

    $home = new AdminController($app);
    $requests = new RequestController($app);
    $bookings = new BookingController($app);
    $blocks = new BlockController($app);
    $calendar = new CalendarController($app);
    $apartments = new ApartmentController($app);
    $pricing = new PricingController($app);
    $audit = new AuditController($app);
    $export = new ExportController($app);
    $email = new EmailController($app);

    $router->get('/admin', [$home, 'dashboard']);
    $router->get('/admin/login', [$home, 'loginForm']);
    $router->post('/admin/login', [$home, 'login']);
    $router->post('/admin/logout', [$home, 'logout']);

    $router->get('/admin/richieste', [$requests, 'index']);
    $router->get('/admin/richieste/{id}', [$requests, 'show']);
    $router->post('/admin/richieste/{id}/conferma', [$requests, 'confirm']);
    $router->get('/admin/richieste/{id}/rifiuta', [$requests, 'rejectForm']); // confirmation page, writes nothing
    $router->post('/admin/richieste/{id}/rifiuta', [$requests, 'reject']);

    $router->get('/admin/prenotazioni', [$bookings, 'index']);
    $router->get('/admin/prenotazioni/nuova', [$bookings, 'newForm']);
    $router->post('/admin/prenotazioni', [$bookings, 'create']);
    $router->get('/admin/prenotazioni/{id}', [$bookings, 'show']);
    $router->get('/admin/prenotazioni/{id}/cancella', [$bookings, 'cancelForm']);
    $router->post('/admin/prenotazioni/{id}/cancella', [$bookings, 'cancel']);

    $router->get('/admin/prenotazioni/{id}/bozza-cancellazione', [$email, 'draftForm']);
    $router->post('/admin/prenotazioni/{id}/bozza-cancellazione', [$email, 'draftSend']);

    $router->get('/admin/email', [$email, 'index']);
    $router->post('/admin/email/{id}/riprova', [$email, 'retry']);

    $router->get('/admin/blocchi', [$blocks, 'index']);
    $router->post('/admin/blocchi', [$blocks, 'create']);
    $router->post('/admin/blocchi/{id}/rimuovi', [$blocks, 'remove']);

    $router->get('/admin/calendario', [$calendar, 'index']);

    $router->get('/admin/appartamenti', [$apartments, 'index']);
    $router->get('/admin/appartamenti/{id}', [$apartments, 'edit']);
    $router->post('/admin/appartamenti/{id}', [$apartments, 'update']);

    $router->get('/admin/listino', [$pricing, 'index']);
    $router->get('/admin/listino/tariffe/nuova', [$pricing, 'rateNew']);
    $router->post('/admin/listino/tariffe', [$pricing, 'rateCreate']);
    $router->get('/admin/listino/tariffe/{id}', [$pricing, 'rateEdit']);
    $router->post('/admin/listino/tariffe/{id}', [$pricing, 'rateUpdate']);
    $router->post('/admin/listino/tariffe/{id}/elimina', [$pricing, 'rateDelete']);
    $router->get('/admin/listino/regole/nuova', [$pricing, 'ruleNew']);
    $router->post('/admin/listino/regole', [$pricing, 'ruleCreate']);
    $router->get('/admin/listino/regole/{id}', [$pricing, 'ruleEdit']);
    $router->post('/admin/listino/regole/{id}', [$pricing, 'ruleUpdate']);
    $router->post('/admin/listino/regole/{id}/elimina', [$pricing, 'ruleDelete']);

    $router->get('/admin/storico', [$audit, 'index']);

    $router->get('/admin/export', [$export, 'index']);
    $router->get('/admin/export/richieste.csv', [$export, 'requests']);
    $router->get('/admin/export/prenotazioni.csv', [$export, 'bookings']);
};
