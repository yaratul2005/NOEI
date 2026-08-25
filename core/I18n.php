<?php

declare(strict_types=1);

namespace Core;

/**
 * Pure-PHP Lightweight Internationalization (i18n) Engine for NOEI CMS.
 * Fast JSON dictionary catalog loader with in-memory caching and parameter replacement.
 */
class I18n
{
    private static string $locale = 'en';
    private static string $fallbackLocale = 'en';
    /** @var array<string, array<string, string>> */
    private static array $catalogs = [];
    private static ?string $langDir = null;

    /**
     * Set language catalogs directory.
     *
     * @param string $dir
     */
    public static function setLangDir(string $dir): void
    {
        self::$langDir = rtrim($dir, '/\\');
    }

    /**
     * Get language catalogs directory.
     *
     * @return string
     */
    public static function getLangDir(): string
    {
        if (self::$langDir === null) {
            self::$langDir = dirname(__DIR__) . '/lang';
        }
        return self::$langDir;
    }

    /**
     * Set active locale.
     *
     * @param string $locale
     */
    public static function setLocale(string $locale): void
    {
        $clean = strtolower(trim($locale));
        if (!empty($clean)) {
            self::$locale = $clean;
        }
    }

    /**
     * Get active locale.
     *
     * @return string
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Translate text key with parameter replacement.
     *
     * @param string $key Text key or default string
     * @param array<string, string|int|float> $replace Associative array of replacement params (:key or {key})
     * @return string
     */
    public static function translate(string $key, array $replace = []): string
    {
        $locale = self::getLocale();
        $catalog = self::loadCatalog($locale);

        $translation = $catalog[$key] ?? null;

        // Fallback to English catalog if missing
        if ($translation === null && $locale !== self::$fallbackLocale) {
            $fallbackCatalog = self::loadCatalog(self::$fallbackLocale);
            $translation = $fallbackCatalog[$key] ?? $key;
        }

        $text = $translation ?? $key;

        // Perform parameter replacements
        if (!empty($replace)) {
            foreach ($replace as $paramKey => $paramVal) {
                $text = str_replace(
                    [':' . $paramKey, '{' . $paramKey . '}'],
                    (string)$paramVal,
                    $text
                );
            }
        }

        return $text;
    }

    /**
     * Load translation JSON catalog into memory.
     *
     * @param string $locale
     * @return array<string, string>
     */
    public static function loadCatalog(string $locale): array
    {
        $loc = strtolower(trim($locale));
        if (isset(self::$catalogs[$loc])) {
            return self::$catalogs[$loc];
        }

        $file = self::getLangDir() . "/{$loc}.json";
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content ?: '', true);
            self::$catalogs[$loc] = is_array($data) ? $data : [];
        } else {
            self::$catalogs[$loc] = [];
        }

        return self::$catalogs[$loc];
    }

    /**
     * Clear loaded language catalog memory cache.
     */
    public static function clearCache(): void
    {
        self::$catalogs = [];
    }
}
