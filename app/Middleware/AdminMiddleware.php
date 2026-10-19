<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Administrator-only routes. Checked on the server for every request. */
final class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            throw new HttpException(401);
        }
        if ((Auth::user()['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'This area is reserved for administrators.', [], 'forbidden');
        }
        return $next($request);
    }
}
