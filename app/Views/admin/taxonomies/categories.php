<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Categories Management</h1>
    <a href="/admin/posts" class="btn btn-secondary">&larr; Back to Posts</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add Category Form -->
    <div class="card">
        <h2 class="card-title">Add New Category</h2>
        <form method="POST" action="/admin/categories">
            <?= CsrfMiddleware::field() ?>

            <div class="form-group">
                <label for="name">Category Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Technology" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Category description..."></textarea>
            </div>

            <button type="submit" class="btn" style="width: 100%;">Add Category</button>
        </form>
    </div>

    <!-- Category List Table -->
    <div class="card">
        <h2 class="card-title">Existing Categories</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--muted-color);">No categories created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= (int)$cat['taxonomy_id'] ?></td>
                                <td><strong><?= e($cat['name']) ?></strong></td>
                                <td><code><?= e($cat['slug']) ?></code></td>
                                <td><?= (int)$cat['count'] ?></td>
                                <td>
                                    <form method="POST" action="/admin/categories/<?= (int)$cat['taxonomy_id'] ?>/delete" onsubmit="return confirm('Delete category [<?= e($cat['name']) ?>]?');">
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
</div>
