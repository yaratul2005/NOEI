<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Media;
use App\Services\MediaService;
use Core\Request;
use Core\Response;

/**
 * Headless RESTful API Controller for Media Library & Uploads.
 */
class MediaApiController
{
    private Media $mediaModel;
    private MediaService $mediaService;

    public function __construct(?Media $mediaModel = null, ?MediaService $mediaService = null)
    {
        $this->mediaModel = $mediaModel ?? new Media();
        $this->mediaService = $mediaService ?? new MediaService();
    }

    /**
     * Get list of media files.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $filter = $request->get('filter');
        $search = $request->get('search');
        $limit = max(1, min(100, (int)$request->get('limit', 30)));

        $media = $this->mediaModel->getAll(
            $filter ? (string)$filter : null,
            $search ? (string)$search : null,
            $limit
        );

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $media,
        ]);
    }

    /**
     * Upload a new media file (Protected).
     *
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
    {
        $file = $request->file('file');

        if (!$file) {
            $response = new Response();
            return $response->json(['success' => false, 'error' => 'No file provided in request.'], 422);
        }

        try {
            $uploaded = $this->mediaService->upload($file, 1);
            $response = new Response();
            return $response->json([
                'success' => true,
                'data' => $uploaded,
            ], 201);
        } catch (\Throwable $e) {
            $response = new Response();
            return $response->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
