<?php
namespace MintCart\Update;

/**
 * Parses and validates an Update Manager package manifest
 * (`MINTCART_TECHNICAL_PLAN.md` §33 / `MINTCART_IMPLEMENTATION_GUIDE.md` §69).
 *
 * This is the first increment of the Update Manager flow (check →
 * compatibility → backup → download → verify → maintenance → files →
 * migrations → cache clear → health check → done) — a pure, OpenCart-
 * independent value object for the manifest a MintCart update/theme/
 * extension package ships alongside its files, the same shape
 * `MintCart\Core\Migration\MigrationManager` (Phase 1) and
 * `MintCart\Onboarding\WizardProgress` (Phase 13) already established:
 * build the primitive first, wire a real downloader/installer/UI around it
 * once that consumer exists (`CLAUDE.md` §85). The remaining steps of the
 * flow (backup, download, verify-against-`checksum`, maintenance mode,
 * file copy, running `migrations` through the existing MigrationManager,
 * cache clear, health check via `MintCart\Core\HealthCheck` once that
 * lands) are deliberately not attempted here.
 *
 * Not the same thing as `MintCart\Compatibility\CompatibilityChecker`
 * (analyzes a third-party OCMOD extension's `install.xml` for conflicts,
 * Technical Plan §39/Functional Scope §29). This class only concerns
 * itself with one manifest's own declared identity, requirements and file
 * list — it never inspects OCMOD XML.
 *
 * `removes` (added alongside `MintCart\Update\BackupPlanner`, the flow's
 * `backup` step) follows `docs/adr/005-theme-extension-update-rollback.md`
 * decision #3: a path a *new* manifest no longer lists is not, by itself,
 * safe to infer as "delete this" (it may simply have moved elsewhere in
 * the package). `removes` is how a package author states deletions
 * explicitly; `BackupPlanner` snapshots a `removes` path exactly like an
 * overwritten `files` path before anything is deleted.
 */
final class PackageManifest
{
    private string $name;

    private string $version;

    /** @var array<string,string> component => version constraint, e.g. ['mint_version' => '^0.1.0'] */
    private array $requirements;

    /** @var string[] relative file paths this package installs/overwrites */
    private array $files;

    /** @var string[] relative file paths this package deletes (ADR 005 decision #3) — distinct from files() overwrites */
    private array $removes;

    /** @var string[] migration filenames (see MigrationManager's naming convention) this package includes */
    private array $migrations;

    private ?string $checksum;

    private function __construct(
        string $name,
        string $version,
        array $requirements,
        array $files,
        array $removes,
        array $migrations,
        ?string $checksum
    ) {
        $this->name = $name;
        $this->version = $version;
        $this->requirements = $requirements;
        $this->files = $files;
        $this->removes = $removes;
        $this->migrations = $migrations;
        $this->checksum = $checksum;
    }

    /**
     * @throws \InvalidArgumentException listing every validation problem
     *                                    found (not just the first), so a
     *                                    caller can report the whole set at
     *                                    once rather than round-tripping.
     */
    public static function fromArray(array $data): self
    {
        $errors = self::validate($data);

        if ($errors !== []) {
            throw new \InvalidArgumentException(
                'Invalid package manifest: ' . implode('; ', $errors)
            );
        }

        return new self(
            $data['name'],
            $data['version'],
            $data['requirements'] ?? [],
            $data['files'] ?? [],
            $data['removes'] ?? [],
            $data['migrations'] ?? [],
            $data['checksum'] ?? null
        );
    }

    /**
     * @return string[] validation problems; empty array means $data is safe
     *                   to pass to fromArray().
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            $errors[] = 'name is required and must be a non-empty string';
        }

        if (!isset($data['version']) || !is_string($data['version']) || trim($data['version']) === '') {
            $errors[] = 'version is required and must be a non-empty string';
        }

        foreach (['requirements', 'files', 'removes', 'migrations'] as $listField) {
            if (isset($data[$listField]) && !is_array($data[$listField])) {
                $errors[] = "{$listField} must be an array when present";
            }
        }

        if (isset($data['requirements']) && is_array($data['requirements'])) {
            foreach ($data['requirements'] as $component => $constraint) {
                if (!is_string($component) || !is_string($constraint) || trim($constraint) === '') {
                    $errors[] = "requirements.{$component} must be a non-empty version constraint string";
                }
            }
        }

        if (isset($data['checksum']) && $data['checksum'] !== null && !is_string($data['checksum'])) {
            $errors[] = 'checksum must be a string when present';
        }

        return $errors;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return array<string,string> */
    public function requirements(): array
    {
        return $this->requirements;
    }

