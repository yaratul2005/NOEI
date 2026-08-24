<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
$apiKey = $options['api_key'] ?? '';
?>

<div class="page-header">
    <h1 class="page-title">Site Settings</h1>
</div>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="/admin/settings/general" class="btn btn-sm btn-secondary">General</a>
    <a href="/admin/settings/reading" class="btn btn-sm btn-secondary">Reading</a>
    <a href="/admin/settings/seo" class="btn btn-sm btn-secondary">SEO & Social</a>
    <a href="/admin/settings/robots" class="btn btn-sm btn-secondary">Robots.txt</a>
    <a href="/admin/settings/api" class="btn btn-sm btn-primary">API Tokens</a>
</div>

<div class="card" style="max-width: 800px;">
    <h2 class="card-title">REST API & Headless Access Tokens</h2>
    <p style="color: var(--muted-color); font-size: 0.9rem; margin-bottom: 20px;">
        Use this API token to authenticate protected write requests (<code>POST</code>, <code>PUT</code>, <code>DELETE</code>) against the headless REST API via the <code>Authorization: Bearer &lt;token&gt;</code> or <code>X-API-Key</code> headers.
    </p>

    <div class="form-group">
        <label for="current_api_key">Current API Master Key</label>
        <div style="display: flex; gap: 12px; align-items: center;">
            <input type="text" id="current_api_key" class="form-control" value="<?= e($apiKey ?: 'No API key generated yet') ?>" readonly style="font-family: monospace; font-size: 0.95rem;">
            <?php if (!empty($apiKey)): ?>
                <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText('<?= e($apiKey) ?>'); alert('API Key copied to clipboard!');">Copy</button>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top: 24px; display: flex; gap: 12px;">
        <form method="POST" action="/admin/settings/api/generate">
            <?= CsrfMiddleware::field() ?>
            <button type="submit" class="btn"><?= empty($apiKey) ? '🔑 Generate API Key' : '🔄 Regenerate API Key' ?></button>
        </form>

        <?php if (!empty($apiKey)): ?>
            <form method="POST" action="/admin/settings/api/revoke" onsubmit="return confirm('Revoking this API key will immediately invalidate access for all headless integrations. Continue?');">
                <?= CsrfMiddleware::field() ?>
                <button type="submit" class="btn btn-danger">Revoke API Key</button>
            </form>
        <?php endif; ?>
    </div>
</div>
