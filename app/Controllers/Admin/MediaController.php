<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Media;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\MediaService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Media Gallery & File Upload Controller.
 */
class MediaController
{
    private AuthService $auth;
    private Media $mediaModel;
    private MediaService $mediaService;

    public function __construct(
        ?AuthService $auth = null,
        ?Media $mediaModel = null,
        ?MediaService $mediaService = null
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->mediaModel = $mediaModel ?? new Media();
        $this->mediaService = $mediaService ?? new MediaService();
    }

    /**
     * Display Media Library gallery view.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $filter = $request->get('filter');
        $search = $request->get('search');

        $mediaItems = $this->mediaModel->getAll(
            $filter ? (string)$filter : null,
            $search ? (string)$search : null
        );

        $html = View::render('admin/media/index', [
            'title' => 'Media Library - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'media',
            'mediaItems' => $mediaItems,
            'currentFilter' => $filter,
            'searchQuery' => $search,
        ]);

        return new Response($html);
    }

    /**
     * Handle file upload request (standard or AJAX).
     *
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
    {
        $user = $this->auth->user();
        $file = $request->file('file');

        if (!$file) {
            if ($request->isJson() || $request->isAjax()) {
                $res = new Response();
                return $res->json(['error' => 'No file provided in request.'], 400);
            }
            FlashService::error('Please select a file to upload.');
            $res = new Response();
            return $res->redirect('/admin/media');
        }

        try {
            $uploaded = $this->mediaService->upload($file, (int)$user['id']);

            if ($request->isJson() || $request->isAjax()) {
                $res = new Response();
                return $res->json([
                    'status' => 'success',
                    'media' => $uploaded,
                ]);
            }

            FlashService::success("File [{$uploaded['filename']}] uploaded successfully.");
        } catch (\Throwable $e) {
            if ($request->isJson() || $request->isAjax()) {
                $res = new Response();
                return $res->json(['error' => $e->getMessage()], 400);
            }
            FlashService::error('Upload failed: ' . $e->getMessage());
        }

        $res = new Response();
        return $res->redirect('/admin/media');
    }

    /**
     * Update media title / alt text metadata.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function update(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $title = trim((string)$request->post('title', ''));
        $alt = trim((string)$request->post('alt', ''));

        $this->mediaModel->updateMeta($id, [
            'title' => $title,
            'alt' => $alt,
        ]);

        FlashService::success('Media metadata updated.');
        $res = new Response();
        return $res->redirect('/admin/media');
    }

    /**
     * Delete media record and files.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);

        if ($this->mediaService->deleteMedia($id)) {
            FlashService::success('Media item deleted successfully.');
        } else {
            FlashService::error('Failed to delete media item.');
        }

        $res = new Response();
        return $res->redirect('/admin/media');
    }

    /**
     * API Endpoint returning JSON media items for Media Picker Modal component.
     *
     * @param Request $request
     * @return Response
     */
    public function apiList(Request $request): Response
    {
        $filter = $request->get('filter');
        $search = $request->get('search');

        $items = $this->mediaModel->getAll(
            $filter ? (string)$filter : null,
            $search ? (string)$search : null,
            60
        );

        $res = new Response();
        return $res->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }
}
