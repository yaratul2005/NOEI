<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">My Profile</h1>
</div>

<div class="card" style="max-width: 600px;">
    <h2 class="card-title">Update Account Details</h2>

    <form method="POST" action="/admin/profile">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="username">Username (Cannot be changed)</label>
            <input type="text" id="username" class="form-control" value="<?= e($user['username']) ?>" disabled>
        </div>

        <div class="form-group">
            <label for="role">Role</label>
            <input type="text" id="role" class="form-control" value="<?= e($user['role_name'] ?? 'Subscriber') ?>" disabled>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">New Password (Leave blank to keep current password)</label>
            <input type="password" id="password" name="password" class="form-control" minlength="8">
        </div>

        <button type="submit" class="btn">Save Profile Changes</button>
    </form>
</div>
