<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Edit User Account: <?= e($editUser['username']) ?></h1>
    <a href="/admin/users" class="btn btn-secondary">&larr; Back to Users List</a>
</div>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="/admin/users/<?= (int)$editUser['id'] ?>">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="username">Username (Cannot be changed)</label>
            <input type="text" id="username" class="form-control" value="<?= e($editUser['username']) ?>" disabled>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($editUser['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">New Password (Leave blank to keep current password)</label>
            <input type="password" id="password" name="password" class="form-control" minlength="8">
        </div>

        <div class="form-group">
            <label for="role_id">Role</label>
            <select id="role_id" name="role_id" class="form-control" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)$editUser['role_id'] ? 'selected' : '' ?>>
                        <?= e($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Account Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <button type="submit" class="btn">Update User Account</button>
    </form>
</div>
