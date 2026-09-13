<?php
namespace MintCart\Update;

/**
 * The "check" step of the Update Manager flow
 * (`MINTCART_TECHNICAL_PLAN.md` §33 / `MINTCART_IMPLEMENTATION_GUIDE.md`
 * §69): given a package manifest and the versions currently installed,
 * decides whether the manifest represents an available, compatible update.
 * The flow's next named step is "compatibility" — this class answers both
 * at once (the manifest already carries `requirements`, so a caller gains
 * nothing from splitting "is this newer" from "is this compatible" into two
 * round-trips).
 *
 * Deliberately has no downloader/HTTP client — resolving *how* to fetch a
 * remote manifest is the flow's own `download` step, a later increment. A
 * caller passes in an already-parsed `PackageManifest` plus the versions it
 * already knows are installed. Same "resolved values in, normalized
 * contract out" shape as `MintCart\Onboarding\StoreReadiness`.
 */
final class UpdateChecker
{
    /**
     * @param string $installedOwnVersion the currently installed version of
     *        the thing $manifest is an update *for* (e.g.
     *        `MintCart\Core\Version::VERSION` for a MintCart Core update,
     *        or a theme's own installed `theme.json` version for a theme
     *        update) — compared against $manifest->version() to decide
     *        `update_available`. This is distinct from $installedVersions
     *        below: a package is not a "requirement" of itself.
     * @param array<string,string> $installedVersions component => currently
     *        installed version, checked against $manifest's own declared
     *        `requirements` via `PackageManifest::isCompatible()` (e.g.
     *        `['mint_version' => MintCart\Core\Version::VERSION, 'php_version' => PHP_VERSION]`).
     *
     * @return array{
     *     update_available: bool,
     *     compatible: bool,
     *     should_apply: bool,
     *     manifest_version: string,
     *     installed_version: string,
     * }
     */
    public function check(
        PackageManifest $manifest,
        string $installedOwnVersion,
        array $installedVersions = []
    ): array {
        $updateAvailable = version_compare($manifest->version(), $installedOwnVersion, '>');
        $compatible = $manifest->isCompatible($installedVersions);

        return [
            'update_available' => $updateAvailable,
            'compatible' => $compatible,
            // Both conditions must hold: a newer-but-incompatible manifest
            // must not be offered as applicable, and an older-but-otherwise-
            // "compatible" manifest is not an update at all.
            'should_apply' => $updateAvailable && $compatible,
            'manifest_version' => $manifest->version(),
            'installed_version' => $installedOwnVersion,
        ];
    }
}
