# MintCart — Implementation Guide for Claude

## 0. Твоя роль

Ти працюєш над **MintCart** — дружнім ecommerce-дистрибутивом на базі OpenCart 3.0.5.1.

MintCart не є переписуванням OpenCart і не повинен перетворитися на новий framework.

Головна продуктова мета:

> Скоротити шлях від чистої установки до магазину, готового приймати замовлення.

Головний технічний принцип:

> Не переписувати те, що OpenCart уже робить нормально. Створювати окремий MintCart layer для тих речей, які OpenCart робить незручно або не робить взагалі.

Працюй **послідовно за фазами нижче**.

Не проси після кожної фази вказівок «що робити далі». Якщо фаза завершена й тести проходять — переходь до наступної.

Якщо бачиш архітектурну проблему, яка робить наступний крок неправильним, спочатку зафіксуй її в документації й виправ фундамент.

Не роби великих unrelated refactor.

---

# 1. Вихідна платформа

Базова версія:

```text
OpenCart 3.0.5.1
```

MintCart повинен залишатися максимально сумісним з екосистемою OpenCart 3.

Не переносити MintCart на OpenCart 4 у рамках MintCart 1.0.

Не переписувати MVC OpenCart.

Не вводити:

```text
Laravel
Symfony application framework
React
Vue
Angular
SPA admin
headless architecture
GraphQL
ORM
Redis requirement
RabbitMQ requirement
Docker requirement
```

Якщо щось із цього колись знадобиться — воно має вирішувати конкретну проблему, а не використовуватися заради «сучасності».

---

# 2. Namespace MintCart

Старий namespace `st-*` не використовувати.

Він належить попередній авторській дизайн-системі й не повинен переноситися в MintCart.

Використовувати:

```text
PHP namespace:
MintCart\

CSS:
mc-

CSS custom properties:
--mc-

JS hooks:
data-mc-*

JS classes/modules:
Mc*
```

Приклади:

```html
<article class="mc-product-card">

<button class="mc-button mc-button--primary">

<div class="mc-product-card__actions">

<button data-mc-action="add-to-cart">
```

CSS:

```css
--mc-primary
--mc-surface
--mc-space-4
--mc-radius-md
```

PHP:

```php
MintCart\Core\Settings
MintCart\Design\ComponentRenderer
MintCart\Commerce\Cart
```

---

# 3. Frontend stack

## 3.1 JavaScript

Новий MintCart JS пишеться на:

```text
Vanilla JavaScript
ES modules
ES2020+ syntax
```

Не використовувати jQuery у новому MintCart-коді.

Заборонено:

```javascript
$('.mc-product-card')
$.ajax(...)
$(document).on(...)
```

Використовувати:

```javascript
document.querySelector()
document.querySelectorAll()
fetch()
FormData
URLSearchParams
CustomEvent
AbortController
```

jQuery лишається завантаженим **тільки для OpenCart та legacy extensions**.

Не видаляти його до окремої фази compatibility research.

---

# 4. JavaScript dependencies

Тримати dependencies мінімальними.

Базовий набір для нового MintCart:

```text
Bootstrap 5.3.8
```

Використовувати як базовий UI/layout foundation для нового storefront і нових MintCart admin screens.

Дозволено використовувати:

```text
container
row
col-*
g-*
d-*
flex utilities
spacing utilities
responsive utilities
modal
dropdown
offcanvas
collapse
tooltip / popover — лише коли реально потрібні
```

Bootstrap не є Theme API MintCart і не повинен визначати DOM-контракт ecommerce components.

Тобто допустимо:

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

але не будувати API `product-card` навколо Bootstrap-класів.

```text
jQuery 3.7.1
```

Legacy only. Новий MintCart code його не використовує.

jQuery може залишатися завантаженим там, де він потрібен OpenCart 3 або legacy extensions.

```text
Swiper 14.1.x
```

Використовувати для:

```text
hero slider
product gallery
product carousel
brands carousel
testimonials carousel
```

Не завантажувати глобально, якщо на сторінці немає Swiper.

```text
SortableJS 1.15.7
```

Використовувати в admin для:

```text
homepage blocks
categories
menus
banner items
benefits
FAQ
gallery
footer columns
```

Не використовувати jQuery UI Sortable.

```text
Bootstrap Icons 1.13.x
```

Використовувати як базове джерело SVG-іконок MintCart.

Не використовувати icon font як новий runtime API.

Переважно:

```text
SVG sprite
inline SVG renderer
```

---

# 5. Чого НЕ встановлювати

Без конкретної необхідності не додавати:

```text
Axios
Lodash
jQuery UI
Alpine.js
Vue
React
Moment.js
Slick
Owl Carousel
Select2
SweetAlert
Bootstrap 3
Bootstrap 4
```

Якщо функціональність можна реалізувати 20–50 рядками нормального native JS — не додавати dependency.

Не дублювати можливості Bootstrap 5 сторонніми UI-бібліотеками без вагомої причини.

---

# 6. Build system

Використовувати:

```text
npm
esbuild
```

Не вводити складну frontend build ecosystem.

Структура:

```text
assets/
  src/
    css/
    js/
    icons/
  dist/
```

JS:

```text
assets/src/js/catalog.js
assets/src/js/admin.js
```

Build:

```text
catalog.js → catalog.min.js
admin.js   → admin.min.js
```

CSS також можна bundle через esbuild imports.

Обов'язково:

```text
package.json
package-lock.json
```

Dependencies pin у lockfile.

Production assets minified.

