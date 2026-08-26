# Manual verification checklist

What still needs a human on a real, configured install before treating the
corresponding work as fully done. This is a **live backlog, not an audit
log** — once an item is confirmed with no loose ends, remove it from this
file entirely rather than leaving a checked-off record here. The full
"how it was verified, what was found" narrative belongs in
`docs/IMPLEMENTATION_STATUS.md`, which is the permanent record; this file
only exists to make "what's still outstanding" scannable at a glance.

If something fails, open an issue/PR referencing the item below rather
than silently fixing and forgetting to update this file.

---

## Phase 8 — rating stars never actually seen rendered

No product in the demo dataset has a non-zero rating (no approved reviews
seeded), so `mc-rating`'s star display has been confirmed correct in
isolation (component test) but never observed on a real product-card.
Add a review via Admin → Catalog → Reviews (approve one) and reload a
category page containing that product.

## Phase 8 — `mintcart.log` didn't get written during a live degrade test

Confirmed: the category page degrades gracefully (HTTP 200, no fatal) when
`product-card.twig` is temporarily removed. But the `ERROR` line
`MintCart\Core\Logger` should write to `mintcart.log` never appeared
during that live HTTP request, even though isolated `php -r` checks
confirm both `ComponentRenderer::resolve()`'s throw and `Logger`'s write
work correctly on their own. Likely a local Apache-process-user vs.
`storage/logs/` write-permission gap, not a code defect — not root-caused
further. Worth checking file permissions before this matters for
production.

## Phase 8 — third-party theme (N/A until one exists)

No third-party (non-Mint) theme is installed in this environment. When
one is, confirm it renders unaffected and that `ComponentRenderer`/
`ProductViewProvider` are never instantiated for that request — confirms
the `theme.json`-presence gate actually excludes non-Mint themes, not
just the literal string `'default'`.

## Phase 9 — Presets, entirely unverified visually (currently blocked)

The preset *contract* is machine-checked (`npm test`), but whether Warm
actually **looks** like a coherent, radically different design has never
been seen — and currently can't be, on a real MintCart page:
`assets/dist/css/index.min.css` still isn't enqueued anywhere (a blanket
enqueue attempt broke legacy Bootstrap 3 widgets and was reverted — see
`header.twig`'s own comment). Once that's resolved, on a `mint_default`
category page:

```js
document.documentElement.dataset.mcPreset = 'warm';   // or 'clean'
```

- Warm reads as one deliberate look (terracotta buttons, cream surfaces,
  serif headings, rounder corners, roomier spacing, warm-tinted shadows),
  not a half-applied palette.
- Nothing is left showing mint green/neutral gray — any hit is a
  component reading a hardcoded color instead of a semantic token (a
  component bug, not a preset bug, per ADR 004).
- Text contrast holds under Warm (WCAG 2.2 AA) — picked for contrast on
  paper, never measured against real rendered output.
- Removing the attribute looks identical to `data-mc-preset="clean"`
  (ADR 004 decision 3).
- The same two presets applied to `test_theme`'s different DOM also both
  render coherently.

In the meantime, a standalone HTML file that links the built CSS directly
(no OpenCart involved) would let someone eyeball the palette without
waiting for the enqueue fix, if that's wanted sooner.

## Phase 15 — header cart dropdown DOM swap (browser-only)

Confirmed via `curl` that `common/cart/info`'s own response reflects an
add made through `mintcart/cart/add` (both endpoints share the same
session cart). The actual JS behavior — does clicking product-card's Add
to Cart visibly swap `#cart`'s markup in the header — is browser-only;
`curl` can't click a button. A human should confirm in a real browser.

## Phase 10 — Homepage Products block, never rendered end to end

`MintCart\Blocks\BlockRenderer`/`ProductsBlockProvider`/`ModelMintcartBlock`
are covered by standalone tests, but no live MySQL/`vendor/twig/twig` in
this environment means the actual homepage render was never observed. On
a real install: insert a row into `mc_page` (`code = 'home'`,
`status = 1`) and a row into `mc_block` (`page_id` = that page's id,
`type = 'products'`, `sort_order = 0`, `status = 1`,
`settings_json = '{}'`), then load the homepage while `mint_default` is
the active theme. Expect a "Products" section (latest 8 products,
storewide) above the existing content_top/content_bottom modules; confirm
the legacy `default` theme's homepage is unaffected either way.
