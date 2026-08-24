<?php
use App\Services\FlashService;

$flash = FlashService::get();
$user = $user ?? null;
?>
<header class="admin-topbar">
    <div class="topbar-left">
        <strong>NOEI CMS Admin Panel</strong>
    </div>

    <div class="topbar-right">
        <?php if ($user): ?>
            <div class="topbar-user">
                <div class="user-avatar">
                    <?= e(strtoupper(substr($user['username'] ?? 'A', 0, 1))) ?>
                </div>
                <span><?= e($user['username'] ?? 'Admin') ?> (<?= e($user['role_name'] ?? 'Role') ?>)</span>
                <a href="/admin/profile" class="topbar-nav-link">Profile</a>
                <a href="/admin/logout" class="topbar-nav-link" style="color: #ef4444;">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" style="margin: 20px 28px 0 28px;">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>
