<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 403);
        if (! $request->user()->hasVerifiedPartnerAccount()) return redirect()->route('account.pending');
        return $next($request);
    }
}