<?php
// Router for Exportable Architecture

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Serve Static Files from 'export/' folder
// (Since assets are now in export/assets, but browser requests /assets/...)
$exportFile = __DIR__ . '/export' . $uri;
if (file_exists($exportFile) && !is_dir($exportFile)) {
    $ext = strtolower(pathinfo($exportFile, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];

    if (isset($mimes[$ext])) {
        header("Content-Type: " . $mimes[$ext]);
    }
    readfile($exportFile);
    exit;
}

// 2. Admin Route
if ($uri === '/admin') {
    require 'admin/index.php';
    exit;
}

// 3. Product Route
if (preg_match('#^/product/(\d+)/?$#', $uri, $matches)) {
    $_GET['product_id'] = $matches[1];
    require 'export/product.php';
    exit;
}

// 4. Chat Route
if ($uri === '/chat') {
    require 'export/chat.php';
    exit;
}

// 5. Home Route
if ($uri === '/' || $uri === '/index.php') {
    require 'export/index.php';
    exit;
}

// 6. 404
http_response_code(404);
if (file_exists('export/404.php')) {
    require 'export/404.php';
} else {
    echo "404 Not Found";
}
