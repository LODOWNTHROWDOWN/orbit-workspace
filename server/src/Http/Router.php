<?php
declare(strict_types=1);

namespace Orbit\Http;

/**
 * Tiny method + path router. Route placeholders like {id} match digits only
 * and are passed to the handler as an associative array.
 */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as [$method, $regex, $handler]) {
            if ($method === $request->method && preg_match($regex, $request->path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $handler($request, $params);
            }
        }
        throw new HttpException(404, 'Endpoint not found.');
    }
}
