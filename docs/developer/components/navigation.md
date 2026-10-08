# Navigation — `mc-nav`

`mint_default/template/common/menu.twig` overrides the stock category menu
template. Data is unchanged: the stock `ControllerCommonMenu` `categories`
array (`name`, `href`, `children[]`, `column`) and `text_all`. No controller
change.

```html
<ul class="mc-nav">
  <li class="mc-nav__item">            <a class="mc-nav__link">
  <li class="mc-nav__item dropdown">   <a class="mc-nav__link mc-nav__link--parent" data-bs-toggle="dropdown" data-bs-display="static">
    <div class="mc-nav__panel dropdown-menu">
      <ul class="mc-nav__sublist"><li><a class="mc-nav__sublink">
      <a class="mc-nav__all">
```

- Rendered inside `mc-header`'s `.mc-header__nav` (offcanvas drawer below
  `lg`, static bar from `lg`).
- Below `lg`: vertical list, sub-menu opens inline. From `lg`: horizontal
  bar, sub-menu is a flyout panel. Open state = Bootstrap 5 dropdown `.show`
  / `aria-expanded`; Popper is off (`data-bs-display="static"`), CSS
  positions it (`assets/src/css/components/navigation.css`).
- Keyboard: Bootstrap dropdown (Enter/Space opens, arrows move, Esc closes).
- Tokens: `--mc-nav-link-color`, `--mc-nav-link-hover-color`.
- `category.column` (stock multi-column flyout) is not used yet.

Why: the stock `default/` menu relies on Bootstrap 3 `.navbar-collapse`,
which Bootstrap 5 hides at every width (ADR 010).
