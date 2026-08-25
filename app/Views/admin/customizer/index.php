<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Visual Theme Customizer</h1>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Controls Column -->
    <div class="card">
        <h2 class="card-title">Theme Appearance & Branding</h2>
        
        <form method="POST" action="<?= base_url('/admin/customizer') ?>">
            <?= CsrfMiddleware::field() ?>

            <!-- Branding -->
            <div class="form-group">
                <label for="theme_logo">Custom Logo Image URL</label>
                <input type="text" id="theme_logo" name="theme_logo" class="form-control" value="<?= e($options['theme_logo'] ?? '') ?>" placeholder="Leave blank to use default NOEI.svg brand logo">
                <small style="color: var(--muted-color); font-size: 0.8rem;">Provide full URL or path from Media Library (e.g. /storage/uploads/2026/08/logo.png)</small>
            </div>

            <div class="form-group">
                <label for="theme_favicon">Custom Favicon URL</label>
                <input type="text" id="theme_favicon" name="theme_favicon" class="form-control" value="<?= e($options['theme_favicon'] ?? '') ?>" placeholder="e.g. /favicon.ico or /storage/uploads/icon.png">
            </div>

            <!-- Color Palette -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="theme_primary_color">Primary Brand Color</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="color" id="theme_primary_color" name="theme_primary_color" value="<?= e($options['theme_primary_color'] ?? '#2563eb') ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                        <input type="text" class="form-control" value="<?= e($options['theme_primary_color'] ?? '#2563eb') ?>" oninput="document.getElementById('theme_primary_color').value = this.value">
                    </div>
                </div>

                <div class="form-group">
                    <label for="theme_accent_color">Accent / Hover Color</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="color" id="theme_accent_color" name="theme_accent_color" value="<?= e($options['theme_accent_color'] ?? '#1d4ed8') ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                        <input type="text" class="form-control" value="<?= e($options['theme_accent_color'] ?? '#1d4ed8') ?>" oninput="document.getElementById('theme_accent_color').value = this.value">
                    </div>
                </div>
            </div>

            <!-- Custom CSS Editor -->
            <div class="form-group">
                <label for="custom_css">Custom CSS Injections</label>
                <textarea id="custom_css" name="custom_css" class="form-control" rows="6" style="font-family: monospace; font-size: 0.9rem;" placeholder="/* Add custom styling overrides here */&#10;.site-header { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }"><?= e($options['custom_css'] ?? '') ?></textarea>
            </div>

            <!-- Script Injections -->
            <div class="form-group">
                <label for="custom_head_scripts">Header Scripts (Google Analytics, Meta Pixels, &lt;head&gt;)</label>
                <textarea id="custom_head_scripts" name="custom_head_scripts" class="form-control" rows="4" style="font-family: monospace; font-size: 0.85rem;" placeholder="<!-- Google tag (gtag.js) -->"><?= e($options['custom_head_scripts'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="custom_footer_scripts">Footer Scripts (Live Chat, Tracking, &lt;/body&gt;)</label>
                <textarea id="custom_footer_scripts" name="custom_footer_scripts" class="form-control" rows="4" style="font-family: monospace; font-size: 0.85rem;" placeholder="<script>/* Custom tracking script */</script>"><?= e($options['custom_footer_scripts'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn">💾 Save Customizer Settings</button>
        </form>
    </div>

    <!-- Live Preview Column -->
    <div class="card" style="display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 class="card-title" style="margin-bottom: 0;">Live Site Preview</h2>
            <a href="<?= base_url('/') ?>" target="_blank" class="btn btn-sm btn-secondary">Open In New Tab &nearr;</a>
        </div>
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; min-height: 550px;">
            <iframe src="<?= base_url('/') ?>" style="width: 100%; height: 100%; min-height: 550px; border: none;"></iframe>
        </div>
    </div>
</div>
