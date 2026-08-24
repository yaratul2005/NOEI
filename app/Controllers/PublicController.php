<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\OptionService;
use App\Services\ThemeService;
use Core\Database;
use Core\Request;
use Core\Response;

/**
 * Visitor-Facing Public Frontend Controller for NOEI CMS.
 */
class PublicController
{
    private Post $postModel;
    private Taxonomy $taxonomyModel;
    private ThemeService $themeService;

    public function __construct(
        ?Post $postModel = null,
        ?Taxonomy $taxonomyModel = null,
        ?ThemeService $themeService = null
    ) {
        $this->postModel = $postModel ?? new Post();
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
        $this->themeService = $themeService ?? new ThemeService();
    }

    /**
     * Public Homepage feed or static front page.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');
        $homepageType = (string)OptionService::get('homepage_type', 'posts');

        // Check if designated as static front page
        if ($homepageType === 'page') {
            $pageId = (int)OptionService::get('homepage_page_id', 0);
            if ($pageId > 0) {
                $page = $this->postModel->find($pageId);
                if ($page && $page['status'] === 'published') {
                    $html = $this->themeService->render('page', [
                        'title' => "{$page['title']} - {$siteTitle}",
                        'siteTitle' => $siteTitle,
                        'page' => $page,
                    ]);
                    return new Response($html);
                }
            }
        }

        // Default: Chronological posts feed
        $limit = (int)OptionService::get('posts_per_page', 10);
        if ($limit <= 0) {
            $limit = 10;
        }

        $posts = $this->postModel->getAll('post', 'published', $limit);

        $html = $this->themeService->render('home', [
            'title' => $siteTitle,
            'siteTitle' => $siteTitle,
            'posts' => $posts,
        ]);

        return new Response($html);
    }

    /**
     * Render single post.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function showPost(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $post = $this->postModel->findBySlug($slug, 'post');

        if (!$post || $post['status'] !== 'published') {
            return $this->render404();
        }

        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');

        $html = $this->themeService->render('single', [
            'title' => "{$post['title']} - {$siteTitle}",
            'siteTitle' => $siteTitle,
            'post' => $post,
        ]);

        return new Response($html);
    }

    /**
     * Render single page.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function showPage(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $page = $this->postModel->findBySlug($slug, 'page');

        if (!$page || $page['status'] !== 'published') {
            return $this->render404();
        }

        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');

        $html = $this->themeService->render('page', [
            'title' => "{$page['title']} - {$siteTitle}",
            'siteTitle' => $siteTitle,
            'page' => $page,
        ]);

        return new Response($html);
    }

    /**
     * Render category archive feed.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function showCategory(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $db = Database::getInstance();

        $category = $db->fetch(
            "SELECT t.*, tax.id as taxonomy_id FROM cms_terms t JOIN cms_taxonomies tax ON t.id = tax.term_id WHERE t.slug = :slug AND tax.taxonomy = 'category' LIMIT 1",
            ['slug' => $slug]
        );

        if (!$category) {
            return $this->render404();
        }

        $posts = $db->fetchAll(
            "SELECT p.*, u.username as author_name 
             FROM cms_posts p 
             JOIN cms_term_relationships tr ON p.id = tr.object_id 
             LEFT JOIN cms_users u ON p.author_id = u.id 
             WHERE tr.taxonomy_id = :tax_id AND p.status = 'published' AND p.type = 'post' 
             ORDER BY p.id DESC",
            ['tax_id' => $category['taxonomy_id']]
        );

        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');

        $html = $this->themeService->render('archive', [
            'title' => "Category: {$category['name']} - {$siteTitle}",
            'siteTitle' => $siteTitle,
            'archiveTitle' => $category['name'],
            'taxonomy' => 'category',
            'posts' => $posts,
        ]);

        return new Response($html);
    }

    /**
     * Render tag archive feed.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function showTag(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');
        $db = Database::getInstance();

        $tag = $db->fetch(
            "SELECT t.*, tax.id as taxonomy_id FROM cms_terms t JOIN cms_taxonomies tax ON t.id = tax.term_id WHERE t.slug = :slug AND tax.taxonomy = 'post_tag' LIMIT 1",
            ['slug' => $slug]
        );

        if (!$tag) {
            return $this->render404();
        }

        $posts = $db->fetchAll(
            "SELECT p.*, u.username as author_name 
             FROM cms_posts p 
             JOIN cms_term_relationships tr ON p.id = tr.object_id 
             LEFT JOIN cms_users u ON p.author_id = u.id 
             WHERE tr.taxonomy_id = :tax_id AND p.status = 'published' AND p.type = 'post' 
             ORDER BY p.id DESC",
            ['tax_id' => $tag['taxonomy_id']]
        );

        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');

        $html = $this->themeService->render('archive', [
            'title' => "Tag: {$tag['name']} - {$siteTitle}",
            'siteTitle' => $siteTitle,
            'archiveTitle' => $tag['name'],
            'taxonomy' => 'tag',
            'posts' => $posts,
        ]);

        return new Response($html);
    }

    /**
     * Render 404 Not Found response.
     *
     * @return Response
     */
    public function render404(): Response
    {
        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');
        $html = $this->themeService->render('404', [
            'title' => "404 Not Found - {$siteTitle}",
            'siteTitle' => $siteTitle,
        ]);

        $res = new Response($html);
        return $res->setStatusCode(404);
    }
}