Development assets мають source maps.

Bootstrap, Swiper, SortableJS та інші npm dependencies повинні підключатися через контрольований build/import layer, а не через випадкові CDN-посилання в Twig.

---

# 7. Bootstrap policy

## Bootstrap 5.3.8 є базовою frontend UI-бібліотекою MintCart.

Для нового MintCart використовувати:

```text
Bootstrap 5.3.8
```

Його роль:

```text
responsive grid
containers
layout utilities
spacing utilities
display/flex helpers
standard interaction primitives
base form/layout behavior
```

MintCart не пише власний CSS framework замість Bootstrap.

Не створювати `mc-grid`, `mc-cluster`, `mc-stack` та інші абстракції лише для дублювання того, що вже нормально вирішує Bootstrap.

Власний CSS потрібен там, де починається саме продуктова дизайн-система MintCart:

```text
product card
price
badge
header
navigation
cart
checkout
search
filters
blocks
theme tokens
ecommerce UI
```

---

# 8. Bootstrap і BEM працюють разом

Bootstrap-класи дозволено використовувати для layout та utilities.

MintCart-owned components використовують `mc-*` BEM.

Наприклад:

```html
<section class="mc-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <article class="mc-product-card">
                    <div class="mc-product-card__media">
                        ...
                    </div>

                    <div class="mc-product-card__body">
                        ...
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>
```

Добре:

```text
container
row
col-lg-*
d-flex
align-items-center
gap-*
mt-*
mb-*
```

для структурних і utility задач.

Добре:

```text
mc-product-card
mc-product-card__media
mc-price
mc-badge
mc-cart-drawer
```

для стабільних MintCart components.

Не робити Bootstrap-клас частиною Theme API contract.

Наприклад Theme API не повинен вимагати:

```text
.card
.btn
.alert
```

щоб component працював.

---

# 9. Bootstrap components vs MintCart components

Bootstrap components можна використовувати для generic UI primitives, якщо вони нормально вирішують задачу.

Наприклад:

```text
modal
offcanvas
dropdown
collapse
tooltip
```

Не потрібно переписувати Bootstrap Modal своїми силами без продуктової причини.

Але ecommerce components мають бути MintCart-owned.

Наприклад:

```text
mc-product-card
mc-product-gallery
mc-price
mc-cart-drawer
mc-search
mc-checkout
mc-order-summary
```

Причина:

Theme SDK повинен мати стабільний семантичний API, незалежний від конкретної внутрішньої реалізації Bootstrap.

---

# 10. OpenCart 3 / Bootstrap 3 compatibility

OpenCart 3 і частина сторонніх extensions можуть генерувати Bootstrap 3 markup.

Bootstrap 3 НЕ завантажувати паралельно з Bootstrap 5.

Не підключати одночасно:

```text
bootstrap-3.css
bootstrap-5.css
```

через глобальні конфлікти класів:

```text
.btn
.container
.row
.modal
.dropdown
.alert
.form-control
```

Замість цього створити compatibility layer:

```text
css/compatibility/opencart3.css
```

або окремо:

```text
css/compatibility/bootstrap3-shim.css
```

Він підтримує найбільш поширені legacy patterns, яких немає або які змінилися в Bootstrap 5:

```text
.pull-right
.pull-left
.hidden-xs
.visible-xs
.btn-default
.panel
.panel-heading
.panel-body
.input-group-btn
```

Наприклад:

```css
.pull-right {
    float: right !important;
}

.pull-left {
    float: left !important;
}

.btn-default {
    --bs-btn-color: var(--mc-text);
    --bs-btn-bg: var(--mc-surface);
    --bs-btn-border-color: var(--mc-border);
}

.panel {
    margin-bottom: var(--mc-space-4);
    background: var(--mc-surface);
    border: 1px solid var(--mc-border);
    border-radius: var(--mc-radius-md);
}

.panel-heading,
.panel-body {
    padding: var(--mc-space-4);
}
```

Compatibility shim не повинен намагатися повністю емулювати весь Bootstrap 3.

Підтримувати реальні патерни, які зустрічаються у перевірених OpenCart extensions.

---

# 11. CSS architecture

Взяти сильні сторони попередньої дизайн-системи, але перейменувати й адаптувати під Bootstrap 5.

Структура:

```text
css/
  settings/
    tokens.css

  generic/
    base.css

  elements/
    typography.css
    forms.css
    media.css

  components/
    button.css
    field.css
    product-card.css
    price.css
    badge.css
    rating.css
    header.css
    navigation.css
    cart.css
    checkout.css
    alert.css
    pagination.css
    breadcrumb.css
    ...

  utilities/
    utilities.css

  compatibility/
    opencart3.css
    bootstrap3-shim.css
    font-awesome.css
    extensions.css

  index.css
```

Не дублювати Bootstrap reset, grid та utility system власними файлами без потреби.

Порядок imports:

```text
Bootstrap 5
MintCart tokens
MintCart elements
MintCart components
MintCart utilities
compatibility
theme overrides
```

Основна система themes повинна працювати через native CSS custom properties.

Не використовувати Sass як обов'язкову частину Theme SDK.

Theme developer повинен мати можливість змінювати дизайн через:

```text
tokens
component CSS
Twig component overrides
```

без компіляції Bootstrap source.

---

# 12. BEM rules

Для MintCart використовувати BEM.

```text
mc-block
mc-block__element
mc-block--modifier
```

Приклад:

