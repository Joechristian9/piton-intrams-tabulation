<?php

/**
 * Router for `php artisan serve` (Laravel uses this file instead of its own when
 * it exists). Same as Laravel's default — real files are served directly, every
 * other URL goes to public/index.php — plus what the bare built-in server lacks:
 *
 *  - caching headers, so judges' phones don't re-download the app and photos on
 *    every full page load (build files and uploaded photos never change in place,
 *    so they are cached for a year; other photos, logos and fonts for a day, then
 *    revalidated with a cheap 304 "not modified");
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
    // Build files and uploaded photos are never changed in place (a content hash or a
    // fresh random name each time), so phones keep them for a year and never ask
    // again. Other files (the original pageant's photos, logos, fonts) keep their
    // names when replaced: a day, then a cheap 304 check.
    header('Cache-Control: ' . (str_starts_with($uri, '/build/assets/') || str_starts_with($uri, '/uploads/candidates/')
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=86400'));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Vary: Accept-Encoding');

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);

        return true;
    }

    $compressible = in_array($extension, ['js', 'css', 'svg'], true);
    if ($compressible && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $body = gzencode(file_get_contents($file), 6);
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($body));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            echo $body;
        }

        return true;
    }

    // Images and fonts: stream straight from disk (no copy in memory; this server
    // handles one request at a time, so every millisecond here delays the next one).
    header('Content-Length: ' . $size);
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($file);
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
