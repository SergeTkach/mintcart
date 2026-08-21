# MintCart — Functional Scope

## 0. Призначення документа

Цей документ визначає **яку функціональність MintCart не можна загубити**.

Він не диктує конкретну реалізацію.

Архітектурний спосіб реалізації визначають:

- `MINTCART_TECHNICAL_PLAN.md`;
- `MINTCART_IMPLEMENTATION_GUIDE.md`.

Якщо тут описана функція, а в Implementation Guide вона ще не деталізована, це не означає, що функцію потрібно видалити.

---

# 1. Пріоритети

Позначення:

```text
P0 — формує ідентичність MintCart / MVP
P1 — важливо для MintCart 1.0
P2 — після стабілізації основи
RESEARCH — спочатку перевірити проблему, UX або feasibility
```

---

# 2. Setup Wizard

**Priority: P0**

Після чистої установки користувач не повинен потрапляти у звичайну OpenCart admin і сам шукати десятки налаштувань.

Wizard повинен вести через логічний запуск магазину.

Базові кроки:

- мова;
- валюта;
- країна / регіон;
- назва магазину;
- логотип;
- телефон / email;
- адреса;
- соціальні мережі;
- способи доставки;
- способи оплати;
- базові SEO-речі;
- вибір design preset/theme;
- імпорт demo data — опційно;
- перший товар.

Вимоги:

- progress saving;
- resume later;
- optional steps можна пропускати;
- використовувати стандартні OpenCart settings там, де вони вже існують;
- не створювати дублікати business data в Mint-specific tables.

Результат:

```text
Store Readiness
```

з checklist і прогресом.

---

# 3. Централізовані Business Settings

**Priority: P0**

Повсякденні налаштування малого магазину не повинні бути розкидані по `Extensions`, `Settings`, `Layouts`, `Modules`.

Цільові розділи:

```text
Business → Contacts
Business → Delivery
Business → Payments
Settings → Store
```

Туди вивести звичайні бізнес-параметри, не дублюючи storage OpenCart.

---

# 4. Системні SEO URL / ЧПУ

**Priority: P1**

Окрема функціональна вимога.

Користувач повинен мати зрозуміле місце, де задаються URL для системних сторінок.

Наприклад:

```text
cart
checkout
search
contact
account
login
register
wishlist
compare
special
manufacturer
sitemap
```

Проблема:

стандартний користувач не повинен розбиратися, чому SEO URL контентних сторінок і URL системних routes керуються різними механізмами.

Цільовий UX:

```text
Marketing / SEO
  → URLs
     → System pages
     → Products
     → Categories
     → Information pages
```

На чистій установці MintCart повинен уже мати адекватні default aliases для системних routes, якщо це безпечно щодо мов і сумісності.

Потрібно врахувати:

- multi-language;
- multi-store;
- uniqueness;
- canonical;
- redirects при зміні URL — якщо це входить у SEO Core;
- compatibility зі стандартною OpenCart SEO URL логікою.

---

# 5. Модерна адмінка

**Priority: P0 / P1 по частинах**

MintCart admin повинен відчуватися сучасним навіть без переписування ядра.

## P0

- нова інформаційна архітектура меню;
- mobile-friendly screens;
- modern visual system;
- consistent form patterns;
- drawers/modals для коротких пов'язаних дій;
- Toast / Confirm / Alert primitives;
- normal empty states;
- Store Readiness dashboard.

## P1

- швидкий пошук налаштувань;
- command/search palette — якщо UX виправданий;
- autosave для безпечних форм;
- admin appearance/theme options;
- contextual help;
- keyboard-friendly entity selection.

Autosave не повинен застосовуватися сліпо до критичних операцій.

Для order/payment/security/settings із сильними side effects потрібне явне Save/Confirm.

---

# 6. Drag-and-drop / Sortable UX

**Priority: P0/P1**

Використовувати там, де порядок є природною частиною задачі.