    /** @return string[] */
    public function files(): array
    {
        return $this->files;
    }

    /** @return string[] */
    public function removes(): array
    {
        return $this->removes;
    }

    /** @return string[] */
    public function migrations(): array
    {
        return $this->migrations;
    }

    public function checksum(): ?string
    {
        return $this->checksum;
    }

    /**
     * True when this manifest declares no constraint at all for $component
     * (nothing to check against) or when $installedVersion satisfies the
     * declared constraint.
     */
    public function isCompatibleWith(string $component, string $installedVersion): bool
    {
        if (!isset($this->requirements[$component])) {
            return true;
        }

        return self::satisfiesConstraint($installedVersion, $this->requirements[$component]);
    }

    /**
     * True only when $installedVersion satisfies every declared requirement.
     *
     * @param array<string,string> $installedVersions component => currently
     *                                                 installed version.
     *                                                 A component this
     *                                                 manifest requires but
     *                                                 that is missing here
     *                                                 is treated as
     *                                                 incompatible rather
     *                                                 than silently passed.
     */
    public function isCompatible(array $installedVersions): bool
    {
        foreach ($this->requirements as $component => $constraint) {
            if (!array_key_exists($component, $installedVersions)) {
                return false;
            }

            if (!self::satisfiesConstraint($installedVersions[$component], $constraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Supports the constraint forms Update Manager manifests are expected
     * to use: an exact version ("1.2.3"), a comparison operator prefix
     * (">=1.2.3", "<=1.2.3", ">1.2.3", "<1.2.3", "=1.2.3") or a caret range
     * ("^1.2.3"). Caret follows the same semantics npm/Composer use:
     * ^1.2.3 allows any 1.x.y >= 1.2.3; ^0.2.3 allows any 0.2.y >= 0.2.3
     * (0.x is not considered stable, so only the minor is free to float);
     * ^0.0.3 allows only 0.0.3 itself (nothing is free to float below the
     * first nonzero component). Not a full semver range grammar (no "~",
     * no "||", no ">=1.0 <2.0" combined ranges) — this covers what a
     * single-manifest "minimum compatible version" declaration needs;
     * extend it only when a real manifest needs a form this doesn't cover.
     */
    public static function satisfiesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '') {
            return true;
        }

        if ($constraint[0] === '^') {
            return self::satisfiesCaret($version, substr($constraint, 1));
        }

        foreach (['>=', '<=', '>', '<', '='] as $operator) {
            if (strncmp($constraint, $operator, strlen($operator)) === 0) {
                $target = substr($constraint, strlen($operator));

                return version_compare($version, $target, self::operatorFor($operator));
            }
        }

        // No operator prefix: exact match.
        return version_compare($version, $constraint, '==');
    }

    private static function operatorFor(string $operator): string
    {
        return $operator === '=' ? '==' : $operator;
    }

    private static function satisfiesCaret(string $version, string $target): bool
    {
        if (version_compare($version, $target, '<')) {
            return false;
        }

        $parts = explode('.', $target);
        $major = (int) ($parts[0] ?? 0);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        if ($major > 0) {
            $ceiling = ($major + 1) . '.0.0';
        } elseif ($minor > 0) {
            $ceiling = '0.' . ($minor + 1) . '.0';
        } else {
            $ceiling = '0.0.' . ($patch + 1);
        }

        return version_compare($version, $ceiling, '<');
    }

    /**
     * @return array{name:string,version:string,requirements:array<string,string>,files:string[],removes:string[],migrations:string[],checksum:?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'requirements' => $this->requirements,
            'files' => $this->files,
            'removes' => $this->removes,
            'migrations' => $this->migrations,
            'checksum' => $this->checksum,
        ];
    }
}
