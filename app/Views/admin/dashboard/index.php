<?php
$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Dashboard Overview</h1>
</div>

<!-- Stat Cards -->
<div class="grid-stats">
    <div class="stat-card">
        <h3>Total Posts</h3>
        <div class="stat-value"><?= (int)($stats['posts'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Pages</h3>
        <div class="stat-value"><?= (int)($stats['pages'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Users</h3>
        <div class="stat-value"><?= (int)($stats['users'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <h3>Media Items</h3>
        <div class="stat-value"><?= (int)($stats['media'] ?? 0) ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Quick Shortcuts Card -->
    <div class="card">
        <h2 class="card-title">Quick Action Shortcuts</h2>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px;">
            <a href="/admin/posts/create" class="btn">+ Create New Post</a>
            <a href="/admin/pages/create" class="btn btn-secondary">+ Add New Page</a>
            <a href="/admin/media/upload" class="btn btn-secondary">+ Upload Media</a>
            <a href="/admin/users/create" class="btn btn-secondary">+ Add User</a>
        </div>
    </div>

    <!-- Server Environment Info -->
    <div class="card">
        <h2 class="card-title">System Environment</h2>
        <ul style="list-style: none; line-height: 2;">
            <li><strong>PHP Version:</strong> <?= e($server['php_version']) ?></li>
            <li><strong>Database Driver:</strong> <?= e($server['pdo_driver']) ?></li>
            <li><strong>Max Upload Limit:</strong> <?= e($server['max_upload']) ?></li>
            <li><strong>Memory Limit:</strong> <?= e($server['memory_limit']) ?></li>
        </ul>
    </div>
</div>
