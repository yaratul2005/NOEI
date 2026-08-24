<?php

declare(strict_types=1);

namespace Core;

/**
 * PSR-4 Compliant Fallback Autoloader for NOEI CMS.
 * Enables zero-dependency execution on shared hosting without Composer.
 */
class Autoloader
{
    /**
     * @var array<string, string>
     */
    private static array $prefixes = [];

    /**
     * Register the autoloader with spl_autoload_register.
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);

        // Default namespace mappings
        self::addNamespace('Core\\', __DIR__);
        self::addNamespace('App\\', dirname(__DIR__) . '/app');

        // Load core global helper functions
        $helpersFile = __DIR__ . '/helpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }
    }

    /**
     * Add a base directory for a namespace prefix.
     *
     * @param string $prefix The namespace prefix.
     * @param string $baseDir The base directory for class files in the namespace.
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        self::$prefixes[$prefix] = $baseDir;
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $class The fully-qualified class name.
     * @return bool True if loaded successfully, false otherwise.
     */
    public static function loadClass(string $class): bool
    {
        $prefix = $class;

        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            if (isset(self::$prefixes[$prefix])) {
                $file = self::$prefixes[$prefix] . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require $file;
                    return true;
                }
            }

            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }
}
