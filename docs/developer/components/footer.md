# `mc-footer`

MintCart's storefront footer component (`MINTCART_IMPLEMENTATION_GUIDE.md`
§48, Technical Plan §14). Second Phase 7 component after Header (Header is
not yet started — see `docs/IMPLEMENTATION_STATUS.md`).

CSS: `assets/src/css/components/footer.css`
No JS — purely presentational; the newsletter form has no submit behavior
yet (see "Newsletter" below).

## Provider

`MintCart\Design\FooterViewProvider`
(`upload/system/library/mintcart/design/footerviewprovider.php`) is the
single place the `footer` shape gets assembled — columns, contacts, social
links, payment badges, newsletter and the "powered by" text — mirroring
`MintCart\Commerce\ProductViewProvider`'s role for `product-card`
(`docs/developer/commerce/product-view-provider.md`). Contacts source from
OpenCart's existing `config_telephone`/`config_email`/`config_address`
settings — never duplicated into a Mint-owned table
(`MINTCART_IMPLEMENTATION_GUIDE.md` §39, `CLAUDE.md` §39).

```php
$footer = (new \MintCart\Design\FooterViewProvider())->build(
    $columns,     // link columns, e.g. Information / Service / Account
    $contacts,    // ['phone' => ..., 'email' => ..., 'address' => ...]
    $social,      // [] until Business Settings (Phase 14) has storage
    $payments,    // [] until Business -> Payments (Phase 14) has storage
    $newsletter,  // null/disabled until a subscribe endpoint exists (Phase 15)
    $powered      // pre-formatted "Powered by X" text
);
```

### Why social/payments/newsletter default to empty/disabled

OpenCart has no built-in storage for social profile links or payment
badges, and no subscribe endpoint for a newsletter form exists yet
(`MintCart\Commerce` gets a `CartService`/`SearchService` in Phase 15, not
before). Rather than invent a settings table or a fake POST target ahead of
a real consumer (`CLAUDE.md` §85), `FooterViewProvider` renders these
sections exactly as empty/disabled as the data actually is today — a caller
that has nothing to pass gets a footer with no social/payments/newsletter
sections at all, not broken-looking placeholders. `newsletter.enabled` is
only ever `true` when the caller supplies **both** `enabled: true` **and** a
real `action` URL — supplying one without the other still renders nothing,
because a form pointed at nowhere is worse than no form.

## Live Twig template

`catalog/view/theme/mint_default/template/mint/components/footer.twig`
matches the contract below exactly and is resolvable today via the existing
`MintCart\Design\ComponentRenderer` — no renderer changes were needed,
since `ComponentRenderer::resolve()`/`render()` already take an arbitrary
component name (`docs/developer/theme-api/component-renderer.md`), not just
`product-card`.

