<?php

// Router for PHP's built-in development server, so the notepad can be tried
// locally without Apache/Nginx:
//
//   php -S localhost:8000 router.php
//
// Then open http://localhost:8000 (set $base_url in index.php accordingly,
// e.g. $base_url = 'http://localhost:8000').

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['note'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// Static files (css, js, lib, images, favicon...) are served directly.
return false;
