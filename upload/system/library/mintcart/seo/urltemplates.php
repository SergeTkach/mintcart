<?php
namespace MintCart\Seo;

/**
 * The SEO URL templates of `SEO -> URL Generator` (setting `mintcart_seo_url`, JSON:
 * `{"product": "{brand} {name}", "category": "{name}", ...}`). A template is written like a meta tag
 * template (`MetaTemplate`: `{name}`, `{brand}` ...); the rendered text is then turned into a keyword
 * by `Slugger`. A keyword cannot contain `/` (OpenCart splits the request path on it), so the
 * parts are joined with dashes.
 */
final class UrlTemplates
{
    public const DEFAULT_TEMPLATE = '{name}';

    /** Entity types and the variables a template for each can use. */
    public const TYPES = [
        'product' => ['name', 'brand', 'category', 'model'],
        'category' => ['name', 'parent'],
        'information' => ['name'],
        'manufacturer' => ['name'],
    ];

    public static function forType(?string $json, string $type): string
    {
        $all = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        $template = is_array($all) && isset($all[$type]) && is_string($all[$type]) ? trim($all[$type]) : '';

        return $template !== '' ? $template : self::DEFAULT_TEMPLATE;
    }

    /** @param array<string, mixed> $posted type => template */
    public static function normalize(array $posted): string
    {
        $clean = [];

        foreach (array_keys(self::TYPES) as $type) {
            $template = isset($posted[$type]) && is_string($posted[$type]) ? trim(html_entity_decode($posted[$type], ENT_QUOTES, 'UTF-8')) : '';

            if ($template !== '' && $template !== self::DEFAULT_TEMPLATE) {
                $clean[$type] = mb_substr($template, 0, 200);
            }
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param array<string, string|int|float|null> $variables
     */
    public static function keyword(string $template, array $variables, string $language = ''): string
    {
        $slug = Slugger::slug(MetaTemplate::render($template, $variables), $language);

        if ($slug === '' && $template !== self::DEFAULT_TEMPLATE) {
            // A template that renders empty (a missing brand only): the plain name.
            $slug = Slugger::slug(MetaTemplate::render(self::DEFAULT_TEMPLATE, $variables), $language);
        }

        return $slug;
    }
}
