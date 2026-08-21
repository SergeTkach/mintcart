# mc-button

CSS-only component. Source: `assets/src/css/components/button.css`.

There is no Twig rendering pipeline yet (that starts with Phase 6 Product
Card / Phase 8 Theme resolver), so this component is plain markup - any
future `mc_button()` Twig helper wraps the same class contract documented
here without changing it.

## Markup contract

```html
<button type="button" class="mc-button">Add to cart</button>
```

Variants (default is primary - no modifier class needed):

```html
<button type="button" class="mc-button mc-button--secondary">Cancel</button>
<button type="button" class="mc-button mc-button--ghost">Learn more</button>
<button type="button" class="mc-button mc-button--danger">Delete</button>
```

Sizes (default is the base size - no modifier class needed):

```html
<button type="button" class="mc-button mc-button--sm">Small</button>
<button type="button" class="mc-button mc-button--lg">Large</button>
```

Works on `<a>` too when the action navigates instead of running a script:

```html
<a href="/checkout" class="mc-button">Checkout</a>
```

## Behavior hooks

`mc-button` has no JS of its own. A real `<button>`/`<a>` is already
keyboard usable, so nothing extra is required for that. Add whatever
`data-mc-action="..."` hook the consumer needs
(`docs/MINTCART_IMPLEMENTATION_GUIDE.md` §14) - this component's CSS never
depends on it:

```html
<button
    type="button"
    class="mc-button"
    data-mc-action="add-to-cart"
    data-mc-product-id="42"
>
    Add to cart
</button>
```

## States

| State | How |
| --- | --- |
| hover / active | plain CSS pseudo-classes, no extra markup |
| focus-visible | plain CSS pseudo-class, visible focus ring using `--mc-accent` |
| disabled | native `disabled` attribute, not a class or `aria-disabled` |

## Accessibility

- Use a real `<button>` or `<a>` element - never a `<div>`/`<span>` with a
  click handler.
- `disabled` buttons are skipped by keyboard navigation and screen readers
  automatically; do not also add `aria-disabled`.
- Focus ring (`:focus-visible`) meets WCAG 2.2 AA visibility - do not
  suppress it with `outline: none`.

## Tokens consumed

`--mc-primary(-hover|-active)`, `--mc-on-primary`, `--mc-surface-alt`,
`--mc-color-gray-200/300`, `--mc-text`, `--mc-border`, `--mc-danger`,
`--mc-color-red-700`, `--mc-on-danger`, `--mc-accent`, spacing/radius/font/
motion tokens. No hardcoded colors.
