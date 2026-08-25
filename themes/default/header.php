<?php
use App\Services\MenuService;

$menuService = new MenuService();
$seoContext = [
    'title' => $title ?? null,
    'post' => $post ?? null,
    'page' => $page ?? null,
    'archiveTitle' => $archiveTitle ?? null,
];

$customLogo = option('theme_logo');
$customFavicon = option('theme_favicon');
$primaryColor = option('theme_primary_color', '#2563eb');
$accentColor = option('theme_accent_color', '#1d4ed8');
$customCss = option('custom_css', '');
$customHeadScripts = option('custom_head_scripts', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= seo_head($seoContext) ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(!empty($customFavicon) ? base_url($customFavicon) : base_url('/themes/default/assets/images/NOEI.svg')) ?>">
    <link rel="stylesheet" href="<?= base_url('/themes/default/assets/css/style.css') ?>">
    
    <style>
        :root {
            --primary-color: <?= e($primaryColor) ?>;
            --accent-color: <?= e($accentColor) ?>;
        }
        <?php if (!empty($customCss)): ?>
            <?= $customCss ?>
        <?php endif; ?>
    </style>

    <?php if (!empty($customHeadScripts)): ?>
        <?= $customHeadScripts ?>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <a href="<?= base_url('/') ?>" class="site-branding">
            <img src="<?= e(!empty($customLogo) ? base_url($customLogo) : base_url('/themes/default/assets/images/NOEI.svg')) ?>" alt="<?= e($siteTitle ?? 'NOEI CMS') ?>" class="brand-logo">
            <span class="site-title"><?= e($siteTitle ?? 'NOEI CMS') ?></span>
        </a>

        <?= $menuService->render('primary', 'site-nav') ?>
    </header>

    <main class="site-container">
