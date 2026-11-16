<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; ".
                "img-src 'self' data: blob:; ".
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
                "font-src 'self' data: https://fonts.bunny.net https://cdn.jsdelivr.net; ".
                "script-src 'self' 'unsafe-inline'; ".
                "connect-src 'self' ws: wss:; ".
                "frame-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
            );
        }

        return $response;
    }
}
