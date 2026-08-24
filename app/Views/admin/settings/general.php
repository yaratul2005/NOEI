<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Site Settings</h1>
</div>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="/admin/settings/general" class="btn btn-sm btn-primary">General</a>
    <a href="/admin/settings/reading" class="btn btn-sm btn-secondary">Reading</a>
    <a href="/admin/settings/seo" class="btn btn-sm btn-secondary">SEO & Social</a>
    <a href="/admin/settings/robots" class="btn btn-sm btn-secondary">Robots.txt</a>
    <a href="/admin/settings/api" class="btn btn-sm btn-secondary">API Tokens</a>
</div>

<div class="card" style="max-width: 800px;">
    <h2 class="card-title">General Settings</h2>

    <form method="POST" action="/admin/settings/general">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="site_title">Site Title</label>
            <input type="text" id="site_title" name="site_title" class="form-control" value="<?= e($options['site_title'] ?? 'NOEI CMS') ?>" required>
        </div>

        <div class="form-group">
            <label for="site_tagline">Tagline</label>
            <input type="text" id="site_tagline" name="site_tagline" class="form-control" value="<?= e($options['site_tagline'] ?? '') ?>" placeholder="In a few words, explain what this site is about.">
        </div>

        <div class="form-group">
            <label for="site_url">Site Address (URL)</label>
            <input type="url" id="site_url" name="site_url" class="form-control" value="<?= e($options['site_url'] ?? 'http://localhost') ?>" required>
        </div>

        <div class="form-group">
            <label for="admin_email">Administration Email Address</label>
            <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?= e($options['admin_email'] ?? 'admin@noei.local') ?>" required>
        </div>

        <div class="form-group">
            <label for="timezone">Timezone</label>
            <input type="text" id="timezone" name="timezone" class="form-control" value="<?= e($options['timezone'] ?? 'UTC') ?>">
        </div>

        <div class="form-group">
            <label for="date_format">Date Format</label>
            <input type="text" id="date_format" name="date_format" class="form-control" value="<?= e($options['date_format'] ?? 'F j, Y') ?>">
        </div>

        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>
