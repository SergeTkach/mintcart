# `MintCart\Compatibility\OpenCart3\ExtensionAdapter`

The first class in MintCart's `Compatibility` layer
(`MINTCART_TECHNICAL_PLAN.md` §5, `MINTCART_IMPLEMENTATION_GUIDE.md` §38):
`Mint code → Adapter → OpenCart model/service/storage`, so a normalized
"is this feature usable" question doesn't require Mint code to know
OpenCart's own two-step installed/enabled storage shape.

PHP: `upload/system/library/mintcart/compatibility/opencart3/extensionadapter.php`
(class `MintCart\Compatibility\OpenCart3\ExtensionAdapter`)

Test: `tests/mintcart/compatibility/extension-adapter-test.php`
(`php tests/mintcart/compatibility/extension-adapter-test.php`)

Consumer: `upload/admin/model/mintcart/readiness.php`
(`ModelMintcartReadiness::hasEnabledExtension()`, feeding
`MintCart\Onboarding\StoreReadiness`'s `shipping`/`payment` signals — see
`docs/developer/onboarding/store-readiness.md`)

## Why this exists

OpenCart's `setting/extension` model tracks which extensions are
**installed** (a row in the `extension` table), but each extension only
becomes **enabled** when its own admin controller saves a
`<type>_<code>_status` config flag (e.g. `extension/shipping/flat.php`
saves `shipping_flat_status`). "Installed" on its own is not a usable
signal — a store can have a shipping extension installed and still left
disabled, which still leaves it unable to actually ship anything.

`ModelMintcartReadiness::getSignals()` originally inlined this
installed-then-check-config-flag loop directly (`hasEnabledExtension()`,
added with `StoreReadiness` — see `docs/developer/onboarding/store-readiness.md`).
It is the same shape `MINTCART_TECHNICAL_PLAN.md` §23 expects a future
`Business → Delivery`/`Business → Payments` façade to need again ("read a
list of standard shipping extensions and show a simplified management
flow" — not a second shipping/payment subsystem), so it was extracted here
rather than being copied a second time.

## Contract

```php
$adapter = new \MintCart\Compatibility\OpenCart3\ExtensionAdapter(
    $config,                      // OpenCart's Config object — needs get(string $key)
    $modelSettingExtension        // the loaded setting/extension model proxy — needs getInstalled(string $type): array
);

$adapter->hasEnabled('shipping'); // bool — true if at least one installed `shipping` extension has its status flag on
$adapter->hasEnabled('payment');  // same, for `payment`
```

Both constructor arguments are duck-typed rather than declared against a
concrete OpenCart class — OpenCart's model proxies (`$this->model_setting_extension`)
have no common interface to type against, and `Config` is a plain
storage object. Any caller that has already run
`$this->load->model('setting/extension')` can pass `$this->config` and
`$this->model_setting_extension` directly, exactly as `ModelMintcartReadiness`
does.

`hasEnabled()` is the only public method — `getEnabledCodes()` (the
per-code list `hasEnabled()` counts) is currently a private implementation
detail, kept out of the public surface until a real caller needs the list
itself rather than the yes/no answer (`CLAUDE.md` §85). Widening it to
`public` when that caller exists is a one-line change, not a redesign.

## Verified / not verified

- `php -l` clean on the new adapter and the updated `readiness.php`.
- `tests/mintcart/compatibility/extension-adapter-test.php` (5 scenarios:
  nothing installed, installed-but-disabled, installed-and-enabled,
  "at least one enabled" among several installed, and type isolation —
  an enabled `shipping` extension must not count as an enabled `payment`
  one).
- Full `tests/mintcart/` suite re-run end to end (35 files, was 34) — all
  pass, no regressions. `ModelMintcartReadiness::hasEnabledExtension()`'s
  observable behavior is unchanged (same inputs → same boolean), only its
  implementation moved.
- **Not verified live** — same pre-existing gap as every other
  OpenCart-wiring increment in this repo: no MySQL/OpenCart admin session
  in this environment. A human should confirm `mintcart/readiness` still
  reports `shipping`/`payment` correctly on a real install (enabling/
  disabling a shipping or payment extension should still flip the
  corresponding signal), since that is the one live consumer this change
  touches.
