# 007 — Modern Editor (Functional Scope §24): keep Summernote for 1.0

## Context

`MINTCART_FUNCTIONAL_SCOPE.md` §24 lists "Modern Editor" as **P2 / RESEARCH**:
check whether the rich-text editor needs updating before building anything,
against these requirements — stable, lightweight, safe HTML, multilingual,
image/media integration, no unnecessary framework coupling — and explicitly:
"Не міняти editor лише через номер версії або «сучасність»" (don't change the
editor merely for a version number or "modernity"). This ADR is the research
step, following the same RESEARCH-item-resolved-via-ADR pattern already used
for Autosave (Functional Scope §22) and Theme/extension rollback (Functional
Scope §21).

**What MintCart currently ships**, verified against this repository (not
memory, per `CLAUDE.md` §10/§84):

- OpenCart 3.0.5.1's stock WYSIWYG is **Summernote 0.8.18**
  (`upload/admin/view/javascript/summernote/`), a jQuery plugin.
- It is wired into exactly five admin templates: `catalog/product_form.twig`
  (product description), `catalog/category_form.twig` (category
  description), `catalog/information_form.twig` (information page content),
  `extension/module/html.twig` (HTML block module content), and
  `marketing/contact.twig` (contact-form intro text). All five are
  **admin-only** — nothing storefront-facing loads Summernote or its jQuery
  dependency.
- Each of those five is a per-language field, driven by OpenCart's existing
  language-tab admin form pattern (one Summernote instance shown/hidden per
  language tab) — multilingual support already exists at the form level, not
  inside the editor itself.
- Submitted HTML is DB-escaped (`$this->db->escape(...)`, e.g.
  `upload/admin/model/catalog/product.php:13`) but not run through any
  HTML sanitizer/purifier before storage or before storefront rendering.
  This is stock OpenCart behavior, not something MintCart introduced, and it
  is the same trust model every WYSIWYG-backed CMS admin field uses: the
  people who can reach these forms already have full admin access (product/
  category/information/module management, in most installs also file and DB
  access via Extensions/Backup), so raw-HTML-in / raw-HTML-out from an
  authenticated admin session is not a privilege boundary and is out of
  scope for this ADR as a "fix" — flagging it as one would be inventing a
  vulnerability against MintCart's own trusted-admin threat model.

## Decision

**Keep Summernote 0.8.18 as MintCart's rich-text editor for 1.0. No
replacement, no forced version bump, no new `Mc*` editor component.**

Checked against Functional Scope §24's own requirements:

- **stable** — Summernote 0.8.x has been OpenCart 3's stock editor since
  before the version this repository forked from; no crash/data-loss reports
  in this codebase's own history (`docs/fixes/registry.md`) or the Known Fix
  Research Queue (Functional Scope §18).
- **lightweight** — a single-purpose WYSIWYG, materially smaller than
  TinyMCE 6 or CKEditor 5's default builds; replacing it with either would
  be a net weight *increase*, the opposite of what "lightweight" asks for.
- **safe HTML** — the sanitization question sits at the trusted-admin
  boundary described above, independent of which editor library produces
  the markup; swapping editors would not change it.
- **multilingual** — already satisfied at the form level (per-language tab
  instances), not an editor-library property.
- **image/media integration** — Summernote's own image plugin already
  integrates with OpenCart's existing admin file-manager dialog in these
  five templates; `McMediaPicker` (Phase 11, Implementation Guide §52) is a
  separate, newer, non-jQuery picker built for MintCart-owned admin screens
  (e.g. product image gallery) that have no legacy widget to begin with —
  the two are not in conflict and neither needs the other today.
- **no unnecessary framework coupling** — Summernote depends on jQuery, which
  `CLAUDE.md` §7 / `MINTCART_IMPLEMENTATION_GUIDE.md` §3 explicitly keep
  around for exactly this case: "jQuery may remain for OpenCart legacy
  code." Replacing an already-jQuery-coupled stock OpenCart widget with a
  vanilla-JS one would not remove jQuery from the five pages that use it —
  `admin.js`'s own legacy chrome and other OpenCart admin widgets on the
  same pages still load jQuery regardless — so the coupling this would
  remove is illusory, while a new `Mc*` editor component would be a second
  concrete maintenance surface (patching, XSS-in-editor-chrome review,
  keyboard/a11y work) for zero product gap identified above.

No requirement in Functional Scope §24 is unmet by what already ships.
Building or wiring anything new here would be an abstraction without a real
gap to close — `CLAUDE.md` §85/§86.

**Revisit condition**, so this isn't a silent permanent decision: reopen this
ADR (a new numbered one, not an edit to this file) if any of the following
becomes true —

1. A concrete MintCart-owned screen needs rich text with no legacy widget
   present at all (the same trigger `McMediaPicker` used against
   `McEntityPicker`'s antecedent) — e.g. a Homepage Builder block's rich-text
   field once the admin asset-enqueue blocker (`docs/developer/blocks/homepage-admin.md`)
   is resolved and a non-legacy-admin-theme screen exists to host it.
2. Summernote 0.8.18 is found to have an unpatched, exploitable defect
   reachable in that trusted-admin threat model (not merely "old version
   number") — goes through the normal Fixes Policy (`CLAUDE.md` §13,
   `docs/fixes/registry.md`), not this ADR.
3. A real product requirement appears that Summernote genuinely cannot do
   (e.g. structured/blocks-based content editing) — a feature gap, not a
   modernity preference, per Functional Scope §24's own instruction.

## Consequences

- No code changes ship with this ADR — the research conclusion is "no gap,
  no action," which is itself a valid, documented outcome for a RESEARCH
  item (same shape as choosing *not* to build something after evaluating
  it, `CLAUDE.md` §17/§20).
- Functional Scope §24 is resolved: future runs should not re-open "should
  we replace the editor" without one of the three revisit conditions above
  being true.
- The five existing Summernote-backed admin templates are explicitly
  **not** a target for the still-open admin asset-enqueue question
  (`docs/developer/blocks/homepage-admin.md`) — that question is about
  enqueuing MintCart's own Bootstrap-5-based `assets/dist/{css,js}` bundle
  onto admin screens, unrelated to whether Summernote itself is replaced.
- If revisit condition 1 is ever met, the natural shape (not decided here,
  per `CLAUDE.md` §85 — no interface ahead of a real consumer) would be a
  small `Mc*` wrapper around a chosen non-jQuery library, following the same
  "wrap, don't reimplement" pattern `mc-modal`/`mc-drawer`/`mc-toast` already
  established for Bootstrap 5's own JS components.
