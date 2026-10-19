<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small router: exact segments plus {name} placeholders; per-route middleware.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    /** @var list<string> */
    private array $groupMiddleware = [];

    /** @param array{0: class-string, 1: string} $handler @param list<string> $middleware */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/', function (array $m): string {
            $constraint = $m[2] ?? ($m[1] === 'id' || str_ends_with($m[1], '_id') ? '[0-9]{1,10}' : '[^/]+');
            return '(?P<' . $m[1] . '>' . $constraint . ')';
        }, $pattern);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => array_values(array_merge($this->groupMiddleware, $middleware)),
        ];
    }

    /** @param array{0: class-string, 1: string} $h @param list<string> $m */
    public function get(string $p, array $h, array $m = []): void { $this->add('GET', $p, $h, $m); }
    /** @param array{0: class-string, 1: string} $h @param list<string> $m */
    public function post(string $p, array $h, array $m = []): void { $this->add('POST', $p, $h, $m); }

    /** @param list<string> $middleware */
    public function group(array $middleware, callable $define): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($previous, $middleware);
        $define($this);
        $this->groupMiddleware = $previous;
    }

    /**
     * @return array{0: array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<string>}, 1: array<string, string>}
     */
    public function match(Request $request): array
    {
        $allowed = [];
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return [$route, array_map('strval', $params)];
        }
        throw new HttpException($allowed !== [] ? 405 : 404);
    }
}
