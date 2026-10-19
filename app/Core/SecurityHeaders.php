<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    public static function apply(Response $response, Request $request): Response
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if (str_starts_with($response->headers['Content-Type'] ?? '', 'text/html')) {
            $defaults['Content-Security-Policy'] = $csp;
        }
        if ($request->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=15552000';
        }
        foreach ($defaults as $name => $value) {
            if (!isset($response->headers[$name])) {
                $response->headers[$name] = $value;
            }
        }
        if (!isset($response->headers['Cache-Control']) && $response->status !== 304) {
            $response->headers['Cache-Control'] = 'no-store, private';
        }
        return $response;
    }
}
