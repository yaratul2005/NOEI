<?php

declare(strict_types=1);

use Core\Event;

/**
 * Reference Demonstration Extension Module for NOEI CMS.
 */
class SampleNoticeModule
{
    /**
     * Triggered during Front Controller initialization when module is active.
     */
    public function boot(): void
    {
        // Hook into the_content filter to prepend reading time badge
        Event::addFilter('the_content', function ($content) {
            $wordCount = str_word_count(strip_tags((string)$content));
            $minutes = max(1, (int)ceil($wordCount / 200));

            $badge = '<div class="sample-notice-box" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px; font-size: 0.9rem; color: #1e3a8a;">'
                   . '<strong>📖 Reading Time:</strong> ~' . $minutes . ' min read (' . $wordCount . ' words)'
                   . '</div>';

            return $badge . $content;
        });

        // Hook into theme_footer action
        Event::addAction('theme_footer', function () {
            echo '<!-- Sample Notice Module Active -->';
        });
    }

    /**
     * Triggered once when user activates this module.
     */
    public function onActivate(): void
    {
        // Module setup logic or default options
    }

    /**
     * Triggered when user deactivates this module.
     */
    public function onDeactivate(): void
    {
        // Module cleanup logic
    }
}
