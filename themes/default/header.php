<?php
use App\Services\MenuService;

$menuService = new MenuService();
$seoContext = [
    'title' => $title ?? null,
    'post' => $post ?? null,
    'page' => $page ?? null,
    'archiveTitle' => $archiveTitle ?? null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= seo_head($seoContext) ?>
    <link rel="icon" type="image/svg+xml" href="/themes/default/assets/images/NOEI.svg">
    <link rel="stylesheet" href="/themes/default/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <a href="/" class="site-branding">
            <img src="/themes/default/assets/images/NOEI.svg" alt="NOEI CMS" class="brand-logo">
            <span class="site-title"><?= e($siteTitle ?? 'NOEI CMS') ?></span>
        </a>

        <?= $menuService->render('primary', 'site-nav') ?>
    </header>

    <main class="site-container">
