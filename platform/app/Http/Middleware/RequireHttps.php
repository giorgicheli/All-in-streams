<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class RequireHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = parse_url((string) config('app.url'));

        if (app()->environment('production') && ! $request->isSecure()
            && ($url['scheme'] ?? null) === 'https' && isset($url['host'])) {
            $origin = 'https://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '');

            return new RedirectResponse($origin.$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
