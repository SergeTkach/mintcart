# `MintCart\Core\Diagnostics`

`MINTCART_FUNCTIONAL_SCOPE.md` §19 ("Потрібен dev diagnostic mode для
вимірювання: number of queries; DB time; render time; cache status"),
`MINTCART_TECHNICAL_PLAN.md` §35 (Diagnostics), Phase 10 (Technical Plan
numbering) in `docs/IMPLEMENTATION_STATUS.md`.

PHP: `upload/system/library/mintcart/core/diagnostics.php`
(class `MintCart\Core\Diagnostics`)

Test: `tests/mintcart/core/diagnostics-test.php`
(`php tests/mintcart/core/diagnostics-test.php`, 10 scenarios)

**First real consumer (this run): `ControllerProductCategory` +
`MintCart\Design\ComponentRenderer`.** `ComponentRenderer::render()` now
accepts an optional 4th constructor argument, `?Diagnostics $diagnostics
= null` — see `docs/developer/theme-api/component-renderer.md`'s own
"`$diagnostics` (Phase 10)" section for the full wiring detail. This is
the real render-time-measurement decision this file previously deferred:
`render()` is the one method every component render (product-card,
blocks, header, footer, ...) already passes through, so instrumenting it
there covers every current and future consumer without touching each
individual call site. `ControllerProductCategory` constructs a
`Diagnostics($enabled)` gated by a new `mintcart_dev_mode` setting (read
via `$this->config->get()`, standard OpenCart settings storage, no new
Mint table), passes it into `ComponentRenderer`, and — only when
enabled — appends `$diagnostics->summary()` as an HTML comment to the
response body. Still **not** built: real DB query counting (see the DoD
checklist below), an admin UI to toggle `mintcart_dev_mode` (today it can
only be set directly in `oc_setting`), and wiring the same pattern into
Search/Manufacturer/Special (each still builds its own `ComponentRenderer`
without a `Diagnostics` — same shape as `ControllerProductCategory`'s
pre-Phase-10 state, a natural next increment).

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
- [x] Real render-time measurement — `ComponentRenderer::render()`'s
      optional `Diagnostics` argument (this run), wired into
      `ControllerProductCategory`; Search/Manufacturer/Special not wired
      yet (see above)
- [x] A "dev mode" setting gating `$enabled` — `mintcart_dev_mode`,
      standard OpenCart setting (`$this->config->get('mintcart_dev_mode')`
      in `ControllerProductCategory`); no admin UI toggle yet, must be set
      directly in `oc_setting` today
- [x] A visible surface — an HTML comment
      (`<!-- mintcart-diagnostics {...} -->`) appended to
      `product/category`'s response body when `mintcart_dev_mode` is on
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)

## Next step

- Decide how to source a real query count without modifying OpenCart's
  `\DB\MySQLi` in place (a thin counting wrapper registered instead of the
  stock class, vs. instrumenting call sites individually) — a real
  upstream-diff decision, still not made.
- Wire the same `ComponentRenderer` + `Diagnostics` pattern into
  `ControllerProductSearch`/`ControllerProductManufacturer`/
  `ControllerProductSpecial` (each still constructs a bare
  `ComponentRenderer` with no `Diagnostics`), so the same dev-mode flag
  measures every listing page, not just Category.
- An admin UI toggle for `mintcart_dev_mode` (Business/Settings facade, or
  a small Advanced/Settings field) instead of requiring a direct
  `oc_setting` write.
- A structured JSON endpoint (same shape as `mintcart/readiness`) as an
  alternative surface to the HTML comment, for tooling that would rather
  fetch diagnostics than scrape a page's markup.
