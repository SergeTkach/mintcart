<?php
namespace MintCart\Seo;

/**
 * The meta tag templates saved in `Marketing -> SEO -> Meta tags`
 * (setting `mintcart_seo_meta`, JSON): per page type, per language, a title
 * and a description template, each with an "also when the field is filled"
 * flag. Pure: no OpenCart dependency.
 *
 * Stored shape:
 *   { "<type>": { "<language_id>": { "title": {"template": "...", "always": true},
 *                                    "description": {"template": "...", "always": false} } } }
 */
final class MetaRules
{
    /** Page types with a template, and the variables each one can use. */
    public const TYPES = [
        'home' => ['store'],
        'product' => ['name', 'store', 'brand', 'category', 'model', 'price', 'description'],
        'category' => ['name', 'parent', 'store', 'description'],
        'manufacturer' => ['name', 'store'],
        'information' => ['name', 'store', 'description'],
    ];

    public const FIELDS = ['title', 'description'];

    /**
     * @return array{title?: array{template: string, always: bool}, description?: array{template: string, always: bool}}
     *         only the fields that have a template
     */
    public static function forPage(?string $json, string $type, int $languageId): array
    {
        $all = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        if (!is_array($all) || !isset(self::TYPES[$type])) {
            return [];
        }

        $rules = [];

        foreach (self::FIELDS as $field) {
            $rule = $all[$type][$languageId][$field] ?? null;
            $template = is_array($rule) && isset($rule['template']) && is_string($rule['template']) ? trim($rule['template']) : '';

            if ($template !== '') {
                $rules[$field] = ['template' => $template, 'always' => !empty($rule['always'])];
            }
        }

        return $rules;
    }

    /**
     * The posted form -> the JSON to store. Unknown types, languages and variables are dropped;
     * a template that uses only unknown variables would render empty, so it is kept as typed
     * (the admin sees it in the preview) and `MetaBuilder` falls back when it renders empty.
     *
     * @param array<string, mixed> $posted type => language_id => field => {template, always}
     * @param list<int> $languageIds
     */
    public static function normalize(array $posted, array $languageIds): string
    {
        $clean = [];

        foreach (array_keys(self::TYPES) as $type) {
            foreach ($languageIds as $languageId) {
                foreach (self::FIELDS as $field) {
                    $rule = $posted[$type][$languageId][$field] ?? null;

                    if (!is_array($rule)) {
                        continue;
                    }

                    $template = isset($rule['template']) && is_string($rule['template']) ? trim(html_entity_decode($rule['template'], ENT_QUOTES, 'UTF-8')) : '';

                    if ($template === '') {
                        continue;
                    }

                    $clean[$type][$languageId][$field] = ['template' => mb_substr($template, 0, 300), 'always' => !empty($rule['always'])];
                }
            }
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
