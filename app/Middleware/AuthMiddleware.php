<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Core\Request;
use Core\Response;

/**
 * Authentication Guard Middleware.
 * Redirects unauthenticated requests to the admin login page or returns HTTP 401 for API requests.
 */
class AuthMiddleware
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Intercept request and verify authentication.
     *
     * @param Request $request
     * @param callable $next
     * @param array $params
     * @return Response
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        if (!$this->auth->check()) {
            if ($request->isJson() || $request->isAjax() || str_starts_with($request->getPath(), '/api/')) {
                $response = new Response();
                return $response->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required to access this resource.'
                ], 401);
            }

            $response = new Response();
            return $response->redirect('/admin/login');
        }

        return $next($request);
    }
}
