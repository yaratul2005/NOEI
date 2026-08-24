<?php require __DIR__ . '/header.php'; ?>

<article class="post-card">
    <h1 class="post-title" style="font-size: 2.2rem;"><?= e($page['title']) ?></h1>
    <div class="post-content" style="margin-top: 20px;">
        <?= nl2br(e($page['content'])) ?>
    </div>
</article>

<?php require __DIR__ . '/footer.php'; ?>
