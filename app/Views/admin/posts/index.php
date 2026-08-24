<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Manage Posts</h1>
    <div>
        <a href="/admin/categories" class="btn btn-secondary">Categories</a>
        <a href="/admin/tags" class="btn btn-secondary">Tags</a>
        <a href="/admin/posts/create" class="btn">+ Create New Post</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted-color);">No posts found. Create your first post!</td></tr>
                <?php else: ?>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><strong><?= e($p['title']) ?></strong></td>
                            <td><code><?= e($p['slug']) ?></code></td>
                            <td><?= e($p['author_name'] ?? 'System') ?></td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'published' ? 'active' : 'inactive' ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td><?= e($p['created_at']) ?></td>
                            <td>
                                <a href="/admin/posts/<?= (int)$p['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                                <a href="/admin/posts/<?= (int)$p['id'] ?>/revisions" class="btn btn-sm btn-secondary">Revisions</a>
                                <form method="POST" action="/admin/posts/<?= (int)$p['id'] ?>/delete" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete post [<?= e($p['title']) ?>]?');">
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
