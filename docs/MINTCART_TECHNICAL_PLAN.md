# MintCart — Technical Plan

## 0. Призначення

Цей документ описує **як функціональні вимоги MintCart розкладаються на технічні шари й послідовність реалізації**.

Він не замінює:

- `MINTCART_PRODUCT_VISION.md` — навіщо існує продукт;
- `MINTCART_FUNCTIONAL_SCOPE.md` — що продукт повинен уміти;
- `MINTCART_IMPLEMENTATION_GUIDE.md` — конкретні правила кодування та виконання.

---

# 1. Базова стратегія

MintCart 1.x будується як distribution/fork OpenCart 3 із мінімальним permanent upstream diff.

Основна схема:

```text
OpenCart
   ↓
MintCart Compatibility / Adapters
   ↓
MintCart Core APIs
   ↓
Admin / Commerce / Design / SEO / Update
   ↓
Components / Blocks
   ↓
Themes / Presets / Extensions
```

Головний принцип:

> Не переписувати OpenCart заради сучасності. Закривати product gaps окремим MintCart layer.

---

# 2. Архітектурні цілі

MintCart повинен:

1. залишатися практично сумісним з екосистемою OpenCart 3;
2. мінімізувати прямі зміни core-файлів;
3. не використовувати OCMOD для власного Core / Theme SDK;
4. дозволяти новій темі мати 0 controller patches;
5. централізувати нові API навколо реальних product use cases;
6. залишатися deployable на типовому shared hosting;
7. не вимагати Redis/RabbitMQ/daemon;
8. мати передбачувані migrations/update;
9. мати сучасний frontend/admin layer;
10. не створювати абстракції «про запас».

---

# 3. Базові технічні шари

```text
MintCart Core
MintCart Compatibility
MintCart Admin
MintCart Commerce
MintCart Design
MintCart Blocks
MintCart SEO
MintCart Update
MintCart Diagnostics
MintCart Documentation
```

---

# 4. MintCart Core

Відповідальність:

- MintCart bootstrap;
- version;
- settings abstraction;
- migration manager;
- basic service registration;
- logging;
- health checks;
- shared value objects / utilities;
- feature flags — тільки якщо реально потрібні.

PHP namespace:

```text
MintCart\
```

Не створювати окремий framework поверх OpenCart.

---

# 5. OpenCart Compatibility / Adapter Layer

Мета — не розмазувати OpenCart-specific calls по новому коду.

Приклади:

```text
MintCart\Compatibility\OpenCart3\SettingsAdapter
MintCart\Compatibility\OpenCart3\ProductAdapter
MintCart\Compatibility\OpenCart3\CategoryAdapter
MintCart\Compatibility\OpenCart3\OrderAdapter
MintCart\Compatibility\OpenCart3\CustomerAdapter
MintCart\Compatibility\OpenCart3\ExtensionAdapter
```

Mint code:

```text
Mint API
→ Adapter
→ OpenCart model/service/storage
```

Майбутня цінність:

```text
OpenCart3Adapter
OpenCart4Adapter
```

але OC4 adapter не є задачею MintCart 1.0.

---

# 6. Storage policy

Не дублювати OpenCart data.

Якщо OpenCart уже має:

- store name;
- email;
- telephone;
- address;
- language;
- currency;
- payment settings;
- shipping settings;

MintCart пише в стандартний storage через service/adapter.

Mint-specific tables тільки для Mint-specific features:

```text
mc_migration
mc_page
mc_page_description
mc_block
mc_block_description
mc_update_backup
...
```

Нові tables:

```text
InnoDB
utf8mb4
```

---

# 7. Migration Manager

Створити до масового додавання schema changes.

Формат:

```text
YYYYMMDDHHMM_description.php
```

Наприклад:

```text
202608210001_create_mc_migration.php
202608210002_create_mc_pages.php
202608210003_create_mc_blocks.php
```

Вимоги:

- apply once;
- ordered;
- logged;
- failure stops update;
- cache clear after relevant migrations;
- no scattered ad-hoc ALTER logic.

---

# 8. Frontend stack

Стандарт визначено в Implementation Guide.

Коротко:

```text
Bootstrap 5.3.8
Vanilla JS / ES modules
Swiper 14.1.x
SortableJS 1.15.7
Bootstrap Icons 1.13.x (SVG)
esbuild
```