```html
<article class="mc-product-card mc-product-card--compact">
    <div class="mc-product-card__media"></div>

    <div class="mc-product-card__body">
        <h3 class="mc-product-card__title"></h3>
    </div>

    <div class="mc-product-card__actions"></div>
</article>
```

Не робити:

```text
mc-product-card-title
mc-product-card-inner-left
mc-product-card-red
mc-product-card-mt20
```

---

# 13. Стани

Стани не є BEM modifiers, якщо це transient runtime state.

Використовувати:

```text
is-active
is-open
is-loading
is-disabled
has-error
has-items
```

або семантичні attributes:

```html
aria-expanded="true"
aria-current="page"
aria-selected="true"
disabled
hidden
```

CSS за можливості орієнтувати на semantic attributes.

---

# 14. JS hooks

JS не повинен шукати елемент за CSS-класом компонента.

Не робити:

```javascript
document.querySelector('.mc-cart-button')
```

для поведінки.

Робити:

```html
<button
    class="mc-button mc-button--primary"
    data-mc-action="add-to-cart"
>
```

JS:

```javascript
document.addEventListener('click', (event) => {
    const button = event.target.closest(
        '[data-mc-action="add-to-cart"]'
    );

    if (!button) {
        return;
    }
});
```

CSS class = presentation contract.

`data-mc-*` = behavior contract.

---

# 15. IDs

Не використовувати `id` для CSS styling.

Не використовувати `id` як основний JS-hook.

ID дозволені для:

```text
label → input
aria-controls
aria-labelledby
fragment URLs
унікальних HTML associations
```

---

# 16. CSS token system

Використовувати три рівні.

## Primitive

```css
--mc-color-blue-600
--mc-color-gray-100
```

## Semantic

```css
--mc-primary
--mc-accent
--mc-surface
--mc-text
--mc-muted
--mc-border
--mc-success
--mc-danger
```

## Component local variables

```css
.mc-button {
    --mc-button-bg: var(--mc-primary);
    --mc-button-color: var(--mc-on-primary);

    background: var(--mc-button-bg);
    color: var(--mc-button-color);
}

.mc-button--ghost {
    --mc-button-bg: transparent;
}
```

---

# 17. Що повинно бути tokenized

Обов'язково:

```text
colors
font families
font sizes
font weights
line heights
spacing scale
radii
shadows
container widths
motion durations
easing
z-index levels
```

Не треба перетворювати на token кожне технічне CSS-значення.

Це нормально:

```css
display: grid;
width: 100%;
position: relative;
overflow: hidden;
```

Не треба створювати:

```css
--mc-display-grid: grid;
```

---

# 18. Inline styles

Звичайні inline styles заборонені.

Не робити:

```html
<div style="margin-top:20px">
```

Допустимий виняток — **data-driven CSS custom property**.

Наприклад custom sticker color:

```html
<span
    class="mc-badge"
    style="--mc-badge-bg:#e83232"
>
```

Тут колір є даними користувача, а не hardcoded theme style.

---

# 19. Typography

HTML heading level визначає семантику.

CSS class визначає вигляд.

Тобто:

```html
<h2 class="mc-section__title">
```

і:

```html
<p class="mc-heading mc-heading--xl">
```

можуть візуально виглядати однаково.

Не вибирати `h1/h2/h3` через розмір шрифту.

---

# 20. Rich text

Створити:

```text
mc-prose
```

для HTML з CKEditor та іншого rich text.

Наприклад:

```css
.mc-prose h2 {}
.mc-prose h3 {}
.mc-prose p {}
.mc-prose ul {}
.mc-prose table {}
```

Не використовувати правила rich text глобально там, де вони можуть поламати components.

---

# 21. Іконки

Для MintCart-owned markup не використовувати Font Awesome class names як API.

Не робити:

```html
<i class="fa fa-shopping-cart"></i>
```

у нових Mint components.

Створити semantic icon renderer:

```twig
{{ mc_icon('cart') }}
{{ mc_icon('heart') }}
{{ mc_icon('search') }}
```

Базове джерело іконок:

```text
Bootstrap Icons 1.13.x SVG
```

Але runtime icon font не потрібний.

Переважно використовувати:

```text
SVG sprite
```

або inline SVG renderer.

Контракт:

```text
cart
heart
search
account
menu
close
chevron-left
chevron-right
plus
minus
trash
edit
check
warning
```

---

# 22. Legacy Font Awesome

OpenCart і сторонні модулі продовжують використовувати:

```text
fa fa-*
```

Не переписувати їх масово.

Font Awesome compatibility лишається окремим legacy layer.

MintCart components використовують `mc_icon()`.

Legacy extensions використовують свої `fa-*`.

Ці дві системи можуть існувати паралельно.

---

# 23. Найважливіша зміна від старої дизайн-системи

НЕ дотримуватися жорсткого правила:

> усі themes мають один і той самий Twig.

Це добре для skins, але занадто обмежує повноцінні themes.

MintCart має розділяти:

```text
Preset
Theme
```

---

# 24. Preset

Preset змінює лише дизайн.

Наприклад:

```text
Clean
Warm
Tech
Fashion
Bold
Minimal
```

Preset містить:

```text
tokens.css
theme settings values
optional icon selection
```

DOM не змінюється.

---

# 25. Full Theme

Theme може змінити structure, але тільки через офіційний Theme API.

Theme НЕ може вимагати OCMOD тільки для зміни дизайну.

Theme може override:

```text
component
page layout
block template
header variant
footer variant
```

але не controller business logic.

---

# 26. Component-first architecture

Основою Theme API є components.

