<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Add New Page</h1>
    <a href="/admin/pages" class="btn btn-secondary">&larr; Back to Pages</a>
</div>

<form method="POST" action="/admin/pages">
    <?= CsrfMiddleware::field() ?>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 24px;">
        <div>
            <div class="card">
                <div class="form-group">
                    <label for="title">Page Title</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Enter page title..." required>
                </div>

                <div class="form-group">
                    <label for="slug">Slug (Optional)</label>
                    <input type="text" id="slug" name="slug" class="form-control" placeholder="auto-generated-slug">
                </div>

                <div class="form-group">
                    <label for="content">Page Content</label>
                    <textarea id="content" name="content" class="form-control" rows="14" placeholder="Write page content..."></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h3 class="card-title">Attributes</h3>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="parent_id">Parent Page</label>
                    <select id="parent_id" name="parent_id" class="form-control">
                        <option value="0">(No Parent / Top Level)</option>
                        <?php foreach ($parentPages as $pp): ?>
                            <option value="<?= (int)$pp['id'] ?>"><?= e($pp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Create Page</button>
            </div>
        </div>
    </div>
</form>
