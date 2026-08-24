<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\FlashService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Database and Site Backup Controller.
 */
class BackupController
{
    private AuthService $auth;
    private BackupService $backupService;

    public function __construct(?AuthService $auth = null, ?BackupService $backupService = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->backupService = $backupService ?? new BackupService();
    }

    /**
     * Display backup manager screen.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $backups = $this->backupService->listBackups();

        $html = View::render('admin/backups/index', [
            'title' => 'Backups & Restoration - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'backups',
            'backups' => $backups,
        ]);

        return new Response($html);
    }

    /**
     * Create database SQL backup.
     *
     * @param Request $request
     * @return Response
     */
    public function createDb(Request $request): Response
    {
        try {
            $path = $this->backupService->createDatabaseBackup();
            FlashService::success("Database backup [" . basename($path) . "] created successfully.");
        } catch (\Throwable $e) {
            FlashService::error("Database backup failed: " . $e->getMessage());
        }

        $response = new Response();
        return $response->redirect('/admin/backups');
    }

    /**
     * Create full site archive backup.
     *
     * @param Request $request
     * @return Response
     */
    public function createFull(Request $request): Response
    {
        try {
            $path = $this->backupService->createFullBackup();
            FlashService::success("Full site backup [" . basename($path) . "] created successfully.");
        } catch (\Throwable $e) {
            FlashService::error("Full backup failed: " . $e->getMessage());
        }

        $response = new Response();
        return $response->redirect('/admin/backups');
    }

    /**
     * Restore database from selected SQL file.
     *
     * @param Request $request
     * @return Response
     */
    public function restore(Request $request): Response
    {
        $filename = (string)$request->post('filename', '');

        if (empty($filename) || !$this->backupService->restoreDatabase($filename)) {
            FlashService::error("Failed to restore database from [{$filename}].");
        } else {
            FlashService::success("Database restored successfully from [{$filename}].");
        }

        $response = new Response();
        return $response->redirect('/admin/backups');
    }

    /**
     * Download backup file.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function download(Request $request, array $params): Response
    {
        $filename = (string)($params['filename'] ?? '');
        $path = $this->backupService->getBackupPath($filename);

        if (!$path || !file_exists($path)) {
            FlashService::error('Backup file not found.');
            $response = new Response();
            return $response->redirect('/admin/backups');
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $contentType = ($ext === 'zip') ? 'application/zip' : 'text/plain; charset=utf-8';

        $response = new Response(file_get_contents($path) ?: '');
        $response->setHeader('Content-Type', $contentType);
        $response->setHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
        return $response;
    }

    /**
     * Delete backup file.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function delete(Request $request, array $params): Response
    {
        $filename = (string)($params['filename'] ?? '');

        if ($this->backupService->deleteBackup($filename)) {
            FlashService::success("Backup [{$filename}] deleted.");
        } else {
            FlashService::error("Failed to delete backup [{$filename}].");
        }

        $response = new Response();
        return $response->redirect('/admin/backups');
    }
}
