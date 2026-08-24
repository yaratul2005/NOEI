<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Media Data Model for NOEI CMS.
 * Handles database operations for user uploaded media files and responsive image metadata.
 */
class Media
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get paginated media items with optional MIME filter and search query.
     *
     * @param string|null $mimeFilter 'image'|'document'|'archive'|null
     * @param string|null $search
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll(?string $mimeFilter = null, ?string $search = null, int $limit = 40, int $offset = 0): array
    {
        $sql = "SELECT m.*, u.username as uploader_name 
                FROM cms_media m 
                LEFT JOIN cms_users u ON m.user_id = u.id 
                WHERE 1=1";

        $params = [];

        if ($mimeFilter === 'image') {
            $sql .= " AND m.mime_type LIKE 'image/%'";
        } elseif ($mimeFilter === 'document') {
            $sql .= " AND (m.mime_type LIKE '%pdf%' OR m.mime_type LIKE '%text%' OR m.mime_type LIKE '%document%')";
        } elseif ($mimeFilter === 'archive') {
            $sql .= " AND (m.mime_type LIKE '%zip%' OR m.mime_type LIKE '%tar%' OR m.mime_type LIKE '%compressed%')";
        }

        if (!empty($search)) {
            $sql .= " AND (m.filename LIKE :search OR m.file_path LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $sql .= " ORDER BY m.id DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Find a media item by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $res = $this->db->fetch(
            "SELECT m.*, u.username as uploader_name 
             FROM cms_media m 
             LEFT JOIN cms_users u ON m.user_id = u.id 
             WHERE m.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $res ?: null;
    }

    /**
     * Insert a new media record into database.
     *
     * @param array $data
     * @return int Media ID
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO cms_media (user_id, filename, file_path, mime_type, file_size, meta_data, created_at) 
                VALUES (:user_id, :filename, :file_path, :mime_type, :file_size, :meta_data, CURRENT_TIMESTAMP)";

        $metaDataJson = is_array($data['meta_data'] ?? null) ? json_encode($data['meta_data'], JSON_UNESCAPED_UNICODE) : ($data['meta_data'] ?? '{}');

        $this->db->execute($sql, [
            'user_id' => $data['user_id'],
            'filename' => $data['filename'],
            'file_path' => $data['file_path'],
            'mime_type' => $data['mime_type'],
            'file_size' => (int)$data['file_size'],
            'meta_data' => $metaDataJson,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update media title/alt text metadata.
     *
     * @param int $id
     * @param array $metaData
     * @return bool
     */
    public function updateMeta(int $id, array $metaData): bool
    {
        $media = $this->find($id);
        if (!$media) {
            return false;
        }

        $existingMeta = json_decode($media['meta_data'] ?? '{}', true) ?: [];
        $mergedMeta = array_merge($existingMeta, $metaData);

        $this->db->execute(
            "UPDATE cms_media SET meta_data = :meta WHERE id = :id",
            [
                'meta' => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE),
                'id' => $id,
            ]
        );

        return true;
    }

    /**
     * Delete media record from database.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM cms_media WHERE id = :id", ['id' => $id]);
        return true;
    }
}
