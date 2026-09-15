# 006 — Autosave strategy: scope and state machine

## Context

`MINTCART_FUNCTIONAL_SCOPE.md` §22 flags Autosave as **P1 / RESEARCH**:
possible scenarios are the block editor, theme settings, non-critical
design forms, and draft page editing; it must explicitly **not** apply to
order state changes, payment settings, user permissions, destructive
operations, or migration/update operations. It lists four required states
(dirty, saving, saved, failed) plus conflict handling and a navigation
warning, but leaves the mechanics — trigger, transport, state naming,
conflict/failure behavior — open. Functional Scope §5 separately repeats
the same "not applied blindly to critical operations" constraint for the
wider Modern Admin effort.

No existing checklist item in `docs/IMPLEMENTATION_STATUS.md` tracks this
— it's mentioned only in the Functional Scope, not the Technical Plan or
Implementation Guide, and nothing in the 40+ pull requests open against
this repository at the time of this ADR touches it (checked via
`gh pr list --state open` and a local `git diff --name-only` sweep of
every open PR's branch against `master`).

Per `CLAUDE.md` §13/§20 and Functional Scope §22's own framing ("можливі
сценарії" / "не застосовувати автоматично до..." — read as: decide the
boundaries before building), this ADR is that decision step. **No
autosave code is added in this increment.** Two things point the same
way here: `CLAUDE.md` §85 (an API/primitive is built once a real
consumer exists) and, concretely, every plausible first consumer named
below (Homepage Builder block settings, a Theme settings form) lives
behind admin screens whose own JS/CSS bundle still isn't enqueued on any
admin page yet (`docs/IMPLEMENTATION_STATUS.md`'s Phase 7 "Known gap",
carried through Phases 10/12/17) — the same blocker several already-open
PRs are independently working through. Landing an `McAutosave` primitive
now, with no consumer to prove it against and a real chance of colliding
with in-flight admin-bundle work, would be pure speculation. This ADR
exists so that once a consumer and a working admin bundle both exist, the
implementer doesn't have to re-derive these answers.

## Decision

Six questions, six decisions.

### 1. What gets autosave — allowlist, not a flag to disable

Autosave is **opt-in per form**, marked with a `data-mc-autosave` hook on
the `<form>` element (`MINTCART_IMPLEMENTATION_GUIDE.md` §14 — behavior is
a `data-mc-*` attribute, never inferred from a CSS class or route). There
is no denylist and no global default: a form the Functional Scope
excludes (order edit, payment settings, permission grants, any
destructive action, Update Manager screens) is safe by construction
because nothing ever adds the attribute to it — the exclusion list
doesn't need to be encoded or checked anywhere at runtime. The first
candidates once an admin bundle is enqueued are Homepage Builder block
settings forms (Phase 10) and a future Theme settings form (Phase
9/`settings.json`-driven), both explicitly named in Functional Scope §22.

### 2. Trigger and transport

Debounced on `input`/`change` (short delay after the last keystroke,
e.g. ~1200ms — final constant is an implementation detail, not an
architectural one) and immediately on `blur`/field-change events that
don't naturally debounce (a `<select>`, a checkbox). Transport reuses the
form's own existing `action` and method — autosave is a `fetch()` +
`FormData` submission of the **same route the manual Save button already
posts to**, not a second parallel endpoint. This follows the same
"reuse the real thing, don't invent a shadow API" reasoning
`MINTCART_TECHNICAL_PLAN.md` §15C/38E applies to product listings: every
MintCart admin save action already needs to keep working for a real
(non-autosave) submit, so autosave is that same action called more often,
not a new contract each controller must additionally implement. A
controller that wants a cheaper partial-update path for its own reasons
may still add one — nothing here requires it.

### 3. State machine and how it's exposed

Four states plus idle, mirroring Functional Scope §22 exactly:

```text
idle → dirty → saving → saved → idle
                  ↓
                error → dirty (next successful edit) or saving (retry)
```

