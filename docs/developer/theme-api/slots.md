# Extension slots (`MintCart\Design\SlotRegistry`)

`MINTCART_TECHNICAL_PLAN.md` §15 ("Slots / Extension points") and
`MINTCART_IMPLEMENTATION_GUIDE.md` §35 list named extension points a
third-party extension should be able to render markup into — e.g.
`product.after_actions`, `header.before`/`header.after`,
`cart.before_totals`/`cart.after_totals`, `checkout.before_confirm` — so a
store doesn't need an OCMOD patch or a theme fork just to add a small
fragment (a trust badge, a promo banner, a tracking pixel) at a known,
stable position.

PHP: `upload/system/library/mintcart/design/slotregistry.php`
(`MintCart\Design\SlotRegistry`)
Test: `tests/mintcart/design/slot-registry-test.php`

**First real template consumer:** `product-card.twig`'s `product.after_actions`
slot (`docs/developer/components/product-card.md`), wired through
`MintCart\Design\ProductCardListRenderer`'s optional 3rd constructor
argument (`docs/developer/theme-api/product-card-list-renderer.md`).

## Why this was not built earlier

Every other Theme API primitive in this codebase (`ComponentRenderer`,
`ProductViewProvider`, `Stage`) was built with a real, already-identified
consumer in hand, per `CLAUDE.md` §85 ("API з'являється тоді, коли існує
мінімум один реальний consumer"). `docs/developer/core/migration-manager.md`
(a previous increment) explicitly checked for a Slots implementation before
writing a Slots developer doc, found none, and declined to write one ahead
of the mechanism existing — this increment is that mechanism, plus its
first real template call site.

## Why a plain registry, not `MintCart\Core\Stage`

`MintCart\Core\Stage` (`docs/developer/core/stage.md`, Technical Plan §15B)
already gives controllers a fine-grained before/after event pair around a
*method* boundary — a listener mutates `$data` before/after a stage runs.
A slot is a different question: a *template* asking "does anything want to
render inside me, right here", answered with an HTML fragment, not a
mutation to intercept. Reusing `Stage`/OpenCart's `\Event` for this would
force every slot caller to hold a `\Registry`/`\Event` dependency just to
ask a template question — the same "no OpenCart dependency" rationale
`ComponentRenderer`/`ProductViewProvider` already follow
(`CLAUDE.md` §86: don't reach for a heavier existing mechanism when a
smaller, purpose-built one is clearer). `SlotRegistry` has zero OpenCart
dependency and is fully unit-testable in isolation.

## API

```php
$slots = new \MintCart\Design\SlotRegistry();

// An extension (or a future admin-configurable "custom HTML" feature)
// registers a listener for a documented slot name:
$slots->listen('product.after_actions', function (array $context): string {
    return '<p class="mc-badge mc-badge--new">Ships today</p>';
});

// Whoever owns the slot's call site (ProductCardListRenderer, today) asks:
$slots->has('product.after_actions');   // bool
$slots->render('product.after_actions', $context); // string, '' if no listener
```

- `listen(string $slot, callable $renderer)` — `$renderer` is
  `callable(array $context): string`. Multiple listeners on the same slot
  all render, concatenated in registration order — a second extension never
  silently overrides the first one's fragment.
- `has(string $slot): bool` — whether any listener is registered.
- `render(string $slot, array $context = []): string` — always returns a
  string (a non-string listener return value is cast), `''` when no
  listener is registered. Never throws on an unregistered slot — a slot
  with nothing registered is the default, expected state for every slot in
  this codebase today, not an error.

`$context`'s shape is documented per slot by whoever owns that slot's call
site, not by this class — `product.after_actions`'s context is the
product's own normalized `product` array
(`docs/developer/commerce/product-view-provider.md`), the way
`ProductCardListRenderer` passes it.

## No listener is registered anywhere in this codebase yet

`render()` returns `''` for every slot today — there is no admin UI, no
extension, nothing that calls `listen()`. This is the expected, stable
state until a real consumer needs one (`CLAUDE.md` §85) — the mechanism and
its first template call site are what this increment proves, the same way
`ProductViewProvider`'s `options_summary` key was reserved in its contract
ahead of a Product Page listing context actually populating it. A future
increment (an admin "Custom HTML" settings field, a theme's own
`functions.php`-equivalent bootstrap, or a real third-party extension) is
expected to be the first real `listen()` caller.

## Adding a second slot

1. Confirm the slot name is one Technical Plan §15 already lists, or has a
   genuine new real consumer (don't pre-wire a slot nobody asked for,
   `CLAUDE.md` §85).
2. The owning component/template renders it the same way
   `product-card.twig` does: `{% if slots is defined and slots['<name>'] %}{{ slots['<name>']|raw }}{% endif %}`.
3. Whoever builds that component's data (today, `ProductCardListRenderer`;
   for `header.before`/`header.after`, a future `HeaderViewProvider`
   change) accepts an optional `?SlotRegistry $slotRegistry = null`
   constructor/method argument and only adds the `slots` key when it's
   non-null — the same zero-behavior-change-by-default shape
   `ComponentRenderer`'s optional `$diagnostics` argument already
   established.
4. Document the slot's `$context` shape next to that component's own
   contract doc (see "Extension slot" in
   `docs/developer/components/product-card.md`).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
  (`tests/mintcart/design/slot-registry-test.php`)
- [x] A real template consumer — `product-card.twig`'s `product.after_actions`
      (see `docs/developer/components/product-card.md`)
- [x] No OCMOD required
- [ ] A real `listen()` caller — none exists yet (see "No listener is
      registered anywhere in this codebase yet" above); this is the
      expected state until a real consumer needs one, not a gap to rush
- [ ] Live verification — this environment has no `vendor/twig/twig`, so
      `product-card.twig`'s `{% if slots[...] %}` branch has never actually
      been exercised by a real Twig render (same pre-existing gap every
      other Theme API component in this file carries); see
      `docs/MANUAL_VERIFICATION.md`'s Theme API / Slots entry.

## Verified

`php -l` clean on `slotregistry.php` and `productcardlistrenderer.php`;
`tests/mintcart/design/slot-registry-test.php` (5 scenarios, standalone,
no OpenCart dependency) and
`tests/mintcart/design/product-card-list-renderer-test.php` (10 scenarios,
up from 8 — the 2 new ones prove the optional `SlotRegistry` argument is
consulted with the product's own id when supplied, and is never touched at
all when omitted) both pass; full `tests/mintcart/**/*-test.php` suite
re-run end to end, no regressions. `npm run build`/`npm test` both pass;
`mc-product-card__slot` confirmed present in both
`assets/dist/css/index.min.css` and `assets/dist/css/components.min.css`.

**Not verified:** no live MySQL/`vendor/twig/twig` session in this
environment — a human should confirm on a real install that the `{% if %}`
guard in both `product-card.twig` files renders nothing when no
`SlotRegistry` is wired (today, always — no controller constructs one yet)
and renders the listener's fragment once a future consumer wires one in.
