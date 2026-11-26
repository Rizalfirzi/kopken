<?php
/**
 * Local Development Router for PHP Built-in Server
 * Usage: php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Serve static files (assets, css, js, images, json) directly
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// Route API endpoints matching vercel.json & .htaccess
if (preg_match('#^/api/kopken/outlets/?$#', $uri)) {
    require __DIR__ . '/api/outlets.php';
    exit;
}

if (preg_match('#^/api/kopken/item/?$#', $uri)) {
    require __DIR__ . '/api/item.php';
    exit;
}

if (preg_match('#^/api/kopken/wifi/?$#', $uri)) {
    require __DIR__ . '/api/wifi.php';
    exit;
}

if (preg_match('#^/api/kopken/menu/([^/]+)/?$#', $uri, $matches)) {
    $_GET['outlet'] = $matches[1];
    require __DIR__ . '/api/menu.php';
    exit;
}

if (preg_match('#^/api/kopken/menu/?$#', $uri)) {
    require __DIR__ . '/api/menu.php';
    exit;
}

if (preg_match('#^/api/kopken/track/([^/]+)/?$#', $uri, $matches)) {
    $_GET['code'] = $matches[1];
    require __DIR__ . '/api/track.php';
    exit;
}

if (preg_match('#^/api/kopken/track/?$#', $uri)) {
    require __DIR__ . '/api/track.php';
    exit;
}

if (preg_match('#^/api/kopken/order/?$#', $uri)) {
    require __DIR__ . '/api/order.php';
    exit;
}

// Default fallback to index.php
require __DIR__ . '/index.php';
