<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Core\Request;
use Core\Response;

/**
 * Cross-Site Request Forgery (CSRF) Protection Middleware.
 * Automatically generates session tokens and validates all state-changing HTTP requests.
 */
class CsrfMiddleware
{
    /**
     * Generate or fetch the current CSRF token from session.
     *
     * @return string
     */
    public static function getToken(): string
    {
        AuthService::startSession();

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Generate hidden HTML form field for CSRF protection.
     *
     * @return string
     */
    public static function field(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
    }

    /**
     * Handle incoming request and enforce CSRF validation on mutating methods.
     *
     * @param Request $request
     * @param callable $next
     * @param array $params
     * @return Response
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        self::getToken(); // Ensures session CSRF token exists

        $method = $request->getMethod();
        $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (in_array($method, $protectedMethods, true)) {
            $sessionToken = $_SESSION['_csrf_token'] ?? '';
            $submittedToken = $request->input('_csrf_token') ?? $request->header('X-CSRF-TOKEN');

            if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, (string)$submittedToken)) {
                $response = new Response();
                return $response->json([
                    'error' => 'CSRF Token Validation Failed',
                    'message' => 'Invalid or expired security token. Please refresh the page and try again.'
                ], 403);
            }
        }

        return $next($request);
    }
}
