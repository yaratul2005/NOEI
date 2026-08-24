    </main>

    <footer class="site-footer">
        <div>&copy; <?= date('Y') ?> <?= e($siteTitle ?? 'NOEI CMS') ?> &mdash; Powered by NOEI CMS</div>
    </footer>

    <?php \Core\Event::doAction('theme_footer'); ?>
</body>
</html>
