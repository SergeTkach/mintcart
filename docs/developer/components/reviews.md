# `mc-reviews` / `mc-rating-input`

Product page Reviews tab (`mint_default/template/product/product.twig`) —
the list of approved reviews plus a write-review form. Closes the gap
Phase 6/8's product detail page work explicitly deferred ("the Reviews tab
is a read-only placeholder... a separate, self-contained increment" —
`docs/upstream-diff.md`'s `product.php` entry), per
`MINTCART_FUNCTIONAL_SCOPE.md` §11 "integration-ready reviews area".

CSS: `assets/src/css/components/reviews.css`
JS: `assets/src/js/components/reviews.js` (`initReviews`)
List fragment template: `mint_default/template/product/review.twig`

## Why this talks to stock OpenCart routes, not a new MintCart backend

OpenCart 3.0.5.1 already ships a complete, working review system:
`ControllerProductProduct::review()` (GET, paginated, returns a rendered
HTML fragment via `$this->load->view('product/review', $data)`) and
`::write()` (POST, validates and inserts via `ModelCatalogReview`, returns
`{error}` or `{success}` JSON). Both are untouched by MintCart. Per
`CLAUDE.md` §10/§84 ("never modify an existing OpenCart flow... prefer a
small upstream diff"), this component is a pure **theme-layer** addition:

- `review.twig` is a normal per-theme template override — the same
  mechanism `product.twig`/`category.twig`/`manufacturer_list.twig`
  already use, **not** routed through `MintCart\Design\ComponentRenderer`
  (there is no cross-theme fallback chain for it; a theme that wants its
  own review markup just ships its own `product/review.twig`, same as any
  other stock OpenCart theme template).
- `reviews.js` fetches the GET endpoint's HTML response and injects it via
  `innerHTML` — it does **not** re-implement `Pagination::render()`'s
  markup or parse/transform the response in any way. Re-rendering a
  server-side `Pagination` instance's HTML in JS would be exactly the kind
  of "copied business logic" `CLAUDE.md` §8A warns against, for zero real
  benefit (the stock markup already works, and the whole point of
  `Pagination::render()` existing is that no caller has to rebuild it).
- The write form POSTs via `fetch()`/`FormData` to the stock `write()`
  action and renders whatever single `error`/`success` message it returns
  — no new validation rules invented, no second copy of OpenCart's own
  review-validation logic.

## Markup contract

```html
<div class="mc-reviews" data-mc-reviews data-mc-reviews-product-id="42">
    <div class="mc-reviews__list" data-mc-reviews-list aria-live="polite">
        <p class="mc-reviews__empty">No reviews yet.</p>
    </div>

    <div class="mc-reviews__form-wrapper">
        <p class="mc-reviews__form-title">Write a review</p>

        <form class="mc-reviews__form" data-mc-reviews-form novalidate>
            <div class="mc-field mb-3">
                <label class="mc-field__label" for="input-review-name">Your Name</label>
                <input type="text" name="name" id="input-review-name" class="mc-field__control" required minlength="3" maxlength="25">
            </div>

            <div class="mc-field mb-3">
                <label class="mc-field__label" for="input-review-text">Your Review</label>
                <textarea name="text" id="input-review-text" class="mc-field__control" required minlength="25" maxlength="1000"></textarea>
            </div>

            <fieldset class="mc-rating-input mb-3">
                <legend class="mc-field__label">Rating</legend>
                <div class="mc-rating-input__stars">
                    <input type="radio" class="mc-rating-input__control visually-hidden" name="rating" id="input-review-rating-5" value="5" required>
                    <label class="mc-rating-input__star" for="input-review-rating-5">
                        <span class="visually-hidden">5 - Good</span>
                        <span aria-hidden="true">★</span>
                    </label>
                    <!-- ...4, 3, 2... -->
                    <input type="radio" class="mc-rating-input__control visually-hidden" name="rating" id="input-review-rating-1" value="1" required>
                    <label class="mc-rating-input__star" for="input-review-rating-1">
                        <span class="visually-hidden">1 - Bad</span>
                        <span aria-hidden="true">★</span>
                    </label>
                </div>
            </fieldset>

            <div data-mc-reviews-message></div>

            <button type="submit" class="mc-button mc-button--primary" data-mc-reviews-submit>Continue</button>
        </form>
    </div>
</div>
```

- `data-mc-reviews-product-id` is the only piece of server data `reviews.js`
  needs — it builds both endpoint URLs from it client-side (same
  "hardcode `index.php?route=...` directly in JS" convention
  `header-cart.js`/`quick-order-client.js` already use, not threaded
  through `$data` as a pre-built `url->link()` string).
- Review rows inside `[data-mc-reviews-list]` (rendered server-side by
  `review.twig`, not by this JS file) reuse the exact same `mc-rating`
  read-only markup `product-card.twig` already renders for each product's
  average rating — see `docs/developer/components/rating.md`.
- **Star order is 5→1 in source, reversed visually by CSS** — see
  `mc-rating-input` below. Don't "fix" the apparent reverse order in the
  markup; it's required for the CSS trick to work.
- The guest/logged-in gate (`review_guest`) and the login/register prompt
  it falls back to are unchanged stock `$data` keys — see
  `ControllerProductProduct::index()`.

## `mc-rating-input`

A different component from the read-only `mc-rating`
(`docs/developer/components/rating.md`) — that doc explicitly deferred
building an interactive picker "until a real consumer existed"
(`CLAUDE.md` §85); this write-review form is that consumer.

Pure CSS radio-group star picker, no JS:

- Five `<input type="radio" name="rating">` authored in **5, 4, 3, 2, 1**
  source order, each immediately followed by its `<label>`.
- `.mc-rating-input__stars { flex-direction: row-reverse; }` flips the
  *visual* order back to 1→5 left-to-right, while keeping DOM order 5→1.
- `:checked ~ .mc-rating-input__star` (general sibling combinator) then
  lights up every star whose radio comes *after* the checked one in DOM
  order — which, because of the reversal, are the stars visually *at or
  to the left of* the checked one. Same trick for `:hover`.
- Wrapped in a `<fieldset>`/`<legend>` for a single accessible group name;
  each star's visible glyph is `aria-hidden`, with the real accessible
  name (`"N - Good"` / `"N - Bad"` / plain `"N"`) in a `.visually-hidden`
  span inside the `<label>` — reusing Bootstrap 5's own `.visually-hidden`
  utility (`MINTCART_IMPLEMENTATION_GUIDE.md` §6 — generic Bootstrap
  utilities are fine to use).
- Native radio semantics mean Tab/Arrow-key/Space all work with zero
  custom key handling — same reasoning `mc-product-gallery`'s thumbnail
  `<a>` links use real link semantics instead of a custom click handler.

## Behavior (`reviews.js`)

- `initReviews()` finds every `[data-mc-reviews]` root and, for each,
  eagerly fetches page 1 of the review list on init — mirrors the stock
  `default` theme's own unconditional `$('#review').load(...)` on page
  load (not lazy on tab-show).
- Clicking a pagination link inside `[data-mc-reviews-list]` is
  intercepted (event delegation on the list element, not a fixed set of
  elements bound once — the pagination links themselves are replaced on
  every fetch) and re-fetches that page instead of a full navigation.
  Because `Pagination::render()`'s own `<a href>` targets are real,
  working URLs, this still works with JS disabled.
- Submitting `[data-mc-reviews-form]` runs the browser's own
  `reportValidity()` first (the `required`/`minlength`/`maxlength`
  attributes already on the markup), then POSTs via `fetch()`/`FormData`.
  The stock endpoint only ever returns one message (not per-field errors),
  so the response renders as a single `mc-alert` inside
  `[data-mc-reviews-message]` — no `has-error` field highlighting is
  attempted (there's no way to know which field a message refers to from
  this response shape).
- On success, the form resets but the list is **not** re-fetched — a new
  review needs admin approval first (the stock `text_success` message says
  so), so it would not appear in the list yet regardless.

## Accessibility

- `[data-mc-reviews-list]` has `aria-live="polite"` so a screen reader
  announces when the fetched review list replaces the loading/placeholder
  content.
- The rating picker is a real `<fieldset>`/`<legend>` radio group — see
  `mc-rating-input` above.
- Review-list stars reuse `mc-rating`'s own `role="img"` + `aria-label`
  contract — no new accessibility pattern needed there.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] JS behavior only via `data-mc-reviews-*`/`data-mc-action`, never a
      CSS class selector
- [x] Colors/spacing read from `--mc-*` tokens
- [x] Keyboard usable (native radio group + native form controls + real
      `<a>` pagination links)
- [x] Mobile checked (`mc-reviews__pagination` wraps; form is a simple
      stacked `mc-field` list, same as every other MintCart form)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required (two one-line additive `$data` assignments only —
      see MC-0022/MC-0023)
- [x] Theme override checked — `test_theme` doesn't override
      `product.twig`/`review.twig`, so both fall back to `mint_default`'s
      version via the same Theme resolver fallback chain Phase 8 already
      proved (ADR 002/003)
