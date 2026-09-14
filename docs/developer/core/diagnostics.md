# `MintCart\Core\Diagnostics`

`MINTCART_FUNCTIONAL_SCOPE.md` §19 ("Потрібен dev diagnostic mode для
вимірювання: number of queries; DB time; render time; cache status"),
`MINTCART_TECHNICAL_PLAN.md` §35 (Diagnostics), Phase 10 (Technical Plan
numbering) in `docs/IMPLEMENTATION_STATUS.md`.

PHP: `upload/system/library/mintcart/core/diagnostics.php`
(class `MintCart\Core\Diagnostics`)

Test: `tests/mintcart/core/diagnostics-test.php`
(`php tests/mintcart/core/diagnostics-test.php`, 10 scenarios)

**Consumer: none yet.** This ships a tested, documented collector with no
live wiring into any real OpenCart controller/model/template — same "ship
a tested contract before its consumer" precedent already set in this
codebase (`CLAUDE.md` §85), e.g. `MintCart\Seo\HreflangBuilder` before a
language-URL resolver existed, or `MintCart\Onboarding\StoreReadiness`
before its own JSON endpoint was wired.

## Design

No OpenCart dependency — no `\Registry`, `\DB`, `\Config`, `\Template` —
same shape as `StoreReadiness`/`ProductViewProvider`. `Diagnostics` only
collects and summarizes numbers a caller feeds it; it does not itself:

- instrument OpenCart's `\DB\MySQLi` to count real queries,
- wrap `\Template\Twig` (or the Twig adapter `ComponentRenderer` uses) to
  measure real render time, or
- decide when "dev mode" is on for a given request/store.

Each of those is a real, separate design decision this increment
deliberately does not make. Whether to subclass/wrap a core OpenCart class
to count real queries is exactly the kind of upstream-diff question
`CLAUDE.md` §10/§75 says to weigh deliberately (inspect the existing
implementation, its call sites, backward-compat risk) before touching it —
not something to default into while building the collector itself. That
decision, plus the actual "dev mode" flag/UI and where a request first
constructs a `Diagnostics` instance, is deferred to a future run's live
wiring (see "Next step" below).

## API

```php
$diagnostics = new \MintCart\Core\Diagnostics($enabled);

$diagnostics->mark('breadcrumbs_built');      // named checkpoint, elapsed ms since construction
$diagnostics->increment('queries');           // +1 to a named counter
$diagnostics->increment('cache_hit', 5);      // +N to a named counter
$diagnostics->time('db', fn () => $this->db->query($sql)); // measures + accumulates duration under a named bucket, returns $sql's result unchanged

$diagnostics->counter('queries');   // int, 0 if never incremented
$diagnostics->duration('db');       // float ms, 0.0 if the bucket was never used
$diagnostics->elapsedMs();          // float ms since construction

$diagnostics->summary();
// [
//     'enabled'    => true,
//     'elapsed_ms' => 42.31,
//     'marks'      => [['name' => 'breadcrumbs_built', 'elapsed_ms' => 3.12], ...],
//     'counters'   => ['queries' => 7, 'cache_hit' => 5],
//     'durations'  => ['db' => 18.44],
// ]
```

### `$enabled` — a cheap off switch, not just a display filter

Every `mark()`/`increment()` is a no-op when `$enabled` is `false` — no
array writes, no bookkeeping. `time()` is the one exception: it always
runs `$work()` and always returns its result (a call site must never have
to branch on whether diagnostics are on just to get its normal return
value) — only the timing measurement itself is skipped when disabled.
This matters for Functional Scope §19's "Production UI не повинен
показувати sensitive debug data": the cheapest way to not show debug data
in production is to not collect it there at all, so a future live-wiring
call site can pass a store's dev-mode flag straight through to the
constructor with no per-call-site conditional needed.

### Why an injectable clock

The constructor accepts an optional `callable(): float` clock (defaulting
to `microtime(true)`) so tests get exact, deterministic elapsed-time
assertions instead of tolerating real-wall-clock flakiness — see the
test's `fakeClock()` helper, a queue of pre-set times popped in call
order.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file (this
      increment adds only new files)
- [x] Smoke test covering enabled/disabled behavior, mark ordering,
      counter/duration independence, and `time()`'s always-runs-the-work
      contract (10 scenarios)
- [ ] Real query counting — needs a decision on how to hook
      `\DB\MySQLi` (or a thin wrapper around it) without duplicating
      OpenCart's own DB class; not attempted this run
- [ ] Real render-time measurement — needs a decision on where a request
      first constructs a `Diagnostics` instance and how far through
      `ComponentRenderer`/page rendering it gets threaded
- [ ] A "dev mode" admin setting/flag gating `$enabled` — not built yet;
      no admin screen reads or writes it
- [ ] A visible surface (admin toolbar, log line, JSON endpoint) that
      renders `summary()` — not built yet
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)

## Next step

- Decide how to source a real query count without modifying OpenCart's
  `\DB\MySQLi` in place (a thin counting wrapper registered instead of the
  stock class, vs. instrumenting call sites individually) — a real
  upstream-diff decision, not a default to make silently.
- Wire `time('render', ...)` around `ComponentRenderer::render()` calls
  once that decision is made, so `mc_render` duration reflects real
  component rendering.
- A "dev mode" setting (Business/Settings facade, or a new
  `mintcart_dev_mode` config key) that a request reads once and passes to
  `new Diagnostics($enabled)`.
- A visible surface — most likely a small admin-only JSON endpoint (same
  shape as `mintcart/readiness`) or an HTML comment/log line appended when
  dev mode is on — once there is a real request path constructing and
  populating a `Diagnostics` instance to render.
