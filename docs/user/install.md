# Installing MintCart

MintCart is a distribution of OpenCart 3.0.5.1 — installation uses OpenCart's
own installer, unmodified. This guide covers what's actually true of this
repository today; the MintCart-specific onboarding (Setup Wizard) starts
*after* this installer finishes — see
[`setup-wizard.md`](setup-wizard.md).

## 1. Requirements

Checked live by the installer itself
(`upload/install/controller/install/step_2.php`) — if any of these are
missing, the installer's own "Pre-Installation" step will say so before you
can continue.

**PHP:** a database driver extension (`mysqli`, `pgsql`, or `pdo` — MintCart
targets MySQL/`mysqli` in practice, see §2) plus the following extensions:

- `gd`
- `curl`
- `zlib`
- `zip`
- `mbstring` (or `iconv`, either satisfies the check)
- `dom`
- `hash`
- `xmlwriter`
- `json`

**Filesystem — writable by the web server user:**

- `config.php` (repo root)
- `admin/config.php`
- `image/`
- `image/cache/`
- `image/catalog/`
- `system/storage/cache/`
- `system/storage/logs/`
- `system/storage/download/`
- `system/storage/upload/`
- `system/storage/modification/`

**Database:** MySQL (or MariaDB), `InnoDB` + `utf8mb4`. MintCart's base
schema (`install/opencart.sql`) is already normalized to
`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` for all
134 tables — this is a MintCart-specific change from stock OpenCart 3.0.5.1
(which ships mixed `MyISAM`/`latin1` on some tables); no server-side
configuration is required for it, it's already the shape of the SQL dump
you'll load.

MintCart does not require Redis, RabbitMQ, or Docker. A typical shared PHP
hosting account with MySQL is sufficient (`CLAUDE.md` §1/§87).

## 2. Get the files onto your server

This repository's `upload/` directory **is** the document root you serve —
same layout as any stock OpenCart 3.0.5.1 release: `upload/catalog/`,
`upload/admin/`, `upload/system/`, `upload/install/`, `upload/image/`.

Point your web server's document root at `upload/`, or upload the contents
of `upload/` to your hosting account's public directory.

> **Frontend build note (developers building from source):** this
> repository's MintCart-owned CSS/JS lives as source under `assets/src/`
> and is compiled to `assets/dist/` via `npm run build` (see the root
> `package.json`) — `assets/dist/` is gitignored and not committed. A
> released MintCart package ships `assets/dist/` already built inside
> `upload/`, the same way OpenCart itself ships pre-built files, so an
> end user installing MintCart never needs Node or npm. If you're working
> from this source repository directly rather than a packaged release, run
> `npm install && npm run build` at the repo root once before installing so
> `assets/dist/` exists (harmless either way — nothing in the installer or
> the stock storefront currently reads from it yet, per
> `docs/IMPLEMENTATION_STATUS.md`'s Phase 7 "Known gap").

## 3. Run the installer

Visit your domain in a browser. OpenCart's stock installer wizard runs
(`route=install/install` under `upload/install/`) — this part is entirely
unmodified stock OpenCart 3.0.5.1, not a MintCart screen:

1. **License** — accept the GPL license.
2. **Pre-Installation** — the requirements from §1 are checked live; fix
   anything marked red and reload before continuing.
3. **Configuration** — enter your database host/port/username/password/
   database name/table prefix, plus your admin account's username, email,
   and password. This step writes `config.php` and `admin/config.php` and
   loads `install/opencart.sql`.
4. **Finished** — the installer tells you to delete (or rename) the
   `install/` directory. **Do this** — leaving it in place is a known
   security risk with any OpenCart-based install, MintCart included.

## 4. Log in and continue to Setup

Go to `/admin/` and log in with the admin account you created in step 3.

As of this writing there is **no automatic redirect** from a fresh install
or from the admin login screen into MintCart's own Setup Wizard — that's a
tracked, not-yet-built piece (see
[`setup-wizard.md`](setup-wizard.md#known-gaps)). Navigate to
`admin/index.php?route=mintcart/wizard&user_token=<your token>` (replace
`<your token>` with the value from your current admin session URL) to reach
it directly. Continue with [`setup-wizard.md`](setup-wizard.md).

## Troubleshooting

- **A requirement check fails and you can't change server config:** every
  item in §1 is either a PHP extension (ask your host to enable it, or
  switch hosts — MintCart doesn't relax OpenCart's own requirements) or a
  file/directory permission (`chmod`/`chown` to make it writable by the
  web server user).
- **The installer never appears / shows a blank page:** almost always a PHP
  error being suppressed by your host's `display_errors` setting — check
  your web server's PHP error log first.
- **You need to reinstall from scratch:** delete `config.php` and
  `admin/config.php` (or restore them to their blank stock state), drop and
  recreate the database, and revisit the installer. There is no MintCart-
  specific reset tool for this yet.

## What this guide intentionally does not cover

Demo data import, choosing a design preset, and configuring shipping/
payment are Setup Wizard and Business Settings concerns
(`MINTCART_FUNCTIONAL_SCOPE.md` §2/§3/§30), most of which are still being
built — see `docs/IMPLEMENTATION_STATUS.md` for exactly what exists today
before assuming a Wizard step works end to end.
