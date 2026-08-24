<?php
use App\Middleware\CsrfMiddleware;

$this->extend('admin/layouts/main');
?>

<div class="page-header">
    <h1 class="page-title">Media Library</h1>
</div>

<!-- Upload Form Card -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title">Upload New Media File</h2>
    <form method="POST" action="/admin/media/upload" enctype="multipart/form-data" style="display: flex; gap: 16px; align-items: center;">
        <?= CsrfMiddleware::field() ?>
        <input type="file" name="file" class="form-control" style="max-width: 400px;" required>
        <button type="submit" class="btn">Upload File</button>
    </form>
</div>

<!-- Media Items Gallery Grid -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="card-title" style="margin: 0;">Uploaded Files</h2>
        <div style="display: flex; gap: 12px;">
            <a href="/admin/media" class="btn btn-sm <?= empty($currentFilter) ? 'btn-primary' : 'btn-secondary' ?>">All Files</a>
            <a href="/admin/media?filter=image" class="btn btn-sm <?= $currentFilter === 'image' ? 'btn-primary' : 'btn-secondary' ?>">Images</a>
            <a href="/admin/media?filter=document" class="btn btn-sm <?= $currentFilter === 'document' ? 'btn-primary' : 'btn-secondary' ?>">Documents</a>
        </div>
    </div>

    <?php if (empty($mediaItems)): ?>
        <p style="text-align: center; color: var(--muted-color); padding: 40px 0;">No media items found. Upload your first file above!</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 18px;">
            <?php foreach ($mediaItems as $item): ?>
                <?php
                    $meta = json_decode($item['meta_data'] ?? '{}', true) ?: [];
                    $isImage = str_starts_with($item['mime_type'], 'image/');
                    $thumbUrl = $isImage ? '/' . ($meta['variants']['thumbnail'] ?? $item['file_path']) : '/public/assets/images/NOEI.svg';
                ?>
                <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: #fafafa; display: flex; flex-direction: column;">
                    <div style="height: 140px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['filename']) ?>" style="max-height: 100%; max-width: 100%; object-fit: cover;">
                    </div>
                    <div style="padding: 10px; font-size: 0.8rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= e($item['filename']) ?>">
                            <?= e($item['filename']) ?>
                        </div>
                        <div style="color: var(--muted-color); margin-top: 4px;">
                            <?= e(round($item['file_size'] / 1024, 1)) ?> KB
                        </div>
                        <div style="margin-top: 10px; display: flex; gap: 6px;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText(window.location.origin + '/<?= e($item['file_path']) ?>'); alert('Direct URL copied to clipboard!');">Copy URL</button>
                            <form method="POST" action="/admin/media/<?= (int)$item['id'] ?>/delete" onsubmit="return confirm('Delete this media item permanently?');">
                                <?= CsrfMiddleware::field() ?>
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
