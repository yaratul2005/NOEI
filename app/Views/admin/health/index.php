<?php
$this->extend('admin/layouts/main');

$scoreColor = ($score >= 80) ? '#10b981' : (($score >= 60) ? '#f59e0b' : '#ef4444');
?>

<div class="page-header">
    <h1 class="page-title">Site Health & System Diagnostics</h1>
</div>

<!-- Score Overview Card -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div>
            <h2 class="card-title" style="margin-bottom: 6px;">System Health Score</h2>
            <p style="color: var(--muted-color); font-size: 0.95rem; margin: 0;">
                Comprehensive shared-hosting environment validation and performance metrics.
            </p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="text-align: right;">
                <div style="font-size: 2.2rem; font-weight: 700; color: <?= $scoreColor ?>;"><?= $score ?>%</div>
                <div style="font-size: 0.85rem; color: var(--muted-color);">Overall Quality</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <span class="badge" style="background: #ecfdf5; color: #065f46;"><?= $goodCount ?> Passed</span>
                <?php if ($recommendedCount > 0): ?>
                    <span class="badge" style="background: #fffbeb; color: #92400e;"><?= $recommendedCount ?> Recommended</span>
                <?php endif; ?>
                <?php if ($criticalCount > 0): ?>
                    <span class="badge" style="background: #fef2f2; color: #991b1b;"><?= $criticalCount ?> Critical</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Diagnostic Checks List -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title">Environment & Security Checks</h2>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Check Item</th>
                    <th>Category</th>
                    <th>Observed Value</th>
                    <th>Status</th>
                    <th>Details & Recommendations</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $chk): ?>
                    <?php
                        $badgeStyle = match($chk['status']) {
                            'good' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
                            'recommended' => 'background: #fffbeb; color: #92400e; border: 1px solid #fde68a;',
                            'critical' => 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;',
                            default => 'background: #f1f5f9; color: #475569;'
                        };
                        $statusLabel = match($chk['status']) {
                            'good' => '✔ Good',
                            'recommended' => '▲ Recommended',
                            'critical' => '✖ Critical',
                            default => $chk['status']
                        };
                    ?>
                    <tr>
                        <td><strong><?= e($chk['label']) ?></strong></td>
                        <td><span style="font-size: 0.85rem; color: var(--muted-color);"><?= e($chk['category']) ?></span></td>
                        <td><code><?= e($chk['value']) ?></code></td>
                        <td>
                            <span class="badge" style="<?= $badgeStyle ?> font-weight: 600;">
                                <?= e($statusLabel) ?>
                            </span>
                        </td>
                        <td style="font-size: 0.9rem; color: #334155;"><?= e($chk['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- cPanel & Shared Hosting Best Practices Guide -->
<div class="card">
    <h2 class="card-title">Shared Hosting / cPanel Optimization Guide</h2>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 0.9rem; color: #334155;">
        <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1rem; color: #0f172a; margin-top: 0; margin-bottom: 8px;">📂 File Permissions in cPanel</h3>
            <p style="margin: 0 0 8px 0;">All folders should be set to <code>0755</code> and files to <code>0644</code>. In cPanel File Manager, right-click any folder or file to inspect and update permissions.</p>
        </div>

        <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1rem; color: #0f172a; margin-top: 0; margin-bottom: 8px;">🐘 PHP Extensions & Limits</h3>
            <p style="margin: 0 0 8px 0;">Use <strong>cPanel &rarr; Select PHP Version</strong> to ensure PHP 8.1+ is active and check the boxes for <code>pdo_mysql</code>, <code>mbstring</code>, <code>gd</code>, and <code>zip</code>.</p>
        </div>

        <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1rem; color: #0f172a; margin-top: 0; margin-bottom: 8px;">🔒 Free Let's Encrypt SSL</h3>
            <p style="margin: 0 0 8px 0;">Ensure HTTPS is enforced for all visitors by installing a free SSL certificate via <strong>cPanel &rarr; SSL/TLS Status &rarr; Run AutoSSL</strong>.</p>
        </div>

        <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1rem; color: #0f172a; margin-top: 0; margin-bottom: 8px;">🚀 Zero Daemon Architecture</h3>
            <p style="margin: 0 0 8px 0;">NOEI CMS uses pure-PHP file caching, pure-PDO database dumper, and pure-PHP autoloader. No Redis or Node daemons are ever required.</p>
        </div>
    </div>
</div>