**Live controller wiring is done.** `catalog/controller/common/footer.php`
(`ControllerCommonFooter::index()`) builds a `FooterViewProvider` result and
renders it via `ComponentRenderer::render('footer', ...)` into
`$data['mc_footer']`, gated by the same "only when the active theme ships a
`theme.json` manifest" check `ControllerProductCategory` uses for
`product-card` (`docs/upstream-diff.md`). The controller's original
per-field `$data` (`informations`/`contact`/`return`/`sitemap`/`account`/
`order`/`wishlist`/`newsletter`/...) is left completely untouched — the
new code only *regroups* those same already-resolved values into
`FooterViewProvider` columns, it doesn't change what they resolve to.
Contacts come from `config_telephone`/`config_email`/`config_address`.
`catalog/view/theme/mint_default/template/common/footer.twig` is the new
page-level template that actually outputs `{{ mc_footer }}` (plus the
`styles`/`scripts`/`</body></html>` every theme's footer template needs);
the legacy `default` theme's own `common/footer.twig` is untouched and
never reads `$data['mc_footer']`. The three new language strings the
template needs (`text_contact_us`, `text_newsletter_email`,
`text_newsletter_submit`) were added to
`catalog/language/en-gb/common/footer.php` — `text_newsletter` itself
already existed and is reused as-is (same "Newsletter" text serves both the
pre-existing account-link label and the component's newsletter heading).

**Theme override proof is done.** `catalog/view/theme/test_theme/` (already
overriding `product-card.twig` since Phase 8's milestone proof) now also
overrides `footer.twig` with a genuinely different DOM: no Bootstrap grid
columns, no social-links list, no payment badges, no newsletter form — just
a single stacked link list, an optional one-line phone/email row, and the
"powered by" text, marked with its own `mc-footer--test` class instead of
`mint_default`'s `mc-footer`. `test_theme`'s `theme.json` still declares
`"parent": null`, so this proves `ComponentRenderer` resolves a per-theme
override independently for *each* component name — a theme can override
some components (`product-card`, now `footer`) and still inherit everything
else (Header, once it exists) from Mint Default automatically, 0 OCMOD.
Verified statically the same way scenario 10 verified `product-card.twig`
(this environment still has no MySQL/`vendor/twig/twig`, so no live
`config_theme`-switch-and-load-a-page check was possible):
`tests/mintcart/design/component-renderer-test.php` scenario 12 confirms
`ComponentRenderer::resolve('footer')` returns `test_theme`'s own file when
it's active, and still returns Mint Default's file when `mint_default` is
active — both directions of the fallback, against the real shipped repo
files, not fixtures.

**Not yet done:** a live end-to-end request test (no MySQL/`vendor/twig/twig`
in this environment — see `docs/upstream-diff.md`'s entry for this
controller for the full verification writeup).

## Markup contract

Given a `FooterViewProvider::build()` result assigned to `columns` /
`contacts` / `social` / `payments` / `newsletter` / `powered`:

```twig
<footer class="mc-footer">
    <div class="container">
        <div class="row g-4">
            {% for column in columns %}
                <div class="col-6 col-lg-3">
                    <h5 class="mc-footer__column-title">{{ column.title }}</h5>
                    <ul class="mc-footer__links">
                        {% for link in column.links %}
                            <li><a href="{{ link.href }}">{{ link.label }}</a></li>
                        {% endfor %}
                    </ul>
                </div>
            {% endfor %}

            <div class="col-6 col-lg-3">
                <h5 class="mc-footer__column-title">{{ text_contact_us }}</h5>

                {% if contacts.phone or contacts.email or contacts.address %}
                    <ul class="mc-footer__contacts">
                        {% if contacts.phone %}<li><a href="tel:{{ contacts.phone }}">{{ contacts.phone }}</a></li>{% endif %}
                        {% if contacts.email %}<li><a href="mailto:{{ contacts.email }}">{{ contacts.email }}</a></li>{% endif %}
                        {% if contacts.address %}<li>{{ contacts.address }}</li>{% endif %}
                    </ul>
                {% endif %}

                {% if social %}
                    <ul class="mc-footer__social">
                        {% for link in social %}
                            <li>
                                <a class="mc-footer__social-link" href="{{ link.href }}" aria-label="{{ link.label }}" target="_blank" rel="noopener noreferrer">
                                    {{ link.label|slice(0, 1)|upper }}
                                </a>
                            </li>
                        {% endfor %}
                    </ul>
                {% endif %}
            </div>

            {% if newsletter.enabled or payments %}
                <div class="col-6 col-lg-3">
                    {% if newsletter.enabled %}
                        <h5 class="mc-footer__column-title">{{ text_newsletter }}</h5>
                        <form class="mc-footer__newsletter-form" action="{{ newsletter.action }}" method="post">
                            <div class="mc-field">
                                <label class="mc-field__label" for="mc-footer-newsletter-email">{{ text_newsletter_email }}</label>
                                <input type="email" id="mc-footer-newsletter-email" name="email" class="mc-field__control" required>
                            </div>
                            <button type="submit" class="mc-button mc-button--primary">{{ text_newsletter_submit }}</button>
                        </form>
                    {% endif %}

                    {% if payments %}
                        <ul class="mc-footer__payments">
                            {% for payment in payments %}
                                <li class="mc-footer__payment">
                                    {% if payment.icon %}
                                        <img class="mc-footer__payment-icon" src="{{ payment.icon }}" alt="{{ payment.name }}" loading="lazy">
                                    {% else %}
                                        {{ payment.name }}
                                    {% endif %}
                                </li>
                            {% endfor %}
                        </ul>
                    {% endif %}
                </div>
            {% endif %}
        </div>

        <div class="mc-footer__bottom">
            <p>{{ powered }}</p>
        </div>
    </div>
</footer>
```

`text_contact_us`, `text_newsletter`, `text_newsletter_email`,
`text_newsletter_submit` are ordinary OpenCart language strings — same
pattern every other `catalog/language/*` file already uses, not a new i18n
mechanism (not yet added to a real language file since there's no live
controller wiring — see "Live Twig template" above).

## Social icon placeholder

`{{ link.label|slice(0, 1)|upper }}` (the network name's first letter) is a
placeholder the same way `product-card.twig` uses a literal `♥`/`⇄` for
wishlist/compare — swap for `{{ mc_icon(link.network) }}` once the
Bootstrap Icons SVG renderer exists (`MINTCART_IMPLEMENTATION_GUIDE.md`
§21).

## Bootstrap grid, not a parallel one

`.container`/`.row`/`.col-6.col-lg-3` provide the responsive column layout
(`docs/developer/layout-conventions.md`) — `mc-footer` and its `__*`
elements never reimplement grid math.

## Accessibility

- Social links use `aria-label` (the network name, not icon-only text) so
  a screen reader announces "Facebook" rather than a bare letter glyph.
- The newsletter email input has a real, associated `<label>`
  (`.mc-field__label`, per `docs/developer/components/field.md`'s
  contract) — never a placeholder-only field.
- Contact `tel:`/`mailto:` links are real anchors, keyboard-reachable like
  any other link — no click-only JS behavior.
- `.mc-footer__social-link:focus-visible` gets the same visible-outline
  treatment as every other interactive MintCart component.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (pure presentation; newsletter form has no submit
      behavior yet — no endpoint exists, Phase 15)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>`/`<label>`/`<input>`/`<button>` elements)
- [x] Mobile checked (Bootstrap grid columns stack at `col-6`/`col-lg-3`,
      no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Ships a real Mint Default `.twig` file, resolvable via
      `ComponentRenderer` (verified: `if`/`for` tag nesting is balanced,
      same script-based check used for `product-card.twig`)
- [x] Live controller wiring — `ControllerCommonFooter::index()`, see "Live
      Twig template" above
- [x] Theme override checked — `test_theme` now overrides `footer.twig`
      with a genuinely different DOM, see "Live Twig template" above
