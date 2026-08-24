<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Session Flash Notification Service for NOEI CMS Admin.
 */
class FlashService
{
    /**
     * Set a flash message in session.
     *
     * @param string $type 'success'|'error'|'warning'|'info'
     * @param string $message
     */
    public static function set(string $type, string $message): void
    {
        AuthService::startSession();
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    /**
     * Retrieve and clear the current flash message.
     *
     * @return array|null
     */
    public static function get(): ?array
    {
        AuthService::startSession();
        if (isset($_SESSION['_flash'])) {
            $flash = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            return $flash;
        }
        return null;
    }
}
