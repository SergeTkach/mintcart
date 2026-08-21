# Claude — MintCart Project Instructions

@docs/MINTCART_PRODUCT_VISION.md
@docs/MINTCART_FUNCTIONAL_SCOPE.md
@docs/MINTCART_TECHNICAL_PLAN.md
@docs/MINTCART_IMPLEMENTATION_GUIDE.md

## 1. Source of truth

The imported documents above are the canonical specification for MintCart.

They have different responsibilities:

### `MINTCART_PRODUCT_VISION.md`

Defines:

- why MintCart exists;
- product philosophy;
- target users;
- product principles;
- what MintCart should optimize for.

Use it to answer:

> Why are we building this?

---

### `MINTCART_FUNCTIONAL_SCOPE.md`

Defines:

- which product capabilities must not be lost;
- feature priorities;
- P0 / P1 / P2 / RESEARCH scope;
- functional requirements that may not yet be fully implemented.

Use it to answer:

> What functionality must MintCart eventually provide?

A feature is not optional merely because it is not mentioned in the Implementation Guide.

---

### `MINTCART_TECHNICAL_PLAN.md`

Defines:

- architectural layers;
- system boundaries;
- implementation sequence;
- technical responsibilities of Core, Compatibility, Admin, Commerce, Design, Blocks, SEO, Update, etc.

Use it to answer:

> How should the product be structured technically?

---

### `MINTCART_IMPLEMENTATION_GUIDE.md`

Defines the concrete implementation rules:

- frontend stack;
- Bootstrap version;
- JavaScript rules;
- BEM naming;
- Theme API rules;
- Twig/component rules;
- coding constraints;
- Definition of Done;
- implementation phases.

Use it to answer:

> How exactly should this code be written?

---

## 2. Conflict resolution

Do not use a naive rule such as "the last file wins".

Determine what kind of decision is conflicting.

### Product goal or feature scope

Prefer:

```text
MINTCART_FUNCTIONAL_SCOPE.md
+
MINTCART_PRODUCT_VISION.md
```

### Architecture

Prefer:

```text
MINTCART_TECHNICAL_PLAN.md
```

### Concrete implementation convention

Prefer:

```text
MINTCART_IMPLEMENTATION_GUIDE.md
```

If a real contradiction remains after applying these rules, document the contradiction before making a large architectural change.

Do not silently choose a historical/reference implementation over the canonical docs.

---

## 3. References are not requirements

Files under:

```text
references/
```

are historical or supporting material only.

They may contain useful:

- UX ideas;
- proven algorithms;
- domain knowledge;
- edge cases;
- previous theme architecture experiments.

They may also contain outdated decisions.

Do not treat reference material as the current MintCart architecture.

In particular, do not restore deprecated decisions such as:

```text
st-* namespace
Bootstrap 3 as MintCart foundation
author-specific Cartello/Livenza branding
theme OCMOD patches
large OCMOD-based architecture
old library versions
```

If a useful idea exists in a reference, adapt the idea to the current MintCart architecture.

Reuse knowledge, not obsolete implementation.

---

## 4. Current frontend decisions

For new MintCart UI:

```text
Bootstrap 5.3.8
Vanilla JavaScript
ES modules
Swiper 14.1.x
SortableJS 1.15.7
Bootstrap Icons 1.13.x as SVG source
esbuild
```

### Bootstrap

Bootstrap 5.3.8 is the current layout/UI foundation for new MintCart frontend and MintCart admin screens.

Bootstrap 3 is:

```text
legacy compatibility target only
```

Do not use Bootstrap 3 as the MintCart foundation.

Do not globally load Bootstrap 3 and Bootstrap 5 together.

Legacy OpenCart 3 / extension markup should be handled through a compatibility shim where practical.

---

## 5. MintCart namespaces

Do not use `st-*`.

Use:

```text
PHP namespace:
MintCart\

CSS/BEM:
mc-*

CSS variables:
--mc-*

JavaScript behavior hooks:
data-mc-*

JavaScript classes/modules:
Mc*

Transient states:
is-*
has-*
```

