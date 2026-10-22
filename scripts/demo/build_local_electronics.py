#!/usr/bin/env python3
"""
LOCAL-ONLY demo: an electronics store with real product photos, to judge how
MintCart themes look with real catalogue data.

Not for the repository: the product photos in the source theme come from a
commercial shop, so they are copied into git-ignored folders
(upload/image/catalog/products/, upload/image/catalog/local/) and must never be
committed or shipped. The publishable demo is build_demo.py (home goods, free
photos).

Reads (SELECT only) a source database that has a real catalogue (default
`themes_nolio`, products with id >= 301) and writes a SQL file that REPLACES the
catalogue of the target database (like build_demo.py: orders and customers stay):

  categories, brands, products (names, prices, specials, gallery images,
  attributes), reviews, badges, and the Homepage Builder blocks of the home page
  (slider with the free Pexels hero photos of the source theme, benefits,
  categories, new arrivals, about, reviews, FAQ, CTA).

Usage:
    python scripts/demo/build_local_electronics.py <out.sql> [--source-db themes_nolio]
        [--source-dir C:/laragon/www/themes/nolio/nolio.localhost] [--target-db mintcart]
    mysql -uroot mintcart --default-character-set=utf8mb4 < <out.sql>

Take a database dump before applying it.
"""

import argparse
import json
import os
import shutil
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMAGE_ROOT = os.path.join(ROOT, 'upload', 'image')
MYSQL = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe'

FIRST_REAL_PRODUCT = 301

WIPE = [
    'oc_product_attribute', 'oc_product_description', 'oc_product_discount',
    'oc_product_filter', 'oc_product_image', 'oc_product_option_value',
    'oc_product_option', 'oc_product_recurring', 'oc_product_related',
    'oc_product_reward', 'oc_product_special', 'oc_product_to_category',
    'oc_product_to_download', 'oc_product_to_layout', 'oc_product_to_store',
    'oc_review', 'oc_cart', 'oc_customer_wishlist', 'oc_coupon_product',
    'oc_mc_product_badge', 'oc_mc_badge',
    'oc_product', 'oc_category_description', 'oc_category_path',
    'oc_category_to_store', 'oc_category_to_layout', 'oc_category_filter',
    'oc_coupon_category', 'oc_category', 'oc_manufacturer_to_store',
    'oc_manufacturer', 'oc_attribute_description', 'oc_attribute',
    'oc_attribute_group_description', 'oc_attribute_group',
]

# Free (Pexels) hero photos shipped in the source theme: file -> text of the slide.
SLIDES = [
    ('hero-4.jpg', 304, {'eyebrow': 'Smartphones', 'title': 'The phone you will <em>actually enjoy</em>', 'subtitle': 'Top models from Samsung, Xiaomi and more, ready to ship.', 'price': 'from $169', 'btn_text': 'Shop phones', 'btn2_text': 'Tablets'}, 305),
    ('hero-1.jpg', 310, {'eyebrow': 'Audio', 'title': 'Sound that <em>gets out of the way</em>', 'subtitle': 'Headphones and earbuds from Sony, Bose and JBL.', 'price': '$59', 'old_price': '$79', 'btn_text': 'Shop audio', 'btn2_text': 'Speakers'}, 311),
    ('hero-5.jpg', 307, {'eyebrow': 'Laptops', 'title': 'Work anywhere, <em>finish earlier</em>', 'subtitle': 'Light laptops for the office, the sofa and the road.', 'price': 'from $449', 'btn_text': 'Shop laptops', 'btn2_text': 'Monitors'}, 309),
]


def mysql(db, sql):
    out = subprocess.run([MYSQL, '-uroot', '--default-character-set=utf8mb4', '-N', '-B', db, '-e', sql],
                         capture_output=True, text=True, encoding='utf-8')
    if out.returncode:
        sys.exit(out.stderr)
    return [line.split('\t') for line in out.stdout.splitlines() if line]


