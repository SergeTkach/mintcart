# Setup Wizard

The Setup Wizard walks you from a just-installed store to one that is ready to take a test order
(`MINTCART_PRODUCT_VISION.md` §3, "Time to First Order"). It writes to the normal OpenCart settings, so
everything it sets can also be changed later in the usual places.

## Reaching it

The first time you open the admin dashboard after installing, the Wizard starts by itself. "Skip setup for now"
(top right) leaves it; you can come back any time:

```
admin/index.php?route=mintcart/wizard&user_token=<your token>
```

Progress is saved after every step. Close the browser, come back, and it continues where you stopped. Steps you
have finished are reachable from the side list, so you can go back and change an answer.

## The steps

| # | Step | What you set | Can be skipped |
|---|------|--------------|----------------|
| 1 | Welcome | | |
| 2 | Store | Name, e-mail, telephone, address, **country**, whether you also sell to other countries, logo | no |
| 3 | Contacts | Links to your social profiles (shown in the footer) | yes |
| 4 | Language and currency | Languages and currencies the store offers (a bundled list, live exchange rates) | yes |
| 5 | Delivery | Shipping methods, with their price and zone right on the page | yes |
| 6 | Payment | Payment methods | yes |
| 7 | Design | A look (preset) and the logo | yes |
| 8 | First product | A product with just enough to be sold (optional text per language) | yes |
| 9 | Ready | A store-readiness checklist | |

Optional steps have a **Skip for now** button next to **Continue**: it marks the step as passed without saving
anything. A passed step that is still unfinished is marked with `!` in the side list, and the readiness checklist
on the last step says what is missing.

### Store country

The country is the store's own: it sets the phone format of the checkout (for the store's country) and the default
address OpenCart uses for taxes. OpenCart installs as United Kingdom without asking, so the Wizard shows the country
as *not chosen yet* until you pick it. The region is not asked: MintCart works with countries only.

### "I also sell to other countries"

- **Off** (the default): the checkout does not ask the buyer for a country. Every order goes to the store's
  country, and the phone format is the store's.
- **On**: the checkout asks for the country, and the phone format follows the buyer's country.

You can change it later in `Business → Contacts`.

## Where things end up

Nothing is stored in separate Wizard tables except the progress. The name, e-mail, telephone, address and country
are the standard OpenCart store settings; social links, shipping and payment methods, languages, currencies and the
first product are the normal OpenCart data for each.
