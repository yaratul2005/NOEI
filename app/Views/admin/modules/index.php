<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Extension Modules</h1>
</div>

<!-- Upload Module Package Card -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title">Install New Module (.zip)</h2>
    <form method="POST" action="/admin/modules/upload" enctype="multipart/form-data" style="display: flex; gap: 16px; align-items: center;">
        <?= CsrfMiddleware::field() ?>
        <input type="file" name="module_zip" accept=".zip" class="form-control" style="max-width: 400px;" required>
        <button type="submit" class="btn">Upload & Install</button>
    </form>
</div>

<!-- Installed Modules Card -->
<div class="card">
    <h2 class="card-title">Installed Modules</h2>

    <?php if (empty($modules)): ?>
        <p style="text-align: center; color: var(--muted-color); padding: 30px 0;">No extension modules installed. Upload a module ZIP package above!</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Declared Permissions</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $mod): ?>
                        <tr>
                            <td>
                                <strong><?= e($mod['name']) ?></strong>
                                <div style="font-size: 0.8rem; color: var(--muted-color);">v<?= e($mod['version']) ?> | <code><?= e($mod['slug']) ?></code></div>
                            </td>
                            <td><?= e($mod['description']) ?></td>
                            <td>
                                <?php if (empty($mod['permissions'])): ?>
                                    <span style="color: var(--muted-color); font-size: 0.8rem;">None</span>
                                <?php else: ?>
                                    <?php foreach ($mod['permissions'] as $perm): ?>
                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; margin-right: 4px; font-size: 0.75rem;"><?= e($perm) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($mod['author_uri'])): ?>
                                    <a href="<?= e($mod['author_uri']) ?>" target="_blank"><?= e($mod['author']) ?></a>
                                <?php else: ?>
                                    <?= e($mod['author']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $mod['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $mod['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($mod['is_active']): ?>
                                    <form method="POST" action="/admin/modules/<?= e($mod['slug']) ?>/deactivate" style="display: inline-block;">
                                        <?= CsrfMiddleware::field() ?>
                                        <button type="submit" class="btn btn-sm btn-secondary">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="/admin/modules/<?= e($mod['slug']) ?>/activate" style="display: inline-block;">
                                        <?= CsrfMiddleware::field() ?>
                                        <button type="submit" class="btn btn-sm">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