Приблизна структура:

```text
mint/
  components/
    button/
    field/
    product-card/
    price/
    badge/
    rating/
    breadcrumb/
    pagination/
    quantity/
    gallery/
    cart/
    search/
    header/
    footer/

  blocks/
    hero/
    products/
    categories/
    banner-grid/
    benefits/
    brands/
    testimonials/
    faq/
    gallery/
    text-image/
    newsletter/
    html/

  pages/
    home/
    category/
    product/
    search/
    cart/
    checkout/
    account/
```

---

# 27. Component contract

Кожен component має чітко визначений data contract.

Наприклад `product-card` отримує не весь OpenCart controller context, а приблизно:

```text
product
display
context
```

`product`:

```text
id
name
url
image
image_alt
price
special
tax
rating
stock
badges
```

`display`:

```text
show_rating
show_description
show_second_image
add_button_style
```

Theme не повинен знати:

```text
який controller створив product
яка model використовувалась
який SQL виконувався
```

---

# 28. Component renderer

Створити:

```php
MintCart\Design\ComponentRenderer
```

Resolver шукає template у такому порядку:

```text
1. active theme override
2. parent/base theme, якщо використовується
3. MintCart default component
```

Наприклад:

```text
theme/custom/template/mint/components/product-card.twig

fallback:

theme/mint_default/template/mint/components/product-card.twig
```

Таким чином дизайнер може створити інший product card без OCMOD.

---

# 29. Theme override rule

Theme може override:

```text
product-card.twig
header.twig
footer.twig
product-gallery.twig
```

Theme не може через override міняти business logic.

Якщо для нового дизайну бракує даних:

НЕ додавати OCMOD у theme.

Замість цього:

1. визначити, чи ці дані універсально корисні;
2. додати їх у component contract;
3. реалізувати provider у MintCart;
4. оновити API documentation.

---

# 30. Theme manifest

Кожна тема повинна містити:

```text
theme.json
```

Приклад:

```json
{
  "name": "Mint Default",
  "code": "mint_default",
  "version": "1.0.0",
  "mint_api": "^1.0",
  "parent": null,
  "presets": [
    "clean",
    "warm",
    "tech"
  ]
}
```

---

# 31. Theme settings schema

Не писати окремий admin controller/form для кожної теми.

Створити декларативний:

```text
settings.json
```

Наприклад:

```json
{
  "primary_color": {
    "type": "color",
    "default": "#2457d6"
  },
  "card_radius": {
    "type": "select",
    "options": [
      "square",
      "soft",
      "round"
    ]
  },
  "show_second_image": {
    "type": "boolean",
    "default": false
  }
}
```

MintCart admin будує form зі schema.

---

# 32. Blocks architecture

Mint-native module не повинен генерувати довільний theme-specific HTML.

Розділити:

```text
Block Provider
Block Renderer
Block Template
```

Provider отримує дані.

Наприклад Products block:

```text
ProductsBlockProvider
```

повертає:

```text
title
products[]
limit
layout
```

Renderer вибирає:

```text
blocks/products.twig
```

Theme може override template.

Provider залишається тим самим.

---

# 33. Homepage Builder

Перший Page Builder — тільки homepage.

Не будувати Elementor.

Перший набір blocks:

```text
Hero
Products
Categories
Banner Grid
Benefits
Text
Text + Image
Brands
Testimonials
CTA
Newsletter
FAQ
Gallery
HTML
Spacer
```

Кожен block:

```text
id
type
sort_order
status
settings
```

Drag & Drop через SortableJS.

---

# 34. Block storage

Створити таблиці приблизно:

```text
mc_page
mc_page_description
mc_block
mc_block_description
```

У `mc_block`:

```text
block_id
page_id
type
sort_order
status
settings_json
```

Не створювати окрему SQL table для кожного block type, якщо для цього немає серйозної причини.

---

# 35. Extension slots

Створити Theme API slots/hooks.

Приклади:

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

Extension повинно мати можливість додати fragment без OCMOD.

---

# 36. Legacy modules

Розмітку сторонніх модулів, яку MintCart не контролює:

НЕ переписувати автоматично.

Наприклад:

```text
OCFilter
payment widgets
shipping widgets
captcha
third-party modules
```

Використовувати compatibility skin.

За потреби wrap:

```html
<div class="mc-legacy">
    {{ legacy_module_output }}
</div>
```

Але не ламати IDs, classes або `data-*`, які потрібні JS самого extension.

---

# 37. Core principle for OCMOD

OCMOD залишається для сумісності зі старою екосистемою.

Але:

```text
MintCart Core → no OCMOD
MintCart Default Theme → no OCMOD
MintCart Blocks → no OCMOD
MintCart Theme SDK → no OCMOD
```

Ціль:

```text
новий MintCart theme
=
0 OCMOD modifications
```

---

# 38. OpenCart Adapter

Не розмазувати OpenCart-specific calls по MintCart.

Створити:

```text
MintCart\Compatibility\OpenCart3
```

Наприклад:

```php
SettingsAdapter
ProductAdapter
CategoryAdapter
OrderAdapter
CustomerAdapter
ExtensionAdapter
```

Mint layer викликає:

```php
$products->find($id);
```

а adapter уже працює з OpenCart model.

Це створює майбутню можливість:

```text
OpenCart3Adapter
OpenCart4Adapter
```

без переписування Design/Blocks/Wizard.

---


# 38A. Controller decomposition rules

Для нового MintCart-коду і поступового refactor старих OpenCart controllers дотримуватися правила:

```text
Controller orchestrates.
Service/Action performs reusable work.
Provider builds normalized view data.
Twig renders.
```

Controller action не повинен залишатися великим процедурним методом, якщо його можна природно розбити на етапи.

Добре:

```php
public function index(): void
{
    $this->initialize();
    $this->loadContext();
    $this->buildBreadcrumbs();
    $this->buildListing();
    $this->buildLayout();
    $this->render();
}
```

Допустимий chaining:

```php
public function index(): void
{
    $this
        ->initialize()
        ->loadContext()
        ->buildBreadcrumbs()
        ->buildListing()
        ->buildLayout()
        ->render();
}
```

Chaining не є самоціллю.

Головна вимога:

```text
meaningful stage
=
окремий method/service
+
можлива documented extension point
```

Не створювати десятки дрібних methods лише для візуально короткого `index()`.

---

# 38B. Fine-grained event rules

Не вішати нові MintCart extension points тільки на весь controller action, якщо extensions реально потребують втручання в окремий етап.

Приклади documented stage events:

```text
catalog.category.breadcrumbs.before
catalog.category.breadcrumbs.after

catalog.category.listing.before
catalog.category.listing.after

catalog.category.layout.before
catalog.category.layout.after
```

Правила:

1. event name стабільний і семантичний;
2. event відповідає реальному extension use case;
3. не створювати event на кожен приватний helper;
4. передавати контрольований context/data contract;
5. не змушувати extension мутувати весь controller object;
6. documented events є частиною extension API.

За потреби реалізувати lightweight stage helper, щоб не дублювати `before/after` boilerplate.

---

# 38C. Product listing reuse

Не дублювати формування списку товарів у:

```text
category
search
manufacturer
special
featured
latest
bestseller
related
homepage blocks
```

Створити reusable `ProductListingService` або еквівалентний сервіс.

Вхід:

```text
ProductListRequest / ListingContext
```

Наприклад:

```text
category_id
manufacturer_id
search
filter
sort
order
start
limit
```

Вихід:

```text
ProductListResult
```

Мінімум:

```text
products[]
total
pagination
sort
order
limit
```

---

# 38D. One ProductViewProvider

Усі listing contexts повинні використовувати один provider для presentation data товару.

Наприклад:

```text
MintCart\Commerce\ProductViewProvider
```

Він формує normalized product data для Theme API.

Не повторювати в controller:

```php
foreach ($results as $result) {
    $data['products'][] = [
        // duplicate image/price/special/rating logic
    ];
}
```

Category/Search/Manufacturer/Special/Blocks повинні отримувати однаковий normalized `ProductView`.

Це критична вимога Theme API:

```text
один product-card override
→ працює у всіх listing contexts
```

---

# 38E. HMVC principle without framework overengineering

Можна використовувати HMVC-подібний підхід до перевикористання page fragments/actions, але не вводити великий HMVC framework лише заради терміну.

Правило:

> Reuse by responsibility, not by copying controllers.

Виносити окремий reusable action/service, коли:

- логіка використовується у кількох controllers/blocks;
- вона має самостійну відповідальність;
- її корисно тестувати окремо;
- для неї потрібна стабільна extension point.

Не перетворювати кожен controller method на окремий class без реальної потреби.


# 39. Не дублювати OpenCart DB

Якщо OpenCart уже має поле/setting:

```text
store name
telephone
email
currency
language
address
payment config
shipping config
```

MintCart використовує стандартне OpenCart storage.

Не створювати:

```text
mc_store_name
mc_store_email
```

для тих самих даних.

Mint tables використовувати тільки для Mint-specific functionality.

---

# 40. Migration system

Створити MintCart Migration Manager до початку великої кількості нових таблиць.

Приклад:

```text
202608210001_create_mc_settings.php
202608210002_create_mc_pages.php
202608210003_create_mc_blocks.php
```

Вести таблицю:

```text
mc_migration
```

Кожна migration повинна виконуватися один раз.

---

# 41. Phase 0 — Audit

Перед змінами:

1. визначити точний OpenCart version;
2. зафіксувати existing libraries;
3. знайти всі OCMOD;
4. знайти modifications core files;
5. знайти custom modules;
6. знайти custom themes;
7. знайти PHP compatibility fixes;
8. перевірити DB engine/collation;
9. скласти карту залежностей.

Створити:

```text
docs/audit.md
```

Не починати масову переробку до завершення audit.

---

# 42. Phase 1 — Foundation

Реалізувати:

```text
MintCart PHP namespace
bootstrap/init
service container / service registry bridge
OpenCart adapter interfaces
migration manager
version information
basic logging
```

Не створювати новий framework.

Використовувати існуючий OpenCart lifecycle.

---

# 43. Phase 2 — Frontend foundation

Створити:

```text
package.json
esbuild config
assets/src
assets/dist
```

Додати та зафіксувати через npm:

```text
Bootstrap 5.3.8
Swiper 14.1.x
SortableJS 1.15.7
Bootstrap Icons 1.13.x
```

Підключити:

```text
catalog.js
admin.js
index.css
```

Налаштувати Bootstrap 5 як базовий layout/UI foundation для нового MintCart.

Створити CSS layers/files із розділу вище.

Ввести `mc-` namespace для MintCart-owned components.

Створити `bootstrap3-shim.css` тільки для реально потрібної сумісності з legacy OpenCart markup.

На цьому кроці ще не переробляти весь storefront.

---

# 44. Phase 3 — Design tokens

