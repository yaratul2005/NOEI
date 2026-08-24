<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Add New User Account</h1>
    <a href="/admin/users" class="btn btn-secondary">&larr; Back to Users List</a>
</div>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="/admin/users">
        <?= CsrfMiddleware::field() ?>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="password">Password (min 8 characters)</label>
            <input type="password" id="password" name="password" class="form-control" required minlength="8">
        </div>

        <div class="form-group">
            <label for="role_id">Role</label>
            <select id="role_id" name="role_id" class="form-control" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Account Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <button type="submit" class="btn">Create User Account</button>
    </form>
</div>