Сценарії:

- Homepage Blocks;
- Categories;
- Menu;
- Banner items;
- Benefits;
- FAQ;
- Gallery;
- Footer columns;
- Featured item ordering.

Не додавати drag-and-drop там, де звичайний sort field або table sort зрозуміліший.

---

# 7. Inline Edit / Bulk Actions

**Priority: P1**

Особливо для каталогу.

Products:

- status;
- quantity;
- price;
- special;
- manufacturer;
- category;
- stock state;
- SEO fields — де безпечно;
- mass actions;
- multi-select.

Джерело UX-досвіду:

```text
Handy Product Manager
```

Але не копіювати його стару module architecture буквально.

---

# 8. Entity Picker

**Priority: P0**

Універсальний вибір сутностей замість старих autocomplete patterns.

Типи:

- products;
- categories;
- manufacturers;
- attributes;
- options;
- information pages;
- customers.

Повинен підтримувати:

- search;
- filters;
- pagination;
- images;
- multi-select;
- selected state;
- keyboard navigation;
- create-new flow через drawer/modal, де це доцільно.

---

# 9. Theme System / Theme Builder

**Priority: P0**

Не «візуальний конструктор всього сайту».

Потрібна керована Theme System.

Функції:

- глобальні colors;
- typography;
- spacing/radius/borders/shadows;
- container/layout options;
- product card style;
- button style;
- header variants;
- footer variants;
- product gallery variants;
- presets;
- live preview — P1;
- theme settings schema;
- component overrides;
- Theme API без OCMOD.

Розділити:

```text
Preset
Full Theme
```

Preset змінює design tokens/settings.

Full Theme може змінювати DOM компонентів через офіційний resolver.

---

# 10. Homepage Builder / Blocks

**Priority: P0**

Початковий scope:

```text
Homepage only
```

Блоки:

- Hero;
- Products;
- Categories;
- Banner Grid;
- Benefits;
- Text;
- Text + Image;
- Brands;
- Testimonials;
- CTA;
- Newsletter;
- FAQ;
- Gallery;
- HTML;
- Spacer.

Вимоги:

- drag-and-drop;
- block enable/disable;
- block settings;
- localization;
- theme override;
- provider/render separation;
- не робити Elementor-style system.

---

# 11. Commerce Essentials

**Priority: P0**

MintCart повинен бути готовим до типового малого магазину без пошуку базового набору модулів.

Функціональність:

- modern checkout;
- guest checkout;
- Quick Order / 1 click;
- AJAX Search;
- cart drawer / normal cart feedback;
- wishlist;
- viewed products;
- configurable badges/stickers;
- benefits;
- top info bar;
- cookie consent;
- delivery/payment/return information на product page;
- stock statuses;
- reusable product card;
- normal search UX;
- integration-ready reviews area.

---

# 12. Checkout

**Priority: P0**

UI повинен бути зрозумілим і коротким.

Орієнтовний flow:

```text
Contacts
Address
Delivery
Payment
Review / Confirm
```

Не створювати другу payment/shipping ecosystem.

Поверх стандартних OpenCart:

- shipping extensions;
- payment extensions;
- totals;
- order model.

При зміні адреси / доставки:

- актуалізувати shipping;
- payment;
- totals;
- availability.

---

# 13. Quick Order

**Priority: P0/P1**

Quick Order — platform commerce component, а не feature конкретної теми.

Повинен працювати з:

- product ID;
- quantity;
- required options;
- name;
- phone/email;
- basic validation.

Theme лише рендерить button/component.

---

# 14. AJAX Search

**Priority: P0**

Єдиний normalized search endpoint/service.

Результат мінімум:

- image;
- name;
- URL;
- price;
- special;
- category/context за потреби.

Theme не повинен патчити controller для пошуку.

---

# 15. Cart UX

**Priority: P0**

Після Add to Cart користувач повинен однозначно розуміти результат.