Перенести корисні рішення зі старого design-system, але:

```text
st-* → mc-*
```

Не переносити branding Livenza/Cartello.

Створити нейтральні MintCart default tokens.

Категорії:

```text
color
typography
spacing
layout
radius
border
shadow
motion
z-index
```

---

# 45. Phase 4 — Core UI components

Спочатку реалізувати:

```text
mc-button
mc-field
mc-select
mc-checkbox
mc-radio
mc-card
mc-badge
mc-price
mc-rating
mc-alert
mc-breadcrumb
mc-pagination
mc-quantity
mc-modal
mc-drawer
mc-toast
```

Для кожного component:

1. Twig contract;
2. CSS;
3. JS, якщо потрібен;
4. accessibility states;
5. documentation example.

---

# 46. Phase 5 — Layout conventions

Зафіксувати правила використання Bootstrap 5 layout system.

Основні primitives:

```text
.container / .container-*
.row
.col-*
.g-*
.d-flex
.grid / flex utilities
spacing utilities
responsive utilities
```

Не створювати власний паралельний grid framework.

`mc-*` класи використовувати для семантичних MintCart components і специфічних ecommerce layouts, а не як заміну Bootstrap utilities.

Перевірити, що responsive storefront layouts можна будувати комбінацією Bootstrap 5 + MintCart components без залежності від Bootstrap 3.

---

# 47. Phase 6 — Product Card

Product Card зробити першим складним reference component.

Підтримати:

```text
image
second image
name
price
special price
tax price
rating
badges
stock
wishlist
compare
add to cart
quick order
optional description
```

Не тягнути controller modifications із Cartello.

Якщо даних бракує — зробити нормальний Mint data provider/API.

---

# 48. Phase 7 — Header / Footer / Navigation

Створити standard component contracts:

```text
Header
Topbar
Logo
Search
Account
Cart
Navigation
Mobile Navigation

Footer
Footer Columns
Contacts
Social links
Payments
Newsletter
```

Theme може override components.

Business data надходить централізовано.

Не зберігати telephone/footer contacts окремо в кожній темі.

---

# 49. Phase 8 — Theme resolver

Реалізувати component fallback.

Перевірка:

створи тестову тему, яка override тільки:

```text
product-card.twig
```

Все інше має автоматично братися з Mint Default.

Якщо для цього потрібен OCMOD — архітектура неправильна.

---

# 50. Phase 9 — Presets

Створити мінімум два radically different presets на одному DOM.

Наприклад:

```text
Clean
Warm
```

Вони повинні відрізнятися:

```text
colors
fonts
radius
spacing
card look
button look
```

але не Twig.

Це validation design token system.

---

# 51. Phase 10 — Homepage Blocks

Створити Block API та:

```text
Hero
Products
Categories
Banner Grid
Benefits
Text/Image
Brands
Testimonials
FAQ
```

Після цього зробити drag&drop Homepage Editor.

---

# 52. Phase 11 — Mint Admin primitives

Створити reusable admin components:

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

Новий Mint Admin JS:

```text
vanilla JS
```

Не jQuery.

Legacy OpenCart admin продовжує працювати на своєму стеку.

---

# 53. Phase 12 — Entity Picker

Це пріоритетна UX-функція.

Єдиний picker для:

```text
products
categories
manufacturers
attributes
options
information pages
customers
```

Підтримати:

```text
search
filters
pagination
images
multi-select
keyboard navigation
selected state
```

Не використовувати старий autocomplete як основний UX.

---

# 54. Phase 13 — Setup Wizard

Реалізувати flow:

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

Wizard повинен:

```text
save progress
resume later
write to normal OpenCart settings
skip optional steps
```

Після завершення показувати Store Readiness.

---

# 55. Store Readiness

Створити service:

```php
MintCart\Onboarding\StoreReadiness
```

Він визначає:

```text
logo configured
contacts configured
currency configured
language configured
shipping available
payment available
first product exists
test order possible
```

UI:

```text
Your store is 72% ready
```

Це не hardcoded percentage — рахувати з checklist.

---

# 56. Phase 14 — Business Settings

Створити:

```text
Business → Contacts
Business → Delivery
Business → Payments
```

Не змушувати користувача шукати це через:

```text
Extensions
Modules
Settings
Layouts
```

У background використовувати стандартне OpenCart storage та extensions.

---

# 57. Phase 15 — Commerce API

Створити Mint facade для:

```text
Cart
Search
Checkout
Quick Order
```

Наприклад:

```text
MintCart\Commerce\CartService
MintCart\Commerce\SearchService
```

Theme взаємодіє з Mint APIs.

Не патчить OpenCart controllers.

---

# 58. Cart JS

Створити єдиний frontend Cart client.

API:

```javascript
add()
remove()
update()
refresh()
```

Після операції dispatch:

```javascript
new CustomEvent('mc:cart:updated')
```

Components:

```text
cart drawer
header cart
product card
product page
```

реагують на одну подію.

---

# 59. AJAX Search

Створити єдиний endpoint.

Повертає normalized data:

```json
{
  "items": []
}
```

Item:

```text
id
name
url
image
price
special
```

Theme лише рендерить результат.

---

# 60. Quick Order

Quick Order — Mint Commerce component.

Не theme feature.

Theme тільки показує button/component.

Business logic лишається у Commerce layer.

---

# 61. Checkout

Створити modern checkout поверх стандартних OpenCart:

```text
shipping extensions
payment extensions
totals
order model
```

Не винаходити другу payment/shipping ecosystem.

UI:

