# `MintCart\Core\Stage`

`MINTCART_TECHNICAL_PLAN.md` §15B ("Fine-grained Events") and
`MINTCART_IMPLEMENTATION_GUIDE.md` §38B, Phase 1 (Technical Plan numbering)
in `docs/IMPLEMENTATION_STATUS.md`.

PHP: `upload/system/library/mintcart/core/stage.php` (class `MintCart\Core\Stage`)

Test: `tests/mintcart/core/stage-test.php`
(`php tests/mintcart/core/stage-test.php`, 6 scenarios)

**First real consumer: `ControllerProductManufacturer::index()`.** The
short A–Z brand-listing method (not `info()`, which is large and already
carries several Mint primitives — see below) now fires
`catalog.manufacturer.breadcrumbs.before`/`.after` around its breadcrumb
build, and `catalog.manufacturer.listing.before`/`.after` around its
manufacturer-list build. `$data['breadcrumbs']`/`$data['categories']` are
still built exactly as before; `Stage::run()` only wraps the existing code
in a closure and fires the two events around it.

## Why this exists

`MINTCART_TECHNICAL_PLAN.md` §15/§15A-§15F and
`MINTCART_IMPLEMENTATION_GUIDE.md` §35/§38A-§38B call for controller
actions to expose stable, documented before/after extension points around
*meaningful stages* (breadcrumbs, listing, layout, render — not every
private helper), so a third-party extension can hook into a specific part
of a page without OCMOD-patching the controller file itself. Technical
Plan §15B explicitly suggests the shape this class takes:

```php
$this->stage('breadcrumbs', function () {
    $this->buildBreadcrumbs();
});
```

Before this, MintCart had no fine-grained event mechanism at all — only
OpenCart's own native route-level events (`controller/product/category/before`,
fired once per whole controller action by `Loader`/`router.php`, see
`upload/system/engine/loader.php`). A route-level event can't let an
extension react to "breadcrumbs are about to be built" versus "the product
listing is about to be queried" within the same action.

## Design

Duck-typed, not hard-coupled to OpenCart's `\Event` class: `Stage`'s
constructor only requires an object with a `trigger(string $event, array
$args = [])` method, so the class is unit-testable without bootstrapping
OpenCart's registry/engine (same "no OpenCart dependency" shape as
`Diagnostics`/`StoreReadiness`). In production, a controller passes
`$this->event`, which resolves to OpenCart's real `\Event` instance via
`Controller::__get()` → the registry.

**Naming convention — dot-separated, not slash-separated.** OpenCart's own
native events use a slash-separated route path:
`controller/product/category/before`. `Stage`-fired events instead use
dots: `catalog.manufacturer.breadcrumbs.before`. This is deliberate: these
are MintCart-defined *sub-method* stage boundaries, not OpenCart's
route/model/view lifecycle, and a different separator keeps the two event
namespaces from colliding or being mistaken for one another in the admin
Extensions → Events screen (which lists both side by side once an
extension registers a listener for either).

**Reuses OpenCart's existing event mechanism — no second event system.**
`Stage::run()` is a thin wrapper around the same `Event::trigger()` →
`Action::execute()` → `call_user_func_array()` chain OpenCart's core
already uses for `controller/*/before`, `model/*/before`, etc. (see
`upload/system/engine/loader.php`). This matters for two reasons:

1. `MINTCART_TECHNICAL_PLAN.md` §90 / `CLAUDE.md` §86: don't build a
   parallel framework when the existing mechanism already does the job.
2. A third-party extension developer registers a `Stage`-fired event via
   the exact same Admin → Extensions → Events UI and `install.xml`
   mechanism they'd use for any other OpenCart event — nothing new to
   learn, and OCMOD remains available to legacy extensions exactly as
   `CLAUDE.md` §37/§8 describes, even though MintCart's own code that
   calls `Stage` is itself OCMOD-free.

**`$data` is passed to both events by reference.** OpenCart's own
`controller/*/before` event already lets a registered listener mutate a
by-reference `$data` argument before the real controller body runs (that's
how an OCMOD-free override pattern is possible at all in native OpenCart).
`Stage::run()`'s `$data` parameter is `array &$data`, and it's handed to
`trigger()` as `[&$data]` — so a listener can read or add to `$data` from
either the `.before` or `.after` event, and the mutation is visible both
to `$work()` itself (if the listener ran on `.before`) and to the
caller once `run()` returns.

**No swallowed failures.** If `$work()` throws, the exception propagates
unchanged and the `.after` event never fires — firing it after a failed
stage would misrepresent what happened. Existing MintCart call sites
already own their own `try`/`catch` around risky work (e.g. `category.php`'s
`CanonicalUrlBuilder` wiring); `Stage` doesn't add a second failure-handling
convention on top of that.

## API

```php
$stage = new \MintCart\Core\Stage($this->event, 'catalog.manufacturer');

$data['categories'] = array();

$stage->run('listing', $data, function () use (&$data) {
    // ... build $data['categories'] exactly as before ...
});
// fires 'catalog.manufacturer.listing.before' with [&$data],
// then runs the closure,
// then fires 'catalog.manufacturer.listing.after' with [&$data]
```

A third-party extension registers a listener the same way any other
OpenCart event listener is registered (Admin → Extensions → Events, or an
OCMOD `install.xml` adding a row to `oc_event`), pointing at a controller
route whose method signature mutates `$data` by reference:

```php
// upload/catalog/controller/extension/event/example.php
class ControllerExtensionEventExample extends Controller {
    public function onManufacturerListingBefore(&$data) {
        // inspect or add to $data before the listing is built
    }
}
```

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency in the class itself — unit-testable in
      isolation (duck-typed against `\Event`)
- [x] Smoke test covering before/after ordering, return-value passthrough,
      before/after listener mutation of `$data` by reference, `$prefix`
      namespacing, and that a `$work()` exception skips `.after` (6
      scenarios)
- [x] A real consumer — `ControllerProductManufacturer::index()`'s
      `breadcrumbs`/`listing` stages (see `docs/upstream-diff.md`'s
      `manufacturer.php` entry for the exact diff and compatibility
      analysis)
- [ ] Live verification against a real install/extension registration —
      this environment has no MySQL/OpenCart admin session (same
      pre-existing gap noted throughout `docs/IMPLEMENTATION_STATUS.md`);
      a human should register a test event listener on
      `catalog.manufacturer.listing.before` via Admin → Extensions →
      Events and confirm it receives `$data` by reference as documented.

## Next step

- Decompose `ControllerProductCategory::index()` into named stage methods
  with `Stage`-fired events per Implementation Guide §38A's example
  (`buildBreadcrumbs()`, `buildListing()`, ...) — deliberately **not**
  attempted in this increment because PR #157 ("SortOptionsBuilder +
  LimitOptionsBuilder, wired into category.php") was open and touching the
  same file at the time; re-check `gh pr list --state open` for
  `category.php` collisions before starting this.
- Extend the same pattern to `search.php`/`special.php`/`product.php`'s
  breadcrumb/listing stages once `manufacturer.php`'s proof lands cleanly.
- `ControllerProductManufacturer::info()` (the larger method, listing
  products for one manufacturer) is a natural second consumer in the same
  controller — not attempted here to keep this increment small and
  reviewable.
- A documented catalog of all `Stage`-fired event names (a single
  reference page, once more than one or two controllers use `Stage`) —
  not worth creating for a single consumer yet (`CLAUDE.md` §85).
