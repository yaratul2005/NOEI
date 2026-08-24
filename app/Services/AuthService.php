<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Authentication and Session Management Service for NOEI CMS.
 * Handles user login, password verification, session regeneration, and RBAC permission checks.
 */
class AuthService
{
    private static ?array $currentUser = null;

    /**
     * Start session safely with secure cookie parameters if not already active.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

                session_set_cookie_params([
                    'lifetime' => 7200,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            @session_start();
        }
    }

    /**
     * Authenticate a user by username or email.
     *
     * @param string $emailOrUsername
     * @param string $password
     * @return bool
     */
    public function login(string $emailOrUsername, string $password): bool
    {
        self::startSession();

        $db = Database::getInstance();
        $sql = "SELECT u.*, r.slug as role_slug 
                FROM cms_users u 
                LEFT JOIN cms_roles r ON u.role_id = r.id 
                WHERE (u.email = :input OR u.username = :input) AND u.status = 'active' 
                LIMIT 1";

        $user = $db->fetch($sql, ['input' => trim($emailOrUsername)]);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Re-hash password if algorithm settings updated
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $db->execute("UPDATE cms_users SET password_hash = :hash WHERE id = :id", [
                'hash' => $newHash,
                'id' => $user['id'],
            ]);
            $user['password_hash'] = $newHash;
        }

        // Regenerate Session ID upon successful login
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role_id'] = (int)$user['role_id'];
        $_SESSION['user_role'] = $user['role_slug'] ?? 'subscriber';

        self::$currentUser = $user;

        return true;
    }

    /**
     * Log out current user and destroy session.
     */
    public function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$currentUser = null;
    }

    /**
     * Check if a user is currently authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }

    /**
     * Get the authenticated user's ID.
     *
     * @return int|null
     */
    public function id(): ?int
    {
        self::startSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Clear cached current user data.
     */
    public static function clearUserCache(): void
    {
        self::$currentUser = null;
    }

    /**
     * Get the current authenticated user data array.
     *
     * @param bool $forceRefresh
     * @return array|null
     */
    public function user(bool $forceRefresh = false): ?array
    {
        if (!$this->check()) {
            return null;
        }

        if (self::$currentUser !== null && !$forceRefresh) {
            return self::$currentUser;
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT u.*, r.name as role_name, r.slug as role_slug 
             FROM cms_users u 
             LEFT JOIN cms_roles r ON u.role_id = r.id 
             WHERE u.id = :id AND u.status = 'active' 
             LIMIT 1",
            ['id' => $_SESSION['user_id']]
        );

        if ($user) {
            self::$currentUser = $user;
            return $user;
        }

        return null;
    }

    /**
     * Check if current user has a specific capability or role.
     *
     * @param string $capability
     * @return bool
     */
    public function can(string $capability): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Administrator has unrestricted permissions
        if (($user['role_slug'] ?? '') === 'administrator') {
            return true;
        }

        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM cms_role_permission rp 
                JOIN cms_permissions p ON rp.permission_id = p.id 
                WHERE rp.role_id = :role_id AND (p.slug = :cap OR p.slug = '*')";

        $count = (int)$db->fetchColumn($sql, [
            'role_id' => $user['role_id'],
            'cap' => $capability,
        ]);

        return $count > 0;
    }
}
