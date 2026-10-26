# Design > Styles

The owner's look settings, stored in the `mintcart_design` setting code and applied as one
`<style id="mc-style-overrides">` block after the theme CSS (and a Google Fonts `<link>` when a web
font is chosen). A value left empty means "whatever the preset says".

| Setting (`mintcart_design_*`) | Values | Effect |
| --- | --- | --- |
| `preset` | a preset code the active theme lists | token set (colours, spacing, shadows) |
| `primary`, `accent` | `#rrggbb` | `--mc-primary` (+ hover / active mixed from it) and `--mc-accent`; the text colour on them is picked for contrast |
| `radius` | `sharp`, `soft`, `rounded` | `--mc-radius-sm..xl` |
| `border` | `light`, `strong` | border colour / `--mc-border-width` |
| `button_style` | `outline`, `soft` (empty = solid) | the main `mc-button--primary` |
| `button_shape` | `square`, `pill` (empty = follow corners) | `--mc-button-radius` |
| `container` | `narrow` 1140px, `wide` 1520px (empty = Bootstrap 1320px) | `.container` max-width from 1400px up |
| `font_heading`, `font_body` | ids in `StyleOverrides::FONTS` | `--mc-font-family-heading/base` |

Code: `MintCart\Design\StyleOverrides` (pure; validates every value, so nothing else can reach the
stylesheet), wired in `ControllerCommonHeader`, form in `ControllerMintcartDesignStyles`
(`design/styles.twig`). The live preview (`assets/src/js/components/design-styles.js`) repeats the
token values of `StyleOverrides`; the look tiles and the wizard's look step read their swatches from
`system/mintcart/data/design-presets.json`. All bundled web fonts include Cyrillic. Test:
`tests/mintcart/design/style-overrides-test.php`.

Not done: a self-hosted font option (the fonts load from Google), per-block overrides, and a preview
that is the real storefront in an iframe.
