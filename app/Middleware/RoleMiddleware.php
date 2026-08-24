<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Core\Request;
use Core\Response;

/**
 * Role & Capability Authorization Middleware.
 * Enforces role-based access control (RBAC) on routes.
 */
class RoleMiddleware
{
    private AuthService $auth;
    private string $requiredCapability;

    /**
     * @param string $requiredCapability Capability or role name required
     * @param AuthService|null $auth
     */
    public function __construct(string $requiredCapability = 'manage_options', ?AuthService $auth = null)
    {
        $this->requiredCapability = $requiredCapability;
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Handle incoming request and verify permissions.
     *
     * @param Request $request
     * @param callable $next
     * @param array $params
     * @return Response
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        if (!$this->auth->check()) {
            $response = new Response();
            return $response->redirect('/admin/login');
        }

        if (!$this->auth->can($this->requiredCapability)) {
            $response = new Response();
            if ($request->isJson() || $request->isAjax() || str_starts_with($request->getPath(), '/api/')) {
                return $response->json([
                    'error' => 'Forbidden',
                    'message' => 'Insufficient permissions to perform this action.'
                ], 403);
            }

            return $response->setContent(
                "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>403 Forbidden</title></head>" .
                "<body><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p></body></html>"
            )->setStatusCode(403);
        }

        return $next($request);
    }
}
