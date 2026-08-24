<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Tags Management</h1>
    <a href="/admin/posts" class="btn btn-secondary">&larr; Back to Posts</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add Tag Form -->
    <div class="card">
        <h2 class="card-title">Add New Tag</h2>
        <form method="POST" action="/admin/tags">
            <?= CsrfMiddleware::field() ?>

            <div class="form-group">
                <label for="name">Tag Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="e.g. php8" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Tag description..."></textarea>
            </div>

            <button type="submit" class="btn" style="width: 100%;">Add Tag</button>
        </form>
    </div>

    <!-- Tag List Table -->
    <div class="card">
        <h2 class="card-title">Existing Tags</h2>
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
                    <?php if (empty($tags)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--muted-color);">No tags created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tags as $tag): ?>
                            <tr>
                                <td><?= (int)$tag['taxonomy_id'] ?></td>
                                <td><strong><?= e($tag['name']) ?></strong></td>
                                <td><code><?= e($tag['slug']) ?></code></td>
                                <td><?= (int)$tag['count'] ?></td>
                                <td>
                                    <form method="POST" action="/admin/tags/<?= (int)$tag['taxonomy_id'] ?>/delete" onsubmit="return confirm('Delete tag [<?= e($tag['name']) ?>]?');">
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