Examples:

```html
<article class="mc-product-card">
    <div class="mc-product-card__media"></div>
</article>

<button
    class="mc-button mc-button--primary"
    data-mc-action="add-to-cart"
>
    Add to cart
</button>
```

---

## 6. Bootstrap + BEM policy

Bootstrap may be used for:

```text
containers
grid
responsive columns
spacing utilities
display utilities
flex utilities
generic Bootstrap interaction primitives where appropriate
```

MintCart-owned ecommerce components use `mc-*` BEM.

Example:

```html
<div class="container">
    <div class="row g-4">
        <div class="col-6 col-lg-3">
            <article class="mc-product-card">
                ...
            </article>
        </div>
    </div>
</div>
```

Do not create a separate MintCart grid framework merely to duplicate Bootstrap.

Do not make Bootstrap component classes such as `.card`, `.btn`, or `.alert` the public Theme API contract for MintCart ecommerce components.

---

## 7. JavaScript policy

New MintCart JavaScript must not depend on jQuery.

Use:

```text
querySelector / querySelectorAll
fetch
FormData
URLSearchParams
CustomEvent
AbortController
ES modules
```

jQuery may remain for:

```text
OpenCart legacy code
legacy third-party extensions
```

Do not rewrite legacy code merely to remove jQuery unless there is a concrete product or maintenance benefit.

---

## 8. Core architecture invariants

These are strict goals:

```text
MintCart Core → no OCMOD
MintCart Default Theme → no OCMOD
MintCart Theme SDK → no OCMOD
MintCart Blocks → no OCMOD
```

Legacy external OpenCart extensions may still use OCMOD.

A new MintCart theme must not require controller patches just to change design.

Target:

```text
new MintCart theme
=
0 OCMOD
0 controller patches
0 copied business logic
```

---


## 8A. Controller / reusable action invariant

When refactoring or creating MintCart controllers:

```text
Controller = orchestration
Reusable Service/Action = shared logic
Provider/ViewModel = normalized presentation data
Twig = rendering
```

Do not duplicate product-list preparation across Category, Search, Manufacturer, Specials and Blocks.

Prefer one shared Product Listing pipeline and one ProductViewProvider.

Expose fine-grained documented stage events where extensions need stable hooks.

Do not introduce a large HMVC framework merely for terminology; reuse logic by responsibility and real use case.


## 9. Theme architecture

Theme dependency direction:

```text
Theme
→ MintCart API
→ OpenCart Adapter
→ OpenCart
```

Never design new MintCart themes around:

```text
Theme
→ arbitrary OpenCart controller
→ OCMOD
→ core patch
```

Theme may override official MintCart components through the Theme API.

Theme must not own core commerce logic.

---

## 10. Before changing existing OpenCart functionality

Never modify an existing OpenCart flow based only on memory.

Before changing a subsystem:

1. inspect the current repository implementation;
2. find all relevant call sites;
3. inspect events, OCMOD modifications and extensions touching it;
4. determine backward-compatibility risk;
5. identify whether the change can live in the MintCart layer instead of the OpenCart core;
6. only then implement.

Prefer a small upstream diff.

---

## 11. Upstream diff policy

Maintain:

```text
docs/upstream-diff.md
```

For each direct OpenCart core modification document:

```text
file
reason
why it cannot live in MintCart layer
compatibility impact
tests
```

Treat every new core modification as technical debt that needs justification.

---

## 12. Migrations

Schema changes must use the MintCart migration system.

Do not scatter ad-hoc schema mutation logic across installers and controllers.

New MintCart tables should use:

```text
InnoDB
utf8mb4
```

Critical multi-step operations should use transactions where appropriate.

---

## 13. Fixes policy

Historical OpenCart fixes are candidates, not automatic patches.

Maintain:

```text
docs/fixes/registry.md
```

Before applying a historical fix:

1. identify affected OpenCart versions;
2. reproduce or verify it on the current MintCart baseline;
3. check whether upstream already fixed it;
4. implement a MintCart solution only if still necessary;
5. add tests.

