<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Post;
use App\Services\SlugService;
use Core\Request;
use Core\Response;

/**
 * Headless RESTful API Controller for Hierarchical Pages.
 */
class PageApiController
{
    private Post $postModel;

    public function __construct(?Post $postModel = null)
    {
        $this->postModel = $postModel ?? new Post();
    }

    /**
     * Get list of published pages.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $pages = $this->postModel->getAll('page', 'published', 50);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * Get single published page by ID or Slug.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function show(Request $request, array $params): Response
    {
        $idOrSlug = (string)($params['idOrSlug'] ?? '');
        $page = is_numeric($idOrSlug)
            ? $this->postModel->find((int)$idOrSlug)
            : $this->postModel->findBySlug($idOrSlug, 'page');

        if (!$page || $page['type'] !== 'page' || $page['status'] !== 'published') {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Page not found.'], 404);
        }

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $page,
        ]);
    }

    /**
     * Create new page (Protected).
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $title = trim((string)$request->input('title', ''));
        $content = (string)$request->input('content', '');
        $status = (string)$request->input('status', 'published');
        $parentId = (int)$request->input('parent_id', 0);
        $authorId = (int)$request->input('author_id', 1);

        if (empty($title)) {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Title is required.'], 422);
        }

        $slug = SlugService::uniqueSlug($title);

        $pageId = $this->postModel->create([
            'author_id' => $authorId,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'type' => 'page',
            'status' => $status,
            'parent_id' => $parentId,
        ]);

        $created = $this->postModel->find($pageId);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $created,
        ], 201);
    }

    /**
     * Update existing page (Protected).
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $page = $this->postModel->find($id);

        if (!$page || $page['type'] !== 'page') {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Page not found.'], 404);
        }

        $title = trim((string)$request->post('title', $page['title']));
        $content = (string)$request->post('content', $page['content']);
        $status = (string)$request->post('status', $page['status']);
        $parentId = (int)$request->post('parent_id', $page['parent_id']);

        $this->postModel->update($id, [
            'title' => $title,
            'slug' => $page['slug'],
            'content' => $content,
            'status' => $status,
            'parent_id' => $parentId,
        ]);

        $updated = $this->postModel->find($id);

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Delete page (Protected).
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $page = $this->postModel->find($id);

        if (!$page || $page['type'] !== 'page') {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'Page not found.'], 404);
        }

        $this->postModel->delete($id);

        $response = new Response();
        return $response->json([
            'success' => true,
            'message' => "Page [{$id}] deleted successfully.",
        ]);
    }
}
