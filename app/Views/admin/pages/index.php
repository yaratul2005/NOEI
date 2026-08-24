<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Manage Pages</h1>
    <a href="/admin/pages/create" class="btn">+ Add New Page</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Parent Page</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted-color);">No pages found. Create your first page!</td></tr>
                <?php else: ?>
                    <?php foreach ($pages as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><strong><?= e($p['title']) ?></strong></td>
                            <td><code><?= e($p['slug']) ?></code></td>
                            <td><?= e($p['parent_title'] ?? '&mdash;') ?></td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'published' ? 'active' : 'inactive' ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td><?= e($p['created_at']) ?></td>
                            <td>
                                <a href="/admin/pages/<?= (int)$p['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="POST" action="/admin/pages/<?= (int)$p['id'] ?>/delete" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete page [<?= e($p['title']) ?>]?');">
                                    <?= CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
