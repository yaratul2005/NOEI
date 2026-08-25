<?php

declare(strict_types=1);

namespace Core;

/**
 * Pure-PHP WordPress-Grade Shortcode Parser & Dynamic Content Engine for NOEI CMS.
 */
class Shortcode
{
    /** @var array<string, callable> */
    private static array $shortcodes = [];
    private static bool $initialized = false;

    /**
     * Register a shortcode tag and its handler callback.
     *
     * @param string $tag
     * @param callable $callback Handler signature: fn(array $attrs, ?string $content = null, string $tag = ''): string
     */
    public static function add(string $tag, callable $callback): void
    {
        self::initDefaults();
        self::$shortcodes[strtolower($tag)] = $callback;
    }

    /**
     * Remove a registered shortcode.
     *
     * @param string $tag
     */
    public static function remove(string $tag): void
    {
        unset(self::$shortcodes[strtolower($tag)]);
    }

    /**
     * Check if a shortcode tag exists.
     *
     * @param string $tag
     * @return bool
     */
    public static function has(string $tag): bool
    {
        self::initDefaults();
        return isset(self::$shortcodes[strtolower($tag)]);
    }

    /**
     * Parse all registered shortcodes in content.
     *
     * @param string $content
     * @return string
     */
    public static function parse(string $content): string
    {
        self::initDefaults();

        if (empty(self::$shortcodes) || !str_contains($content, '[')) {
            return $content;
        }

        $tagNames = implode('|', array_map('preg_quote', array_keys(self::$shortcodes)));
        $pattern = '/\[(' . $tagNames . ')(?:\s+([^\]]*?))?(?:\/\]|\](.*?)\[\/\1\]|\])/s';

        return (string)preg_replace_callback($pattern, function ($matches) {
            $tag = strtolower($matches[1]);
            $rawAttrs = $matches[2] ?? '';
            $innerContent = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : null;

            $attrs = self::parseAttributes($rawAttrs);
            $callback = self::$shortcodes[$tag] ?? null;

            if ($callback && is_callable($callback)) {
                return (string)call_user_func($callback, $attrs, $innerContent, $tag);
            }

            return $matches[0];
        }, $content);
    }

    /**
     * Strip all registered shortcode tags from content without execution.
     *
     * @param string $content
     * @return string
     */
    public static function strip(string $content): string
    {
        self::initDefaults();

        if (empty(self::$shortcodes) || !str_contains($content, '[')) {
            return $content;
        }

        $tagNames = implode('|', array_map('preg_quote', array_keys(self::$shortcodes)));
        $pattern = '/\[(' . $tagNames . ')(?:\s+([^\]]*?))?(?:\/\]|\](.*?)\[\/\1\]|\])/s';

        return (string)preg_replace($pattern, '', $content);
    }

    /**
     * Parse raw attribute string into key-value pairs.
     *
     * @param string $text
     * @return array<string, string>
     */
    public static function parseAttributes(string $text): array
    {
        $attrs = [];
        $pattern = '/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower($match[1]);
                $val = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
                $attrs[$key] = $val;
            }
        }

        return $attrs;
    }

    /**
     * Initialize standard built-in shortcodes.
     */
    public static function initDefaults(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // [button text="Click Me" url="#" target="_self" style="primary"]
        self::$shortcodes['button'] = function (array $attrs, ?string $content): string {
            $text = htmlspecialchars($attrs['text'] ?? ($content ?? 'Click Here'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $url = htmlspecialchars($attrs['url'] ?? '#', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $target = htmlspecialchars($attrs['target'] ?? '_self', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $style = $attrs['style'] ?? 'primary';

            $btnClass = ($style === 'secondary') ? 'btn btn-secondary' : (($style === 'outline') ? 'btn btn-outline' : 'btn');

            return '<a href="' . $url . '" target="' . $target . '" class="' . htmlspecialchars($btnClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block; margin: 8px 0; text-decoration:none;">' . $text . '</a>';
        };

        // [notice type="info|warning|success|danger"]Message[/notice]
        self::$shortcodes['notice'] = function (array $attrs, ?string $content): string {
            $type = $attrs['type'] ?? 'info';
            $colors = [
                'info' => ['bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af'],
                'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e'],
                'success' => ['bg' => '#ecfdf5', 'border' => '#10b981', 'text' => '#065f46'],
                'danger' => ['bg' => '#fef2f2', 'border' => '#ef4444', 'text' => '#991b1b'],
            ];

            $c = $colors[$type] ?? $colors['info'];
            $body = nl2br(htmlspecialchars($content ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

            return '<div class="shortcode-notice notice-' . htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="background:' . $c['bg'] . '; border-left:4px solid ' . $c['border'] . '; color:' . $c['text'] . '; padding:12px 16px; margin:16px 0; border-radius:4px;">' . $body . '</div>';
        };

        // [quote author="Author Name" source="Book/Source"]Quote Body[/quote]
        self::$shortcodes['quote'] = function (array $attrs, ?string $content): string {
            $author = $attrs['author'] ?? '';
            $source = $attrs['source'] ?? '';
            $body = htmlspecialchars($content ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $footer = '';
            if (!empty($author)) {
                $footer = '<footer style="margin-top:8px; font-size:0.9rem; color:var(--muted-color);">&mdash; ' . htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if (!empty($source)) {
                    $footer .= ', <cite>' . htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</cite>';
                }
                $footer .= '</footer>';
            }

            return '<blockquote style="border-left:4px solid var(--primary-color, #2563eb); margin:16px 0; padding:10px 20px; background:rgba(0,0,0,0.02); font-style:italic;"><p style="margin:0;">' . $body . '</p>' . $footer . '</blockquote>';
        };

        // [youtube id="VIDEO_ID"]
        self::$shortcodes['youtube'] = function (array $attrs): string {
            $id = htmlspecialchars($attrs['id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (empty($id)) {
                return '';
            }

            return '<div class="video-responsive" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; margin:20px 0;"><iframe src="https://www.youtube-nocookie.com/embed/' . $id . '" frameborder="0" allowfullscreen style="position:absolute; top:0; left:0; width:100%; height:100%; border-radius:6px;"></iframe></div>';
        };
    }
}
