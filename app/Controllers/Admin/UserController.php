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
 * User & RBAC Management Controller.
 */
class UserController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * List all system users.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        if (!$this->auth->can('manage_users')) {
            FlashService::error('Insufficient permissions to manage users.');
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $db = Database::getInstance();
        $users = $db->fetchAll("
            SELECT u.*, r.name as role_name, r.slug as role_slug 
            FROM cms_users u 
            LEFT JOIN cms_roles r ON u.role_id = r.id 
            ORDER BY u.id ASC
        ");

        $html = View::render('admin/users/index', [
            'title' => 'User Management - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'users',
            'users' => $users,
        ]);

        return new Response($html);
    }

    /**
     * Show create user form.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        if (!$this->auth->can('manage_users')) {
            FlashService::error('Insufficient permissions.');
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $db = Database::getInstance();
        $roles = $db->fetchAll("SELECT * FROM cms_roles ORDER BY id ASC");

        $html = View::render('admin/users/create', [
            'title' => 'Add New User - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'users',
            'roles' => $roles,
        ]);

        return new Response($html);
    }

    /**
     * Store new user in database.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        if (!$this->auth->can('manage_users')) {
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $username = trim((string)$request->post('username', ''));
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');
        $roleId = (int)$request->post('role_id', 5);
        $status = (string)$request->post('status', 'active');

        if (empty($username) || empty($email) || empty($password)) {
            FlashService::error('Username, Email, and Password are required.');
            $response = new Response();
            return $response->redirect('/admin/users/create');
        }

        if (strlen($password) < 8) {
            FlashService::error('Password must be at least 8 characters long.');
            $response = new Response();
            return $response->redirect('/admin/users/create');
        }

        $db = Database::getInstance();

        // Check unique username & email
        $existing = $db->fetch("SELECT id FROM cms_users WHERE username = :u OR email = :e LIMIT 1", [
            'u' => $username,
            'e' => $email,
        ]);

        if ($existing) {
            FlashService::error('Username or Email address is already taken.');
            $response = new Response();
            return $response->redirect('/admin/users/create');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute("INSERT INTO cms_users (username, email, password_hash, role_id, status) VALUES (:u, :e, :h, :r, :s)", [
            'u' => $username,
            'e' => $email,
            'h' => $hash,
            'r' => $roleId,
            's' => $status,
        ]);

        FlashService::success("User account [{$username}] created successfully.");
        $response = new Response();
        return $response->redirect('/admin/users');
    }

    /**
     * Show edit user form.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function edit(Request $request, array $params): Response
    {
        if (!$this->auth->can('manage_users')) {
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();

        $editUser = $db->fetch("SELECT * FROM cms_users WHERE id = :id LIMIT 1", ['id' => $id]);
        if (!$editUser) {
            FlashService::error('User not found.');
            $response = new Response();
            return $response->redirect('/admin/users');
        }

        $roles = $db->fetchAll("SELECT * FROM cms_roles ORDER BY id ASC");

        $html = View::render('admin/users/edit', [
            'title' => 'Edit User - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'users',
            'editUser' => $editUser,
            'roles' => $roles,
        ]);

        return new Response($html);
    }

    /**
     * Update existing user account.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function update(Request $request, array $params): Response
    {
        if (!$this->auth->can('manage_users')) {
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $id = (int)($params['id'] ?? 0);
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');
        $roleId = (int)$request->post('role_id', 5);
        $status = (string)$request->post('status', 'active');

        $db = Database::getInstance();
        $targetUser = $db->fetch("SELECT * FROM cms_users WHERE id = :id LIMIT 1", ['id' => $id]);

        if (!$targetUser) {
            FlashService::error('User account not found.');
            $response = new Response();
            return $response->redirect('/admin/users');
        }

        // Email uniqueness check
        $existing = $db->fetch("SELECT id FROM cms_users WHERE email = :e AND id != :id LIMIT 1", [
            'e' => $email,
            'id' => $id,
        ]);

        if ($existing) {
            FlashService::error('Email address is already in use by another account.');
            $response = new Response();
            return $response->redirect("/admin/users/{$id}/edit");
        }

        // Update parameters
        $paramsMap = [
            'email' => $email,
            'role_id' => $roleId,
            'status' => $status,
            'id' => $id,
        ];

        if (!empty($password)) {
            if (strlen($password) < 8) {
                FlashService::error('Password must be at least 8 characters long.');
                $response = new Response();
                return $response->redirect("/admin/users/{$id}/edit");
            }
            $paramsMap['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $db->execute("UPDATE cms_users SET email = :email, role_id = :role_id, status = :status, password_hash = :password_hash WHERE id = :id", $paramsMap);
        } else {
            $db->execute("UPDATE cms_users SET email = :email, role_id = :role_id, status = :status WHERE id = :id", $paramsMap);
        }

        FlashService::success("User account [{$targetUser['username']}] updated successfully.");
        $response = new Response();
        return $response->redirect('/admin/users');
    }

    /**
     * Delete user account with safety checks.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        if (!$this->auth->can('manage_users')) {
            $response = new Response();
            return $response->redirect('/admin/dashboard');
        }

        $id = (int)($params['id'] ?? 0);
        $currentUserId = $this->auth->id();

        // Safeguard 1: Self-deletion block
        if ($id === $currentUserId) {
            FlashService::error('You cannot delete your own active account while logged in.');
            $response = new Response();
            return $response->redirect('/admin/users');
        }

        $db = Database::getInstance();
        $targetUser = $db->fetch("SELECT u.*, r.slug as role_slug FROM cms_users u LEFT JOIN cms_roles r ON u.role_id = r.id WHERE u.id = :id LIMIT 1", ['id' => $id]);

        if (!$targetUser) {
            FlashService::error('User account not found.');
            $response = new Response();
            return $response->redirect('/admin/users');
        }

        // Safeguard 2: Last Administrator protection
        if ($targetUser['role_slug'] === 'administrator') {
            $adminCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_users u JOIN cms_roles r ON u.role_id = r.id WHERE r.slug = 'administrator' AND u.status = 'active'");
            if ($adminCount <= 1) {
                FlashService::error('Cannot delete the sole remaining Administrator account.');
                $response = new Response();
                return $response->redirect('/admin/users');
            }
        }

        $db->execute("DELETE FROM cms_users WHERE id = :id", ['id' => $id]);

        FlashService::success("User account [{$targetUser['username']}] deleted successfully.");
        $response = new Response();
        return $response->redirect('/admin/users');
    }
}
