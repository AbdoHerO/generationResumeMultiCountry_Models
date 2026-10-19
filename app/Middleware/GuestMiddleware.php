<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Sign-in and registration pages are only for visitors. */
final class GuestMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return Response::redirect(url(Auth::user()['role'] === 'admin' ? '/admin' : '/dashboard'));
        }
        return $next($request);
    }
}
