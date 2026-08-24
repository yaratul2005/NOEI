<?php
use Core\Event;

require __DIR__ . '/header.php';
?>

<article class="post-card">
    <h1 class="post-title" style="font-size: 2.2rem;"><?= e($post['title']) ?></h1>
    <div class="post-meta">
        Published on <?= e(date('F j, Y', strtotime($post['created_at']))) ?> by <?= e($post['author_name'] ?? 'Admin') ?>
    </div>
    <div class="post-content">
        <?= Event::applyFilters('the_content', nl2br(e($post['content']))) ?>
    </div>
</article>

<div style="margin-top: 20px;">
    <a href="/" class="btn" style="background-color: var(--muted-color);">&larr; Back to Homepage</a>
</div>

<?php require __DIR__ . '/footer.php'; ?>
