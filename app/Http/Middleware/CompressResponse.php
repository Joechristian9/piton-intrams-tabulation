<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gzips HTML and JSON pages (an Inertia page is 20–35 KB, about a fifth of that
 * compressed) for browsers that accept it. `php artisan serve` sends them raw;
 * server.php only compresses static files. Under Apache with mod_deflate the
 * response is already marked as encoded, so it isn't compressed twice.
 */
class CompressResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->compressible($request, $response)) {
            return $response;
        }

        $compressed = gzencode($response->getContent(), 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->setVary('Accept-Encoding', false);

        return $response;
    }

    private function compressible(Request $request, Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }
        if ($response->headers->has('Content-Encoding') || ! str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            return false;
        }

        $type = $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        return (str_contains($type, 'text/html') || str_contains($type, 'json'))
            && is_string($content) && strlen($content) >= self::MIN_BYTES;
    }
}
