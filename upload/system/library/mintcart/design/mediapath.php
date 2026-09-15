<?php
namespace MintCart\Design;

/**
 * Path-safety and filename/mime-type validation rules for browsing/
 * uploading/deleting inside OpenCart's own `image/catalog/` directory
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §25 "Media UX", priority P1).
 *
 * This is a pure extraction of the exact security rules
 * `upload/admin/controller/common/filemanager.php` already enforces
 * (directory-traversal containment, filename length, extension/mime
 * whitelist) — checked against that real controller before writing this
 * class, per `CLAUDE.md` §84/§10. `ModelDesignMedia` (the new JSON-API
 * counterpart for `mc-media-picker`, `docs/developer/components/media-picker.md`)
 * calls these helpers instead of re-deriving the same rules a second time,
 * and instead of modifying `common/filemanager.php` itself — that legacy
 * screen and its own behavior are untouched by this class.
 *
 * Deliberately has **no OpenCart dependency** (no `DIR_IMAGE`, no
 * `\Registry`) so it is unit-testable in isolation — same shape as every
 * other `MintCart\Seo`/`MintCart\Update` primitive in this codebase. Any
 * real filesystem call (`realpath()`, `glob()`) stays the caller's job;
 * this class only decides whether an already-resolved path/filename/mime
 * value is acceptable.
 */
final class MediaPath
{
    /** Same whitelist `common/filemanager.php::upload()` already enforces. */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png', 'webp'];

    /** Same whitelist `common/filemanager.php::upload()` already enforces. */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Same rule `common/filemanager.php::index()` applies to
     * `$this->request->get['filter_name']` before it is glob()'d: strip
     * wildcard/path-separator characters (never let a search term escape
     * the current directory or inject a glob wildcard) and trim a
     * trailing slash.
     */
    public static function sanitizeFilterName(string $raw): string
    {
        return rtrim(str_replace(['*', '/', '\\'], '', $raw), '/');
    }

    /**
     * Same containment intent `common/filemanager.php` repeats at every
     * entry point (`index()`, `upload()`, `folder()`, `delete()`) — after
     * normalizing both sides to forward slashes, `$realPath` must sit
     * inside `$catalogRoot` — i.e. `realpath()` did not resolve outside
     * the catalog image directory (a `..`-style traversal, or a symlink
     * escape). Both arguments are expected already resolved
     * (`realpath()`'d) by the caller; this method does no filesystem I/O
     * of its own, only the string comparison.
     *
     * Deliberately **stricter** than `filemanager.php`'s own `substr(...)
     * == ...` check, which is a bare string-prefix comparison: it would
     * accept `/var/www/image/catalog-evil/x` as "within"
     * `/var/www/image/catalog` (both share that literal character
     * prefix), when it plainly is not the same directory. This method
     * requires the match to land on a real path-segment boundary — either
     * an exact match, or the next character after the shared prefix must
     * be `/`. This class ships new code (a new JSON API, not a modified
     * copy of `filemanager.php` itself), so there is no "faithful
     * extraction" obligation to reproduce that flaw — per this project's
     * own security-review rule (never leave a security bug you notice
     * while writing new code), it is fixed here rather than carried
     * forward.
     */
    public static function isWithinCatalog(string $realPath, string $catalogRoot): bool
    {
        $normalizedPath = str_replace('\\', '/', $realPath);
        $normalizedRoot = rtrim(str_replace('\\', '/', $catalogRoot), '/');

        if ($normalizedRoot === '') {
            return false;
        }

        if ($normalizedPath === $normalizedRoot) {
            return true;
        }

        return strncmp($normalizedPath, $normalizedRoot . '/', strlen($normalizedRoot) + 1) === 0;
    }

    /**
     * Same rule `common/filemanager.php::upload()`/`::folder()` apply to
     * an uploaded filename / new folder name before validating it further:
     * decode HTML entities the browser may have encoded, then take only
     * the basename (never a path someone tried to smuggle into the
     * filename field).
     */
    public static function sanitizeName(string $raw): string
    {
        return basename(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Same 3-255 character bound `common/filemanager.php::upload()`
     * enforces on a filename via `utf8_strlen()`. Uses `mb_strlen()`
     * rather than OpenCart's own `utf8_*` helpers so this class stays
     * free of any OpenCart bootstrap dependency.
     */
    public static function isValidFilenameLength(string $filename): bool
    {
        $length = mb_strlen($filename);

        return $length >= 3 && $length <= 255;
    }

    /**
     * Same 3-128 character bound `common/filemanager.php::folder()`
     * enforces on a new folder name.
     */
    public static function isValidFolderNameLength(string $folder): bool
    {
        $length = mb_strlen($folder);

        return $length >= 3 && $length <= 128;
    }

    /**
     * Same extension check `common/filemanager.php::upload()` performs
     * via `strrchr()`/`utf8_strtolower()` against its own `$allowed` list
     * — reproduced here as `ALLOWED_EXTENSIONS`.
     */
    public static function hasAllowedExtension(string $filename): bool
    {
        $dotPosition = strrchr($filename, '.');

        if ($dotPosition === false) {
            return false;
        }

        $extension = mb_strtolower(mb_substr($dotPosition, 1));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Same mime-type whitelist check `common/filemanager.php::upload()`
     * performs against its own `$allowed` list — reproduced here as
     * `ALLOWED_MIME_TYPES`.
     */
    public static function hasAllowedMimeType(string $mime): bool
    {
        return in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }
}
