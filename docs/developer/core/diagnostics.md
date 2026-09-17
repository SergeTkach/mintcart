# `MintCart\Core\Diagnostics`

`MINTCART_FUNCTIONAL_SCOPE.md` §19 ("Потрібен dev diagnostic mode для
вимірювання: number of queries; DB time; render time; cache status"),
`MINTCART_TECHNICAL_PLAN.md` §35 (Diagnostics), Phase 10 (Technical Plan
numbering) in `docs/IMPLEMENTATION_STATUS.md`.

PHP: `upload/system/library/mintcart/core/diagnostics.php`
(class `MintCart\Core\Diagnostics`)

Test: `tests/mintcart/core/diagnostics-test.php`
(`php tests/mintcart/core/diagnostics-test.php`, 10 scenarios)

**First real consumer: `ControllerProductCategory` +
`MintCart\Design\ComponentRenderer`.** `ComponentRenderer::render()`
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
response body.

**Second/third/fourth/fifth consumers: `ControllerProductSearch`,
`ControllerProductSpecial`, `ControllerProductManufacturer::info()`, and
`ControllerProductProduct`'s related-products `ComponentRenderer` call
site.** Each now carries the identical wiring — its own
`Diagnostics($enabled)` gated by the same `mintcart_dev_mode` setting,
passed as `ComponentRenderer`'s 4th argument, `mark('view_rendered')`
before its `load->view()` call, and the same dev-mode-gated HTML-comment
summary appended to its response body. `ControllerProductManufacturer`'s
plain manufacturer-list index and its `error/not_found` path never
construct a `ComponentRenderer` and were left untouched.
`ControllerProductProduct`'s `$mint_diagnostics` is constructed
unconditionally (even when the active theme isn't Mint-aware and the
related-products `ComponentRenderer` never runs), so `mark()`/the
HTML-comment summary still cover the rest of the product page's render
time. Every listing/detail page that builds a `ComponentRenderer` is now
wired. Still **not** built: real DB query counting (see the DoD checklist
below), and an admin UI to toggle `mintcart_dev_mode` (today it can only
be set directly in `oc_setting`).

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
      optional `Diagnostics` argument, wired into `ControllerProductCategory`,
      `ControllerProductSearch`, `ControllerProductSpecial`,
      `ControllerProductManufacturer::info()`, and
      `ControllerProductProduct`'s related-products call site — every
      `ComponentRenderer` call site is now wired (see above)
- [x] A "dev mode" setting gating `$enabled` — `mintcart_dev_mode`,
      standard OpenCart setting (`$this->config->get('mintcart_dev_mode')`
      in `ControllerProductCategory`); no admin UI toggle yet, must be set
      directly in `oc_setting` today
- [x] A visible surface — an HTML comment
      (`<!-- mintcart-diagnostics {...} -->`) appended to
      `product/category`'s and `product/product`'s response body when
      `mintcart_dev_mode` is on
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)

## Next step

- Decide how to source a real query count without modifying OpenCart's
  `\DB\MySQLi` in place (a thin counting wrapper registered instead of the
  stock class, vs. instrumenting call sites individually) — a real
  upstream-diff decision, still not made.
- Every `ComponentRenderer` call site (Category, Search, Manufacturer,
  Special, Product) is now wired with `Diagnostics` — no remaining bare
  call sites for this pattern.
- An admin UI toggle for `mintcart_dev_mode` (Business/Settings facade, or
  a small Advanced/Settings field) instead of requiring a direct
  `oc_setting` write.
- A structured JSON endpoint (same shape as `mintcart/readiness`) as an
  alternative surface to the HTML comment, for tooling that would rather
  fetch diagnostics than scrape a page's markup.
