<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Post & Page Data Model for NOEI CMS.
 * Handles database operations for posts, pages, batch eager loading, and post metadata.
 */
class Post
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get paginated list of posts/pages.
     *
     * @param string $type 'post'|'page'|'revision'
     * @param string|null $status 'published'|'draft'|'scheduled'|'private'
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll(string $type = 'post', ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.*, u.username as author_name, parent.title as parent_title 
                FROM cms_posts p 
                LEFT JOIN cms_users u ON p.author_id = u.id 
                LEFT JOIN cms_posts parent ON p.parent_id = parent.id 
                WHERE p.type = :type";

        $params = ['type' => $type];

        if ($status !== null && !empty($status)) {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get published posts with automatic eager loading of meta and taxonomies.
     *
     * @param string $type
     * @param int $limit
     * @param int $offset
     * @param bool $eagerLoad
     * @return array
     */
    public function getPublished(string $type = 'post', int $limit = 10, int $offset = 0, bool $eagerLoad = true): array
    {
        $posts = $this->getAll($type, 'published', $limit, $offset);

        if ($eagerLoad && !empty($posts)) {
            self::eagerLoadAll($posts);
        }

        return $posts;
    }

    /**
     * Batch eager-load all metadata for a collection of posts in a single query (solves N+1).
     *
     * @param array<int, array<string, mixed>> $posts
     */
    public static function eagerLoadMeta(array &$posts): void
    {
        global $_cms_post_meta_cache;
        if (empty($posts)) {
            return;
        }

        $ids = [];
        foreach ($posts as $p) {
            if (isset($p['id']) && is_numeric($p['id'])) {
                $ids[] = (int)$p['id'];
            }
        }

        if (empty($ids)) {
            return;
        }

        $ids = array_unique($ids);
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT post_id, meta_key, meta_value FROM cms_post_meta WHERE post_id IN ({$placeholders})";
        $rows = $db->fetchAll($sql, array_values($ids));

        $metaMap = [];
        foreach ($ids as $id) {
            $metaMap[$id] = [];
        }

        foreach ($rows as $row) {
            $pid = (int)$row['post_id'];
            $k = (string)$row['meta_key'];
            $raw = (string)$row['meta_value'];

            $decoded = json_decode($raw, true);
            $val = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) ? $decoded : $raw;

            $metaMap[$pid][$k] = $val;
            $_cms_post_meta_cache[$pid][$k] = $val;
        }

        foreach ($posts as &$post) {
            $pid = (int)($post['id'] ?? 0);
            $post['meta'] = $metaMap[$pid] ?? [];
        }
        unset($post);
    }

    /**
     * Batch eager-load all taxonomy terms (categories & tags) for a collection of posts in a single query.
     *
     * @param array<int, array<string, mixed>> $posts
     */
    public static function eagerLoadTaxonomies(array &$posts): void
    {
        if (empty($posts)) {
            return;
        }

        $ids = [];
        foreach ($posts as $p) {
            if (isset($p['id']) && is_numeric($p['id'])) {
                $ids[] = (int)$p['id'];
            }
        }

        if (empty($ids)) {
            return;
        }

        $ids = array_unique($ids);
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT tr.object_id, t.id as term_id, t.name, t.slug, tx.taxonomy 
                FROM cms_term_relationships tr 
                JOIN cms_taxonomies tx ON tr.taxonomy_id = tx.id 
                JOIN cms_terms t ON tx.term_id = t.id 
                WHERE tr.object_id IN ({$placeholders})";

        $rows = $db->fetchAll($sql, array_values($ids));

        $catMap = [];
        $tagMap = [];
        foreach ($ids as $id) {
            $catMap[$id] = [];
            $tagMap[$id] = [];
        }

        foreach ($rows as $row) {
            $pid = (int)$row['object_id'];
            if ($row['taxonomy'] === 'category') {
                $catMap[$pid][] = $row;
            } elseif ($row['taxonomy'] === 'tag') {
                $tagMap[$pid][] = $row;
            }
        }

        foreach ($posts as &$post) {
            $pid = (int)($post['id'] ?? 0);
            $post['categories'] = $catMap[$pid] ?? [];
            $post['tags'] = $tagMap[$pid] ?? [];
        }
        unset($post);
    }

    /**
     * Batch eager-load both metadata and taxonomy terms simultaneously.
     *
     * @param array<int, array<string, mixed>> $posts
     */
    public static function eagerLoadAll(array &$posts): void
    {
        self::eagerLoadMeta($posts);
        self::eagerLoadTaxonomies($posts);
    }

    /**
     * Find a post by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $user = $this->db->fetch(
            "SELECT p.*, u.username as author_name 
             FROM cms_posts p 
             LEFT JOIN cms_users u ON p.author_id = u.id 
             WHERE p.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $user ?: null;
    }

    /**
     * Find a post by slug and type.
     *
     * @param string $slug
     * @param string $type
     * @return array|null
     */
    public function findBySlug(string $slug, string $type = 'post'): ?array
    {
        $res = $this->db->fetch(
            "SELECT p.*, u.username as author_name 
             FROM cms_posts p 
             LEFT JOIN cms_users u ON p.author_id = u.id 
             WHERE p.slug = :slug AND p.type = :type LIMIT 1",
            ['slug' => $slug, 'type' => $type]
        );
        return $res ?: null;
    }

    /**
     * Create a new post or page.
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO cms_posts (author_id, title, slug, content, excerpt, type, status, parent_id, created_at, updated_at) 
                VALUES (:author_id, :title, :slug, :content, :excerpt, :type, :status, :parent_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $this->db->execute($sql, [
            'author_id' => $data['author_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'type' => $data['type'] ?? 'post',
            'status' => $data['status'] ?? 'draft',
            'parent_id' => (int)($data['parent_id'] ?? 0),
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing post or page.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE cms_posts 
                SET title = :title, slug = :slug, content = :content, excerpt = :excerpt, status = :status, parent_id = :parent_id, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";

        $this->db->execute($sql, [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'status' => $data['status'] ?? 'draft',
            'parent_id' => (int)($data['parent_id'] ?? 0),
            'id' => $id,
        ]);

        return true;
    }

    /**
     * Delete a post and its associated metadata/revisions.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM cms_term_relationships WHERE object_id = :id", ['id' => $id]);
        $this->db->execute("DELETE FROM cms_post_meta WHERE post_id = :id", ['id' => $id]);
        $this->db->execute("DELETE FROM cms_posts WHERE parent_id = :id AND type = 'revision'", ['id' => $id]);
        $this->db->execute("DELETE FROM cms_posts WHERE id = :id", ['id' => $id]);
        return true;
    }
}