jQuery:

```text
legacy compatibility only
```

Bootstrap 3:

```text
not loaded as MintCart base
```

Для legacy OpenCart/extension markup:

```text
bootstrap3-shim.css / compatibility layer
```

---

# 9. CSS / Design architecture

MintCart використовує:

```text
Bootstrap 5 layout/utilities
+
MintCart design tokens
+
mc-* BEM ecommerce components
```

Не створювати власний CSS framework, який дублює Bootstrap grid/utilities.

MintCart component API:

```text
mc-product-card
mc-price
mc-badge
mc-search
mc-cart-drawer
mc-checkout
...
```

State:

```text
is-*
has-*
```

Behavior hooks:

```text
data-mc-*
```

---

# 10. Mint Design System

Три рівні variables:

```text
primitive
semantic
component-local
```

Token categories:

- colors;
- typography;
- spacing;
- radius;
- borders;
- shadows;
- container settings;
- motion;
- z-index.

Theme не повинен вимагати recompilation Bootstrap source для звичайної кастомізації.

---

# 11. Theme API

Ключова ціль:

```text
новий MintCart theme = 0 OCMOD
```

Architecture:

```text
Data Provider
→ normalized component contract
→ ComponentRenderer
→ active theme override
→ default component fallback
```

Resolver:

```text
1. active theme override
2. parent/base theme
3. Mint Default component
```

Theme може override:

- product card;
- header;
- footer;
- gallery;
- block templates;
- page layout.

Theme не може override business logic через controller patches.

---

# 12. Preset vs Theme

## Preset

Змінює:

- tokens;
- settings;
- visual options.

DOM component contracts не змінюються.

## Full Theme

Може мати інший component DOM через Theme API.

Не через OCMOD.

---

# 13. Theme manifest

Кожна theme:

```text
theme.json
settings.json
```

`theme.json`:

- name;
- code;
- version;
- Mint API compatibility;
- parent;
- presets.

`settings.json`:

- color;
- select;
- boolean;
- typography;
- layout variants;
- component variants.

Admin form генерується декларативно.

---

# 14. Component contracts

Component отримує normalized data, а не довільний controller context.

Наприклад `product-card`:

```text
product
  id
  name
  url
  image
  secondary_image
  image_alt
  price
  special
  tax
  rating
  stock
  badges

display
  show_rating
  show_description
  show_secondary_image
  action_variant

context
  listing_type
  position
```

Theme не повинен знати SQL/model/controller origin.

---

# 15. Slots / Extension points

Стандартизовані slots:

```text
header.before
header.after

product.before_title
product.after_title
product.before_price
product.after_price
product.after_actions

cart.before_totals
cart.after_totals

checkout.before_confirm
```

Ціль:

- extension fragments без OCMOD;
- predictable insertion points;
- theme renders slot output.

---

# 16. Blocks architecture

Розділити:

```text
Block Provider
Block Data
Block Renderer
Block Twig
```

Block storage:

```text
mc_page
mc_page_description
mc_block
mc_block_description
```

`mc_block`:

- ID;
- page;
- type;
- sort_order;
- status;
- settings_json.

Не створювати table на кожен block type без потреби.

---

# 17. Homepage Builder

Перший scope:

```text
Homepage
```

Builder:

- drag-and-drop;
- add/remove;
- enable/disable;
- block settings;
- localization;
- preview;
- theme override.

Перші blocks:

- Hero;
- Products;
- Categories;
- Banner Grid;
- Benefits;
- Text/Image;
- Brands;
- Testimonials;
- FAQ;
- CTA;
- Newsletter;
- Gallery.

---

# 18. Mint Admin

Новий Mint admin layer не повинен вимагати SPA.

Reusable UI primitives:

```text
McDrawer
McModal
McToast
McConfirm
McTabs
McSortable
McMediaPicker
McEntityPicker
McProgress
```

Нові screens можуть використовувати Bootstrap 5.

Legacy OpenCart admin screens залишаються сумісними.

---

# 19. Admin navigation

Основна IA:

```text
Store
Design
Marketing
Business
Settings
Advanced
```

`Advanced` зберігає доступ до:

- Extensions;
- Layouts;
- Modifications;
- OpenCart Settings.

Не видаляти можливості, а прибрати їх із default user journey.

