<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Taxonomy;
use Core\Request;
use Core\Response;

/**
 * Headless RESTful API Controller for Taxonomies (Categories & Tags).
 */
class TaxonomyApiController
{
    private Taxonomy $taxonomyModel;

    public function __construct(?Taxonomy $taxonomyModel = null)
    {
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
    }

    /**
     * Get all categories.
     *
     * @param Request $request
     * @return Response
     */
    public function categories(Request $request): Response
    {
        $categories = $this->taxonomyModel->getCategories();

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get all tags.
     *
     * @param Request $request
     * @return Response
     */
    public function tags(Request $request): Response
    {
        $tags = $this->taxonomyModel->getTags();

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $tags,
        ]);
    }
}
