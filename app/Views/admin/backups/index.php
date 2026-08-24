<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Backups & Restoration</h1>
    <div style="display: flex; gap: 10px;">
        <form method="POST" action="/admin/backups/create-db" style="display: inline-block;">
            <?= CsrfMiddleware::field() ?>
            <button type="submit" class="btn btn-secondary">💾 Create Database Backup</button>
        </form>
        <form method="POST" action="/admin/backups/create-full" style="display: inline-block;">
            <?= CsrfMiddleware::field() ?>
            <button type="submit" class="btn">📦 Create Full Site Backup</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Available Backups</h2>

    <?php if (empty($backups)): ?>
        <p style="text-align: center; color: var(--muted-color); padding: 40px 0;">No backups created yet. Click one of the buttons above to generate your first backup!</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Type</th>
                        <th>File Size</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><code><?= e($b['filename']) ?></code></td>
                            <td>
                                <span class="badge" style="background: <?= $b['extension'] === 'zip' ? '#dbeafe' : '#fef3c7' ?>; color: <?= $b['extension'] === 'zip' ? '#1e40af' : '#92400e' ?>;">
                                    <?= e($b['type']) ?>
                                </span>
                            </td>
                            <td><?= e($b['size_formatted']) ?></td>
                            <td><?= e($b['created_at']) ?></td>
                            <td>
                                <a href="/admin/backups/download/<?= e($b['filename']) ?>" class="btn btn-sm btn-secondary">Download</a>
                                <?php if ($b['extension'] === 'sql'): ?>
                                    <form method="POST" action="/admin/backups/restore" style="display: inline-block;" onsubmit="return confirm('WARNING: Restoring will overwrite existing database tables. Are you sure you want to restore [<?= e($b['filename']) ?>]?');">
                                        <?= CsrfMiddleware::field() ?>
                                        <input type="hidden" name="filename" value="<?= e($b['filename']) ?>">
                                        <button type="submit" class="btn btn-sm">Restore</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="/admin/backups/delete/<?= e($b['filename']) ?>" style="display: inline-block;" onsubmit="return confirm('Delete backup file [<?= e($b['filename']) ?>]?');">
                                    <?= CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
