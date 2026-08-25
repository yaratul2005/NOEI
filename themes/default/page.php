<?php
use Core\Event;

require __DIR__ . '/header.php';
?>

<article class="post-card">
    <h1 class="post-title" style="font-size: 2.2rem;"><?= e($page['title']) ?></h1>
    <div class="post-content" style="margin-top: 20px;">
        <?= Event::applyFilters('the_content', nl2br(e($page['content']))) ?>
    </div>
</article>

<div style="margin-top: 20px;">
    <a href="<?= base_url('/') ?>" class="btn" style="background-color: var(--muted-color);">&larr; Back to Homepage</a>
</div>

<?php require __DIR__ . '/footer.php'; ?>
