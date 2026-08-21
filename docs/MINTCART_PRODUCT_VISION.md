# MintCart — Product Vision

## 1. Що таке MintCart

**MintCart — це не ще одна CMS для створення інтернет-магазину.**

MintCart — це коротший шлях від ідеї **«хочу продавати онлайн»** до магазину, який уже готовий приймати замовлення.

Продуктова формула:

> **OpenCart, який не треба спочатку перетворювати на магазин.**

MintCart бере перевірену ecommerce-основу OpenCart і змінює досвід її використання: менше технічних рішень, менше пошуку модулів, менше ручного стикування, менше знань про внутрішню архітектуру CMS.

---

## 2. Аналогія з Linux Mint

Внутрішня логіка бренду:

```text
Linux Mint : Linux = MintCart : OpenCart
```

Не:

> OpenCart поганий, тому ми його переписали.

А:

> OpenCart — зріла й гнучка ecommerce-база.  
> MintCart робить її дружнішою для сучасного повсякденного використання.

Технічна сила OpenCart повинна зберігатися.

---

## 3. Центральна продуктова обіцянка

### Швидший шлях до першого замовлення

Користувач не повинен проходити технічний квест:

```text
встановити CMS
→ знайти тему
→ знайти checkout
→ знайти пошук
→ знайти SEO
→ розібратися з Layouts
→ узгодити сумісність модулів
→ виправити тему
→ налаштувати магазин
```

Цільовий шлях MintCart:

```text
встановити
→ пройти Setup Wizard
→ додати товар
→ обрати вигляд
→ підключити доставку
→ підключити оплату
→ зробити тестове замовлення
```

North Star Metric:

```text
Time to First Order
```

У першу чергу — Time to First Test Order.

---

## 4. Основна проблема OpenCart, яку вирішує MintCart

Проблема не просто в тому, що OpenCart має старі UI-патерни.

Головна проблема — **когнітивна й операційна вартість використання**.

OpenCart простий для людини, яка вже знає OpenCart.

MintCart повинен бути зрозумілим ще до того, як користувач вивчив систему.

Типові бар'єри:

- немає повноцінного Setup Wizard;
- користувачеві треба знати, де саме лежить кожне налаштування;
- повсякденні дії розкидані по різних розділах;
- Layouts / Positions / Extensions відображають архітектуру CMS, а не мову бізнесу;
- слабкі drag-and-drop сценарії;
- незручний вибір товарів, категорій, атрибутів та інших сутностей;
- слабке масове редагування;
- новий дизайн часто вимагає OCMOD/controller patches;
- сучасний магазин часто потрібно збирати з набору модулів;
- оновлення можуть бути ризикованими;
- базові SEO/UX речі потребують додаткових розширень.

---

## 5. Головний продуктовий принцип

> **MintCart прибирає технічні рішення, які користувач не повинен приймати, і залишає рішення, важливі для його бізнесу.**

Користувач повинен вирішувати:

- який логотип;
- яка доставка;
- яка оплата;
- які товари;
- який стиль бренду;
- які акції;
- як побудована головна сторінка.

Користувач не повинен вирішувати:

- який checkout сумісний із темою;
- який slider потрібен;
- куди вставляється module position;
- чому не застосувався OCMOD;
- де в OpenCart редагується конкретний телефон;
- чому URL системної сторінки налаштовується не там, де очікується;
- який модуль потрібен для базової Schema.org.

---

## 6. П'ять продуктових стовпів

### 6.1 Ready to Start

Не налаштовуй CMS — запускай магазин.

Ключові елементи:

- Setup Wizard;
- централізовані Business Settings;
- Store Readiness;
- готові значення для системних сторінок;
- зрозумілий перший запуск;
- перший товар як частина onboarding.

---

### 6.2 Ready to Sell

Все необхідне для типового малого магазину вже працює разом.

Базовий commerce experience:

- сучасний checkout;
- Quick Order;
- AJAX Search;
- нормальний cart UX;
- wishlist;
- viewed products;
- product badges;
- інформація про доставку/оплату/повернення;
- нормальні stock states;
- базове SEO;
- Schema.org;
- нормальні product cards;
- cookie consent.

Це не «37 модулів у комплекті».

Це:

> **Базові ecommerce-сценарії вже узгоджені між собою.**

---

### 6.3 Ready to Look Good