def q(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def common_columns(src, dst, table):
    """Columns present in both databases, so a customized source schema still copies."""
    def cols(db):
        return [r[0] for r in mysql('information_schema', "SELECT COLUMN_NAME FROM COLUMNS WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s' ORDER BY ORDINAL_POSITION" % (db, table))]
    s, d = cols(src), set(cols(dst))
    return [c for c in s if c in d]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('out')
    ap.add_argument('--source-db', default='themes_nolio')
    ap.add_argument('--source-dir', default='C:/laragon/www/themes/nolio/nolio.localhost')
    ap.add_argument('--target-db', default='mintcart')
    a = ap.parse_args()

    S, T = a.source_db, a.target_db
    P = 'oc_'
    pid_filter = "product_id >= %d AND product_id <> 35" % FIRST_REAL_PRODUCT
    sql = ['SET NAMES utf8mb4;', 'START TRANSACTION;']

    for table in WIPE:
        sql.append('DELETE FROM %s;' % table)
    sql.append("DELETE FROM oc_seo_url WHERE query LIKE 'path=%' OR query LIKE 'product_id=%' OR query LIKE 'manufacturer_id=%' OR query LIKE 'category_id=%';")

    def copy(table, where, lang=False, extra_cols=''):
        cols = common_columns(S, T, P + table)
        col_list = ', '.join('`%s`' % c for c in cols)
        cond = where + (' AND language_id = 1' if lang else '')
        sql.append('INSERT INTO %s%s (%s) SELECT %s FROM %s.%s%s WHERE %s;' % (P, table, col_list, col_list, S, P, table, cond))

    cats = "category_id IN (SELECT category_id FROM %s.oc_product_to_category WHERE %s)" % (S, pid_filter)
    sql.append('-- catalogue copied from %s' % S)
    copy('manufacturer', "manufacturer_id IN (SELECT manufacturer_id FROM %s.oc_product WHERE %s)" % (S, pid_filter))
    sql.append('INSERT INTO oc_manufacturer_to_store (manufacturer_id, store_id) SELECT manufacturer_id, 0 FROM oc_manufacturer;')
    copy('category', cats + ' OR category_id IN (SELECT path_id FROM %s.oc_category_path WHERE %s)' % (S, cats))
    copy('category_description', "category_id IN (SELECT category_id FROM oc_category)", lang=True)
    sql.append('INSERT INTO oc_category_to_store (category_id, store_id) SELECT category_id, 0 FROM oc_category;')
    copy('category_path', "category_id IN (SELECT category_id FROM oc_category)")
    copy('product', pid_filter)
    copy('product_description', pid_filter, lang=True)
    copy('product_to_category', pid_filter)
    sql.append('INSERT INTO oc_product_to_store (product_id, store_id) SELECT product_id, 0 FROM oc_product;')
    copy('product_image', pid_filter)
    # the source specials have no dates (0000-00-00): make them run until 2099
    sql.append("INSERT INTO oc_product_special (product_id, customer_group_id, priority, price, date_start, date_end) SELECT product_id, customer_group_id, priority, price, '2020-01-01', '2099-12-31' FROM %s.oc_product_special WHERE %s;" % (S, pid_filter))
    copy('product_attribute', pid_filter, lang=True)
    copy('attribute', "attribute_id IN (SELECT attribute_id FROM oc_product_attribute)")
    copy('attribute_description', "attribute_id IN (SELECT attribute_id FROM oc_attribute)", lang=True)
    copy('attribute_group', "attribute_group_id IN (SELECT attribute_group_id FROM oc_attribute)")
    copy('attribute_group_description', "attribute_group_id IN (SELECT attribute_group_id FROM oc_attribute_group)", lang=True)
    # Stock must be on, and one picture per top category (its first product's photo).
    sql.append("UPDATE oc_product SET status = 1, date_available = '2020-01-01';")
    sql.append("UPDATE oc_category c SET c.image = (SELECT p.image FROM oc_product p JOIN oc_product_to_category x ON x.product_id = p.product_id WHERE x.category_id = c.category_id ORDER BY p.product_id LIMIT 1) WHERE c.parent_id = 0;")

    # SEO URLs of the copied entities
    for kind, table, idcol in (('product_id', 'oc_product', 'product_id'), ('category_id', 'oc_category', 'category_id'), ('manufacturer_id', 'oc_manufacturer', 'manufacturer_id')):
        sql.append("INSERT INTO oc_seo_url (store_id, language_id, `query`, keyword) SELECT 0, 1, s.`query`, MIN(s.keyword) FROM %s.oc_seo_url s WHERE s.language_id = 1 AND s.`query` REGEXP '^%s=[0-9]+$' AND CAST(SUBSTRING_INDEX(s.`query`, '=', -1) AS UNSIGNED) IN (SELECT %s FROM %s) GROUP BY s.`query`;" % (S, kind, idcol, table))

    # ---- images: copy the files this catalogue uses (git-ignored folders) ----
    paths = set()
    for r in mysql(S, "SELECT image FROM oc_product WHERE %s UNION SELECT image FROM oc_product_image WHERE %s" % (pid_filter, pid_filter)):
        if r[0]:
            paths.add(r[0])
    copied = 0
    for rel in sorted(paths):
        src = os.path.join(a.source_dir, 'image', rel)
        dst = os.path.join(IMAGE_ROOT, rel)
        if os.path.isfile(src):
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            shutil.copyfile(src, dst)
            copied += 1
    local = os.path.join(IMAGE_ROOT, 'catalog', 'local')
    os.makedirs(local, exist_ok=True)
    # OpenCart's resize() pads a picture of another ratio with white, so the slider
    # pictures are cropped to the 760x600 (about 4:3) the Slider block asks for.
    from PIL import Image, ImageOps
    for fn in {s[0] for s in SLIDES} | {'hero-9.jpg'}:
        im = Image.open(os.path.join(a.source_dir, 'image', 'catalog', 'nolio', 'home', fn)).convert('RGB')
        ImageOps.fit(im, (1520, 1200), Image.LANCZOS, centering=(0.5, 0.5)).save(os.path.join(local, fn), 'JPEG', quality=88)

    # ---- reviews, badges, featured module ----
    ids = [int(r[0]) for r in mysql(S, "SELECT product_id FROM oc_product WHERE %s ORDER BY product_id" % pid_filter)]
    names = {int(r[0]): r[1] for r in mysql(S, "SELECT product_id, name FROM oc_product_description WHERE language_id = 1 AND %s" % pid_filter)}
    reviews = [
        (301, 'Olena K.', 5, 'Battery lasts two days, the screen is bright and the camera is better than I expected. Arrived next morning.', 3),
        (330, 'Andrii M.', 5, 'Noise cancelling is superb on the train. Comfortable even after a few hours.', 6),
        (316, 'Marta S.', 4, 'Fast, quiet and light for the price. The keyboard feels good, the speakers are just OK.', 9),
        (326, 'Ivan P.', 5, 'Sharp picture, easy stand. Colours were fine out of the box.', 14),
        (338, 'Natalia D.', 5, 'Loud for its size and survived a trip to the beach. Battery is great.', 20),
        (310, 'Oleh B.', 4, 'Pad is light and the screen is great for reading. Wish it had more storage.', 27),
    ]
    for pid, author, rating, text, days in reviews:
        if pid in names:
            sql.append("INSERT INTO oc_review (product_id, customer_id, author, text, rating, status, date_added, date_modified) VALUES (%d, 0, %s, %s, %d, 1, DATE_SUB(NOW(), INTERVAL %d DAY), NOW());" % (pid, q(author), q(text), rating, days))
    for bid, label, color, pids in [(1, 'Bestseller', '#2457d6', [301, 330, 316]), (2, 'New', '', [305, 320, 333]), (3, 'Hit', '#d92d20', [326, 338])]:
        sql.append("INSERT INTO oc_mc_badge (badge_id, label, color, sort_order, date_added) VALUES (%d, %s, %s, %d, NOW());" % (bid, q(label), q(color), bid))
        for pid in pids:
            if pid in names:
                sql.append("INSERT INTO oc_mc_product_badge (product_id, badge_id) VALUES (%d, %d);" % (pid, bid))
    featured = [i for i in (301, 330, 316, 326) if i in names]
    sql.append("UPDATE oc_module SET setting = %s WHERE module_id = 28;" % q(json.dumps({'name': 'Home Page', 'product': [str(i) for i in featured], 'limit': '4', 'width': '600', 'height': '600', 'status': '1'})))

    # ---- brand carousel (text logos, same generator as the publishable demo) ----
    sys.path.insert(0, os.path.dirname(__file__))
    import build_demo
    sql.append('DELETE FROM oc_banner_image WHERE banner_id = 8;')
    brands = mysql(S, "SELECT manufacturer_id, name FROM oc_manufacturer WHERE manufacturer_id IN (SELECT manufacturer_id FROM oc_product WHERE %s) ORDER BY name" % pid_filter)
    for i, (bid, bname) in enumerate(brands):
        fn = 'brand%s.jpg' % bid
        build_demo.render_brand(os.path.join(local, fn), bname)
        sql.append("INSERT INTO oc_banner_image (banner_id, language_id, title, link, image, sort_order) VALUES (8, 1, %s, %s, %s, %d);" % (q(bname), q('index.php?route=product/manufacturer/info&manufacturer_id=%s' % bid), q('catalog/local/' + fn), i))

    # ---- Homepage Builder blocks ----
    sql.append("INSERT INTO oc_mc_page (code, status, date_added, date_modified) VALUES ('home', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = 1;")
    sql.append("SET @home_page = (SELECT page_id FROM oc_mc_page WHERE code = 'home');")
    sql.append("DELETE d FROM oc_mc_block_description d JOIN oc_mc_block b ON b.block_id = d.block_id WHERE b.page_id = @home_page;")
    sql.append("DELETE FROM oc_mc_block WHERE page_id = @home_page;")

    def block(order, btype, settings, content):
        sql.append("INSERT INTO oc_mc_block (page_id, type, sort_order, status, settings_json, date_added, date_modified) VALUES (@home_page, %s, %d, 1, %s, NOW(), NOW());" % (q(btype), order, q(json.dumps(settings, ensure_ascii=False))))
        sql.append("INSERT INTO oc_mc_block_description (block_id, language_id, content_json) VALUES (LAST_INSERT_ID(), 1, %s);" % q(json.dumps(content, ensure_ascii=False)))

    block(5, 'slider',
          {'layout': 'split', 'autoplay': 6000, 'items': [
              {'image': 'catalog/local/' + fn, 'link': 'index.php?route=product/category&path=%d_%d' % (301 if cid in (304, 305) else 303 if cid in (310, 311) else 302, cid),
               'btn2_link': 'index.php?route=product/category&path=%d_%d' % (301 if c2 in (304, 305) else 303 if c2 in (310, 311) else 302, c2), 'color': 'navy'}
              for fn, cid, _t, c2 in SLIDES]},
          {'items': [t for _f, _c, t, _c2 in SLIDES]})
    block(10, 'benefits',
          {'items': [{'icon': 'truck'}, {'icon': 'arrow-repeat'}, {'icon': 'shield-check'}, {'icon': 'headset'}]},
          {'title': '', 'items': [
              {'title': 'Free delivery', 'description': 'On orders over $100'},
              {'title': '30-day returns', 'description': 'Changed your mind? Easy'},
              {'title': '2-year warranty', 'description': 'Official warranty on every device'},
              {'title': 'Friendly support', 'description': 'We reply within a day'}]})
    block(20, 'categories', {'parent_id': 0, 'limit': 8}, {'title': 'Shop by category'})
    block(30, 'products', {'limit': 8, 'sort': 'p.date_added', 'order': 'DESC'}, {'title': 'New arrivals'})
    block(40, 'text-image', {'image': 'catalog/local/hero-9.jpg', 'image_width': 1100, 'image_height': 868, 'image_position': 'left'},
          {'title': 'Tech that earns its place on your desk', 'body': '<p>We keep a small, honest range of phones, laptops and audio from brands we trust, with official warranty and clear specifications - no filler.</p><p>Every order ships in recyclable packaging, and you can return it within 30 days.</p>', 'image_alt': 'A laptop on a desk'})
    block(50, 'testimonials', {'source': 'reviews', 'limit': 3}, {'title': 'What customers say'})
    block(70, 'faq', {}, {'title': 'Questions and answers', 'items': [
        {'question': 'How long does delivery take?', 'answer': 'Usually 1-3 working days within the country. You get a tracking number by email.'},
        {'question': 'Is there an official warranty?', 'answer': 'Yes, every device comes with the manufacturer warranty, 24 months for most products.'},
        {'question': 'Which payment methods do you accept?', 'answer': 'Bank cards, bank transfer and cash on delivery.'}]})
    block(80, 'cta', {}, {'title': 'Not sure what to choose?', 'text': 'Tell us what you need it for and we will suggest a few good options.', 'cta_label': 'Contact us', 'cta_url': 'index.php?route=information/contact'})
    sql.append("DELETE FROM oc_layout_module WHERE code = 'slideshow.27' AND layout_id = 1;")
    sql.append("UPDATE oc_layout_module SET position = 'content_bottom' WHERE code IN ('featured.28', 'carousel.29') AND layout_id = 1;")

    sql.append('COMMIT;')

    with open(a.out, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(sql) + '\n')

    print('products:', len(ids), 'images copied:', copied, 'sql statements:', len(sql))


if __name__ == '__main__':
    main()
