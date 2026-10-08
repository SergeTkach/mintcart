<?php
namespace MintCart\Design;

/**
 * Resolves OpenCart's active theme directory name the same way
 * `catalog/controller/event/theme.php` does (`config_theme`, falling back to
 * `theme_default_directory` when `'default'` is selected), plus whether that
 * theme is Mint-API-aware (ships a `theme.json` manifest).
 *
 * `ControllerProductCategory` (Phase 8) and `ControllerCommonFooter`
 * (Phase 7) each carried their own private copy of this lookup rather than
 * share one — CLAUDE.md §85/§86: no abstraction before a second real
 * consumer exists, and `docs/upstream-diff.md`'s footer entry explicitly
 * said to revisit "if a third controller needs it". `ControllerProductSearch`
 * (this run) is that third controller, so the duplication is extracted here.
 *
 * Deliberately takes plain string values rather than an OpenCart `Config`
 * object, so this stays unit-testable without bootstrapping OpenCart at all
 * — same rationale as `MintCart\Commerce\ProductViewProvider` and
 * `MintCart\Design\ComponentRenderer`.
 */
final class ActiveTheme
{
    /**
     * @param string|null $configTheme The raw `config_theme` setting value.
     * @param string|null $themeDefaultDirectory The raw `theme_default_directory`
     *        setting value, used when `$configTheme` is `'default'` (or unset).
     */
    public static function directory(?string $configTheme, ?string $themeDefaultDirectory): string
    {
        if ($configTheme === null || $configTheme === 'default') {
            return (string) $themeDefaultDirectory;
        }

        return $configTheme;
    }

    /**
     * A theme is Mint-API-aware when it ships a `theme.json` manifest
     * directly under its own template directory — the same gate
     * `ControllerProductCategory`/`ControllerCommonFooter`/`event/theme.php`
     * already use before running any Mint-owned rendering pipeline, so a
     * store on the legacy `default` theme or an unrelated third-party OC3
     * theme never pays for work its own template never reads.
     */
    public static function isMintAware(string $themesDirectory, string $directory): bool
    {
        return is_file(rtrim($themesDirectory, '/') . '/' . $directory . '/theme.json');
    }

    /**
     * Preset codes the theme declares in its `theme.json` (`presets`).
     * Empty for a theme without a manifest or without presets. Used by
     * Design -> Styles and the Setup Wizard's Design step.
     *
     * @return string[]
     */
    public static function presets(string $themesDirectory, string $directory): array
    {
        $path = rtrim($themesDirectory, '/') . '/' . $directory . '/theme.json';

        if (!is_file($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (!is_array($manifest) || !isset($manifest['presets']) || !is_array($manifest['presets'])) {
            return [];
        }

        return array_values(array_filter($manifest['presets'], 'is_string'));
    }
}
