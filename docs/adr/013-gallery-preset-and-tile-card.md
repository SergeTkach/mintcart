# ADR 013 — Gallery preset and the tile product card

## Context

Clean and Warm are both "boxed" looks: bordered, rounded cards with shadows.
Real fashion/eyewear/footwear stores (Eyewear Index, both, Footshop) use an
image-first look instead: white page, light grey image tiles, black
rectangular actions, no frames, hierarchy by whitespace. We want to offer that
look and, at the same time, exercise the Theme API with a second structurally
different product card (Technical Plan §45).

## Decision

- `gallery` is a normal preset (`presets/gallery.css`): semantic tokens only —
  black primary, grey `--mc-surface-alt`, square radii, no shadows. Declared in
  `mint_default/theme.json`, so it works on every Mint theme that lists it.
- The tile card is a DOM change, so it ships as a theme, not a preset:
  `mint_gallery` (`parent: mint_default`) overrides only
  `template/mint/components/product-card.twig`. It uses the same
  `product` / `display` / `context` contract (brand = `product.manufacturer`).
  Its CSS is the `mc-product-card--tile` modifier in `product-card.css`.
- The add-to-cart button is revealed on hover/focus for mouse users and always
  visible when the device has no hover.

## Consequences

- 0 OCMOD, 0 controller changes, no copied business logic.
- A store gets the full look by choosing theme `mint_gallery` + preset
  `gallery`; the preset alone gives the colours/shapes on the default card.
- The tile modifier CSS lives in the shared bundle (tiny, inert unless the
  modifier is used). Header/footer variants for this look are not done yet.
