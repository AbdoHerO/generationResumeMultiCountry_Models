<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Every state-changing request must carry the session's CSRF token, either as the `_token`
 * form field or as the X-CSRF-Token header (fetch requests).
 */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $request->header('X-CSRF-Token');
            if ($token === '') {
                $token = (string) ($request->post['_token'] ?? $request->json()['_token'] ?? '');
            }
            if (!Session::validCsrf($token)) {
                throw new HttpException(419, '', [], 'csrf');
            }
        }
        return $next($request);
    }
}
