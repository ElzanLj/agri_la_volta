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

/** Error text for a form field, linked to the input through aria-describedby (see invalid_attrs). */
function field_error(array $errors, string $field, string $id): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="' . e($id) . '">' . e($errors[$field]) . '</p>';
}

function invalid_attrs(array $errors, string $field, string $id): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($id) . '"' : '';
}

/** Value of a form field after a failed submission, falling back to a default. */
function old(array $values, string $field, string $default = ''): string
{
    $value = $values[$field] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

/** Fixed text of the public site in the language of the current request (content/it.php, content/en.php). */
function t(string $key, array $replace = []): string
{
    return \App\Site\Text::get($key, \App\Site\Locale::current(), $replace);
}

/** URL of a public page in the current language, e.g. lurl('apartment', ['slug' => 'rosa']). */
function lurl(string $route, array $params = [], array $query = []): string
{
    return \App\Site\Routes::url($route, \App\Site\Locale::current(), $params, $query);
}
