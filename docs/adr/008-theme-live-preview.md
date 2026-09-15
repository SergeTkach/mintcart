# 008 — Theme live preview architecture

## Context

`MINTCART_FUNCTIONAL_SCOPE.md` §31 lists Theme live preview as P1/P2 and
explicitly RESEARCH-flavored: "потрібно визначити UX: iframe preview; draft
settings; preview token; live CSS variables; publish/apply. Не створювати
повноцінний visual page editor, якщо це не потрібно." No open PR (checked
against the full `gh pr list --state open` backlog at the time of this ADR)
covers this decision.

Two pieces of the architecture already exist and constrain the answer:

- **Presets are switched by one attribute.** ADR 004 scoped every preset as
  `:root[data-mc-preset='<code>']` specifically so that "a future
  theme-settings form or live preview flips it with one `setAttribute` and no
  class-list bookkeeping" (ADR 004, decision 1). Token-only preview is
  therefore already a solved sub-problem — this ADR only has to decide how a
  draft value reaches the storefront document at all.
- **A full theme may change component DOM, not just tokens** (Implementation
  Guide §24-25, Technical Plan §12). A DOM-changing theme override cannot be
  live-patched into an already-rendered page the way a CSS custom property
  can — it requires a real server render with the candidate theme/override
  active.

No Theme Settings admin form, draft-storage table, or theme-switching
runtime exists yet (Phase 9's own "not wired yet" note). This ADR fixes the
target architecture so that whichever run builds the settings form doesn't
have to re-decide it, per `CLAUDE.md` §85 ("the API appears once there's a
real consumer" — the settings form is that consumer-to-be; deciding its
target shape now is documentation, not the API itself).

## Decision

**Reuse the storefront's own rendering path in an iframe, driven by a
short-lived, signed preview token — not a parallel preview renderer, and not
a visual page editor.**

1. **Draft settings, not draft pages.** A theme/preset settings change is
   staged as a *draft settings row*, separate from the published one MintCart
   actually serves to normal visitors. Concretely (schema is future work, not
   this ADR): a Mint-owned table keyed by `theme_code` with two rows worth of
   state per store — `published` and `draft` — never two competing new
   tables, and never written into any existing OpenCart settings table (this
   is Mint-specific state, so the no-duplicate-storage policy in `CLAUDE.md`
   §12/Implementation Guide §39 does not apply the other way — there is no
   OpenCart draft-settings concept to reuse).
2. **A signed, expiring preview token, not a session flag.** The admin screen
   embeds the live storefront in an `<iframe>` pointed at a normal storefront
   URL plus one query parameter, e.g. `?mc_preview=<token>`. The token is an
   HMAC-signed, short-TTL (minutes, not hours) value naming the store and the
   draft revision it previews — not the admin's session cookie, and not a
   permanent "preview mode" toggle. This keeps preview stateless on the
   storefront side (no server-side preview-session table) and keeps a leaked
   preview link from being a standing backdoor: it simply expires.
3. **Token presence swaps which settings row the page renders with, nothing
   else.** When `mc_preview` validates, the request renders with the `draft`
   settings row instead of `published` for that one request/response —
   same controller, same theme resolver, same templates. It must never be
   cached (no page/block cache write on a previewed response — Technical
   Plan §36's cache layer needs this as an explicit bypass condition when it
   lands) and must not leak to search engines or normal visitors (`noindex`
   response header while a preview token is active).
4. **Two update paths, matched to the two things a theme can change:**
   - **Token-only changes** (color, radius, spacing, any `--mc-*` value, or
     `data-mc-preset` itself) update *without an iframe reload*: the admin
     settings form posts the changed value(s) to the draft row, then sends
     them to the iframe via `window.postMessage` (same pattern as
     `mc:cart:updated` and friends — a documented `CustomEvent` contract, not
     ad-hoc `postMessage` shapes). A small preview-only listener script
     (loaded only when `mc_preview` is present, never in a normal page
     response) applies the values as inline `style` custom properties on
     `<html>` or flips `data-mc-preset`, giving instant feedback with zero
     round trip to the server after the first load.
   - **DOM-changing changes** (a different Full Theme, a component override,
     a different header/footer variant) require an iframe reload against the
     draft settings row — there is no client-side patch for different Twig
     output, and this ADR does not invent one. The admin UI's job is only to
     know which category a given setting falls into and reload instead of
     `postMessage` when it does.
5. **Publish/discard are the only two draft-lifecycle actions.** Publish
   copies `draft` over `published` (and, if the update-manager backup layer
   from Technical Plan §34 is available by the time this is built, that copy
   is the natural undo point — no separate rollback mechanism is invented
   here). Discard drops the draft row back to a copy of `published`. There is
   no autosave-vs-draft ambiguity to design (`docs/adr/006` territory once it
   exists) because the draft row already *is* the autosave target — every
   settings-form change writes straight to `draft`, never to `published`.
6. **Explicitly out of scope, per the Functional Scope's own instruction not
   to build "a full visual page editor":** in-iframe click-to-select-element
   editing, a live outline/inspector over the previewed DOM, and applying
   this same preview mechanism to the Homepage Builder's block editor. The
   Homepage Builder is free to adopt the same token/iframe/publish pattern
   later as its own consumer — this ADR is scoped to Theme Settings preview
   only, per `CLAUDE.md` §85 (don't design the second consumer before the
   first one exists).

## Consequences

- The settings-form implementation gets a fixed contract to build against:
  a `draft`/`published` pair per store, a signed preview-token query
  parameter, a `postMessage` event contract for token-only changes, and a
  reload-vs-patch decision keyed off "did this setting change a token or a
  component/theme". None of that is built by this ADR.
- The cache-invalidation layer (`MintCart\Core\Cache\CacheInvalidator`, once
  merged) must treat a preview-token request as always-bypass, not as a
  cache key dimension — previews must never populate the shared page cache.
- The preview token's signing key and TTL become a security-relevant
  constant (predictable/non-expiring tokens would let a leaked preview URL
  serve as a way to see unpublished settings indefinitely) — the
  implementing run must treat this the same as any other auth-adjacent
  decision, not as an incidental detail.
- Because token-only preview reuses the exact `data-mc-preset`/`--mc-*`
  mechanism ADR 004 already built for this purpose, no new CSS architecture
  is needed for the common case (color/spacing/typography tweaks, which is
  most Theme Settings usage in practice) — only the DOM-changing, less
  common case pays for a full reload.
- This ADR does not create any new files, tables, controllers, or tests by
  itself — it is a target-architecture decision for whichever future run
  implements the Theme Settings admin form, matching the shape of ADR
  005/006/007's resolution of other Functional Scope RESEARCH items
  (rollback, autosave, editor).
