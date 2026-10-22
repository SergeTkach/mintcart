# Customer reviews page and the Testimonials block

OpenCart only lists the reviews of one product (on its page). MintCart adds a
store-wide list and lets the home page show the latest reviews.

## Page

`route=mintcart/reviews` ("Customer reviews"): a summary band on top (average rating,
number of reviews, how many per star), then all approved reviews, newest first, 12 per
page with pagination. Each review is a card: stars, date, text, author (with an initial
avatar) and the product it is about (picture, name, link).

- Controller `upload/catalog/controller/mintcart/reviews.php`
- Model `upload/catalog/model/mintcart/review.php` (`getReviews()`, `getTotalReviews()`, `getSummary()`;
  same visibility rules as OpenCart's product reviews: review and product enabled,
  product available and in the current store)
- Language `upload/catalog/language/en-gb/mintcart/reviews.php`
- Template `mint_default/template/mintcart/reviews.twig` (child themes inherit it); styles `assets/src/css/components/reviews-page.css`

## Testimonials block with real reviews

Block settings (`mc_block.settings_json`): `{"source": "reviews", "limit": 3}`.
`source` is `reviews` or `manual` (default, the hand-written quotes in
`content_json`). `limit` is 1–12 (default 3). Localized `content_json`:
`title`, optional `more_label` (default "All reviews").

With `source: reviews` the home controller fills the block with the latest
reviews: quote = first 180 characters of the text, author = reviewer,
role line = the product name, rating = the star rating. Under the cards there
is a button to the reviews page. With no reviews the block is not rendered.

Contract change: `TestimonialsBlockProvider::build()` also returns `source`,
`limit`, `more_label`; the template receives `more_url`, `more_label`,
`text_rated` (`more_url` is empty for a manual block, so nothing changes there).

## Tests

`tests/mintcart/blocks/testimonials-block-provider-test.php` (scenario 4).
The query and page were checked on the local store with six demo reviews
(`scripts/demo/build_demo.py`).

## Not done

- The page has no SEO URL alias yet (route form only); it is not in the
  System pages list.
- No link to the page in the footer or menu.
- The home block cannot be switched to reviews from the admin yet (the
  Homepage Builder edits blocks through the existing editor; set
  `source` in the block settings).
