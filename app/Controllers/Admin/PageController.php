<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\RevisionService;
use App\Services\SlugService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Hierarchical Page Management Controller.
 */
class PageController
{
    private AuthService $auth;
    private Post $postModel;
    private RevisionService $revisionService;

    public function __construct(
        ?AuthService $auth = null,
        ?Post $postModel = null,
        ?RevisionService $revisionService = null
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->postModel = $postModel ?? new Post();
        $this->revisionService = $revisionService ?? new RevisionService();
    }

    /**
     * List all pages.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $pages = $this->postModel->getAll('page');

        $html = View::render('admin/pages/index', [
            'title' => 'Manage Pages - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'pages',
            'pages' => $pages,
        ]);

        return new Response($html);
    }

    /**
     * Show create page form.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $parentPages = $this->postModel->getAll('page');

        $html = View::render('admin/pages/create', [
            'title' => 'Create New Page - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'pages',
            'parentPages' => $parentPages,
        ]);

        return new Response($html);
    }

    /**
     * Store new page.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $title = trim((string)$request->post('title', ''));
        $content = (string)$request->post('content', '');
        $status = (string)$request->post('status', 'published');
        $parentId = (int)$request->post('parent_id', 0);
        $user = $this->auth->user();

        if (empty($title)) {
            FlashService::error('Page title is required.');
            $response = new Response();
            return $response->redirect('/admin/pages/create');
        }

        $customSlug = (string)$request->post('slug', '');
        $slug = empty($customSlug) ? SlugService::uniqueSlug($title) : SlugService::uniqueSlug($customSlug);

        $this->postModel->create([
            'author_id' => $user['id'],
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'type' => 'page',
            'status' => $status,
            'parent_id' => $parentId,
        ]);

        FlashService::success("Page [{$title}] created successfully.");
        $response = new Response();
        return $response->redirect('/admin/pages');
    }

    /**
     * Show edit page form.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $page = $this->postModel->find($id);

        if (!$page || $page['type'] !== 'page') {
            FlashService::error('Page not found.');
            $response = new Response();
            return $response->redirect('/admin/pages');
        }

        $allPages = $this->postModel->getAll('page');
        $parentPages = array_filter($allPages, fn($p) => (int)$p['id'] !== $id);

        $html = View::render('admin/pages/edit', [
            'title' => "Edit Page: {$page['title']} - NOEI CMS",
            'user' => $this->auth->user(),
            'currentRoute' => 'pages',
            'page' => $page,
            'parentPages' => $parentPages,
        ]);

        return new Response($html);
    }

    /**
     * Update existing page.
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
            FlashService::error('Page not found.');
            $response = new Response();
            return $response->redirect('/admin/pages');
        }

        $title = trim((string)$request->post('title', ''));
        $content = (string)$request->post('content', '');
        $status = (string)$request->post('status', 'published');
        $parentId = (int)$request->post('parent_id', 0);
        $customSlug = (string)$request->post('slug', '');
        $user = $this->auth->user();

        if (empty($title)) {
            FlashService::error('Page title is required.');
            $response = new Response();
            return $response->redirect("/admin/pages/{$id}/edit");
        }

        // Revision snapshot
        $this->revisionService->createRevision($id, (int)$user['id']);

        $slug = empty($customSlug) ? SlugService::uniqueSlug($title, 'cms_posts', 'slug', $id) : SlugService::uniqueSlug($customSlug, 'cms_posts', 'slug', $id);

        $this->postModel->update($id, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
            'parent_id' => $parentId,
        ]);

        FlashService::success("Page [{$title}] updated successfully.");
        $response = new Response();
        return $response->redirect('/admin/pages');
    }

    /**
     * Delete page.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $page = $this->postModel->find($id);

        if ($page) {
            $this->postModel->delete($id);
            FlashService::success("Page [{$page['title']}] deleted successfully.");
        }

        $response = new Response();
        return $response->redirect('/admin/pages');
    }
}
