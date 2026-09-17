<?php
namespace MintCart\Update;

/**
 * The `download` step of the Update Manager flow (`MINTCART_TECHNICAL_PLAN.md`
 * §33 / `MINTCART_IMPLEMENTATION_GUIDE.md` §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * `docs/developer/update/package-verifier.md`'s own "What's deliberately not
 * done yet" section named this step directly as the remaining candidate
 * before `PackageVerifier::verifyChecksum()` has a real caller ("a `download`
 * step that actually fetches a package archive over HTTP, then hands the
 * local path to `verifyChecksum()`"). This class is that step — same "pure,
 * primitive first" pattern `PackageManifest`/`PackageVerifier`/
 * `MaintenanceMode` already used.
 *
 * Deliberately duck-typed against an injectable `callable(string $url):
 * string` transport rather than hard-coding `file_get_contents()`/cURL —
 * this environment has no outbound network access to verify a real HTTP
 * fetch end to end (same reasoning `MigrationManager` duck-types `\DB` and
 * `SettingsAdapter` duck-types `ModelSettingSetting`), and a future
 * orchestrator may want to swap in a cURL-based transport with progress
 * reporting without this class's file-handling logic changing at all.
 *
 * Only two responsibilities belong here, deliberately: fetching the bytes
 * (delegated to the transport) and writing them to disk atomically (a
 * temp-file-then-rename, so a request that dies mid-download never leaves a
 * half-written file at the final path for a concurrent `verify` step to read).
 * Archive extraction, checksum verification (`PackageVerifier`) and
 * `mc_update_backup` bookkeeping are all separate, later steps — not this
 * class's job.
 */
final class PackageDownloader
{
    /** @var callable(string $url): string */
    private $transport;

    /**
     * @param callable(string $url): string|null $transport Returns the raw
     *        response body, or throws on failure. Defaults to a plain
     *        `file_get_contents()` fetch through a stream context (30s
     *        timeout, following redirects) when omitted — production use;
     *        tests always inject a fake transport instead, since this
     *        environment has no outbound network access.
     */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [self::class, 'fetchViaStreamContext'];
    }

    /**
     * Downloads `$url` and writes it to `$destinationPath`, atomically (a
     * temp file next to the destination, renamed into place only once the
     * full response body has been received and written successfully — a
     * partial/failed download never leaves a corrupt file at the real path).
     * Creates `$destinationPath`'s parent directory if it does not exist yet.
     *
     * @throws \InvalidArgumentException if $url is not http(s) — this class
     *         never fetches a `file://`/`phar://`/etc. URL, since a package
     *         source is expected to be a remote HTTP(S) endpoint, not a
     *         local path (which would make this step meaningless) or a
     *         wrapper scheme that could read arbitrary local files.
     * @throws \RuntimeException if the transport fails, returns an empty
     *         body, or the destination cannot be written/created.
     */
    public function download(string $url, string $destinationPath): void
    {
        self::assertAllowedScheme($url);

        $contents = ($this->transport)($url);

        if (!is_string($contents) || $contents === '') {
            throw new \RuntimeException("Downloaded package from \"{$url}\" is empty.");
        }

        self::writeAtomically($destinationPath, $contents);
    }

    private static function assertAllowedScheme(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException(
                "Package URL must be http:// or https://, got \"{$url}\"."
            );
        }
    }

    private static function writeAtomically(string $destinationPath, string $contents): void
    {
        $directory = dirname($destinationPath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Could not create directory \"{$directory}\" for the downloaded package.");
        }

        $tempPath = $destinationPath . '.part-' . bin2hex(random_bytes(4));

        if (file_put_contents($tempPath, $contents) === false) {
            throw new \RuntimeException("Could not write the downloaded package to \"{$tempPath}\".");
        }

        if (!rename($tempPath, $destinationPath)) {
            @unlink($tempPath);

            throw new \RuntimeException("Could not move the downloaded package into place at \"{$destinationPath}\".");
        }
    }

    /**
     * Default production transport — a plain `file_get_contents()` fetch
     * through a stream context (bounded timeout, following redirects up to
     * a fixed limit). Never exercised by this class's own tests, which
     * always inject a fake transport instead (no outbound network access in
     * this environment); a human should smoke-test this specific method
     * against a real update-package URL before this class's first live
     * caller ships.
     */
    private static function fetchViaStreamContext(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'https' => [
                'timeout' => 30,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);

        if ($contents === false) {
            throw new \RuntimeException("Failed to download package from \"{$url}\".");
        }

        return $contents;
    }
}