Do not blindly import old community patches.

---

## 14. Work tracking

Maintain:

```text
docs/audit.md
docs/IMPLEMENTATION_STATUS.md
docs/upstream-diff.md
docs/adr/
docs/fixes/registry.md
```

`IMPLEMENTATION_STATUS.md` should track the implementation phases.

Example:

```text
[x] Phase 0 — Audit
[x] Phase 1 — Foundation
[ ] Phase 2 — Frontend foundation
...
```

After a meaningful phase:

1. run relevant tests;
2. inspect the git diff;
3. remove debug/accidental code;
4. update implementation status;
5. update architecture documentation if the implementation changed an architectural contract.

---

## 15. Architecture Decision Records

For important architectural choices create an ADR in:

```text
docs/adr/
```

Keep ADRs short:

```text
Context
Decision
Consequences
```

Do not leave important architectural decisions only in chat history.

---

## 16. Scope discipline

Do not add large technologies or features merely because they are modern.

Unless explicitly justified by the canonical documents, do not make these MintCart 1.0 dependencies:

```text
React
Vue
SPA admin
AI assistant
AI product generation
marketplace
multivendor
ERP
CRM
native mobile app
headless architecture
GraphQL
Elasticsearch
mandatory Redis
RabbitMQ
large visual page builder
100+ widgets
```

---

## 17. Do not over-abstract

Do not create APIs, interfaces, factories and adapters solely for hypothetical future use.

A new abstraction should have a real consumer.

Prefer:

```text
one clear service
```

over:

```text
five classes
three factories
multiple interfaces
```

when the simpler solution provides the required separation.

MintCart should remain understandable to an experienced PHP/OpenCart developer.

---

## 18. Product decision filter

Before implementing a meaningful user-facing feature ask:

> Does this shorten the path from installation to a store that is ready to take an order?

For Theme SDK work ask:

> Does this allow a designer to change the store without patching OpenCart?

For compatibility work ask:

> Does this preserve useful OpenCart ecosystem compatibility without forcing legacy architecture into new MintCart code?

If the answer is no, re-check priority before expanding scope.

---

## 19. First architecture milestone

Do not scale feature development until the Theme API is proven.

The milestone is successful only when:

```text
Mint Default
+
a second test theme
```

use:

```text
the same data providers
the same Commerce services
the same component contracts
```

but may have:

```text
different product-card DOM
different header DOM
different footer DOM
different design tokens
different visual style
```

with:

```text
0 OCMOD
0 controller patches
0 duplicated business logic
```

If this fails, fix the Theme API before proceeding to a large feature build-out.

---

## 20. Work autonomously when the next step is clear

Do not ask for confirmation after every small implementation step.

When the current phase and next action are clear:

1. inspect;
2. implement;
3. test;
4. update documentation/status;
5. continue.

Ask for a user decision only when there is a real product or architectural fork that materially changes the result.

---

## 21. Starting sequence

When beginning work on a fresh MintCart repository, follow this order:

```text
1. Audit repository.
2. Create/update docs/audit.md.
3. Create/update docs/IMPLEMENTATION_STATUS.md.
4. Record the exact OpenCart upstream baseline.
5. Identify modified OpenCart core files.
6. Identify OCMOD dependencies.
7. Identify existing custom themes/modules/fixes.
8. Create MintCart namespace/bootstrap.
9. Create migration manager.
10. Configure frontend build pipeline.
11. Add Bootstrap 5.3.8 foundation.
12. Create mc-* design tokens and conventions.
13. Add legacy OpenCart/Bootstrap 3 compatibility shim only as required.
14. Implement first base UI components.
15. Implement Product Card as the reference complex component.
16. Implement ComponentRenderer / Theme resolver.
17. Create a second test theme/preset.
18. Prove Product Card can be structurally changed without OCMOD.
19. Only then continue to Header/Footer/Homepage Blocks and later phases.
```

Do not start by implementing AI, advanced SEO, Update Manager or a huge Page Builder.

Prove the architectural foundation first.
