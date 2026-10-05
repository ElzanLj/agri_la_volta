<?php

declare(strict_types=1);

use App\App;
use App\Security\Csrf;

/** Escapes a value for HTML text and attribute contexts. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds an application URL path, honouring an installation in a subdirectory. */
function url(string $path = '/'): string
{
    return App::current()->basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}
