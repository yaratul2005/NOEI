<?php

declare(strict_types=1);

namespace Core;

/**
 * Custom Post Types (CPT) Registration & Management Engine for NOEI CMS.
 */
class PostType
{
    /** @var array<string, array<string, mixed>> */
    private static array $types = [];
    private static bool $initialized = false;

    /**
     * Register a new Custom Post Type.
     *
     * @param string $type Post type key (lowercase, alphanumeric, dashes/underscores)
     * @param array<string, mixed> $args
     */
    public static function register(string $type, array $args = []): void
    {
        self::initDefaults();

        $key = strtolower(trim($type));
        $label = $args['label'] ?? ucfirst($key);
        $singular = $args['singular_label'] ?? $label;

        self::$types[$key] = array_merge([
            'type' => $key,
            'label' => $label,
            'singular_label' => $singular,
            'description' => '',
            'public' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'icon' => '📄',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        ], $args);
    }

    /**
     * Get all registered post types.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        self::initDefaults();
        return self::$types;
    }

    /**
     * Get metadata for a specific post type.
     *
     * @param string $type
     * @return array<string, mixed>|null
     */
    public static function get(string $type): ?array
    {
        self::initDefaults();
        return self::$types[strtolower(trim($type))] ?? null;
    }

    /**
     * Check if a post type is registered.
     *
     * @param string $type
     * @return bool
     */
    public static function isRegistered(string $type): bool
    {
        self::initDefaults();
        return isset(self::$types[strtolower(trim($type))]);
    }

    /**
     * Initialize core built-in post types (post, page).
     */
    private static function initDefaults(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::$types['post'] = [
            'type' => 'post',
            'label' => 'Posts',
            'singular_label' => 'Post',
            'description' => 'Chronological blog posts and news articles.',
            'public' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'icon' => '📝',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'taxonomies', 'revisions'],
        ];

        self::$types['page'] = [
            'type' => 'page',
            'label' => 'Pages',
            'singular_label' => 'Page',
            'description' => 'Hierarchical static pages.',
            'public' => true,
            'has_archive' => false,
            'hierarchical' => true,
            'icon' => '📄',
            'supports' => ['title', 'editor', 'thumbnail', 'parent', 'revisions'],
        ];
    }
}