Варіанти UI:

- drawer;
- modal;
- toast + header cart update.

Єдиний Cart client / API.

Подія:

```text
mc:cart:updated
```

або еквівалентний stable event contract.

---

# 16. SEO Core

**Priority: P1**

Базово:

- SEO URLs;
- system route URLs;
- canonical;
- hreflang;
- meta title;
- meta description;
- robots controls;
- sitemap;
- Product Schema.org;
- Breadcrumb Schema.org;
- Organization Schema.org.

Advanced SEO не є blocker для 1.0:

- filter landing pages;
- complex SEO pagination rules;
- mass AI texts;
- large-scale landing generators.

---

# 17. MintCart Fixes

**Priority: P1**

Окрема контрольована колекція перевірених OpenCart bugfixes і compatibility fixes.

Не створювати «мішок патчів».

Завести:

```text
docs/fixes/registry.md
```

Для кожного fix:

```text
ID
Problem
Affected OpenCart versions
Source
Reproduced on MintCart baseline: yes/no
Upstream fixed: yes/no
MintCart solution
Tests
```

Правило:

> Не застосовувати historical fix, поки проблема не відтворена або не підтверджена для поточного MintCart upstream baseline.

---

# 18. Known Fix Research Queue

**Priority: RESEARCH**

Перевірити відомі історичні проблеми OpenCart, у тому числі ті, які раніше обговорювалися в екосистемі.

Кандидати:

- database connection / reconnect issues;
- pagination issue з неправильним `?page=1` або дублями;
- multilingual SEO URL issues;
- category path/update issues;
- cache race conditions;
- зайві записи/updates session/cart;
- installer edge cases;
- hardcoded database prefix у backup/restore logic;
- PHP 8.x compatibility problems.

Кожен кандидат спочатку відтворити на поточній базовій версії.

---

# 19. Performance

**Priority: P1/P2**

Не робити «performance edition» через великий набір магічних оптимізацій.

Початковий набір:

- asset minification;
- conditional loading;
- lazy loading;
- responsive images;
- width/height;
- WebP/modern formats, де доступні;
- query profiling;
- critical query optimization;
- cache invalidation API;
- page/block caching — тільки після вимірювання;
- no mandatory Redis.

Потрібен dev diagnostic mode для вимірювання:

- number of queries;
- DB time;
- render time;
- cache status.

---

# 20. Update Manager

**Priority: P1**

MintCart updates мають бути передбачуваними.

Flow:

```text
Check
→ Compatibility check
→ Backup
→ Download
→ Verify
→ Maintenance
→ Files
→ Migrations
→ Cache clear
→ Health check
→ Done
```

---

# 21. Rollback для theme/extension update

**Priority: P1 / RESEARCH**

Окрема функція, не тотожна повному backup.

Сценарій:

```text
before extension/theme update
→ determine files that will be overwritten
→ store previous versions
→ install update
→ allow rollback
```

Особливо важливо для тем, де клієнт міг вручну змінити файли.

Перед реалізацією визначити:

- як надійно виявляти змінені файли;
- чи можна diff-ити package manifest;
- як працювати з files, які package видаляє;
- як rollback взаємодіє з DB migrations;
- retention policy;
- disk limits;
- security.

Для DB changes не обіцяти повний автоматичний rollback без надійного механізму.

---

# 22. Autosave

**Priority: P1 / RESEARCH**

Можливі сценарії:

- block editor;
- theme settings;
- non-critical design forms;
- draft page editing.

Не застосовувати автоматично до:

- order state changes;
- payment settings;
- user permissions;
- destructive operations;
- migration/update operations.

Потрібні:

- dirty state;
- saving state;
- saved state;
- failed state;
- conflict handling;
- navigation warning.

---

# 23. Швидкий пошук налаштувань

**Priority: P1**

Користувач повинен мати можливість знайти:

```text
currency
logo
delivery
SEO URL
email
tax
checkout
```

