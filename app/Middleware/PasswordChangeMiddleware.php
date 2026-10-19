<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Accounts flagged must_change_password (the seeded administrator) can do nothing else until
 * they choose a new password.
 */
final class PasswordChangeMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1) {
            if ($request->isJson()) {
                throw new HttpException(403, 'Please choose a new password before continuing.', [], 'password_change_required');
            }
            return Response::redirect(url('/account/change-password'));
        }
        return $next($request);
    }
}
