<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Taxonomy;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\SlugService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Taxonomy (Categories & Tags) Controller.
 */
class TaxonomyController
{
    private AuthService $auth;
    private Taxonomy $taxonomyModel;

    public function __construct(?AuthService $auth = null, ?Taxonomy $taxonomyModel = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
    }

    /**
     * Categories overview page.
     *
     * @param Request $request
     * @return Response
     */
    public function categories(Request $request): Response
    {
        $categories = $this->taxonomyModel->getCategories();

        $html = View::render('admin/taxonomies/categories', [
            'title' => 'Categories - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'categories' => $categories,
        ]);

        return new Response($html);
    }

    /**
     * Store new category.
     *
     * @param Request $request
     * @return Response
     */
    public function storeCategory(Request $request): Response
    {
        $name = trim((string)$request->post('name', ''));
        $description = (string)$request->post('description', '');
        $parentId = (int)$request->post('parent_id', 0);

        if (empty($name)) {
            FlashService::error('Category name is required.');
            $response = new Response();
            return $response->redirect('/admin/categories');
        }

        $slug = SlugService::slugify($name);
        $this->taxonomyModel->createTerm($name, $slug, 'category', $description, $parentId);

        FlashService::success("Category [{$name}] created successfully.");
        $response = new Response();
        return $response->redirect('/admin/categories');
    }

    /**
     * Delete category.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function deleteCategory(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $this->taxonomyModel->deleteTaxonomy($id);

        FlashService::success('Category deleted successfully.');
        $response = new Response();
        return $response->redirect('/admin/categories');
    }

    /**
     * Tags overview page.
     *
     * @param Request $request
     * @return Response
     */
    public function tags(Request $request): Response
    {
        $tags = $this->taxonomyModel->getTags();

        $html = View::render('admin/taxonomies/tags', [
            'title' => 'Tags - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'posts',
            'tags' => $tags,
        ]);

        return new Response($html);
    }

    /**
     * Store new tag.
     *
     * @param Request $request
     * @return Response
     */
    public function storeTag(Request $request): Response
    {
        $name = trim((string)$request->post('name', ''));
        $description = (string)$request->post('description', '');

        if (empty($name)) {
            FlashService::error('Tag name is required.');
            $response = new Response();
            return $response->redirect('/admin/tags');
        }

        $slug = SlugService::slugify($name);
        $this->taxonomyModel->createTerm($name, $slug, 'post_tag', $description, 0);

        FlashService::success("Tag [{$name}] created successfully.");
        $response = new Response();
        return $response->redirect('/admin/tags');
    }

    /**
     * Delete tag.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function deleteTag(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $this->taxonomyModel->deleteTaxonomy($id);

        FlashService::success('Tag deleted successfully.');
        $response = new Response();
        return $response->redirect('/admin/tags');
    }
}
