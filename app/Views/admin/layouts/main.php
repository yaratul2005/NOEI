<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'NOEI CMS Admin') ?></title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/images/NOEI.svg">
    <link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="admin-main">
            <?php require __DIR__ . '/header.php'; ?>

            <main class="admin-content">
                <?= $content ?? '' ?>
            </main>

            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>
