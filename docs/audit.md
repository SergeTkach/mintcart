# MintCart — Repository Audit (Phase 0)

Date: 2026-08-21

Purpose: per `CLAUDE.md` §21 / `MINTCART_TECHNICAL_PLAN.md` §42 "Phase 0 — Audit", record
the exact upstream baseline and current repository state before any MintCart-layer code is
written. This file is a snapshot; re-run/update it if the baseline changes materially.

## 1. Upstream baseline

- Base product: **OpenCart 3.0.5.1** (`define('VERSION', '3.0.5.1')` in both
  `upload/index.php` and `upload/admin/index.php`).
- Repository layout is the stock OpenCart distribution root (`upload/`) containing
  `catalog/`, `admin/`, `system/`, `install/`, `image/`, plus MintCart docs at the repo root.
- `upload/system/modification.xml` is the **stock OpenCart default modification.xml**
  (OCMOD file-patching bootstrap that ships with every OpenCart install) — not a
  MintCart or third-party addition.

## 2. Modified core files

None found. `git log` shows only:

1. `d0ba7cd` — Initial commit: MintCart docs + OpenCart 3 baseline
2. `427b59d` — Consolidate gitignore rules into root, remove leftover `upload/.gitignore`
3. `89053e2` — Convert install SQL dump tables from MyISAM to **InnoDB**
   (`upload/install/opencart.sql`, all 134 `CREATE TABLE` statements now
   `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`)
4. `9c117b3` — Added controller-decomposition / reusable product-listing-pipeline
   guidance into the canonical technical plan docs (documentation only, no code)
5. `c2e8254` — Ignore `/temp` scratch directory

No `catalog/controller`, `catalog/model`, `admin/controller`, `admin/model`, or
`system/` files have been hand-modified relative to stock OpenCart 3.0.5.1. The only
functional change so far is the InnoDB conversion of the install SQL dump, which is a
deliberate, documented MintCart technical-plan requirement (§66/§12: new tables use
InnoDB/utf8mb4) applied uniformly to the base schema rather than left mixed.

## 3. OCMOD dependencies

None. `upload/system/storage/` (where installed OCMOD modification caches would live)
does not exist yet (git-ignored, created at runtime). No `install.xml` OCMOD packages are
present anywhere in the repository. `modification.xml` is the unmodified OpenCart default.

## 4. Themes

Only the stock **`default`** theme exists under `catalog/view/theme/default/`. No
Cartello, Livenza, or other historical/custom theme is present in this repository. No
MintCart theme exists yet (expected — this is Phase 0/1).

## 5. Modules / extensions

Only stock OpenCart extensions are present, matching the default 3.0.5.1 distribution:

- `admin/controller/extension/{analytics,captcha,currency,dashboard,extension,feed,
  fraud,module,payment,report,shipping,theme,total,advertise}`
- `catalog/controller/extension/{analytics,captcha,currency,advertise,credit_card,
  feed,module,payment,recurring,total}`

No custom/third-party modules (no ST modules, no Handy Product Manager, no SortMaster,
etc. installed in this checkout — those exist only as historical UX references per
`CLAUDE.md` §3/§73, not as code in this repo).

## 6. PHP / environment

- CLI PHP available in this environment: **PHP 8.4.19**.
- OpenCart 3.0.5.1 predates PHP 8 by default; historical PHP 8.x compatibility issues
  are a known candidate in `MINTCART_FUNCTIONAL_SCOPE.md` §18 "Known Fix Research
  Queue" and must go through the Fixes Policy (`CLAUDE.md` §13) — reproduce on this
  baseline before patching, do not blindly import old community patches. Not
  addressed in this audit; flagged for a future Fix Registry entry.

## 7. Database

- `upload/install/opencart.sql` defines 134 tables, all `InnoDB` /
  `utf8mb4` / `utf8mb4_unicode_ci` (already aligned with `CLAUDE.md` §12 requirement
  for new MintCart tables — the base schema was normalized to match in commit
  `89053e2`, prior to this audit).
- No MintCart-specific tables (`mc_*`) exist yet. No migration manager exists yet
  (Phase 1 work).

## 8. Frontend libraries currently bundled (stock OpenCart, pre-MintCart)

