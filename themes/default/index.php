<?php require __DIR__ . '/header.php'; ?>

<h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 24px;">Latest Posts</h1>

<?php if (empty($posts)): ?>
    <div class="post-card" style="text-align: center;">
        <h2>No published posts found yet</h2>
        <p style="color: var(--muted-color); margin-top: 8px;">Log in to the admin panel to publish your first post!</p>
    </div>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
        <article class="post-card">
            <h2 class="post-title"><a href="/post/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></h2>
            <div class="post-meta">
                Published on <?= e(date('F j, Y', strtotime($p['created_at']))) ?> by <?= e($p['author_name'] ?? 'Admin') ?>
            </div>
            <div class="post-content">
                <p><?= e($p['excerpt'] ?: substr(strip_tags($p['content'] ?? ''), 0, 180) . '...') ?></p>
            </div>
            <div style="margin-top: 16px;">
                <a href="/post/<?= e($p['slug']) ?>" class="btn">Read More &rarr;</a>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
