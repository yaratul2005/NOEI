<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Site Settings</h1>
</div>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="/admin/settings/general" class="btn btn-sm btn-secondary">General</a>
    <a href="/admin/settings/reading" class="btn btn-sm btn-secondary">Reading</a>
    <a href="/admin/settings/seo" class="btn btn-sm btn-secondary">SEO & Social</a>
    <a href="/admin/settings/robots" class="btn btn-sm btn-primary">Robots.txt</a>
    <a href="/admin/settings/api" class="btn btn-sm btn-secondary">API Tokens</a>
</div>

<div class="card" style="max-width: 800px;">
    <h2 class="card-title">Robots.txt Editor</h2>
    <p style="color: var(--muted-color); font-size: 0.9rem; margin-bottom: 16px;">
        Customize search engine crawler directives served directly at <code>/robots.txt</code>.
    </p>

    <form method="POST" action="/admin/settings/robots">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="robots_txt">Directives</label>
            <textarea id="robots_txt" name="robots_txt" class="form-control" rows="10" style="font-family: monospace; font-size: 0.95rem;"><?= e($robotsTxt) ?></textarea>
        </div>

        <button type="submit" class="btn">Save Robots.txt</button>
    </form>
</div>
