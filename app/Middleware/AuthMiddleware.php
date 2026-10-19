<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Requires a signed-in, active account (401 → login page or JSON error). */
final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            throw new HttpException(401, 'Your session has ended. Please sign in again.', [], 'unauthenticated');
        }
        return $next($request);
    }
}
