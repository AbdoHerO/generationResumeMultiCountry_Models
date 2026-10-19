<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PasswordChangeMiddleware;

/**
 * Request lifecycle: session → (auto) migrations → route → middleware → controller → response.
 */
final class App
{
    /** @var array<string, class-string> */
    private const MIDDLEWARE = [
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'admin' => AdminMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'password' => PasswordChangeMiddleware::class,
    ];

    private static ?Router $router = null;

    public static function run(): void
    {
        $request = Request::capture();
        try {
            $response = self::handle($request);
        } catch (\Throwable $e) {
            $response = ErrorHandler::render($e, $request);
        }
        SecurityHeaders::apply($response, $request)->send();
    }

    public static function handle(Request $request): Response
    {
        Session::start($request);
        if (config('app.auto_migrate')) {
            Migrator::ensureCurrent();
        }
        [$route, $params] = self::router()->match($request);
        $request->params = $params;

        $middleware = $route['middleware'];
        if ($request->method !== 'GET' && $request->method !== 'HEAD' && !in_array('csrf', $middleware, true)) {
            array_unshift($middleware, 'csrf');
        }

        $core = function (Request $req) use ($route): Response {
            [$class, $method] = $route['handler'];
            $result = (new $class())->{$method}($req);
            return $result instanceof Response ? $result : Response::html((string) $result);
        };
        $next = array_reduce(array_reverse($middleware), function (callable $next, string $name): callable {
            $class = self::MIDDLEWARE[$name] ?? throw new \LogicException("Unknown middleware $name");
            return fn (Request $r): Response => (new $class())->handle($r, $next);
        }, $core);
        return $next($request);
    }

    public static function router(): Router
    {
        if (self::$router === null) {
            self::$router = new Router();
            (require BASE_PATH . '/routes/web.php')(self::$router);
        }
        return self::$router;
    }
}