Користувач не повинен починати запуск магазину з купівлі шаблону.

Потрібні:

- Mint Design System;
- якісний Mint Default;
- design presets;
- глобальні кольори;
- типографіка;
- radius/borders/shadows;
- product card variants;
- header/footer variants;
- product gallery variants;
- Theme API.

---

### 6.4 Design Friendly

Друга важлива аудиторія — дизайнер, верстальник, frontend-розробник, невелика студія.

Принцип:

> **Створюйте дизайн магазину, а не переписуйте OpenCart.**

Технічна вимога:

```text
Новий MintCart theme → 0 OCMOD для дизайну
```

Theme повинен змінювати вигляд через офіційний Theme API, component overrides, blocks і design tokens.

---

### 6.5 OpenCart Inside

MintCart не приховує походження і не відрізає екосистему.

Продукт повинен зберігати:

- PHP/Twig/MySQL стек;
- сторонні OpenCart extensions;
- payment/shipping ecosystem;
- можливість доопрацювання;
- можливість переносу на інший хостинг;
- контроль над даними;
- сумісність із типовими OpenCart-підходами там, де це не шкодить UX.

Формула:

> **Простота MintCart. Екосистема OpenCart.**

---

## 7. Основні аудиторії

### A. Власник малого бізнесу

Він думає:

> Мені потрібен магазин.

Не:

> Мені потрібна CMS.

Цінність:

- швидше запуститися;
- менше технічних рішень;
- не збирати систему по частинах;
- менше залежності від розробника для дрібних змін;
- свій магазин без SaaS lock-in.

---

### B. Фрилансер / дизайнер / маленька студія

Його задача:

> швидко зробити клієнту нормальний магазин і заробити.

Цінність:

- готовий commerce layer;
- готові ecommerce components;
- Theme API;
- blocks;
- design tokens;
- predictable architecture;
- менше OCMOD;
- менше повторної збірки OpenCart на кожному проєкті.

---

## 8. Адмінка повинна говорити мовою бізнесу

Основна навігація:

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

Повсякденний користувач не повинен починати зі структури CMS.

---

## 9. Page Builder

MintCart не повинен перетворюватися на Elementor/Webflow.

Для типового магазину достатньо обмеженого набору якісних ecommerce blocks:

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

Початковий scope — Homepage Builder.

---

## 10. Що не є метою MintCart 1.0

Не потрібно доводити «сучасність» кількістю технологій або функцій.

Не є blocker для 1.0:

- AI assistant;
- AI descriptions;
- marketplace;
- multivendor;
- CRM;
- ERP;
- warehouse platform;
- mobile application;
- React/Vue admin;
- headless;
- GraphQL;
- Elasticsearch;
- Redis requirement;
- RabbitMQ;
- visual email builder;
- 100 page-builder widgets;
- ML recommendations.

---

## 11. Метрики

Основні:

```text
Install → Wizard completed
Wizard → First product
First product → Test order
Install → Store ready
Store ready → First real order
```

Для Theme SDK:

```text
час створення нового дизайну
кількість core overrides
кількість OCMOD modifications
```

Ціль для нового MintCart theme:

```text
0 OCMOD
0 controller patches
0 core PHP modifications
```

---

## 12. Продуктова перевірка кожного рішення

Перед реалізацією функції поставити питання:

> Чи скорочує це шлях користувача до готового магазину?

Для Theme SDK:

> Чи дозволяє це дизайнеру змінити вигляд без патчів OpenCart?

Для сумісності:

> Чи зберігає це практичну цінність OpenCart ecosystem?

Якщо відповідь «ні», функція не повинна автоматично потрапляти в MintCart 1.0.

---

## 13. Коротка формула MintCart

**Проблема:** OpenCart дешевий, гнучкий і перевірений, але його переваги відкриваються після того, як користувач навчиться ним користуватися.

**Ідея:** MintCart робить OpenCart дружнім із першого запуску.

**Продукт:** готове ecommerce-середовище, а не порожній двигун + каталог розширень.

**Обіцянка:** швидший шлях до першого замовлення.

**Для бізнесу:** магазин без технічного квесту.

**Для дизайнерів:** дизайн без переписування CMS.

**Для розробників:** знайома OpenCart-основа й сумісна екосистема.

**Філософія:** простота без урізання можливостей.

> **MintCart — OpenCart, designed for people.**
