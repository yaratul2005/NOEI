<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
$items = $menu['items'] ?? [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Sample Page', 'url' => '/page/sample-page'],
];
?>

<div class="page-header">
    <h1 class="page-title">Visual Menu Builder</h1>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Available Content Items to Add -->
    <div class="card">
        <h2 class="card-title">Add Content Links</h2>

        <div style="margin-bottom: 20px;">
            <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Pages</label>
            <?php foreach ($pages as $p): ?>
                <button type="button" class="btn btn-sm btn-secondary" style="margin-bottom: 6px;" onclick="addMenuItem('<?= e($p['title']) ?>', '/page/<?= e($p['slug']) ?>')">
                    + <?= e($p['title']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div>
            <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Categories</label>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="btn btn-sm btn-secondary" style="margin-bottom: 6px;" onclick="addMenuItem('<?= e($cat['name']) ?>', '/category/<?= e($cat['slug']) ?>')">
                    + <?= e($cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Menu Structure Form -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="card-title" style="margin: 0;">Menu Location: <?= e(strtoupper($location)) ?></h2>
            <div>
                <a href="/admin/menus?location=primary" class="btn btn-sm <?= $location === 'primary' ? 'btn-primary' : 'btn-secondary' ?>">Primary Menu</a>
                <a href="/admin/menus?location=footer" class="btn btn-sm <?= $location === 'footer' ? 'btn-primary' : 'btn-secondary' ?>">Footer Menu</a>
            </div>
        </div>

        <form method="POST" action="/admin/menus">
            <?= CsrfMiddleware::field() ?>
            <input type="hidden" name="location" value="<?= e($location) ?>">

            <div id="menu-items-container">
                <?php foreach ($items as $idx => $item): ?>
                    <div class="menu-item-row" style="display: flex; gap: 12px; margin-bottom: 12px; align-items: center;">
                        <input type="text" name="labels[]" class="form-control" value="<?= e($item['label']) ?>" placeholder="Label" required>
                        <input type="text" name="urls[]" class="form-control" value="<?= e($item['url']) ?>" placeholder="URL" required>
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-secondary" onclick="addMenuItem('Custom Link', '/custom-url')">+ Add Custom Link</button>
                <button type="submit" class="btn">Save Menu Structure</button>
            </div>
        </form>
    </div>
</div>

<script>
function addMenuItem(label, url) {
    const container = document.getElementById('menu-items-container');
    const row = document.createElement('div');
    row.className = 'menu-item-row';
    row.style.cssText = 'display: flex; gap: 12px; margin-bottom: 12px; align-items: center;';
    row.innerHTML = `
        <input type="text" name="labels[]" class="form-control" value="${label}" placeholder="Label" required>
        <input type="text" name="urls[]" class="form-control" value="${url}" placeholder="URL" required>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(row);
}
</script>
