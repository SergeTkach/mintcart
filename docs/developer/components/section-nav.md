# `mc-section-nav`

Sticky in-page navigation between the sections of a long page. First
consumer: mint_default `product/product.twig` (Overview / Description /
Specification / Reviews / Related), which replaced the `mc-tabs` block —
all content is on the page, the nav just jumps to it (same UX idea as
Cartello's PDP sticky bar).

Files: `assets/src/css/components/section-nav.css`,
`assets/src/js/components/section-nav.js` (wired in `catalog.js`).

## Markup

```twig
{% set section_url = (breadcrumbs|last).href %}
<nav class="mc-section-nav" data-mc-section-nav aria-label="{{ text_section_nav }}">
    <ul class="mc-section-nav__list">
        <li><a class="mc-section-nav__link" href="{{ section_url }}#product-overview" aria-current="true">…</a></li>
        <li><a class="mc-section-nav__link" href="{{ section_url }}#product-reviews">…</a></li>
    </ul>
</nav>

<div id="product-overview" data-mc-section>…</div>
<div id="product-reviews" data-mc-section>…</div>
```

- Links carry the **full page URL** + `#id`: OpenCart prints
  `<base href="…/">`, so a bare `#id` would point at the home page. Without
  JS the link still lands on the section (after a reload).
- `[data-mc-section]` marks the targets for the scroll spy. Link order
  should follow page order.
- Any other link to a section (e.g. the rating under the PDP title) gets the
  in-place scroll with `data-mc-action="scroll-to-section"`.

## Behavior

- Sticks under the stuck part of the header: `top:
  var(--mc-header-sticky-offset)`, published on `<html>` by
  `sticky-header.js` (0 when the theme has no sticky header).
- JS measures the bar into `--mc-section-nav-height`; `:root:has(.mc-section-nav)`
  sets `scroll-padding-top` = header + bar + `--mc-space-4`, so both JS
  scrolling and plain anchor jumps clear them.
- Click → `scrollIntoView()`; smoothness comes from Bootstrap's `:root`
  `scroll-behavior` (off under `prefers-reduced-motion`). Ctrl/Cmd/Shift
  clicks are left to the browser.
- Scroll spy: the last section whose top passed the bar's bottom edge
  (+24px) gets `aria-current="true"` on its link; at the very end of the
  page the last section wins. On phones the list scrolls horizontally and
  keeps the active link centred.

## Theme API

Contract = `.mc-section-nav*` classes + `data-mc-section-nav` /
`data-mc-section` / `aria-current`. A theme can restyle it via tokens/CSS
or drop it from its own `product.twig`; nothing else depends on it.