```text
Contacts
Address
Delivery
Payment
Review
```

Guest checkout — first-class path.

---

# 62. Phase 16 — Bulk Editor

Використати напрацювання Handy Product Manager як source of proven UX.

Не копіювати module architecture буквально.

Інтегрувати в:

```text
Store → Products
```

Підтримати:

```text
inline edit
multi select
mass status
price
special
quantity
manufacturer
category
stock
SEO
```

---

# 63. Long operations

Не вимагати background daemon.

Для shared hosting використовувати batch jobs:

```text
100 items
→ request
→ progress
→ next batch
```

Job повинен бути resumable.

---

# 64. Phase 17 — Categories drag & drop

Використати досвід SortMaster.

Через SortableJS.

При move category:

```text
transaction start
validate target
change parent
rebuild category_path subtree
update sort orders
commit
```

Заборонити cycle.

---

# 65. Phase 18 — SEO Core

MintCart базово повинен мати:

```text
SEO URL
canonical
hreflang
meta title
meta description
robots controls
XML sitemap
Schema.org Product
Schema.org Breadcrumb
Schema.org Organization
```

Не тягнути advanced SEO у MVP.

---

# 66. Database policy

Нові Mint tables:

```text
InnoDB
utf8mb4
```

Critical operations використовують transactions.

Особливо:

```text
order creation
stock update
category move
bulk operations
migrations
```

Не переписувати всю OpenCart database architecture одразу.

---

# 67. Performance

Спочатку:

```text
lazy loading
image dimensions
responsive images
WebP where available
asset minification
query optimization
cache invalidation API
```

Не робити Redis mandatory.

Не додавати Elasticsearch у MintCart 1.0.

---

# 68. Accessibility

Нові Mint components проектувати під:

```text
WCAG 2.2 AA
```

Мінімум:

```text
keyboard navigation
visible focus
labels
semantic buttons
ARIA where necessary
modal focus management
sufficient contrast
reduced motion
```

Drag&drop operation повинна мати не тільки mouse/touch interaction.

---

# 69. Update Manager

Після стабілізації Core створити Mint Update Manager.

Flow:

```text
check
compatibility
backup
download
checksum
maintenance mode
files
migrations
cache clear
health check
complete
```

Перед DB migration робити backup.

Не обіцяти automatic DB rollback, якщо він ненадійний.

---

# 70. Compatibility checker

Для OCMOD extensions аналізувати:

```text
install.xml
```

Показувати:

```text
files modified
controllers modified
templates modified
possible Mint conflicts
```

Не блокувати installation лише через heuristic warning.

---

# 71. Documentation

Документація є частиною продукту.

Структура:

```text
docs/
  user/
  developer/
  architecture/
```

User docs:

```text
install
setup wizard
products
delivery
payments
design
homepage
orders
```

Developer docs:

```text
Theme API
Component API
Blocks API
Slots
Events
Adapters
Compatibility
Migrations
```

---

# 72. Architecture Decision Records

Для важливих рішень створювати:

```text
docs/adr/
```

Наприклад:

```text
001-opencart-3-base.md
002-bootstrap-5-foundation.md
003-no-jquery-for-mint-code.md
004-component-theme-api.md
005-css-token-system.md
```

ADR короткий:

```text
Context
Decision
Consequences
```

---

# 73. Coding rule: reuse ideas, not legacy implementation

Існуючі проєкти:

```text
Cartello
Livenza
ST modules
Handy Product Manager
SortMaster
SEO URL Generator
ST Hacks
```

використовувати як:

```text
UX reference
tested algorithms
edge-case knowledge
data requirements
```

Не копіювати сліпо стару архітектуру.

Особливо не переносити великі OCMOD XML у MintCart.

---

# 74. При роботі з Cartello OCMOD

Для кожної modification постав питання:

```text
Навіщо theme потребує цю modification?
```

Перетворити відповідь на platform capability.

Наприклад:

```text
OCMOD modifies Product controller
because theme needs second image
```

Результат:

```text
Product Card contract gets secondary_image
```

Не:

```text
перенести OCMOD у MintCart
```

---

# 75. Upstream diff

Мінімізувати зміни безпосередньо у файлах OpenCart.

Вести:

```text
docs/upstream-diff.md
```

Для кожної core modification:

```text
file
reason
can it move to Mint layer?
```

Ціль — мати якомога менший permanent fork surface.

---

# 76. Naming

Назви повинні описувати домен, а не технологію.

Добре:

```text
Store
Products
Orders
Design
Pages
Delivery
Payments
Contacts
```

Погано для звичайного користувача:

```text
Extensions
Modules
Layouts
Positions
Modifications
```

Legacy screens можуть залишитися під:

```text
Advanced
```

---

# 77. Admin navigation

Цільова структура:

```text
Store
  Products
  Categories
  Orders
  Customers

Design
  Homepage
  Pages
  Header
  Footer
  Navigation
  Styles

Marketing
  Promotions
  Banners
  SEO

Business
  Payments
  Delivery
  Contacts

Settings

Advanced
  Extensions
  Layouts
  Modifications
  OpenCart Settings
```

Не видаляти Advanced functionality.

Просто прибрати її з основного user flow.

---

# 78. Testing

Для кожної великої фази додавати tests.

Мінімальні smoke scenarios:

```text
fresh install
admin login
wizard
create category
create product
open product page
add to cart
change quantity
checkout
create order
edit order
theme switch
extension installation
MintCart update
```

Frontend critical paths тестувати браузерними tests.

---

