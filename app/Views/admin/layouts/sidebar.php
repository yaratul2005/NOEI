<aside class="admin-sidebar">
    <div class="sidebar-header">
        <img src="<?= base_url('/public/assets/images/NOEI.svg') ?>" alt="NOEI CMS" class="brand-logo">
        <span>NOEI CMS</span>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= base_url('/admin/dashboard') ?>" class="nav-item <?= ($currentRoute ?? '') === 'dashboard' ? 'active' : '' ?>">
            <span>📊 Dashboard</span>
        </a>
        <a href="<?= base_url('/admin/posts') ?>" class="nav-item <?= ($currentRoute ?? '') === 'posts' ? 'active' : '' ?>">
            <span>📝 Posts</span>
        </a>
        <a href="<?= base_url('/admin/pages') ?>" class="nav-item <?= ($currentRoute ?? '') === 'pages' ? 'active' : '' ?>">
            <span>📄 Pages</span>
        </a>
        <a href="<?= base_url('/admin/media') ?>" class="nav-item <?= ($currentRoute ?? '') === 'media' ? 'active' : '' ?>">
            <span>🖼️ Media</span>
        </a>
        <a href="<?= base_url('/admin/users') ?>" class="nav-item <?= ($currentRoute ?? '') === 'users' ? 'active' : '' ?>">
            <span>👥 Users (RBAC)</span>
        </a>
        <a href="<?= base_url('/admin/menus') ?>" class="nav-item <?= ($currentRoute ?? '') === 'menus' ? 'active' : '' ?>">
            <span>📑 Menus</span>
        </a>
        <a href="<?= base_url('/admin/customizer') ?>" class="nav-item <?= ($currentRoute ?? '') === 'customizer' ? 'active' : '' ?>">
            <span>🎨 Customizer</span>
        </a>
        <a href="<?= base_url('/admin/modules') ?>" class="nav-item <?= ($currentRoute ?? '') === 'modules' ? 'active' : '' ?>">
            <span>🧩 Modules</span>
        </a>
        <a href="<?= base_url('/admin/settings/general') ?>" class="nav-item <?= ($currentRoute ?? '') === 'settings' ? 'active' : '' ?>">
            <span>⚙️ Settings</span>
        </a>
        <a href="<?= base_url('/admin/backups') ?>" class="nav-item <?= ($currentRoute ?? '') === 'backups' ? 'active' : '' ?>">
            <span>💾 Backups</span>
        </a>
        <a href="<?= base_url('/admin/updates') ?>" class="nav-item <?= ($currentRoute ?? '') === 'updates' ? 'active' : '' ?>">
            <span>🚀 Updates</span>
        </a>
        <a href="<?= base_url('/admin/health') ?>" class="nav-item <?= ($currentRoute ?? '') === 'health' ? 'active' : '' ?>">
            <span>🩺 Site Health</span>
        </a>
        <a href="<?= base_url('/admin/profile') ?>" class="nav-item <?= ($currentRoute ?? '') === 'profile' ? 'active' : '' ?>">
            <span>👤 Profile</span>
        </a>
        <a href="<?= base_url('/') ?>" target="_blank" class="nav-item">
            <span>🌐 View Site</span>
        </a>
    </nav>
</aside>