Exposed two ways, matching how every other MintCart component surfaces
state (`MINTCART_IMPLEMENTATION_GUIDE.md` §13): a
`data-mc-autosave-state="idle|dirty|saving|saved|error"` attribute on the
form for CSS/`:not([hidden])`-style hooks, and a bubbling
`mc:autosave:state` `CustomEvent` (`detail: { state, form }`) — the same
"one stable event, many listeners" shape `mc:cart:updated`
(`MINTCART_IMPLEMENTATION_GUIDE.md` §58) already established — so a host
page can drive its own "Saved" indicator (reusing `mc-toast`/`mc-badge`,
not a new dedicated status component) without autosave needing to know
that indicator exists.

"Dirty" is computed by comparing the form's current serialized values
(`new FormData(form)`, stringified) against the snapshot taken at the
last successful save (or on load) — not a per-field `oninput` flag —
so a field that's edited and then edited back to its original value
correctly reports not-dirty, and a page that never changes never fires a
save.

### 4. Failure handling

A failed save (network error or non-2xx response) moves to `error` and
retries with capped exponential backoff (short base delay, small fixed
retry ceiling — implementation detail, not architectural). The explicit
Save button every autosaved form already has (autosave never replaces
it, only supplements it) stays fully functional throughout — a shopper-
facing analogy is Cart's "add to cart never blocks on a background sync,"
here: autosave failing silently degrades to "the user's next explicit
Save still works," never to data loss or a stuck UI.

### 5. Conflict handling

Optimistic concurrency via a version/`updated_at` token the save route
already has reason to know (the row's own timestamp or a revision
counter) — the autosave request includes the token it last saved
against; a save route that finds the current stored value has since
moved past that token responds with a conflict status instead of
overwriting. On a conflict, autosave stops silently retrying and enters a
distinct "conflict" sub-state of `error` (surfaced through the same
`mc:autosave:state` event, `detail.reason: 'conflict'`) so the host page
can show a real prompt ("this was changed elsewhere — reload?") rather
than either silently discarding the user's edits or silently overwriting
someone else's. No automatic merge is attempted — Functional Scope §22
lists "conflict handling" as a required state, not a required resolution
strategy, and auto-merging two edits neither side saw is exactly the
optimistic-elsewhere-you'd-name failure mode.

### 6. Navigation warning

A `beforeunload` listener is attached only while the tracked state is
`dirty` or `error` (an in-flight or failed change with nothing behind it
yet), and removed the instant a save succeeds — never a page-wide
listener that outlives the one form it's guarding, and never attached at
all for a form with no `data-mc-autosave` hook.

## Consequences

- A future implementation increment needs one small vanilla-JS module —
  working name `McAutosave` in `assets/src/js/components/autosave.js`,
  following the exact `createXxx(form, options)` factory shape
  `McEntityPicker`/`McMediaPicker` already established (not
  auto-initialized in `admin.js`, since it needs a caller-supplied
  `<form>` and no consumer exists yet) — plus a doc page under
  `docs/developer/components/`. Neither is added by this ADR.
- No new backend endpoint contract is mandated; each future consumer's
  own existing save action is reused per decision #2, with a version
  token added to its response/request per decision #5 if it doesn't
  already expose one.
- No new database table or migration is implied — the version token in
  decision #5 is expected to come from a column the owning entity's table
  already has reason to carry (e.g. `mc_block.date_modified`), not a new
  Mint-owned autosave-tracking table.
- None of these six answers rose to "a real product or architectural fork
  that only the project owner can make" (`CLAUDE.md` §20) — each follows
  directly from constraints already stated in Functional Scope §22/§5 (no
  autosave on critical/destructive forms, dirty/saving/saved/failed states
  required, no promise of automatic conflict resolution) or from patterns
  this codebase already established (`data-mc-*` hooks, one CustomEvent
  per concern, reuse the real save route). None were escalated.
- `docs/IMPLEMENTATION_STATUS.md`'s Phase 11 (Mint Admin primitives)
  section records this research as done, with `McAutosave` itself
  unbuilt (design only, no code) and this ADR linked as the next
  increment's starting point once a real consumer and a working admin
  asset bundle both exist.
