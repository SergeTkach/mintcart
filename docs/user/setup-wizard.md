# Setup Wizard

The Setup Wizard is MintCart's replacement for landing a fresh admin login
straight into stock OpenCart's dashboard — it's meant to walk you from a
just-installed store to one ready to take a test order
(`MINTCART_PRODUCT_VISION.md` §3, "Time to First Order").

**This guide describes what the Wizard actually does today, not the full
roadmap.** Two of its nine planned steps have real, working content;
the rest exist only as names in the step list so you can see what's coming.
If you want the full intended flow (including steps not built yet), see
[`MINTCART_FUNCTIONAL_SCOPE.md`](../MINTCART_FUNCTIONAL_SCOPE.md) §2.

## Before you start

The Wizard assumes OpenCart's own installer has already run and you're
logged into `/admin/` — see [`install.md`](install.md) first if you haven't
done that yet.

## Reaching the Wizard

There is currently no link to it anywhere in the admin menu or dashboard.
Go directly to:

```
admin/index.php?route=mintcart/wizard&user_token=<your token>
```

(`<your token>` is the `user_token` value already in your admin session's
URL — the Wizard needs a logged-in admin session, same as any other admin
screen, but does not require a specific permission to be granted first.)

## The step list

The Wizard shows all nine planned steps in its side navigation so you can
see the whole roadmap, but only **Welcome** and **Store** have real content
right now. The rest are visible as upcoming steps you can't open yet — the
Wizard will not let you skip ahead of the first step that isn't finished.

| # | Step | Status |
|---|------|--------|
| 1 | Welcome | **Implemented** |
| 2 | Store | **Implemented** |
| 3 | Contacts | Roadmap only — no form yet |
| 4 | Language & Currency | Roadmap only — no form yet |
| 5 | Delivery | Roadmap only — no form yet |
| 6 | Payment | Roadmap only — no form yet |
| 7 | Design | Roadmap only — no form yet |
| 8 | First Product | Roadmap only — no form yet |
| 9 | Ready | Roadmap only — no Store Readiness summary yet |

### 1. Welcome

A purely informational screen with a "Get started" button — no fields to
fill in, nothing saved. It exists so the Wizard has a real first screen
rather than dropping you straight into a form.

### 2. Store

Collects four fields and saves them to OpenCart's own standard `config_*`
settings — the same settings screen (`Settings → Store`) already reads and
writes, so nothing here is duplicated into a separate MintCart-only table:

- **Store Name** (`config_name`) — up to 64 characters, required.
- **E-Mail** (`config_email`) — up to 96 characters, required, must be a
  valid email address.
- **Telephone** (`config_telephone`) — 3 to 32 characters, required.
- **Address** (`config_address`) — 3 to 256 characters, required.

Saving this step **merges** these four values into your store's existing
`config` settings rather than replacing the whole settings row — any other
`config_*` setting you already have (currency, tax, checkout options, …)
is left untouched.

### Steps 3–9 (not built yet)

Contacts, Language & Currency, Delivery, Payment, Design, First Product,
and Ready are named in the step list so the Wizard shows the intended full
journey, but none of them have a form, save logic, or content yet. Visiting
the Wizard once Store is complete currently shows a "more steps coming
soon" state rather than one of these steps — see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 13 section for exactly which run
adds which step next.

Once **Ready** exists, it's intended to show the same checklist
`MintCart\Onboarding\StoreReadiness` already computes today (logo,
contacts, language, currency, shipping, payment, first product — see
`docs/developer/onboarding/store-readiness.md`), reachable in the meantime
as a raw JSON endpoint at `admin/index.php?route=mintcart/readiness&user_token=<your token>`
for anyone who wants to check store readiness before the Wizard step
catches up.

## Progress and resuming

Your progress is saved after each step (under the standard `oc_setting`
table, setting code `mintcart_wizard` — a handful of flags, not a new
database table). You can close the browser and come back later; revisiting
the Wizard URL takes you back to wherever you left off. You can also
revisit an already-completed step to review or change it — you're only
ever blocked from jumping *ahead* of your current progress.

## What you can safely do without the Wizard

Every field the Wizard's Store step collects is also editable the normal
OpenCart way, right now, under `Settings → Store` — the Wizard is a
guided shortcut through settings that already exist, not the only way to
reach them. If you'd rather configure your store through the regular admin
menu while the remaining Wizard steps are being built, you can — nothing
in the Wizard is required to run a store.

## Known gaps

- No entry point in the admin UI links here yet (no post-install redirect,
  no dashboard card) — you have to know the URL.
- Seven of the nine steps have no real form yet (see table above).
- The Store step's logo upload isn't wired to anything yet, even though a
  reusable media picker component (`McMediaPicker`) already exists —
  it needs a real upload endpoint first.
- `assets/dist/js/admin.min.js` isn't loaded on this page — only the CSS
  bundle is — so nothing here has client-side JS behavior yet (none of the
  two implemented steps currently need any).
