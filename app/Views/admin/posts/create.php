<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Create New Post</h1>
    <a href="/admin/posts" class="btn btn-secondary">&larr; Back to Posts</a>
</div>

<form method="POST" action="/admin/posts">
    <?= CsrfMiddleware::field() ?>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 24px;">
        <div>
            <div class="card">
                <div class="form-group">
                    <label for="title">Post Title</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Enter post title here..." required>
                </div>

                <div class="form-group">
                    <label for="slug">Custom Slug (Optional)</label>
                    <input type="text" id="slug" name="slug" class="form-control" placeholder="auto-generated-slug-if-left-blank">
                </div>

                <div class="form-group">
                    <label for="content">Post Content</label>
                    <textarea id="content" name="content" class="form-control" rows="12" placeholder="Write post content..."></textarea>
                </div>

                <div class="form-group">
                    <label for="excerpt">Excerpt / Short Summary</label>
                    <textarea id="excerpt" name="excerpt" class="form-control" rows="3" placeholder="Short description for post listings..."></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h3 class="card-title">Publish Options</h3>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="private">Private</option>
                    </select>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Save Post</button>
            </div>

            <div class="card">
                <h3 class="card-title">Categories</h3>
                <div style="max-height: 180px; overflow-y: auto;">
                    <?php if (empty($categories)): ?>
                        <p style="color: var(--muted-color); font-size: 0.85rem;">No categories created yet.</p>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <label style="display: block; font-size: 0.9rem; margin-bottom: 6px;">
                                <input type="checkbox" name="categories[]" value="<?= (int)$cat['taxonomy_id'] ?>">
                                <?= e($cat['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>
