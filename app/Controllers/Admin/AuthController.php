<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\FlashService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Authentication & User Profile Controller.
 */
class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Render the admin login view.
     *
     * @param Request $request
     * @return Response
     */
    public function showLogin(Request $request): Response
    {
        if ($this->auth->check()) {
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $html = View::render('admin/auth/login', [
            'title' => 'Admin Login - NOEI CMS',
        ]);

        return new Response($html);
    }

    /**
     * Authenticate user credentials.
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $usernameOrEmail = (string)$request->post('login', '');
        $password = (string)$request->post('password', '');

        if (empty($usernameOrEmail) || empty($password)) {
            FlashService::error('Username/Email and Password are required.');
            $response = new Response();
            return $response->redirect('/admin/login');
        }

        if ($this->auth->login($usernameOrEmail, $password)) {
            FlashService::success('Welcome back to NOEI CMS Admin!');
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        FlashService::error('Invalid login credentials or inactive account.');
        $response = new Response();
        return $response->redirect('/admin/login');
    }

    /**
     * Terminate user session and log out.
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $this->auth->logout();
        FlashService::success('You have been safely logged out.');
        $response = new Response();
        return $response->redirect('/admin/login');
    }

    /**
     * Render profile edit view for current user.
     *
     * @param Request $request
     * @return Response
     */
    public function showProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            $response = new Response();
            return $response->redirect('/admin/login');
        }

        $html = View::render('admin/auth/profile', [
            'title' => 'My Profile - NOEI CMS',
            'user' => $user,
            'currentRoute' => 'profile',
        ]);

        return new Response($html);
    }

    /**
     * Update current user profile and password.
     *
     * @param Request $request
     * @return Response
     */
    public function updateProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            $response = new Response();
            return $response->redirect('/admin/login');
        }

        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');

        if (empty($email)) {
            FlashService::error('Email address cannot be empty.');
            $response = new Response();
            return $response->redirect('/admin/profile');
        }

        $db = Database::getInstance();

        // Email uniqueness check excluding current user
        $existing = $db->fetch("SELECT id FROM cms_users WHERE email = :email AND id != :id LIMIT 1", [
            'email' => $email,
            'id' => $user['id'],
        ]);

        if ($existing) {
            FlashService::error('Email address is already in use by another account.');
            $response = new Response();
            return $response->redirect('/admin/profile');
        }

        if (!empty($password)) {
            if (strlen($password) < 8) {
                FlashService::error('Password must be at least 8 characters long.');
                $response = new Response();
                return $response->redirect('/admin/profile');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->execute("UPDATE cms_users SET email = :email, password_hash = :hash WHERE id = :id", [
                'email' => $email,
                'hash' => $hash,
                'id' => $user['id'],
            ]);
        } else {
            $db->execute("UPDATE cms_users SET email = :email WHERE id = :id", [
                'email' => $email,
                'id' => $user['id'],
            ]);
        }

        AuthService::clearUserCache();

        FlashService::success('Your profile details have been updated successfully.');
        $response = new Response();
        return $response->redirect('/admin/profile');
    }
}
