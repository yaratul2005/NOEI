<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * URL Slug Generator with Multi-Byte Unicode (Bangla/English) and Collision Handling for NOEI CMS.
 */
class SlugService
{
    /**
     * Convert text into a clean URL-safe slug.
     *
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);
        if (empty($text)) {
            return 'n-a';
        }

        // Convert to lower case using mbstring
        $text = mb_strtolower($text, 'UTF-8');

        // Replace non-word / non-digit / non-mark unicode characters with single hyphen
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text);

        // Trim hyphens from start and end
        $slug = trim((string)$slug, '-');

        return empty($slug) ? 'n-a' : $slug;
    }

    /**
     * Generate a unique slug for a database table column, resolving collisions.
     *
     * @param string $title
     * @param string $table
     * @param string $field
     * @param int|null $ignoreId
     * @return string
     */
    public static function uniqueSlug(string $title, string $table = 'cms_posts', string $field = 'slug', ?int $ignoreId = null): string
    {
        $baseSlug = self::slugify($title);
        $slug = $baseSlug;
        $counter = 1;

        $db = Database::getInstance();

        while (true) {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$field}` = :slug";
            $params = ['slug' => $slug];

            if ($ignoreId !== null && $ignoreId > 0) {
                $sql .= " AND id != :ignore_id";
                $params['ignore_id'] = $ignoreId;
            }

            $count = (int)$db->fetchColumn($sql, $params);

            if ($count === 0) {
                return $slug;
            }

            $counter++;
            $slug = "{$baseSlug}-{$counter}";
        }
    }
}
