# MintCart

**OpenCart, designed for people.** MintCart is a ready-to-sell online store distribution built on OpenCart 3.0.5.1:
you install it, walk through a Setup Wizard, add a product, pick a look, connect delivery and payment, and make a
test order. No hunting for modules, no copying a theme's patches into the core.

> Status: **early alpha (0.1.0)**. It installs and the Setup Wizard works; expect rough edges. See
> [`docs/IMPLEMENTATION_STATUS.md`](docs/IMPLEMENTATION_STATUS.md) for what is done and what is not.

## What is inside

- **Setup Wizard**: language, currency, store country, contacts, delivery, payment, design and the first product,
  with a store-readiness checklist at the end. It writes to the normal OpenCart settings.
- **Storefront** on Bootstrap 5 and vanilla JavaScript: a single-page checkout, a product card used everywhere,
  mini cart, Quick Order, wishlist, compare, a homepage builder with ready blocks, several design presets.
- **Delivery**: Nova Poshta and Ukrposhta (searchable city and branch pickers over a local directory),
  InPost UK lockers (not yet verified against the live API), and "Delivery services", one extension for any
  fixed-price carrier without an API.
- **Theme API**: a new theme changes the product card, header, footer and design tokens with no OCMOD and no
  controller patches.
- **OpenCart inside**: PHP, Twig and MySQL, third-party OpenCart extensions keep working, OCMOD stays available
  for legacy extensions.
- 16 interface languages in the admin and the wizard (translations by the assistant, not yet reviewed by native
  speakers).

## Install

1. Download `mintcart-<version>.zip` from the releases page and unpack it into the web root of your site (the
   content of the `upload/` folder goes to the root).
2. Create an empty MySQL or MariaDB database (utf8mb4).
3. Open `https://your-site/install/` and follow the installer.
4. Delete the `install/` folder when it asks you to.
5. Open the admin: the Setup Wizard starts on the dashboard.

Requirements: PHP 8.1 or newer with `mysqli`, `gd`, `curl`, `zip`, `mbstring`, `dom`, `xmlwriter`, `json`;
a MySQL or MariaDB database; Apache with `mod_rewrite` (the bundled `.htaccess.txt` is the starting point). More in
[`docs/user/install.md`](docs/user/install.md). The `storage/` folder lives next to `system/` in the site root and
is blocked by the bundled `.htaccess`; you can move it outside the web root in System > Maintenance > Storage.

## Develop

```sh
npm install
npm run build      # frontend bundles into upload/assets/dist
npm run release    # builds release/mintcart-<version>.zip
```

Tests are plain PHP scripts: `php tests/mintcart/<area>/<name>-test.php`.

The source of truth for the product is in [`docs/`](docs/): vision, functional scope, technical plan and the
implementation guide. Developer docs (Theme API, components, blocks, shipping forms) are under
[`docs/developer/`](docs/developer/); architecture decisions under [`docs/adr/`](docs/adr/).

## License

GNU General Public License v3.0, the same as OpenCart. See [`LICENSE`](LICENSE). MintCart is a derivative of
[OpenCart](https://www.opencart.com/); changes to its files are listed in
[`docs/upstream-diff.md`](docs/upstream-diff.md).

## Questions and bugs

Open an issue at <https://github.com/SergeTkach/mintcart/issues>.
