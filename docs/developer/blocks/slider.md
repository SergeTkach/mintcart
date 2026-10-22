# Homepage Builder — Slider block

The store's main slider. Block type `slider`. Rich slides: a small line above
the title (eyebrow), a title with a highlighted word, a subtitle, a price with
an old price, and two buttons. It is built on the `mc-slider` engine (Swiper,
arrows, dots, keyboard, autoplay: `docs/developer/components/slider.md`, preset
`rich`); the slide markup is `mc-slide__*`.

It replaces the stock OpenCart Slideshow module on the home page (the module
and its Swiper-3 template stay for stores that use them). Idea and layouts
ported from the author's own `st_slideshow`, rewritten for MintCart (Bootstrap 5,
vanilla JS, `mc-*`, no OCMOD, no jQuery).

## Layouts (`settings.layout`)

| Value | Look |
|---|---|
| `split` (default) | text on one side, the picture on the other; stacks on phones |
| `full` | the picture fills the slide, text over it; `color` `navy` = dark text, `light` = white text over a dark shade |
| `cards` | the slider (split slides) next to two promo cards; the cards go under the slider below 992 px |

## Data

`settings_json` (structure) and `content_json` (text of the current language),
matched by position, like Banner Grid and Testimonials.

```json
// settings_json
{
  "layout": "split",
  "autoplay": 6000,
  "items": [
    {"image": "catalog/slide1.jpg", "link": "index.php?route=product/category&path=20",
     "btn2_link": "index.php?route=product/special", "color": "navy"}
  ],
  "cards": [{"image": "catalog/card1.jpg", "link": "index.php?route=product/category&path=21"}]
}
// content_json
{
  "items": [
    {"eyebrow": "New season", "title": "Light for <em>slow</em> evenings", "subtitle": "Lamps and candles",
     "price": "$22", "old_price": "$29", "btn_text": "Shop now", "btn2_text": "Learn more", "image_alt": ""}
  ],
  "cards": [{"kicker": "Sale", "title": "Up to <em>30%</em> off", "link_text": "Shop"}]
}
```

- `autoplay`: milliseconds per slide, at least 2000; `0` turns autoplay off.
- A slide needs a picture or a title; the first button needs `link` (its text
  defaults to "Shop now"), the second needs `btn2_text`.
- Titles may contain `<em>` (the highlighted word) and `<br>`; every other tag
  and all attributes are escaped by the provider, the template prints the result
  as is. All other text is escaped.
- Pictures are resized per layout (split 760×600, full 1600×640, cards 640×340)
  and a missing file only removes the picture.
- Text in slides is not a heading (no h2/h3): the page has one real h1
  (Implementation Guide §19.1).

## Code

| Part | File |
|---|---|
| Provider | `upload/system/library/mintcart/blocks/sliderblockprovider.php` (`MintCart\Blocks\SliderBlockProvider`) |
| Render | `ControllerCommonHome::renderSliderBlock()` in `upload/catalog/controller/common/home.php` |
| Template | `upload/catalog/view/theme/mint_default/template/mint/blocks/slider.twig` (child themes inherit or override) |
| CSS | `assets/src/css/components/slide.css` (+ `slider.css` engine) |
| JS | preset `rich` in `assets/src/js/components/slider.js` |
| Strings | `upload/catalog/language/en-gb/mintcart/slider.php` |
| Admin | type `slider` in `ControllerDesignHomepage::TYPES` and its field schema; labels in `admin/language/en-gb/design/homepage.php` |

## Tests

`tests/mintcart/blocks/slider-block-provider-test.php` (defaults, escaping,
autoplay limits, cards). The `split` layout was checked in headless Edge on the
local store; `full`, `cards` and the phone layout were not yet.

## Not done

- The Homepage Builder edits slides as raw JSON (like the other list blocks); a
  slide editor with picture pickers and drag-and-drop ordering is a separate piece.
- The open category menu to the left of the slider (nolio's `catalog` option).
- Slide entrance animation / floating effect (`float_effect` in `st_slideshow`).
- `full` is inside the page container, not edge to edge.
