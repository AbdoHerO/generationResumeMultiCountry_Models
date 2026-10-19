<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Turns exceptions into pages or JSON errors. Never leaks stack traces or SQL in production.
 */
final class ErrorHandler
{
    public static function render(\Throwable $e, Request $request): Response
    {
        if ($e instanceof HttpException) {
            $status = $e->status;
            $message = $e->getMessage();
            $errors = $e->errors;
            $code = $e->errorCode;
        } else {
            $status = 500;
            $code = 'server_error';
            $errors = [];
            $id = substr(bin2hex(random_bytes(6)), 0, 10);
            Logger::error('Unhandled exception', [
                'ref' => $id,
                'type' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path,
            ]);
            $message = config('app.debug')
                ? $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : HttpException::defaultMessage(500) . ' (reference ' . $id . ')';
        }

        if ($request->isJson()) {
            return Response::error($message, $status, $errors, $code);
        }
        if ($status === 401) {
            Session::put('intended', $request->path . ($request->query ? '?' . http_build_query($request->query) : ''));
            return Response::redirect(url('/login'));
        }
        if ($status === 419) {
            Session::flash('error', $message);
            $back = $request->header('Referer');
            return Response::redirect(self::sameOrigin($back, $request) ? $back : url('/'));
        }
        try {
            $view = in_array($status, [403, 404, 405, 429], true) ? 'errors/' . ($status === 405 ? 404 : $status) : 'errors/500';
            return Response::html(View::render($view, ['status' => $status, 'message' => $message], 'layouts/public'), $status);
        } catch (\Throwable) {
            return Response::html('<!doctype html><meta charset="utf-8"><title>Error</title><p>' . e($message) . '</p>', $status);
        }
    }

    private static function sameOrigin(string $url, Request $request): bool
    {
        if ($url === '') {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return $host === null || $host === ($request->server['HTTP_HOST'] ?? '');
    }
}
