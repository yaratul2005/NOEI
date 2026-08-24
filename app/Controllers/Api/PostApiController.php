<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\SlugService;
use Core\Database;
use Core\Request;
use Core\Response;

/**
 * Headless RESTful API Controller for Posts.
 */
class PostApiController
{
    private Post $postModel;
    private Taxonomy $taxonomyModel;

    public function __construct(?Post $postModel = null, ?Taxonomy $taxonomyModel = null)
    {
        $this->postModel = $postModel ?? new Post();
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
    }

    /**
     * Get paginated list of published posts.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $perPage = max(1, min(100, (int)$request->get('per_page', 10)));
        $offset = ($page - 1) * $perPage;

        $db = Database::getInstance();
        $search = trim((string)$request->get('search', ''));
        $categorySlug = trim((string)$request->get('category', ''));

        $sql = "SELECT p.*, u.username as author_name 
                FROM cms_posts p 
                LEFT JOIN cms_users u ON p.author_id = u.id 
                WHERE p.type = 'post' AND p.status = 'published'";

        $countSql = "SELECT COUNT(DISTINCT p.id) FROM cms_posts p WHERE p.type = 'post' AND p.status = 'published'";
        $params = [];

        if (!empty($categorySlug)) {
            $sql .= " JOIN cms_term_relationships tr ON p.id = tr.object_id 
                      JOIN cms_taxonomies tax ON tr.taxonomy_id = tax.id 
                      JOIN cms_terms t ON tax.term_id = t.id 
                      AND t.slug = :cat_slug AND tax.taxonomy = 'category'";
            $countSql .= " JOIN cms_term_relationships tr ON p.id = tr.object_id 
                           JOIN cms_taxonomies tax ON tr.taxonomy_id = tax.id 
                           JOIN cms_terms t ON tax.term_id = t.id 
                           AND t.slug = :cat_slug AND tax.taxonomy = 'category'";
            $params['cat_slug'] = $categorySlug;
        }

        if (!empty($search)) {
            $sql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
            $countSql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $total = (int)$db->fetchColumn($countSql, $params);
        $totalPages = (int)ceil($total / $perPage);

        $sql .= " ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $posts = $db->fetchAll($sql, $params);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * Get single published post by ID or Slug.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function show(Request $request, array $params): Response
    {
        $idOrSlug = (string)($params['idOrSlug'] ?? '');
        $post = is_numeric($idOrSlug)
            ? $this->postModel->find((int)$idOrSlug)
            : $this->postModel->findBySlug($idOrSlug, 'post');

        if (!$post || $post['type'] !== 'post' || $post['status'] !== 'published') {
            $response = new Response();
            return $response->json([
                'success' => false,
                'error' => 'Post not found or is not published.',
            ], 404);
        }

        $taxonomyIds = $this->taxonomyModel->getObjectTaxonomyIds((int)$post['id']);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => array_merge($post, [
                'taxonomy_ids' => $taxonomyIds,
            ]),
        ]);
    }

    /**
     * Create a new post (Protected).
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $title = trim((string)$request->input('title', ''));
        $content = (string)$request->input('content', '');
        $excerpt = (string)$request->input('excerpt', '');
        $status = (string)$request->input('status', 'published');
        $authorId = (int)$request->input('author_id', 1);

        if (empty($title)) {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Title is required.'], 422);
        }

        $slug = SlugService::uniqueSlug($title);

        $postId = $this->postModel->create([
            'author_id' => $authorId,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'type' => 'post',
            'status' => $status,
        ]);

        $created = $this->postModel->find($postId);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $created,
        ], 201);
    }

    /**
     * Update an existing post (Protected).
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->postModel->find($id);

        if (!$post || $post['type'] !== 'post') {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Post not found.'], 404);
        }

        $title = trim((string)$request->post('title', $post['title']));
        $content = (string)$request->post('content', $post['content']);
        $excerpt = (string)$request->post('excerpt', $post['excerpt']);
        $status = (string)$request->post('status', $post['status']);

        $this->postModel->update($id, [
            'title' => $title,
            'slug' => $post['slug'],
            'content' => $content,
            'excerpt' => $excerpt,
            'status' => $status,
        ]);

        $updated = $this->postModel->find($id);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Delete a post (Protected).
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->postModel->find($id);

        if (!$post || $post['type'] !== 'post') {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Post not found.'], 404);
        }

        $this->postModel->delete($id);

        $response = new Response();
        return $response->json([
            'success' => true,
            'message' => "Post [{$id}] deleted successfully.",
        ]);
    }
}
