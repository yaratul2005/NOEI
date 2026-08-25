    </main>

    <footer class="site-footer">
        <div>&copy; <?= date('Y') ?> <?= e($siteTitle ?? 'NOEI CMS') ?> &mdash; Powered by NOEI CMS</div>
    </footer>

    <?php \Core\Event::doAction('theme_footer'); ?>

    <?php $customFooter = option('custom_footer_scripts'); ?>
    <?php if (!empty($customFooter)): ?>
        <?= $customFooter ?>
    <?php endif; ?>
</body>
</html>
