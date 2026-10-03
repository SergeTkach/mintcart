# Homepage Builder

The Homepage Builder lets you compose your store's front page out of
ready-made blocks — a hero banner, a products carousel, a FAQ list, and so
on — without editing any template code or asking a developer to patch the
theme. This matches `MINTCART_PRODUCT_VISION.md`'s "Ready to Look Good"
promise: you decide what the homepage says, MintCart handles how it's
built.

Find it under **Design → Homepage** in the admin menu.

## How it works

The homepage is a stack of blocks, shown top to bottom in **Sort Order**.
Each block is one of the types below, with its own small settings form.
You can:

- **Add** a block, choose its type, and fill in its fields.
- **Edit** a block at any time — including switching it to a different
  type, which reloads the form with that type's own fields (save again to
  apply the new type).
- **Enable/disable** a block without deleting it, to temporarily hide it
  (a seasonal banner you'll reuse next year, for example).
- **Reorder** blocks by editing the Sort Order number on each row, then
  clicking **Save Order**. (Drag-and-drop reordering is planned — see
  "Known limitations" below.)
- **Delete** a block you no longer need.

A block's form has two parts, where relevant:

- **Settings** — structural choices (how many columns, which category,
  image dimensions). These are not translated.
- **Content** — the Title and any text, shown to shoppers. If your store
  has more than one language installed, Content fields are saved
  separately per language, so each language's homepage can have its own
  wording.

## Block types

| Type | What it shows |
| --- | --- |
| **Hero** | A full-width banner image with a subtitle and a call-to-action button. |
| **Products** | A grid of products from a category (or your whole catalog), sorted by date added, popularity, price or name. |
| **Categories** | A grid of your store's categories (optionally limited to a parent category's children). |
| **Banner Grid** | A row of 2–4 banner images, each linking somewhere. |
| **Benefits** | A row of short selling points, each with an icon (free shipping, secure payment, etc.). |
| **FAQ** | An expandable question/answer list. |
| **Testimonials** | Customer quotes with an optional avatar and star rating. |
| **Brands** | A strip of brand/partner logos. |
| **Text / Image** | A paragraph of text next to an image, image on the left or right. |
| **CTA** | A short text block with one call-to-action button. |
| **Newsletter** | An email sign-up form. |
| **Text** | A plain text block (left- or center-aligned). |
| **HTML** | Raw HTML you paste yourself — for embed codes or markup none of the other blocks cover. |
| **Gallery** | A grid of photos (2, 3, 4 or 6 columns). |
| **Spacer** | Blank vertical space between two other blocks (small/medium/large/extra large). |

## Images

There is no image picker on this screen yet. Upload the image file first
— for example through **Store → Products**' own image manager, or via
FTP — then type its path relative to `image/` into the block's Image
field (e.g. `catalog/banner1.jpg`). The Image Width/Height fields tell the
block how to size the `<img>` tag; they should match (or be proportional
to) the actual uploaded file's dimensions.

## Blocks with a list of items (JSON)

Banner Grid, Benefits, Testimonials, Brands, FAQ and Gallery each show a
repeating list of items (one banner per slot, one question per FAQ entry,
and so on). Until a proper "add/remove row" editor ships, that list is
entered as a single JSON field — a plain-text format for a list of
objects. Each of these fields' help text shows a worked example for that
exact block, for instance the FAQ block:

```json
[
  {"question": "Do you ship internationally?", "answer": "Yes, worldwide."},
  {"question": "How long does delivery take?", "answer": "3-5 business days."}
]
```

For blocks with both a Settings items list and a Content items list
(Banner Grid, Benefits, Testimonials, Brands, Gallery), the two lists are
matched up by position — the first object in Content describes the first
object in Settings, and so on, so keep them the same length and in the
same order. If a field isn't valid JSON, saving the block shows an error
and nothing is written, so a typo here can't silently corrupt the
homepage.

## Known limitations

- **No drag-and-drop reordering yet.** Reordering uses a plain Sort Order
  number per row today, not a drag handle — see `docs/developer/blocks/homepage-admin.md`
  for why (it's tied to an open admin-theme architecture question, not
  forgotten).
- **No image picker.** See "Images" above.
- **JSON text areas, not a visual repeater**, for the five item-list block
  types above.
- **Homepage only.** The Builder does not yet support building other
  pages (an Information-page or landing-page builder is out of scope for
  MintCart 1.0's first Homepage Builder milestone, `MINTCART_FUNCTIONAL_SCOPE.md` §10).

None of these are dead ends — each is a scoped, named follow-up in
`docs/IMPLEMENTATION_STATUS.md`'s Phase 10 section and the developer docs
under `docs/developer/blocks/`, not a permanent ceiling.

## See also

- `docs/developer/blocks/` — one doc per block type, with its full field
  contract and the underlying Provider/Renderer classes, for anyone
  extending or theme-overriding a block.
- `docs/developer/blocks/homepage-admin.md` — the admin screen's own
  design notes and the admin-theme question mentioned above.
