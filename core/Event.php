<?php

declare(strict_types=1);

namespace Core;

/**
 * Event and Hook Dispatcher for NOEI CMS.
 * Implements Action hooks (execution triggers) and Filter hooks (data transformers) for extensible module plugins.
 */
class Event
{
    /**
     * @var array<string, array<int, array<int, callable>>>
     */
    private static array $actions = [];

    /**
     * @var array<string, array<int, array<int, callable>>>
     */
    private static array $filters = [];

    /**
     * Register an Action callback.
     *
     * @param string $hook Name of the action event
     * @param callable $callback Function to execute
     * @param int $priority Execution priority (lower numbers run earlier)
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    /**
     * Trigger an Action hook with arguments.
     *
     * @param string $hook Name of the action event
     * @param mixed ...$args Arguments passed to listeners
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        ksort(self::$actions[$hook]);

        foreach (self::$actions[$hook] as $listeners) {
            foreach ($listeners as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Register a Filter callback.
     *
     * @param string $hook Name of the filter event
     * @param callable $callback Function that transforms input value
     * @param int $priority Execution priority (lower numbers run earlier)
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * Apply Filter callbacks sequentially to transform a value.
     *
     * @param string $hook Name of the filter event
     * @param mixed $value Value to be filtered
     * @param mixed ...$args Additional contextual parameters
     * @return mixed Transformed value
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        ksort(self::$filters[$hook]);

        foreach (self::$filters[$hook] as $listeners) {
            foreach ($listeners as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    /**
     * Clear all registered actions and filters (useful for testing).
     */
    public static function clearAll(): void
    {
        self::$actions = [];
        self::$filters = [];
    }
}
