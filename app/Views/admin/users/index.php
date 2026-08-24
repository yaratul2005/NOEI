<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">User Management</h1>
    <a href="/admin/users/create" class="btn">+ Add New User</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="badge badge-<?= e($u['role_slug'] ?? 'author') ?>">
                                <?= e($u['role_name'] ?? 'Role') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>">
                                <?= e($u['status']) ?>
                            </span>
                        </td>
                        <td><?= e($u['created_at']) ?></td>
                        <td>
                            <a href="/admin/users/<?= (int)$u['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>

                            <?php if ((int)$u['id'] !== (int)($user['id'] ?? 0)): ?>
                                <form method="POST" action="/admin/users/<?= (int)$u['id'] ?>/delete" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete user [<?= e($u['username']) ?>]?');">
                                    <?= CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
