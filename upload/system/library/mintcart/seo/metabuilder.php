<?php
namespace MintCart\Seo;

/**
 * The "meta title; meta description" line item from
 * `MINTCART_FUNCTIONAL_SCOPE.md` §16 "SEO Core" (alongside canonical,
 * hreflang, robots, sitemap and Schema.org — see "Relationship to other
 * SEO Core pieces" in `docs/developer/seo/meta-builder.md`).
 *
 * Several storefront controllers pass an entity's own `meta_title`/
 * `meta_description` straight to `Document::setTitle()`/`setDescription()`
 * with no fallback at all:
 *
 * - `catalog/controller/product/category.php` —
 *   `$this->document->setTitle($category_info['meta_title']);`
 * - `catalog/controller/product/product.php` —
 *   `$this->document->setTitle($product_info['meta_title']);`
 * - `catalog/controller/common/home.php` —
 *   `$this->document->setTitle($this->config->get('config_meta_title'));`
 *
 * A merchant who leaves a category's/product's "Meta Tag Title" field
 * blank in admin — a common case for anything created quickly, and the
 * *default* case for a fresh install's homepage `config_meta_title`
 * setting — ships that page with an empty `<title>` tag and no meta
 * description at all. `MetaBuilder` is a pure, OpenCart-independent
 * fallback rule (same "primitive first, wire the controllers in a later
 * increment" pattern `MintCart\Seo\CanonicalUrlBuilder` and
 * `MintCart\Update\PackageManifest` already established, `CLAUDE.md` §85):
 * given an entity's own value and a sensible fallback the controller
 * already has on hand (the entity's own name, or the store name), it
 * returns a title/description pair that is never silently blank when a
 * usable fallback exists.
 */
final class MetaBuilder
{
    /** @var array */
    private $rules = [];

    /**
     * @param string|null $entityTitle       The entity's own meta title
     *        field (`meta_title`/`config_meta_title`), if any.
     * @param string|null $entityDescription The entity's own meta
     *        description field (`meta_description`/`config_meta_description`),
     *        if any.
     * @param string      $fallbackTitle     Used when `$entityTitle` is
     *        empty/whitespace-only — the controller's own already-computed
     *        entity name (category/product name) or store name.
     * @param string|null $fallbackDescription Used when
     *        `$entityDescription` is empty/whitespace-only. Optional: many
     *        callers (e.g. `product/manufacturer.php`, which has no
     *        description field to fall back to) have nothing sensible to
     *        offer here.
     *
     * @return array{title: string, description: string} Both keys are
     *         always present and always a string (never null) — `''` only
     *         when both the entity value and its fallback were empty.
     */
    /**
     * @param array{title?: array{template: string, always: bool}, description?: array{template: string, always: bool}} $rules
     *        templates from `Marketing -> SEO -> Meta tags` (`MetaRules::forPage()`); none = the plain fallback rule above
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * @param array<string, string|int|float|null> $variables values for the template placeholders
     *        (`{name}`, `{store}`, ...), see `MetaRules::TYPES`
     */
    public function build(
        ?string $entityTitle,
        ?string $entityDescription,
        string $fallbackTitle,
        ?string $fallbackDescription = null,
        array $variables = []
    ): array {
        return [
            'title' => $this->field('title', $entityTitle, $fallbackTitle, $variables) ?? '',
            'description' => $this->field('description', $entityDescription, $fallbackDescription, $variables) ?? '',
        ];
    }

    /**
     * A template applies when the entity's own value is blank, or always when the rule says
     * so; a template that renders empty falls back to the plain rule.
     */
    private function field(string $field, ?string $entityValue, ?string $fallback, array $variables): ?string
    {
        $rule = $this->rules[$field] ?? null;

        if ($rule !== null && ($rule['always'] || trim((string) $entityValue) === '')) {
            $rendered = MetaTemplate::render($rule['template'], $variables);

            if ($rendered !== '') {
                // Like every other stored meta value (OpenCart keeps them HTML-escaped; the header prints them as they are).
                return htmlspecialchars($rendered, ENT_COMPAT, 'UTF-8');
            }
        }

        return $this->pick($entityValue, $fallback);
    }

    private function pick(?string $primary, ?string $fallback): ?string
    {
        $primary = $primary !== null ? trim($primary) : '';

        if ($primary !== '') {
            return $primary;
        }

        $fallback = $fallback !== null ? trim($fallback) : '';

        return $fallback !== '' ? $fallback : null;
    }
}
