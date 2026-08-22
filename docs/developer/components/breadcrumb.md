# `mc-breadcrumb`

MintCart's breadcrumb trail. Shows the page's position in the catalog
hierarchy (Home → Category → Subcategory → Product). Purely presentational —
no JS, no `data-mc-*` hook. Feeds from the `BreadcrumbBuilder` reusable
action named in `MINTCART_TECHNICAL_PLAN.md` §15E once that provider exists;
this component only defines the markup/CSS contract it will render into.

CSS: `assets/src/css/components/breadcrumb.css`

## Markup

```html
<nav class="mc-breadcrumb" aria-label="Breadcrumb">
    <ol class="mc-breadcrumb__list">
        <li class="mc-breadcrumb__item">
            <a class="mc-breadcrumb__link" href="/">Home</a>
        </li>
        <li class="mc-breadcrumb__item">
            <a class="mc-breadcrumb__link" href="/laptops">Laptops</a>
        </li>
        <li class="mc-breadcrumb__item" aria-current="page">MacBook Pro 14"</li>
    </ol>
</nav>
```

- Wrap the list in `<nav aria-label="Breadcrumb">` (or a localized label) so
  assistive tech can identify and skip to/past it.
- Use an `<ol>` — the trail is an ordered sequence of ancestors.
- Every ancestor is a real link (`.mc-breadcrumb__link`) except the current
  page, which is plain text with `aria-current="page"` on the `<li>` — the
  page you are on is not usefully clickable.
- The `/` separator between items is a CSS `::before` on
  `.mc-breadcrumb__item:not(:first-child)`, not an extra DOM node — no
  separator markup to add or hide from screen readers.

## Accessibility

- `aria-current="page"` communicates the current position without relying
  on visual styling alone.
- Separators are decorative and rendered via CSS `content`, not a visible
  DOM character, so they don't add noise for assistive tech.
- Links use the same focus-visible outline as `mc-button`/`mc-field`
  (`--mc-accent`, keyboard-only).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (purely presentational)
- [x] Colors/spacing/typography read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>` links, visible focus)
- [x] Mobile checked (`flex-wrap: wrap`, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