- `catalog/view/javascript/jquery/` — jQuery **2.1.1** and **3.7.1** (both present;
  3.7.1 appears to be a newer stock bundle, 2.1.1 legacy), plus jQuery plugins:
  Magnific Popup, `datetimepicker` (bootstrap-datetimepicker), Swiper (bundled under
  `jquery/swiper`, pre-MintCart version — not yet the pinned 14.1.x from the
  Implementation Guide).
- `catalog/view/javascript/bootstrap/` — **Bootstrap 3** CSS/JS (`bootstrap.css`,
  `bootstrap-theme.css`, `bootstrap.js` and minified variants). This is the legacy
  storefront foundation and is explicitly **not** the MintCart frontend foundation
  per `CLAUDE.md` §4/§10 — Bootstrap 5.3.8 must be introduced separately and never
  loaded alongside Bootstrap 3 globally.
- `catalog/view/javascript/font-awesome/` — Font Awesome (legacy icon system,
  `CLAUDE.md` §22 keeps this as a legacy-only compatibility layer; new MintCart
  markup uses `mc_icon()` / Bootstrap Icons SVG instead).
- `admin/view/javascript/` — same Bootstrap 3 / jQuery / Font Awesome stack, plus
  `codemirror` (code editing) and `summernote` (admin rich-text editor; OpenCart 3
  does not use CKEditor).
- No `package.json`, no `node_modules/`, no esbuild config, no `assets/` build
  pipeline exists yet — Phase 2 (Frontend foundation) has not started.

## 9. PHP namespace / MintCart bootstrap

No `MintCart\` PHP namespace, autoloading, or bootstrap exists yet. No
`system/library` additions for MintCart. Phase 1 (Foundation) has not started.

## 10. Documentation state prior to this audit

Present:

- `CLAUDE.md`
- `docs/MINTCART_PRODUCT_VISION.md`
- `docs/MINTCART_FUNCTIONAL_SCOPE.md`
- `docs/MINTCART_TECHNICAL_PLAN.md`
- `docs/MINTCART_IMPLEMENTATION_GUIDE.md`

Missing (created as part of this Phase 0 increment, or still pending):

- `docs/audit.md` — **created by this change**
- `docs/IMPLEMENTATION_STATUS.md` — **created by this change**
- `docs/upstream-diff.md` — not yet needed (no core modifications exist beyond the
  documented InnoDB schema normalization; will be created once/if a real core patch
  is required, per `CLAUDE.md` §11)
- `docs/fixes/registry.md` — not yet needed (no historical fixes applied yet, per
  `CLAUDE.md` §13)
- `docs/adr/` — not yet needed (no architectural decision has been made yet beyond
  what the canonical docs already specify)

## 11. Conclusion

This repository is a **clean, unmodified OpenCart 3.0.5.1 baseline** with one
deliberate, documented schema normalization (MyISAM → InnoDB) already applied. There
are no OCMOD packages, no custom themes, no custom modules, and no core file patches
to reconcile. This means Phase 0 carries effectively no legacy-compatibility risk to
resolve before Phase 1 (Foundation) begins — the next steps are additive: MintCart
namespace/bootstrap, migration manager, and the frontend build pipeline, exactly as
ordered in `CLAUDE.md` §21 and `MINTCART_TECHNICAL_PLAN.md` §42.

**Correction (2026-09-29, routine run):** "clean, unmodified" above was true of
the source files present, but not complete — the very first commit that
established this baseline never actually committed
`upload/system/library/config.php` (OpenCart's core `Config` engine class,
`new Config()` in `system/framework.php`), because the repository's
`.gitignore` already carried a `upload/**/config.php` rule at that point,
meant only to keep the *installer-generated* `upload/config.php`/
`upload/admin/config.php` DB-credential files out of source control. The
glob also matched this one stock framework file, so it was silently never
tracked — every request would have fataled on `new Config()`. See
`docs/IMPLEMENTATION_STATUS.md`'s Phase 0 section for the fix (restored the
file verbatim from the upstream `3.0.5.1` tag, added a targeted `!`
negation to `.gitignore`). No other `config.php` path under `upload/` was
affected — checked every stock `config.php` location (`system/library/`,
`system/config/*` files which are not literally named `config.php`, the two
installer-generated ones) against the upstream tag; this was the only
victim.
