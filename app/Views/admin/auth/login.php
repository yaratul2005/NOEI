<?php
use App\Middleware\CsrfMiddleware;
use App\Services\FlashService;

$flash = FlashService::get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin Login') ?></title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/images/NOEI.svg">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
    <style>
        body {
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
            padding: 32px;
            text-align: center;
        }
        .login-header {
            margin-bottom: 24px;
        }
        .login-header img {
            max-width: 120px;
            height: auto;
            margin-bottom: 12px;
        }
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .login-header p {
            color: var(--muted-color);
            font-size: 0.9rem;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="/public/assets/images/NOEI.svg" alt="NOEI CMS" class="brand-logo">
            <h1>NOEI CMS Admin</h1>
            <p>Sign in to manage your website</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" style="margin-bottom: 20px;">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/login">
            <?= CsrfMiddleware::field() ?>

            <div class="form-group" style="text-align: left;">
                <label for="login">Username or Email</label>
                <input type="text" id="login" name="login" class="form-control" placeholder="admin@example.com" required autofocus>
            </div>

            <div class="form-group" style="text-align: left;">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Sign In to Dashboard &rarr;</button>
        </form>
    </div>
</body>
</html>