# 79. Definition of Done для компонента

Component не вважається завершеним, поки:

```text
[ ] має documented contract
[ ] використовує mc- BEM
[ ] JS не залежить від CSS classes
[ ] colors/spacing/fonts використовують tokens
[ ] keyboard usable
[ ] mobile checked
[ ] theme override checked
[ ] no OCMOD required
[ ] component не залежить від Bootstrap 3; Bootstrap 5 layout/utilities дозволені
```

---

# 80. Definition of Done для Theme API

Theme API 1.0 готовий, коли можна створити нову тему, яка:

```text
має власний tokens.css
має власний product-card.twig
має інший header
має інший footer
має інший homepage look
```

і для цього:

```text
0 controller modifications
0 core PHP modifications
0 OCMOD
```

---

# 81. Definition of Done для MintCart 1.0

MintCart 1.0 повинен дозволяти:

```text
install
↓
setup wizard
↓
choose design
↓
add product
↓
configure delivery
↓
configure payment
↓
make test order
```

без необхідності розуміти:

```text
Layouts
Positions
OCMOD
Theme controller overrides
extension compatibility internals
```

---

# 82. Не включати в MintCart 1.0

Не дозволяй scope creep.

Не є blocker для 1.0:

```text
AI assistant
AI descriptions
marketplace
multivendor
CRM
ERP
warehouse management
mobile application
React admin
headless API
GraphQL
Elasticsearch
Redis
RabbitMQ
visual email builder
100 page builder widgets
advanced reviews platform
loyalty platform
recommendation ML
```

---

# 83. Робочий процес

На початку створити:

```text
docs/IMPLEMENTATION_STATUS.md
```

З усіма фазами.

Формат:

```text
[x] Phase 0 — Audit
[x] Phase 1 — Foundation
[ ] Phase 2 — Frontend foundation
...
```

Після кожної фази:

1. онови status;
2. запусти відповідні tests;
3. перевір git diff;
4. видали accidental/debug code;
5. онови architecture docs;
6. переходь далі.

---

# 84. Не генерувати код без перевірки існуючого рішення

Перед зміною конкретної функціональності:

1. знайди існуючий OpenCart implementation;
2. знайди всі call sites;
3. знайди extensions/events, які від нього залежать;
4. визнач backward compatibility;
5. тільки після цього змінюй.

Ніколи не припускай структуру OpenCart з пам'яті, якщо repo можна перевірити.

---

# 85. Не вигадувати API наперед

Не створюй 30 interfaces «про запас».

API з'являється тоді, коли існує мінімум один реальний consumer.

Наприклад:

спочатку:

```text
Product Card
```

показує, що потрібен normalized ProductView.

Тоді створюємо його.

Не навпаки.

---

# 86. Простота важливіша за abstraction

Якщо є два рішення:

```text
A: 5 класів, 3 factories, interface, resolver, provider
B: один service з чіткою відповідальністю
```

і B не створює coupling — обирай B.

MintCart має бути зрозумілий PHP/OpenCart-розробнику.

---

# 87. Основний критерій кожного рішення

Перед кожною суттєвою зміною відповідай собі:

> Чи скорочує це шлях користувача до готового магазину?

А для Theme SDK:

> Чи дозволяє це дизайнеру змінити магазин без патчів OpenCart?

Якщо ні — задача, швидше за все, не є пріоритетною для MintCart 1.0.

---

# 88. Перші конкретні дії

Почни прямо зараз у такому порядку:

```text
1. Audit repository.
2. Створи docs/audit.md.
3. Створи docs/IMPLEMENTATION_STATUS.md.
4. Зафіксуй OpenCart upstream baseline.
5. Визнач current modified core files.
6. Визнач current OCMOD dependencies.
7. Створи MintCart PHP namespace/bootstrap.
8. Створи migration manager.
9. Створи frontend build pipeline.
10. Підключи Bootstrap 5.3.8 як frontend foundation.
11. Створи базову mc-* token system і Bootstrap 3 compatibility shim.
12. Зафіксуй правила Bootstrap layout/utilities + BEM components.
13. Реалізуй Button / Field / Alert.
14. Реалізуй Product Card як перший reference component.
15. Реалізуй ComponentRenderer + theme override.
16. Створи другий test theme/preset.
17. Перевір, що Product Card змінюється без OCMOD.
18. Тільки після цього переходь до Header/Footer/Homepage Blocks.
```

Не починай одразу з Checkout, SEO або Update Manager.

Спочатку доведи правильність фундаментальної архітектури Theme API та Design System.

---

# 89. Критична контрольна точка

Перший architecture milestone вважається успішним тільки якщо:

```text
Mint Default
```

і тестова друга тема використовують:

```text
ті самі PHP providers
ті самі Commerce services
той самий component contract
```

але можуть мати:

```text
інший product-card DOM
інший header DOM
інші tokens
інший visual style
```

без:

```text
OCMOD
controller patches
копіювання business logic
```

Якщо це не працює — не йди далі. Виправ Theme API.

---

# 90. Фінальний принцип

MintCart architecture повинна забезпечити три рівні:

```text
OpenCart
    ↓
MintCart platform APIs
    ↓
MintCart components / blocks
    ↓
Themes / presets
```

Dependency має йти вниз:

```text
Theme
→ MintCart API
→ OpenCart Adapter
→ OpenCart
```

Ніколи:

```text
Theme
→ випадковий OpenCart controller
→ OCMOD
→ core patch
```

Саме цей dependency direction є фундаментом MintCart.
