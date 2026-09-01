<?php
// Vercel Serverless Single Entrypoint Router
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$path = trim($uri, '/');

if ($path === '' || $path === 'index' || $path === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/../' . $path;

// 1. Direct PHP file match (e.g. login.php, change-password.php, logout.php)
if (is_file($target)) {
    if (str_ends_with($target, '.php')) {
        require $target;
        exit;
    }
}

// 2. Extensionless route match (e.g. /login -> login.php)
if (is_file($target . '.php')) {
    require $target . '.php';
    exit;
}

// 3. Directory match (e.g. /patient -> /patient/dashboard.php)
if (is_dir($target)) {
    if (is_file($target . '/dashboard.php')) {
        require $target . '/dashboard.php';
        exit;
    }
    if (is_file($target . '/index.php')) {
        require $target . '/index.php';
        exit;
    }
}

// 4. Default 404
http_response_code(404);
echo "404 Not Found";