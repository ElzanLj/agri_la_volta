<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;

final class PageController
{
    /** Temporary placeholder until the public pages are built (Phase 5). */
    public function home(Request $request): Response
    {
        return View::render('pages/home', ['title' => 'Agriturismo La Volta', 'noindex' => true]);
    }
}
