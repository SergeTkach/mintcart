# Storefront: Bootstrap 3 → Bootstrap 5 switch (mint_default)

Branch: `phase-2-storefront-bootstrap5` (from `master`). ADR 010.

## Why

`mint_default/template/common/header.twig` still loads the legacy OpenCart 3
stack (Bootstrap 3 CSS/JS) while every Mint-owned template (header component,
checkout, blocks) is written for Bootstrap 5 (CLAUDE.md §4,
MINTCART_IMPLEMENTATION_GUIDE.md §7/§10). Visible symptoms on a live install:

- BS3's `html { font-size: 10px }` shrinks every `rem`-based `--mc-*` token
  to 62.5 % (labels at ~8.75px);
- BS5 utilities used by Mint templates (`g-*`, `py-*`, `mb-*`,
  `visually-hidden`, `offcanvas`) do not exist → no spacing, broken header.

The 2026-08-24 attempt loaded BS5 *on top of* BS3 and was reverted. The
correct switch is: BS3 out, BS5 in, legacy markup covered by a shim.

## Audit (2026-10-07)

~29 templates are owned by `mint_default`; the other ~160 fall back to
`default/` with BS3 markup (account, cart, information, modules, payment /
shipping / total extension widgets).

Legacy JS → Bootstrap API:

| Pattern | Uses | BS5 | Plan |
|---|---|---|---|
| `$(...).button('loading' / 'reset')` + `data-loading-text` | 157 (common.js, cart, every payment confirm) | removed in BS4 | jQuery shim |
| `data-toggle="collapse/dropdown/tab/tooltip"` | 84 | needs `data-bs-*` | attribute mirror shim (incl. AJAX-inserted HTML) |
| `$(...).modal('show')` | 12 | BS5 jQuery interface | works as is |
| `$(...).tooltip({...})` | 2 | BS5 jQuery interface | works as is |

BS5 registers its jQuery plugins on `DOMContentLoaded` when `window.jQuery`
exists, so jQuery must stay loaded before `catalog.min.js`.

Most frequent BS3-only classes in `default/` (files): `pull-right` 101,
`form-group` 61, `control-label` 56, `form-horizontal` 44, `btn-default` 37,
`checkbox` 33, `close` 30, `text-danger` 26*, `input-group-btn` 22,
`pull-left` 18, `table-responsive` 18*, `panel` 16, `pagination` 16,
`img-responsive` 14, `hidden-xs` 14, `hidden-sm/md` 12, `radio-inline` 12,
`col-xs-*` 10, `caret` 9, `has-error` 6, `thumbnail` 6, `btn-block` 6,
`well` 4, `input-group-addon` 4, `col-sm-offset-*` 4, `label label-*` 4.
(* also exists in BS5 — no shim needed.)

## Steps (check off as they land)

- [x] 1. JS shim `assets/src/js/compatibility/bootstrap3-jquery.js`:
      `$.fn.button('loading'|'reset')`, `data-toggle` → `data-bs-toggle`
      mirror (initial DOM + MutationObserver). Unit-tested.
- [x] 2. CSS shim: extend `compatibility/bootstrap3-shim.css` with the
      class list above (forms, panels, visibility, grid aliases, labels,
      close, caret, thumbnail, well, img-responsive).
- [x] 3. `mint_default/template/common/header.twig`: drop BS3 CSS/JS, load
      `index.min.css` (BS5 + Mint) instead of `components.min.css`; keep
      jQuery, Font Awesome, `common.js`. `ControllerCommonHeader`: choose
      the bundle. Admin keeps `components.min.css` (still BS3).
- [~] 4. Rebuild, copy `assets/dist` → `upload/assets/dist`, live check:
      home, category, product (options, reviews, tabs), search, cart,
      account login/register/edit, information, contact, Mint checkout,
      bank transfer / COD confirm blocks.
      HTTP smoke (2026-10-07): 12 routes 200, no PHP errors, no BS3 asset.
      Watch: product 42 loads BS3-era `bootstrap-datetimepicker` (date/time
      options); legacy modules using `.product-thumb` lost
      default/stylesheet.css. Visual pass by the user still pending.
- [~] 5. Fix what step 4 finds (shim only; no edits to `default/`).
- [~] 6. Docs: ADR 010 status, IMPLEMENTATION_STATUS, MANUAL_VERIFICATION,
      header.twig comment, then PR. Afterwards rebase/merge into
      `phase-15-mint-checkout` and resume its live check.

## Progress note (2026-10-07, end of day)

Steps 4-6 partly done: pages checked with headless Edge screenshots and
fixes landed (menu, carets, colours via bootstrap-bridge.css, cart page,
category header, gallery, sliders). Branch merged into
`phase-15-mint-checkout`; IMPLEMENTATION_STATUS and MANUAL_VERIFICATION
updated. Remaining: the user's own click-through, then PR.

## Product lists (2026-10-07)

- [x] A. Shared `mintcart/product_cards` controller
      (`docs/developer/commerce/product-cards.md`); featured / bestseller /
      special / latest modules render `mc-product-card` in `mc-product-list`
      on mint_default. Featured verified on home over HTTP; the other three
      are not placed in any layout on the dev DB — not seen rendered.
- [x] B. Move the six existing copies (category, search, special,
      manufacturer, product related, home Products block) onto the shared
      controller, one commit each. Card HTML diffed before/after over HTTP:
      identical for category/search/special/manufacturer; related gained
      srcset + width/height; home Products block not on the dev DB, unseen.
- [x] C. Fix: add-to-cart / quick-order JSON redirect kept `&amp;`.
- [x] D. `mc-nav` category menu (stock menu.twig is BS3 navbar, hidden by
      BS5) — `docs/developer/components/navigation.md`.

## Notes for the checkout-branch merge

- JSON contract: URLs unescaped (`&`, not `&amp;`) and product names plain
  text (`html_entity_decode`), escaped by the JS that renders them.
  Exception, resolved at merge (2026-10-07): `mintcart/cart` and checkout
  state keep OpenCart's stored entities (`30&quot;`) on purpose -
  `checkout.js` renders them with `innerHTML` and relies on OpenCart's
  escaped strings (as stock templates do). Do not decode them.

## Out of scope

- Admin stays on BS3 (separate phase).
- No rewrite of `default/` templates or `common.js`.
