<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\RevisionService;
use App\Services\SlugService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Post Management Controller.
 */
class PostController
{
    private AuthService $auth;
    private Post $postModel;
    private Taxonomy $taxonomyModel;
    private RevisionService $revisionService;

    public function __construct(
        ?AuthService $auth = null,
        ?Post $postModel = null,
        ?Taxonomy $taxonomyModel = null,
        ?RevisionService $revisionService = null
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->postModel = $postModel ?? new Post();
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
        $this->revisionService = $revisionService ?? new RevisionService();
    }

    /**
     * List all posts.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $status = $request->get('status');
        $posts = $this->postModel->getAll('post', $status ? (string)$status : null);

        $html = View::render('admin/posts/index', [
            'title' => 'Manage Posts - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'posts' => $posts,
            'currentStatus' => $status,
        ]);

        return new Response($html);
    }

    /**
     * Show create post form.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $categories = $this->taxonomyModel->getCategories();
        $tags = $this->taxonomyModel->getTags();

        $html = View::render('admin/posts/create', [
            'title' => 'Create New Post - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'categories' => $categories,
            'tags' => $tags,
        ]);

        return new Response($html);
    }

    /**
     * Store new post.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $title = trim((string)$request->post('title', ''));
        $content = (string)$request->post('content', '');
        $excerpt = (string)$request->post('excerpt', '');
        $status = (string)$request->post('status', 'draft');
        $user = $this->auth->user();

        if (empty($title)) {
            FlashService::error('Post title is required.');
            $response = new Response();
            return $response->redirect('/admin/posts/create');
        }

        $customSlug = (string)$request->post('slug', '');
        $slug = empty($customSlug) ? SlugService::uniqueSlug($title) : SlugService::uniqueSlug($customSlug);

        $postId = $this->postModel->create([
            'author_id' => $user['id'],
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'type' => 'post',
            'status' => $status,
        ]);

        // Sync selected categories
        $categories = (array)($request->post('categories') ?? []);
        $this->taxonomyModel->syncRelationships($postId, $categories);

        FlashService::success("Post [{$title}] created successfully.");
        $response = new Response();
        return $response->redirect('/admin/posts');
    }

    /**
     * Show edit post form.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->postModel->find($id);

        if (!$post || $post['type'] !== 'post') {
            FlashService::error('Post not found.');
            $response = new Response();
            return $response->redirect('/admin/posts');
        }

        $categories = $this->taxonomyModel->getCategories();
        $selectedCatIds = $this->taxonomyModel->getObjectTaxonomyIds($id);
        $revisionsCount = count($this->revisionService->getRevisions($id));

        $html = View::render('admin/posts/edit', [
            'title' => "Edit Post: {$post['title']} - NOEI CMS",
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'post' => $post,
            'categories' => $categories,
            'selectedCatIds' => $selectedCatIds,
            'revisionsCount' => $revisionsCount,
        ]);

        return new Response($html);
    }

    /**
     * Update existing post and generate revision snapshot.
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
            FlashService::error('Post not found.');
            $response = new Response();
            return $response->redirect('/admin/posts');
        }

        $title = trim((string)$request->post('title', ''));
        $content = (string)$request->post('content', '');
        $excerpt = (string)$request->post('excerpt', '');
        $status = (string)$request->post('status', 'draft');
        $customSlug = (string)$request->post('slug', '');
        $user = $this->auth->user();

        if (empty($title)) {
            FlashService::error('Post title is required.');
            $response = new Response();
            return $response->redirect("/admin/posts/{$id}/edit");
        }

        // Snapshot revision before updating
        $this->revisionService->createRevision($id, (int)$user['id']);

        $slug = empty($customSlug) ? SlugService::uniqueSlug($title, 'cms_posts', 'slug', $id) : SlugService::uniqueSlug($customSlug, 'cms_posts', 'slug', $id);

        $this->postModel->update($id, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'status' => $status,
        ]);

        // Sync selected categories
        $categories = (array)($request->post('categories') ?? []);
        $this->taxonomyModel->syncRelationships($id, $categories);

        FlashService::success("Post [{$title}] updated and revision saved.");
        $response = new Response();
        return $response->redirect('/admin/posts');
    }

    /**
     * Delete post.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->postModel->find($id);

        if ($post) {
            $this->postModel->delete($id);
            FlashService::success("Post [{$post['title']}] deleted successfully.");
        }

        $response = new Response();
        return $response->redirect('/admin/posts');
    }

    /**
     * View post revision history.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function revisions(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->postModel->find($id);

        if (!$post) {
            FlashService::error('Post not found.');
            $response = new Response();
            return $response->redirect('/admin/posts');
        }

        $revisions = $this->revisionService->getRevisions($id);

        $html = View::render('admin/posts/revisions', [
            'title' => "Revisions for: {$post['title']} - NOEI CMS",
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'post' => $post,
            'revisions' => $revisions,
        ]);

        return new Response($html);
    }

    /**
     * Restore post from revision.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function restoreRevision(Request $request, array $params): Response
    {
        $revisionId = (int)($params['revisionId'] ?? 0);
        $user = $this->auth->user();

        if ($this->revisionService->restoreRevision($revisionId, (int)$user['id'])) {
            FlashService::success("Post successfully restored to target revision.");
        } else {
            FlashService::error("Failed to restore revision.");
        }

        $response = new Response();
        return $response->redirect('/admin/posts');
    }
}
