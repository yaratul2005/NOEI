<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">System Updates & Rollback</h1>
</div>

<!-- Update Status Card -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title">Version Status</h2>
    <p style="font-size: 1.1rem; margin-bottom: 16px;">
        Current Installed Version: <strong>v<?= e($currentVersion) ?></strong>
    </p>

    <div style="display: flex; gap: 12px;">
        <form method="POST" action="/admin/updates/check">
            <?= CsrfMiddleware::field() ?>
            <button type="submit" class="btn btn-secondary">🔄 Check for Updates</button>
        </form>
    </div>

    <?php if ($updateInfo['has_update']): ?>
        <div style="margin-top: 20px; padding: 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 4px;">
            <h3 style="color: #065f46; margin-bottom: 8px;">A new version is available: v<?= e($updateInfo['latest_version']) ?></h3>
            <p style="color: #047857; margin-bottom: 12px;"><?= e($updateInfo['release_notes']) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Manual Update Upload Card -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title">Apply Update Package (.zip)</h2>
    <p style="color: var(--muted-color); font-size: 0.9rem; margin-bottom: 16px;">
        Upload a verified NOEI CMS update ZIP. The system will automatically create a pre-update safety snapshot before applying changes.
    </p>

    <form method="POST" action="/admin/updates/apply" enctype="multipart/form-data" style="display: flex; gap: 16px; align-items: center;">
        <?= CsrfMiddleware::field() ?>
        <input type="file" name="update_zip" accept=".zip" class="form-control" style="max-width: 400px;" required>
        <button type="submit" class="btn" onclick="return confirm('Ready to update? A pre-update snapshot will be created automatically.');">Install Update</button>
    </form>
</div>

<!-- Rollback Snapshots Card -->
<div class="card">
    <h2 class="card-title">Pre-Update Snapshots & Instant Rollback</h2>
    <p style="color: var(--muted-color); font-size: 0.9rem; margin-bottom: 16px;">
        Restore the system to an exact pre-update snapshot if an update causes unexpected issues.
    </p>

    <?php if (empty($snapshots)): ?>
        <p style="text-align: center; color: var(--muted-color); padding: 20px 0;">No pre-update snapshots found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Snapshot File</th>
                        <th>Created At</th>
                        <th>Size</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($snapshots as $s): ?>
                        <tr>
                            <td><code><?= e($s['filename']) ?></code></td>
                            <td><?= e($s['created_at']) ?></td>
                            <td><?= e($s['size_formatted']) ?></td>
                            <td>
                                <form method="POST" action="/admin/updates/rollback" onsubmit="return confirm('WARNING: Are you sure you want to rollback to [<?= e($s['filename']) ?>]? All core files and database state will be restored.');">
                                    <?= CsrfMiddleware::field() ?>
                                    <input type="hidden" name="snapshot" value="<?= e($s['filename']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Rollback to Snapshot</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
