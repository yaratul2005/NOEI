<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Site Options & Configuration Storage Engine for NOEI CMS.
 * Features single-query autoloading and in-memory caching.
 */
class OptionService
{
    private static ?array $cache = null;
    private static bool $booted = false;

    /**
     * Boot the option service and autoload core options into memory in a single query.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$cache = [];

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT option_name, option_value FROM cms_options WHERE autoload = 1");
            foreach ($rows as $row) {
                self::$cache[$row['option_name']] = $row['option_value'];
            }
            self::$booted = true;
        } catch (\Throwable $e) {
            // During installation or disconnected DB state, fail gracefully
            self::$cache = [];
        }
    }

    /**
     * Retrieve an option value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$booted) {
            self::boot();
        }

        if (array_key_exists($key, self::$cache ?? [])) {
            return self::$cache[$key];
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM cms_options WHERE option_name = :name LIMIT 1", ['name' => $key]);
            if ($val !== false && $val !== null) {
                self::$cache[$key] = $val;
                return $val;
            }
        } catch (\Throwable $e) {
            return $default;
        }

        return $default;
    }

    /**
     * Store or update an option in the database and cache.
     *
     * @param string $key
     * @param mixed $value
     * @param bool $autoload
     * @return bool
     */
    public static function set(string $key, mixed $value, bool $autoload = true): bool
    {
        if (!self::$booted) {
            self::boot();
        }

        $valStr = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        $autoloadInt = $autoload ? 1 : 0;

        try {
            $db = Database::getInstance();
            $exists = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_options WHERE option_name = :name", ['name' => $key]);

            if ($exists > 0) {
                $db->execute(
                    "UPDATE cms_options SET option_value = :val, autoload = :auto WHERE option_name = :name",
                    ['val' => $valStr, 'auto' => $autoloadInt, 'name' => $key]
                );
            } else {
                $db->execute(
                    "INSERT INTO cms_options (option_name, option_value, autoload) VALUES (:name, :val, :auto)",
                    ['name' => $key, 'val' => $valStr, 'auto' => $autoloadInt]
                );
            }

            self::$cache[$key] = $valStr;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an option from database and memory cache.
     *
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        try {
            $db = Database::getInstance();
            $db->execute("DELETE FROM cms_options WHERE option_name = :name", ['name' => $key]);
            unset(self::$cache[$key]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all cached options.
     *
     * @return array
     */
    public static function all(): array
    {
        if (!self::$booted) {
            self::boot();
        }
        return self::$cache ?? [];
    }

    /**
     * Reset memory cache (useful for testing or after bulk operations).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
        self::$booted = false;
    }
}
