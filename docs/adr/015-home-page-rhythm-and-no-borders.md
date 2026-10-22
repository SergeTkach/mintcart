# 015 — Home page rhythm: bands and soft shapes instead of borders

## Context

The home page was a column of white sections that differed only by their titles:
the same small gap between all blocks, bordered cards (testimonials, FAQ, brand
strip, product cards), tiny section titles. It read as a technically correct page
but not as a store (feedback: "no space, no backgrounds, enough of the borders").

## Decision

- **Rhythm.** `.mc-block` gets fluid vertical padding (2.5–4.5 rem). Section titles are
  bigger and bold (`clamp(1.5rem … 2.125rem)`), and the module titles
  (`mc-product-list__title`) match.
- **Bands, not borders.** Sections are told apart by a full-width background: a faint
  wash of the brand colour for benefits, "about" and FAQ, the brand colour itself for
  the CTA. New semantic token `--mc-surface-tint`
  (`color-mix(in srgb, var(--mc-primary) 6%, var(--mc-surface))`), so a preset that changes
  `--mc-primary` recolours the bands with it. New `--mc-shadow-soft` for floating
  icons and images.
- **Soft shapes.** Testimonial cards, FAQ rows, category tiles, product pictures and the
  slider use `--mc-radius-xl` with a tint or white fill; no 1 px frames. Product cards
  have no frame: the picture sits on a tint, the text on the page. mint_comfort's soft
  card no longer pads the photo (a padded lifestyle photo looked like a frame).
- **Content.** Benefits are an icon beside a short title and line; categories are 4 per
  row with a 4:3 picture and a name that shows an arrow on hover.

## Consequences

- One visual system for the home blocks that follows the preset colour.
- Themes that override these classes keep working; those that relied on the old border
  on `.mc-product-card` (`--mc-product-card-border-color`) still can set it.
- Photos with a transparent or white background look best with the multiply blend already
  used by mint_comfort; photos that fill the frame look best with `object-fit: cover`
  (a future card option, not decided).
