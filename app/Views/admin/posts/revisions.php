<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Revision History: <?= e($post['title']) ?></h1>
    <a href="/admin/posts/<?= (int)$post['id'] ?>/edit" class="btn btn-secondary">&larr; Back to Post Editor</a>
</div>

<div class="card">
    <h2 class="card-title">Saved Revisions</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Revision ID</th>
                    <th>Saved At</th>
                    <th>Saved By</th>
                    <th>Title Snapshot</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revisions)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--muted-color);">No saved revisions found for this post yet. Revisions are created automatically whenever you save changes.</td></tr>
                <?php else: ?>
                    <?php foreach ($revisions as $rev): ?>
                        <tr>
                            <td>#<?= (int)$rev['id'] ?></td>
                            <td><?= e($rev['created_at']) ?></td>
                            <td><?= e($rev['author_name'] ?? 'System') ?></td>
                            <td><?= e($rev['title']) ?></td>
                            <td>
                                <form method="POST" action="/admin/posts/revisions/<?= (int)$rev['id'] ?>/restore" onsubmit="return confirm('Restore this post to the snapshot from <?= e($rev['created_at']) ?>?');">
                                    <?= CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm">Restore This Revision</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
