<?php

/**
 * Router for `php artisan serve` (Laravel uses this file instead of its own when
 * it exists). Same as Laravel's default — real files are served directly, every
 * other URL goes to public/index.php — plus what the bare built-in server lacks:
 *
 *  - caching headers, so judges' phones don't re-download the app and photos on
 *    every full page load (build files never change name-for-content, so they
 *    are cached for a year; photos and fonts for an hour, then revalidated with
 *    a cheap 304 "not modified");
 *  - gzip for JS/CSS/SVG.
 *
 * Under Apache, public/.htaccess does the same job and this file isn't used.
 */
$publicPath = getcwd();
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$file = $publicPath . $uri;

if ($uri !== '/' && is_file($file)) {
    $types = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    // Anything else (robots.txt, manifest.json…): let the built-in server handle it.
    if (! isset($types[$extension])) {
        return false;
    }

    $mtime = filemtime($file);
    $size = filesize($file);
    $etag = sprintf('"%x-%x"', $mtime, $size);

    header('Content-Type: ' . $types[$extension]);
    header('Cache-Control: ' . (str_starts_with($uri, '/build/assets/')
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=3600'));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Vary: Accept-Encoding');

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);

        return true;
    }

    $body = file_get_contents($file);
    $compressible = in_array($extension, ['js', 'css', 'svg'], true);
    if ($compressible && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $body = gzencode($body, 6);
        header('Content-Encoding: gzip');
    }

    header('Content-Length: ' . strlen($body));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo $body;
    }

    return true;
}

if ($uri !== '/' && file_exists($file)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'];
file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath . '/index.php';
