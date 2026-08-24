<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Post & Page Data Model for NOEI CMS.
 * Handles database operations for posts, pages, and post metadata.
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
