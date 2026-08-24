<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\FlashService;
use App\Services\UpdateService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin One-Click CMS Updates and Rollback Controller.
 */
class UpdateController
{
    private AuthService $auth;
    private UpdateService $updateService;
    private BackupService $backupService;

    public function __construct(
        ?AuthService $auth = null,
        ?UpdateService $updateService = null,
        ?BackupService $backupService = null
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->updateService = $updateService ?? new UpdateService();
        $this->backupService = $backupService ?? new BackupService();
    }

    /**
     * Display update manager screen.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $currentVersion = $this->updateService->getCurrentVersion();
        $updateInfo = $this->updateService->checkForUpdates();
        $allBackups = $this->backupService->listBackups();

        $snapshots = array_filter($allBackups, fn($b) => str_starts_with($b['filename'], 'pre-update'));

        $html = View::render('admin/updates/index', [
            'title' => 'System Updates & Rollback - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'updates',
            'currentVersion' => $currentVersion,
            'updateInfo' => $updateInfo,
            'snapshots' => $snapshots,
        ]);

        return new Response($html);
    }

    /**
     * Check for new releases.
     *
     * @param Request $request
     * @return Response
     */
    public function check(Request $request): Response
    {
        $info = $this->updateService->checkForUpdates();

        if ($info['has_update']) {
            FlashService::success("A new version of NOEI CMS (v{$info['latest_version']}) is available!");
        } else {
            FlashService::success("You are running the latest version of NOEI CMS (v{$info['current_version']}).");
        }

        $response = new Response();
        return $response->redirect('/admin/updates');
    }

    /**
     * Apply an update package from uploaded ZIP.
     *
     * @param Request $request
     * @return Response
     */
    public function apply(Request $request): Response
    {
        $file = $request->file('update_zip');

        if (!$file || empty($file['tmp_name'])) {
            FlashService::error('Please select an update ZIP package to install.');
            $response = new Response();
            return $response->redirect('/admin/updates');
        }

        if ($this->updateService->applyUpdatePackage($file['tmp_name'])) {
            FlashService::success("System successfully updated and pre-update snapshot saved.");
        } else {
            FlashService::error("Failed to apply update package.");
        }

        $response = new Response();
        return $response->redirect('/admin/updates');
    }

    /**
     * Roll back system to selected pre-update snapshot.
     *
     * @param Request $request
     * @return Response
     */
    public function rollback(Request $request): Response
    {
        $snapshot = (string)$request->post('snapshot', '');

        if (empty($snapshot) || !$this->updateService->rollbackToSnapshot($snapshot)) {
            FlashService::error("Failed to roll back system using snapshot [{$snapshot}].");
        } else {
            FlashService::success("System successfully rolled back to snapshot [{$snapshot}].");
        }

        $response = new Response();
        return $response->redirect('/admin/updates');
    }
}
