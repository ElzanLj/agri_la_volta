<?php

declare(strict_types=1);

/*
 * Router script for PHP's built-in server in tests. Like Apache with public/.htaccess, it serves
 * existing files (CSS, images) directly and sends every other request to the front controller.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__, 2) . '/public' . $path;

if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // let the server send the static file
}

require dirname(__DIR__, 2) . '/public/index.php';
