<?php

declare(strict_types=1);

namespace MintCart\Compatibility;

/**
 * Turns an `InstallManifestParser::parse()` result into the Compatibility
 * Checker report `MINTCART_FUNCTIONAL_SCOPE.md` §29 describes: modified
 * files, modified controllers, modified Twig/legacy-tpl templates,
 * potential conflicts with MintCart's own core changes, and Bootstrap 3
 * markup assumptions — all heuristic, never a hard block
 * (`MINTCART_IMPLEMENTATION_GUIDE.md` §70: "Не блокувати installation
 * лише через heuristic warning").
 *
 * Deliberately has no OpenCart dependency, same shape as
 * `InstallManifestParser` — a pure array-in/array-out transform, so it is
 * unit-testable against a hand-built manifest array without going through
 * the XML parser at all.
 */
final class CompatibilityChecker
{
    /**
     * The same Bootstrap 3 leaf-class markers
     * `assets/src/css/compatibility/bootstrap3-shim.css` already treats as
     * needing a compatibility shim (`MINTCART_IMPLEMENTATION_GUIDE.md`
     * §10) — reused here as the signal that a legacy extension's markup
     * assumes Bootstrap 3, rather than a separately invented list.
     */
    private const BOOTSTRAP3_MARKERS = [
        'pull-right',
        'pull-left',
        'hidden-xs',
        'visible-xs',
        'btn-default',
        'panel',
        'panel-heading',
        'panel-body',
        'input-group-btn',
    ];

    /**
     * Files MintCart's own upstream diff (`docs/upstream-diff.md`) has
     * already modified as of this writing. An OCMOD touching any of these
     * has a concrete, real chance of conflicting with a MintCart core
     * change — not just a generic "this modifies core" warning. Callers
     * can extend this at call time via `$extraConflictPaths` (e.g. once a
     * new `docs/upstream-diff.md` entry lands) rather than requiring a
     * code change here for every addition.
     */
    private const KNOWN_MINTCART_MODIFIED_FILES = [
        'catalog/controller/product/category.php',
        'catalog/controller/product/search.php',
        'catalog/controller/product/manufacturer.php',
        'catalog/controller/product/special.php',
        'catalog/controller/common/header.php',
        'catalog/controller/common/footer.php',
        'catalog/controller/event/theme.php',
    ];

    /**
     * @param array{
     *     files: list<array{path: string, operation_count?: int, content?: string}>
     * } $manifest As returned by `InstallManifestParser::parse()`.
     * @param list<string> $extraConflictPaths Additional known-modified
     *        paths to treat as potential conflicts, on top of
     *        `KNOWN_MINTCART_MODIFIED_FILES`.
     *
     * @return array{
     *     files: list<array{path: string, is_controller: bool, is_template: bool, is_theme: bool}>,
     *     controller_files: list<string>,
     *     template_files: list<string>,
     *     theme_files: list<string>,
     *     bootstrap3_assumptions: list<array{path: string, markers: list<string>}>,
     *     potential_conflicts: list<string>,
     *     summary: array{
     *         file_count: int,
     *         controller_count: int,
     *         template_count: int,
     *         theme_count: int,
     *         bootstrap3_count: int,
     *         conflict_count: int
     *     }
     * }
     */
    public function check(array $manifest, array $extraConflictPaths = []): array
    {
        $conflictPaths = array_flip(array_merge(self::KNOWN_MINTCART_MODIFIED_FILES, $extraConflictPaths));

        $files = [];
        $controllerFiles = [];
        $templateFiles = [];
        $themeFiles = [];
        $bootstrap3 = [];
        $conflicts = [];

        foreach ($manifest['files'] ?? [] as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $isController = (bool) preg_match('#/controller/#', $path);
            $isTemplate = (bool) preg_match('#\.(twig|tpl)$#', $path);
            $isTheme = (bool) preg_match('#(^|/)view/theme/#', $path);

            $files[] = [
                'path' => $path,
                'is_controller' => $isController,
                'is_template' => $isTemplate,
                'is_theme' => $isTheme,
            ];

            if ($isController) {
                $controllerFiles[] = $path;
            }
            if ($isTemplate) {
                $templateFiles[] = $path;
            }
            if ($isTheme) {
                $themeFiles[] = $path;
            }

            $content = (string) ($file['content'] ?? '');
            $matchedMarkers = [];
            if ($content !== '') {
                foreach (self::BOOTSTRAP3_MARKERS as $marker) {
                    if (stripos($content, $marker) !== false) {
                        $matchedMarkers[] = $marker;
                    }
                }
            }
            if ($matchedMarkers) {
                $bootstrap3[] = ['path' => $path, 'markers' => $matchedMarkers];
            }

            if (isset($conflictPaths[$path])) {
                $conflicts[] = $path;
            }
        }

        $conflicts = array_values(array_unique($conflicts));

        return [
            'files' => $files,
            'controller_files' => $controllerFiles,
            'template_files' => $templateFiles,
            'theme_files' => $themeFiles,
            'bootstrap3_assumptions' => $bootstrap3,
            'potential_conflicts' => $conflicts,
            'summary' => [
                'file_count' => count($files),
                'controller_count' => count($controllerFiles),
                'template_count' => count($templateFiles),
                'theme_count' => count($themeFiles),
                'bootstrap3_count' => count($bootstrap3),
                'conflict_count' => count($conflicts),
            ],
        ];
    }
}