---

# 20. Entity Picker

Єдиний endpoint/service для:

- products;
- categories;
- manufacturers;
- attributes;
- options;
- information;
- customers.

UI:

- search;
- filter;
- pagination;
- thumbnails;
- multi-select;
- keyboard;
- selected state.

Створення пов'язаної сутності — через drawer/modal, якщо це безпечно.

---

# 21. Setup Wizard

Flow:

```text
Welcome
Store
Contacts
Language / Currency
Delivery
Payment
Design
First Product
Ready
```

Requirements:

- save progress;
- resume;
- skip;
- standard OpenCart storage;
- validation;
- readiness integration.

---

# 22. Store Readiness

Service:

```text
MintCart\Onboarding\StoreReadiness
```

Checks:

- logo;
- contacts;
- language;
- currency;
- shipping;
- payment;
- first product;
- test-order readiness.

Result:

```text
percentage + checklist
```

Не hardcode percentage.

---

# 23. Business Settings

UI façade над стандартними OpenCart data/settings/extensions.

Наприклад:

```text
Business → Delivery
```

може читати список стандартних shipping extensions і показувати спрощений management flow.

Не створювати другу shipping subsystem.

---

# 24. Commerce Layer

Сервіси:

```text
CartService
SearchService
QuickOrderService
CheckoutService
ProductViewProvider
```

Theme взаємодіє з Mint Commerce API, а не випадковими OpenCart controller internals.

---

# 25. Cart client

JS API:

```text
add()
remove()
update()
refresh()
```

Після update:

```text
mc:cart:updated
```

Header cart, drawer, product card, product page реагують на єдиний event contract.

---

# 26. AJAX Search

Normalized endpoint.

Response:

```text
items[]
  id
  name
  url
  image
  price
  special
```

Search backend може еволюціонувати без зміни Theme API.

---

# 27. Checkout

Modern UX поверх OpenCart ecosystem.

Не дублювати:

- payment extensions;
- shipping extensions;
- totals;
- order models.

AJAX recalculation для залежних step data.

Guest checkout — first-class.

---

# 28. Quick Order

Commerce service/component.

Theme не володіє business logic.

Required options повинні враховуватися.

---

# 29. Bulk Editor

Інтегрувати в:

```text
Store → Products
```

Для великих operations:

```text
AJAX batch jobs
```

без background daemon requirement.

Job:

- chunked;
- resumable;
- progress;
- failed batch reporting.

---

# 30. Drag-and-drop Categories

Використати SortableJS.

Move operation:

```text
begin transaction
validate target
prevent cycle
change parent
rebuild affected category_path subtree
update sort order
commit
```

Не ламати closure/path consistency.

---

# 31. SEO Core

Шари:

```text
URL management
Metadata
Canonical/Hreflang
Sitemap
Structured Data
```

Окремо — system routes aliases.

Не розмазувати SEO logic між themes.

---

# 32. MintCart Fix Registry

Файлова документація:

```text
docs/fixes/registry.md
```

Кожен fix має reproduction test.

Не переносити historical patch, якщо upstream baseline уже виправив проблему.

---

# 33. Update Manager

Package manifest повинен містити:

- name;
- version;
- requirements;
- files;
- migrations;
- compatibility;
- checksum/signature metadata.

Flow:

```text
check
compatibility
backup
download
verify
maintenance
files
migrations
cache clear
health check
done
```

---

# 34. Theme / Extension rollback

Окремий layer над update/install.

Мета:

- backup overwritten files;
- versioned snapshots;
- rollback action;
- retention cleanup.

RESEARCH:

- package manifest;
- file deletions;
- custom local changes;
- database migrations;
- partial rollback.

DB rollback не обіцяти без надійної моделі.

---

# 35. Diagnostics

Dev mode:

- query count;
- DB time;
- render time;
- cache hits/misses;
- loaded assets;
- Mint version;
- active theme;
- migration status;
- compatibility warnings.

Production UI не повинен показувати sensitive debug data.

---

# 36. Performance

Починати з вимірювань.

Foundation:

- conditional asset loading;
- lazy loading;
- responsive images;
- width/height;
- minification;
- cache invalidation;
- hot query fixes;
- modern image formats.

Page/block cache — тільки після визначення invalidation rules.

---

# 37. Accessibility

Нові components:

- keyboard usable;
- semantic;
- visible focus;
- correct labels;
- dialogs manage focus;
- reduced motion;
- sortable має non-pointer path.

---

# 38. Compatibility layer

Bootstrap 3 не є MintCart base.

Legacy support:

```text
Bootstrap 5
+
MintCart CSS
+
OpenCart/BS3 compatibility shim
```

OCMOD залишається доступним для сторонніх legacy extensions.

MintCart internal:

```text
no OCMOD
```

---

# 39. Compatibility Checker

Пізніше:

- parse `install.xml`;
- identify modified files;
- detect theme/controller modifications;
- classify risk;
- warn.

Heuristic warning != hard block.

---

# 40. Documentation architecture

```text
docs/
  user/
  developer/
  architecture/
  adr/
  fixes/
```

ADR:

```text
Context
Decision
Consequences
```

Critical decisions документувати, а не залишати лише в чатах.

---

# 41. Upstream diff tracking

Файл:

```text
docs/upstream-diff.md
```

Для кожного прямого OpenCart core modification:

- file;
- reason;
- unavoidable?;
- can it move to Mint layer?;
- tests.

Ціль — зменшувати diff.

---

# 42. Release stages

## Phase 0 — Audit

- upstream baseline;
- libraries;
- custom modifications;
- OCMOD;
- themes;
- modules;
- DB;
- PHP fixes;
- dependencies.

## Phase 1 — Foundation

- Mint bootstrap;
- namespace;
- migrations;
- adapters;
- CI;
- version/logging.

## Phase 2 — Frontend foundation

- Bootstrap 5.3.8;
- esbuild;
- JS modules;
- tokens;
- BEM conventions;
- compatibility shim.

## Phase 3 — Design System

- tokens;
- basic components;
- icons;
- component renderer.

## Phase 4 — Theme API proof

- Product Card;
- Header;
- Footer;
- test theme;
- 0 OCMOD.

## Phase 5 — Blocks

- Block API;
- Homepage Builder;
- SortableJS.

## Phase 6 — Admin UX

- navigation;
- Entity Picker;
- drawers/modals;
- Media Picker.

## Phase 7 — Onboarding

- Setup Wizard;
- Store Readiness;
- Business Settings.

## Phase 8 — Commerce Essentials

- Cart;
- Search;
- Quick Order;
- Checkout;
- product UX.

## Phase 9 — Productivity

- Bulk Editor;
- category drag-and-drop;
- admin search;
- selected autosave scenarios.

## Phase 10 — SEO / Fixes / Performance

- SEO Core;
- system URLs;
- Fix Registry;
- diagnostics;
- measured performance improvements.

## Phase 11 — Update / Compatibility

- Update Manager;
- rollback;
- compatibility checker;
- extension tests.

## Phase 12 — Early Access

10–30 real users/developers.

Measure:

- time to install;
- where users stop;
- what they cannot find;
- when they need developer help;
- Time to First Test Order;
- theme override count.

## 1.0

Freeze:

- Theme API 1.0;
- Blocks API 1.0;
- Commerce contracts;
- compatibility policy.

---

# 43. Technical KPIs

### Time to First Test Order

```text
clean install → successful test order
```

### Theme override debt

```text
OCMOD required by new Mint theme = 0
```

### Upstream diff

```text
modified OpenCart core files → minimize
```

### Compatibility

Track successful tests of:

- payment extensions;
- shipping extensions;
- totals;
- selected popular extensions;
- OCMOD packages.

---

# 44. What not to build first

Не стартувати з:

- AI;
- mobile app;
- headless;
- marketplace;
- Redis;
- Elasticsearch;
- complex visual builder;
- huge SEO suite;
- advanced loyalty.

Першим потрібно довести:

```text
Mint layer
Theme API
Setup Wizard
Commerce Essentials
```

---

# 45. Критична архітектурна контрольна точка

До масштабної розробки features потрібно довести:

```text
Mint Default
+
Test Theme
```

використовують:

- ті самі providers;
- ті самі Commerce APIs;
- той самий component contract;

але мають:

- різний Product Card DOM;
- різний Header DOM;
- різні tokens;
- різний visual style;

і при цьому:

```text
0 OCMOD
0 controller patches
0 copied business logic
```

Якщо це не працює — Theme API треба виправити до продовження.
