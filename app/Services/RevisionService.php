<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use RuntimeException;

/**
 * Revision History Snapshot & Restoration Service for NOEI CMS.
 */
class RevisionService
{
    /**
     * Create a revision snapshot of an existing post or page.
     *
     * @param int $postId
     * @param int $authorId
     * @return int Revision Post ID
     */
    public function createRevision(int $postId, int $authorId): int
    {
        $db = Database::getInstance();
        $original = $db->fetch("SELECT * FROM cms_posts WHERE id = :id LIMIT 1", ['id' => $postId]);

        if (!$original) {
            throw new RuntimeException("Original post [{$postId}] not found for revision creation.");
        }

        $sql = "INSERT INTO cms_posts (author_id, title, slug, content, excerpt, type, status, parent_id, created_at, updated_at) 
                VALUES (:author_id, :title, :slug, :content, :excerpt, 'revision', :status, :parent_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $db->execute($sql, [
            'author_id' => $authorId,
            'title' => $original['title'],
            'slug' => $original['slug'],
            'content' => $original['content'],
            'excerpt' => $original['excerpt'],
            'status' => $original['status'],
            'parent_id' => $postId,
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Get list of revisions for a specific post.
     *
     * @param int $postId
     * @return array
     */
    public function getRevisions(int $postId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT r.*, u.username as author_name 
             FROM cms_posts r 
             LEFT JOIN cms_users u ON r.author_id = u.id 
             WHERE r.parent_id = :parent_id AND r.type = 'revision' 
             ORDER BY r.id DESC",
            ['parent_id' => $postId]
        );
    }

    /**
     * Restore a post to a historical revision state.
     *
     * @param int $revisionId
     * @param int $restoredByUserId
     * @return bool
     */
    public function restoreRevision(int $revisionId, int $restoredByUserId): bool
    {
        $db = Database::getInstance();
        $revision = $db->fetch("SELECT * FROM cms_posts WHERE id = :id AND type = 'revision' LIMIT 1", ['id' => $revisionId]);

        if (!$revision) {
            return false;
        }

        $parentId = (int)$revision['parent_id'];
        $original = $db->fetch("SELECT * FROM cms_posts WHERE id = :id LIMIT 1", ['id' => $parentId]);

        if (!$original) {
            return false;
        }

        // Create safety snapshot of current state before restoration
        $this->createRevision($parentId, $restoredByUserId);

        // Restore target revision content to parent post
        $db->execute(
            "UPDATE cms_posts SET title = :title, content = :content, excerpt = :excerpt, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            [
                'title' => $revision['title'],
                'content' => $revision['content'],
                'excerpt' => $revision['excerpt'],
                'id' => $parentId,
            ]
        );

        return true;
    }
}
