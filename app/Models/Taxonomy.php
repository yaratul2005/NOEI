<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Taxonomy (Categories & Tags) Data Model for NOEI CMS.
 */
class Taxonomy
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get terms for a specific taxonomy (e.g., 'category' or 'post_tag').
     *
     * @param string $taxonomy
     * @return array
     */
    public function getTerms(string $taxonomy = 'category'): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, tax.id as taxonomy_id, tax.taxonomy, tax.description, tax.parent_id, tax.count 
             FROM cms_terms t 
             JOIN cms_taxonomies tax ON t.id = tax.term_id 
             WHERE tax.taxonomy = :tax 
             ORDER BY t.name ASC",
            ['tax' => $taxonomy]
        );
    }

    /**
     * Get list of Categories.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return $this->getTerms('category');
    }

    /**
     * Get list of Tags.
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->getTerms('post_tag');
    }

    /**
     * Create a new Term and Taxonomy mapping.
     *
     * @param string $name
     * @param string $slug
     * @param string $taxonomy
     * @param string $description
     * @param int $parentId
     * @return int Taxonomy ID
     */
    public function createTerm(string $name, string $slug, string $taxonomy = 'category', string $description = '', int $parentId = 0): int
    {
        $this->db->execute("INSERT INTO cms_terms (name, slug) VALUES (:name, :slug)", [
            'name' => $name,
            'slug' => $slug,
        ]);
        $termId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO cms_taxonomies (term_id, taxonomy, description, parent_id, count) VALUES (:term_id, :taxonomy, :description, :parent_id, 0)",
            [
                'term_id' => $termId,
                'taxonomy' => $taxonomy,
                'description' => $description,
                'parent_id' => $parentId,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Delete a Taxonomy entry.
     *
     * @param int $taxonomyId
     * @return bool
     */
    public function deleteTaxonomy(int $taxonomyId): bool
    {
        $tax = $this->db->fetch("SELECT * FROM cms_taxonomies WHERE id = :id LIMIT 1", ['id' => $taxonomyId]);
        if ($tax) {
            $this->db->execute("DELETE FROM cms_term_relationships WHERE taxonomy_id = :id", ['id' => $taxonomyId]);
            $this->db->execute("DELETE FROM cms_taxonomies WHERE id = :id", ['id' => $taxonomyId]);
            $this->db->execute("DELETE FROM cms_terms WHERE id = :id", ['id' => $tax['term_id']]);
        }
        return true;
    }

    /**
     * Attach taxonomy IDs to an object (Post/Page).
     *
     * @param int $objectId
     * @param array<int> $taxonomyIds
     */
    public function syncRelationships(int $objectId, array $taxonomyIds): void
    {
        $this->db->execute("DELETE FROM cms_term_relationships WHERE object_id = :id", ['id' => $objectId]);

        foreach ($taxonomyIds as $taxId) {
            $taxId = (int)$taxId;
            if ($taxId > 0) {
                $this->db->execute("INSERT INTO cms_term_relationships (object_id, taxonomy_id) VALUES (:obj, :tax)", [
                    'obj' => $objectId,
                    'tax' => $taxId,
                ]);
            }
        }
    }

    /**
     * Get attached taxonomy IDs for an object.
     *
     * @param int $objectId
     * @return array<int>
     */
    public function getObjectTaxonomyIds(int $objectId): array
    {
        $rows = $this->db->fetchAll("SELECT taxonomy_id FROM cms_term_relationships WHERE object_id = :id", ['id' => $objectId]);
        return array_map(fn($row) => (int)$row['taxonomy_id'], $rows);
    }
}
