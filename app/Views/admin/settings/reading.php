<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
$homepageType = $options['homepage_type'] ?? 'posts';
$homepagePageId = (int)($options['homepage_page_id'] ?? 0);
?>

<div class="page-header">
    <h1 class="page-title">Site Settings</h1>
</div>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="/admin/settings/general" class="btn btn-sm btn-secondary">General</a>
    <a href="/admin/settings/reading" class="btn btn-sm btn-primary">Reading</a>
    <a href="/admin/settings/seo" class="btn btn-sm btn-secondary">SEO & Social</a>
    <a href="/admin/settings/robots" class="btn btn-sm btn-secondary">Robots.txt</a>
    <a href="/admin/settings/api" class="btn btn-sm btn-secondary">API Tokens</a>
</div>

<div class="card" style="max-width: 800px;">
    <h2 class="card-title">Reading Settings</h2>

    <form method="POST" action="/admin/settings/reading">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label>Your Homepage Displays</label>
            <div style="margin-top: 8px;">
                <label style="display: block; margin-bottom: 10px; font-weight: normal;">
                    <input type="radio" name="homepage_type" value="posts" <?= $homepageType === 'posts' ? 'checked' : '' ?>>
                    Your latest posts
                </label>
                <label style="display: block; margin-bottom: 10px; font-weight: normal;">
                    <input type="radio" name="homepage_type" value="page" <?= $homepageType === 'page' ? 'checked' : '' ?>>
                    A static page (select below)
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="homepage_page_id">Homepage Static Page</label>
            <select id="homepage_page_id" name="homepage_page_id" class="form-control">
                <option value="0">&mdash; Select Page &mdash;</option>
                <?php foreach ($pages as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $homepagePageId ? 'selected' : '' ?>>
                        <?= e($p['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="posts_per_page">Blog pages show at most</label>
            <input type="number" id="posts_per_page" name="posts_per_page" class="form-control" style="max-width: 120px;" value="<?= (int)($options['posts_per_page'] ?? 10) ?>" min="1" max="100">
        </div>

        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>
