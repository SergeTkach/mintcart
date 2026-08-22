# `mc-pagination`

MintCart's pagination control for product listings (category, search,
manufacturer, specials — the same contexts the `PaginationBuilder` reusable
action from `MINTCART_TECHNICAL_PLAN.md` §15E targets). Purely
presentational — no JS, no `data-mc-*` hook: page links carry a real `href`
to the already-computed page URL, exactly like OpenCart's existing
pagination, so changing page is plain navigation.

CSS: `assets/src/css/components/pagination.css`

## Markup

```html
<nav class="mc-pagination" aria-label="Pagination">
    <ul class="mc-pagination__list">
        <li class="mc-pagination__item">
            <a class="mc-pagination__link" href="?page=1" aria-label="Previous page">‹</a>
        </li>
        <li class="mc-pagination__item">
            <a class="mc-pagination__link" href="?page=1">1</a>
        </li>
        <li class="mc-pagination__item">
            <a
                class="mc-pagination__link mc-pagination__link--current"
                href="?page=2"
                aria-current="page"
            >2</a>
        </li>
        <li class="mc-pagination__item">
            <a class="mc-pagination__link" href="?page=3">3</a>
        </li>
        <li class="mc-pagination__item">
            <span class="mc-pagination__ellipsis" aria-hidden="true">…</span>
        </li>
        <li class="mc-pagination__item">
            <a class="mc-pagination__link" href="?page=12">12</a>
        </li>
        <li class="mc-pagination__item">
            <a class="mc-pagination__link" href="?page=3" aria-label="Next page">›</a>
        </li>
    </ul>
</nav>
```

- Wrap the list in `<nav aria-label="Pagination">` (or a localized label).
- The current page is a real link (still navigable, e.g. to refresh with the
  same query) marked with both `.mc-pagination__link--current` and
  `aria-current="page"` — the modifier drives the visual state, the
  attribute drives assistive tech.
- Skipped ranges use `.mc-pagination__ellipsis` (a non-interactive `<span
  aria-hidden="true">`), not a disabled link.

### Disabled prev/next boundary

On the first page there is no previous page, and on the last page there is
no next page. Render that boundary as a non-interactive `<span
aria-disabled="true">`, never a link with `href="#"` and never a real `<a>`
neutralized with `pointer-events: none` (Functional Scope §27 — a
keyboard/AT user must not be able to tab to and "activate" a link that goes
nowhere):

```html
<li class="mc-pagination__item">
    <span class="mc-pagination__link" aria-disabled="true">‹</span>
</li>
```

## Accessibility

- Prev/next controls need an accessible label (`aria-label="Previous
  page"`/`"Next page"`) since their visible content is just a `‹`/`›`
  glyph — plain characters, not an icon font, so no extra dependency ahead
  of the Bootstrap Icons renderer (Phase 21).
- `aria-current="page"` on the current page link, `aria-hidden="true"` on
  the ellipsis.
- Focus-visible outline matches `mc-button`/`mc-breadcrumb`
  (`--mc-accent`, keyboard-only); a disabled `<span>` is not focusable at
  all, so no focus style is needed for it.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (purely presentational — real `href`s do the work)
- [x] Colors/spacing/typography read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>` links, visible focus, disabled boundary
      excluded from tab order via non-focusable `<span>`)
- [x] Mobile checked (`flex-wrap: wrap`, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