без знання структури меню.

Пошук повинен вести не просто на сторінку, а за можливості на конкретну секцію/field.

---

# 24. Modern Editor

**Priority: P2 / RESEARCH**

Перевірити потребу в оновленні rich text editor.

Вимоги:

- stable;
- lightweight;
- safe HTML;
- multilingual;
- image/media integration;
- no unnecessary framework coupling.

Не міняти editor лише через номер версії або «сучасність».

---

# 25. Media UX

**Priority: P1**

Покращити вибір зображень:

- Media Picker;
- thumbnails;
- search;
- folders;
- upload;
- recent files;
- safe delete;
- image metadata;
- integration з Product/Category/Blocks.

---

# 26. Admin mobile UX

**Priority: P1**

Не обіцяти повноцінну роботу зі всім каталогом на телефоні.

Але критичні сценарії повинні бути usable:

- подивитися order;
- змінити status;
- знайти product;
- змінити stock/price;
- переглянути dashboard;
- basic settings.

---

# 27. Accessibility

**Priority: P1**

Нові MintCart UI components повинні проектуватися під WCAG 2.2 AA як орієнтир.

Мінімум:

- keyboard navigation;
- visible focus;
- semantic controls;
- labels;
- dialogs/focus management;
- contrast;
- reduced motion;
- non-mouse alternative for important sortable actions.

---

# 28. Compatibility

**Priority: P0/P1**

Зберегти роботу типової OpenCart ecosystem:

- shipping;
- payment;
- totals;
- events;
- OCMOD для legacy extensions;
- стандартні entity models/routes — наскільки практично.

MintCart-owned code:

```text
no OCMOD
```

Legacy extensions:

```text
OCMOD allowed
```

---

# 29. Compatibility Checker

**Priority: P2**

Аналізувати extension package / `install.xml`.

Показувати:

- modified files;
- modified controllers;
- modified Twig templates;
- potential conflict;
- Bootstrap 3 assumptions;
- theme-specific modifications.

Не блокувати install лише за heuristic warning.

---

# 30. Demo Data

**Priority: P1**

Setup Wizard може пропонувати demo data.

Вимоги:

- optional;
- легко видалити;
- не змішувати з реальними даними після запуску;
- demo має показувати Blocks / Product Card / Theme capabilities;
- мінімум, а не великий каталог.

---

# 31. Theme live preview

**Priority: P1/P2**

Користувач повинен бачити зміни theme settings без складного publish cycle.

Потрібно визначити UX:

- iframe preview;
- draft settings;
- preview token;
- live CSS variables;
- publish/apply.

Не створювати повноцінний visual page editor, якщо це не потрібно.

---

# 32. Admin appearance

**Priority: P2**

Можна підтримати:

- light;
- dark;
- system;
- compact density — можливо.

Це не повинно відволікати від основних UX проблем.

---

# 33. Documentation / Help

**Priority: P1**

User docs:

- install;
- wizard;
- products;
- delivery;
- payments;
- design;
- homepage;
- orders;
- SEO basics.

Developer docs:

- Theme API;
- Components;
- Blocks;
- Slots;
- Events;
- Adapters;
- Compatibility;
- Migrations.

Contextual help у Mint admin.

---

# 34. Feature exclusions для 1.0

Не вважати обов'язковими:

- AI assistant;
- AI descriptions;
- multivendor;
- marketplace;
- CRM;
- ERP;
- warehouse suite;
- native mobile app;
- headless;
- GraphQL;
- Elasticsearch;
- mandatory Redis;
- RabbitMQ;
- visual email builder;
- 100+ blocks/widgets;
- recommendation ML;
- loyalty platform.

---

# 35. Джерела / історичні нотатки

Окремі ідеї цього scope походять зі старих робочих нотаток та обговорень.

Historical references не мають пріоритету над актуальною специфікацією.

Корисні посилання винесені в:

```text
references/links.md
```
