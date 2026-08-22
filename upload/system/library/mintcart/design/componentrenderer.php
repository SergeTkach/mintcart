<?php
namespace MintCart\Design;

/**
 * Resolves and renders a MintCart component (e.g. `product-card`) against
 * the active theme, following the fallback chain described in
 * MINTCART_TECHNICAL_PLAN.md §11 and MINTCART_IMPLEMENTATION_GUIDE.md §28-29:
 *
 *   active theme override -> theme.json "parent" chain -> Mint Default
 *
 * This is deliberately independent of OpenCart's existing `event/theme.php`,
 * which resolves whole page routes (e.g. `product/category.twig`) against
 * `config_theme`/`theme_default_directory`. A component is included by many
 * different pages (Category, Search, Manufacturer, Special, Blocks), so its
 * override lookup has to be reusable outside any single page's own route,
 * not tied to whichever route happens to be rendering.
 *
 * No \Registry dependency, same rationale as
 * MintCart\Commerce\ProductViewProvider: given a themes root path and the
 * active theme's directory name (both plain strings a caller resolves from
 * OpenCart's config), this class is unit-testable without bootstrapping
 * OpenCart at all. The one OpenCart dependency is `render()`'s use of the
 * existing `\Template('Twig')` adaptor (system/library/template.php) to
 * actually render Twig markup - reusing it rather than re-implementing
 * Twig rendering here (MINTCART_IMPLEMENTATION_GUIDE.md §9 allowance for
 * wrapping an existing OpenCart mechanism).
 */
final class ComponentRenderer
{
    public const MINT_DEFAULT_THEME = 'mint_default';

    private string $themesDirectory;

    private string $activeTheme;

    public function __construct(string $themesDirectory, string $activeTheme)
    {
        $this->themesDirectory = rtrim($themesDirectory, '/') . '/';
        $this->activeTheme = $activeTheme;
    }

    /**
     * Resolves a component name to the absolute .twig file path that should
     * render it, walking active theme -> parent chain -> Mint Default.
     *
     * @throws \RuntimeException if the component exists nowhere in the chain,
     *         including Mint Default - every documented component must ship
     *         a Mint Default implementation (MINTCART_IMPLEMENTATION_GUIDE.md
     *         §29: a theme overrides business-data-driven markup, it does not
     *         invent a component Mint Default never defined).
     */
    public function resolve(string $component): string
    {
        $theme = $this->activeTheme;
        $visited = [];

        while ($theme !== null && !in_array($theme, $visited, true)) {
            $visited[] = $theme;

            $path = $this->componentPath($theme, $component);

            if (is_file($path)) {
                return $path;
            }

            $theme = $this->parentOf($theme);
        }

        if (!in_array(self::MINT_DEFAULT_THEME, $visited, true)) {
            $path = $this->componentPath(self::MINT_DEFAULT_THEME, $component);

            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException(sprintf(
            "MintCart\\Design\\ComponentRenderer could not resolve component '%s': not found in theme '%s', its parent chain, or '%s'.",
            $component,
            $this->activeTheme,
            self::MINT_DEFAULT_THEME
        ));
    }

    /**
     * Resolves and renders a component with the given template data.
     *
     * @param array<string, mixed> $data Template variables - typically a
     *        MintCart\Commerce\ProductViewProvider::build() result
     *        (`product`/`display`/`context`) plus the component's
     *        `text_*` language strings, per the component's documented
     *        contract (docs/developer/components/*.md).
     */
    public function render(string $component, array $data = []): string
    {
        $path = $this->resolve($component);
        $code = file_get_contents($path);

        if ($code === false) {
            throw new \RuntimeException("MintCart\\Design\\ComponentRenderer could not read component file '{$path}'.");
        }

        // Template\Twig::render() treats a falsy $code as "none supplied"
        // and falls back to re-deriving the path from $filename alone,
        // which doesn't include the resolved theme directory - it would
        // look in the wrong place and throw a misleading error instead of
        // rendering an intentionally empty override. Short-circuit instead.
        if ($code === '') {
            return '';
        }

        $template = new \Template('Twig');

        foreach ($data as $key => $value) {
            $template->set($key, $value);
        }

        return $template->render('mint/components/' . $component, $code);
    }

    private function componentPath(string $theme, string $component): string
    {
        return $this->themesDirectory . $theme . '/template/mint/components/' . $component . '.twig';
    }

    /**
     * Reads the "parent" field out of a theme's theme.json manifest
     * (MINTCART_IMPLEMENTATION_GUIDE.md §30). Missing/unreadable/malformed
     * manifests are treated as "no parent" rather than a hard failure - a
     * theme without a valid manifest simply doesn't extend anything, it
     * doesn't break component resolution for the themes after it in the
     * chain.
     */
    private function parentOf(string $theme): ?string
    {
        $manifestPath = $this->themesDirectory . $theme . '/theme.json';

        if (!is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($manifest) || empty($manifest['parent']) || !is_string($manifest['parent'])) {
            return null;
        }

        return $manifest['parent'];
    }
}
