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
    <a href="/admin/settings/seo" class="btn btn-sm btn-primary">SEO & Social</a>
    <a href="/admin/settings/robots" class="btn btn-sm btn-secondary">Robots.txt</a>
    <a href="/admin/settings/api" class="btn btn-sm btn-secondary">API Tokens</a>
</div>

<div class="card" style="max-width: 800px;">
    <h2 class="card-title">SEO & Social Metadata Settings</h2>

    <form method="POST" action="/admin/settings/seo">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="seo_description">Default Meta Description</label>
            <textarea id="seo_description" name="seo_description" class="form-control" rows="3" placeholder="Default summary used for search engines when no specific description is set..."><?= e($options['seo_description'] ?? '') ?></textarea>
            <small style="color: var(--muted-color);">Recommended length: 150-160 characters.</small>
        </div>

        <div class="form-group">
            <label for="seo_fallback_image">Social Sharing Fallback Image URL (Open Graph / Twitter)</label>
            <input type="url" id="seo_fallback_image" name="seo_fallback_image" class="form-control" value="<?= e($options['seo_fallback_image'] ?? '') ?>" placeholder="https://yourdomain.com/storage/uploads/og-image.jpg">
            <small style="color: var(--muted-color);">Used as the preview image on Facebook, Twitter, and LinkedIn when no featured image is attached.</small>
        </div>

        <div class="form-group">
            <label for="google_site_verification">Google Search Console Verification Code</label>
            <input type="text" id="google_site_verification" name="google_site_verification" class="form-control" value="<?= e($options['google_site_verification'] ?? '') ?>" placeholder="e.g. google1234567890abcdef">
            <small style="color: var(--muted-color);">Paste the verification string from Google Search Console HTML tag.</small>
        </div>

        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>
