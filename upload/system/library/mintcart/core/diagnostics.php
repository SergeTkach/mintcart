<?php
namespace MintCart\Core;

/**
 * Per-request dev diagnostics: query count, named-bucket timing (e.g. "db"
 * vs. "render"), and cache hit/miss counters (`MINTCART_FUNCTIONAL_SCOPE.md`
 * §19 "Потрібен dev diagnostic mode для вимірювання: number of queries; DB
 * time; render time; cache status", `MINTCART_TECHNICAL_PLAN.md` §35).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * \Config) so it is unit-testable in isolation, same "resolved values in,
 * normalized contract out" shape as `StoreReadiness`/`ProductViewProvider`.
 * This class only collects and summarizes numbers a caller feeds it - it
 * does not itself instrument OpenCart's `\DB\MySQLi` to count real queries,
 * wrap `\Template\Twig` to measure real render time, or decide when "dev
 * mode" is on. Those are each a real, separate design decision (whether to
 * subclass/wrap a core OpenCart class is exactly the kind of upstream-diff
 * question CLAUDE.md §10/§75 says to weigh deliberately, not default into
 * while building the collector itself) - deferred to a future run's live
 * wiring, per CLAUDE.md §85 ("API appears when there's a real consumer").
 *
 * `$enabled` exists so a call site can pass `config_mintcart_dev_mode`
 * (or similar) straight through and have every `mark()`/`increment()`/
 * `time()` call become a cheap no-op in production - Functional Scope §19
 * "Production UI не повинен показувати sensitive debug data" starts with
 * not even collecting it.
 */
final class Diagnostics
{
    private bool $enabled;

    /** @var callable(): float */
    private $clock;

    private float $startedAt;

    /** @var list<array{name: string, elapsed_ms: float}> */
    private array $marks = [];

    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, float> */
    private array $durations = [];

    /**
     * @param callable(): float|null $clock Returns the current time in
     *        seconds as a float (same contract as `microtime(true)`,
     *        the default). Injectable so tests get deterministic elapsed
     *        times instead of depending on real wall-clock time.
     */
    public function __construct(bool $enabled = true, ?callable $clock = null)
    {
        $this->enabled = $enabled;
        $this->clock = $clock ?? static function (): float {
            return microtime(true);
        };
        $this->startedAt = ($this->clock)();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Records a named checkpoint at the current elapsed time since
     * construction (e.g. `mark('breadcrumbs_built')`,
     * `mark('product_cards_rendered')`). A no-op when disabled.
     */
    public function mark(string $name): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->marks[] = [
            'name' => $name,
            'elapsed_ms' => $this->elapsedMsSince($this->startedAt),
        ];
    }

    /**
     * Increments a named counter - e.g. `increment('queries')` per DB
     * call, `increment('cache_hit')`/`increment('cache_miss')` per cache
     * lookup. A no-op when disabled.
     */
    public function increment(string $counter, int $by = 1): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->counters[$counter] = ($this->counters[$counter] ?? 0) + $by;
    }

    public function counter(string $counter): int
    {
        return $this->counters[$counter] ?? 0;
    }

    /**
     * Runs `$work`, adding its wall-clock duration to the named bucket
     * (e.g. `time('db', fn () => $this->db->query($sql))` accumulates
     * total DB time across every query in the bucket named `'db'`).
     * `$work`'s return value is passed through unchanged either way, and
     * `$work` itself still always runs (even when disabled) - only the
     * measurement is skipped, so a call site never has to branch on
     * whether diagnostics are enabled just to get its normal return value.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function time(string $bucket, callable $work)
    {
        if (!$this->enabled) {
            return $work();
        }

        $start = ($this->clock)();
        $result = $work();
        $elapsedMs = (($this->clock)() - $start) * 1000;

        $this->durations[$bucket] = ($this->durations[$bucket] ?? 0.0) + $elapsedMs;

        return $result;
    }

    public function duration(string $bucket): float
    {
        return round($this->durations[$bucket] ?? 0.0, 2);
    }

    public function elapsedMs(): float
    {
        return $this->elapsedMsSince($this->startedAt);
    }

    private function elapsedMsSince(float $start): float
    {
        return round((($this->clock)() - $start) * 1000, 2);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     elapsed_ms: float,
     *     marks: list<array{name: string, elapsed_ms: float}>,
     *     counters: array<string, int>,
     *     durations: array<string, float>
     * }
     */
    public function summary(): array
    {
        $durations = [];

        foreach ($this->durations as $bucket => $elapsedMs) {
            $durations[$bucket] = round($elapsedMs, 2);
        }

        return [
            'enabled' => $this->enabled,
            'elapsed_ms' => $this->elapsedMs(),
            'marks' => $this->marks,
            'counters' => $this->counters,
            'durations' => $durations,
        ];
    }
}
