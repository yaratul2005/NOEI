<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Exception;
use RuntimeException;

/**
 * Lightweight Regex-Based URL Router for NOEI CMS.
 * Supports dynamic path parameters, HTTP methods, and middleware pipelines.
 */
class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     handler: Closure|array|string,
     *     middlewares: array<int, Closure|string|object>
     * }>
     */
    private array $routes = [];

    /**
     * @var array<int, Closure|string|object>
     */
    private array $groupMiddlewares = [];

    private string $groupPrefix = '';

    /**
     * Register a route with the router.
     *
     * @param string $method
     * @param string $path
     * @param Closure|array|string $handler
     * @param array<int, Closure|string|object> $middlewares
     * @return self
     */
    public function addRoute(string $method, string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        $fullPath = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    public function get(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function patch(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function any(string $path, Closure|array|string $handler, array $middlewares = []): self
    {
        return $this->addRoute('ANY', $path, $handler, $middlewares);
    }

    /**
     * Define a group of routes sharing prefix and middlewares.
     *
     * @param string $prefix
     * @param Closure $callback
     * @param array $middlewares
     */
    public function group(string $prefix, Closure $callback, array $middlewares = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix = $previousPrefix . '/' . trim($prefix, '/');
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Dispatch an incoming Request against registered routes.
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

        $allowedMethods = [];
        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            $paramNames = [];
            $pattern = $this->convertPathToRegex($route['path'], $paramNames);

            if (preg_match($pattern, $requestPath, $matches)) {
                if ($route['method'] === 'ANY' || $route['method'] === $requestMethod) {
                    $matchedRoute = $route;

                    // Extract named parameters
                    foreach ($paramNames as $name) {
                        if (isset($matches[$name])) {
                            $params[$name] = urldecode($matches[$name]);
                        }
                    }
                    break;
                } else {
                    $allowedMethods[] = $route['method'];
                }
            }
        }

        if ($matchedRoute !== null) {
            return $this->executePipeline($request, $matchedRoute, $params);
        }

        if (!empty($allowedMethods)) {
            $response = new Response();
            return $response->json([
                'error' => 'Method Not Allowed',
                'allowed_methods' => array_values(array_unique($allowedMethods))
            ], 405);
        }

        $response = new Response();
        return $response->json([
            'error' => '404 Not Found',
            'path' => $requestPath
        ], 404);
    }

    /**
     * Convert path pattern with placeholders into a regular expression.
     * e.g. /posts/{id:\d+} or /posts/{slug}
     *
     * @param string $path
     * @param array &$paramNames
     * @return string
     */
    private function convertPathToRegex(string $path, array &$paramNames = []): string
    {
        $paramNames = [];

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)(?::([^}]+))?\}/',
            function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                $regex = isset($matches[2]) ? $matches[2] : '[^/]+';
                return '(?P<' . $matches[1] . '>' . $regex . ')';
            },
            $path
        );

        return '#^' . $pattern . '$#u';
    }

    /**
     * Execute the middleware pipeline and route handler.
     *
     * @param Request $request
     * @param array $route
     * @param array $params
     * @return Response
     */
    private function executePipeline(Request $request, array $route, array $params): Response
    {
        $middlewares = $route['middlewares'];
        $handler = $route['handler'];

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function ($next, $middleware) use ($params) {
                return function (Request $req) use ($next, $middleware, $params) {
                    if (is_callable($middleware)) {
                        return $middleware($req, $next, $params);
                    }
                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        if (method_exists($instance, 'handle')) {
                            return $instance->handle($req, $next, $params);
                        }
                    }
                    throw new RuntimeException("Invalid middleware specified.");
                };
            },
            function (Request $req) use ($handler, $params) {
                return $this->callHandler($handler, $req, $params);
            }
        );

        $result = $pipeline($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return new Response($result);
        }

        return new Response('', 204);
    }

    /**
     * Call controller or closure handler.
     *
     * @param Closure|array|string $handler
     * @param Request $request
     * @param array $params
     * @return mixed
     */
    private function callHandler(Closure|array|string $handler, Request $request, array $params): mixed
    {
        if ($handler instanceof Closure) {
            return $handler($request, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class) && class_exists($class)) {
                $class = new $class();
            }
            if (is_object($class) && method_exists($class, $method)) {
                return $class->$method($request, $params);
            }
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    return $instance->$method($request, $params);
                }
            }
        }

        throw new RuntimeException("Unable to resolve route handler.");
    }
}
