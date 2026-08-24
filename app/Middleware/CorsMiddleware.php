<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Request;
use Core\Response;

/**
 * Cross-Origin Resource Sharing (CORS) Middleware for NOEI Headless REST API.
 */
class CorsMiddleware
{
    /**
     * Handle incoming request and apply CORS headers or resolve OPTIONS preflight.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Intercept OPTIONS preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', 204);
            return $this->applyHeaders($response);
        }

        $response = $next($request);
        if ($response instanceof Response) {
            return $this->applyHeaders($response);
        }

        return new Response('', 500);
    }

    /**
     * Apply standard CORS headers to response.
     *
     * @param Response $response
     * @return Response
     */
    private function applyHeaders(Response $response): Response
    {
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key, X-Requested-With');
        $response->setHeader('Access-Control-Max-Age', '86400');
        return $response;
    }
}
