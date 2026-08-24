<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\OptionService;
use Core\Request;
use Core\Response;

/**
 * Token & API Key Authentication Middleware for Protected REST API Endpoints.
 */
class ApiAuthMiddleware
{
    /**
     * Handle authentication check for protected API endpoints.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $validApiKey = (string)OptionService::get('api_key', '');

        // Extract token from Authorization: Bearer <token> or X-API-Key header
        $authHeader = $request->header('Authorization') ?? '';
        $token = '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }

        if (empty($token)) {
            $token = (string)($request->header('X-API-Key') ?? $request->get('api_key', ''));
        }

        // If no master API key is set, allow fallback default testing key or reject
        if (empty($validApiKey)) {
            $validApiKey = 'noei_default_secret_key';
        }

        if (empty($token) || !hash_equals($validApiKey, $token)) {
            $response = new Response();
            return $response->json([
                'success' => false,
                'error' => 'Invalid or missing API key. Provide Authorization: Bearer <token> or X-API-Key header.',
            ], 401);
        }

        return $next($request);
    }
}
