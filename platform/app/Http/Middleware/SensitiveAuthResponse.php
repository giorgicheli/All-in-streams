<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SensitiveAuthResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::protect($next($request));
    }

    public static function protect(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, no-transform');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        return $response;
    }
}
