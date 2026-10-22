#!/usr/bin/env python3
"""
MintCart demo content: a small home-goods store with real photographs.

All photos are from Pexels (Pexels License: free for commercial use, attribution
not required; the ids are listed in upload/image/catalog/demo/shop/CREDITS.txt).
The script downloads them (cached in scripts/demo/.photo-cache/, git-ignored),
crops them to square product pictures and writes a SQL file that REPLACES the
stock OpenCart demo catalog with:

  8 root categories with subcategories (one third-level), 48 products with a
  photo each (and a detail crop as the second gallery picture), 5 brands, specials,
  specifications, 6 customer reviews, product badges, a brand carousel, and the
  Homepage Builder blocks of the home page (slider, benefits, categories, new
  arrivals, about, reviews, FAQ, CTA).

Usage:
    python scripts/demo/build_demo.py <out.sql>
    mysql -uroot <database> --default-character-set=utf8mb4 < <out.sql>

The SQL deletes the existing catalog rows first (orders and customers are left
alone). Take a database dump before applying it. Product ids 100+, category ids
100+, brand ids 20+, so the content is easy to recognise and remove again.
Replace the photos with your own before launch.
"""

import html
import json
import os
import sys
import urllib.request

from PIL import Image, ImageDraw, ImageFont, ImageOps

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMG_DIR = os.path.join(ROOT, 'upload', 'image', 'catalog', 'demo', 'shop')
IMG_PREFIX = 'catalog/demo/shop/'
CACHE = os.path.join(os.path.dirname(__file__), '.photo-cache')
S = 2  # supersampling for the brand wordmarks


def hex_rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def font(size, bold=False):
    for name in (['seguisb.ttf', 'segoeuib.ttf'] if bold else ['segoeui.ttf']):
        try:
            return ImageFont.truetype(os.path.join('C:/Windows/Fonts', name), size)
        except OSError:
            continue
    return ImageFont.load_default()


class Canvas:
    """A supersampled drawing surface (used for the brand wordmarks)."""

    def __init__(self, w, h, bg):
        self.w, self.h = w * S, h * S
        self.img = Image.new('RGB', (self.w, self.h), bg)
        self.d = ImageDraw.Draw(self.img, 'RGBA')

    def result(self, size):
        return self.img.resize(size, Image.LANCZOS)


def render_brand(path, name):
    c = Canvas(260, 160, (255, 255, 255))
    f = font(44 * S, True)
    w = c.d.textlength(name, font=f)
    c.d.text(((260 * S - w) / 2, 50 * S), name, font=f, fill=hex_rgb('#8a8f97'))
    c.result((260, 160)).save(path, 'JPEG', quality=90)


def q(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def esc(text):
    return html.escape(text, quote=True)


def slug(text):
    return ''.join(ch.lower() if ch.isalnum() else '-' for ch in text).strip('-').replace('--', '-')


# --------------------------------------------------------------------------
# Data
# --------------------------------------------------------------------------

# (id, name, slug, parent id or None, blurb)
CATEGORIES = [
    (100, 'Mugs & Cups', 'mugs-cups', None, 'Everyday stoneware and porcelain for coffee, tea and slow mornings.'),
    (108, 'Coffee Mugs', 'coffee-mugs', 100, 'Big, comfortable mugs for the first cup of the day.'),
    (120, 'Cups & Saucers', 'cups-saucers', 100, 'Small cups for espresso and tea, with matching saucers.'),
    (101, 'Vases', 'vases', None, 'Simple hand-finished vases for a single stem or a full bunch.'),
    (109, 'Ceramic Vases', 'ceramic-vases', 101, 'Sculptural ceramics with a matte glaze.'),
    (121, 'Glass Vases', 'glass-vases', 101, 'Clear glass that lets the stems show.'),
    (102, 'Lighting', 'lighting', None, 'Warm, quiet light for desks, shelves and bedside tables.'),
    (110, 'Table Lamps', 'table-lamps', 102, 'Lamps for side tables and shelves.'),
    (111, 'Desk Lamps', 'desk-lamps', 110, 'Compact lamps that stay out of the way of your work.'),
    (112, 'Floor Lamps', 'floor-lamps', 102, 'Tall lamps that light up a corner.'),
    (122, 'Pendant Lights', 'pendant-lights', 102, 'Hanging lights for the table, the hall and the kitchen.'),
    (103, 'Furniture', 'furniture', None, 'Small pieces of furniture made to last and easy to move.'),
    (113, 'Chairs', 'chairs', 103, 'Chairs for the kitchen, the hall and the living room.'),
    (115, 'Tables, Stools & Benches', 'tables-stools-benches', 103, 'Small surfaces and extra seats.'),
    (104, 'Bags', 'bags', None, 'Sturdy everyday bags with room for a laptop and a lunch.'),
    (116, 'Tote Bags', 'tote-bags', 104, 'Totes for shopping and the daily commute.'),
    (123, 'Backpacks', 'backpacks', 104, 'Comfortable backpacks for work and weekends.'),
    (105, 'Stationery', 'stationery', None, 'Notebooks that lie flat and paper that takes ink well.'),
    (117, 'Notebooks & Planners', 'notebooks-planners', 105, 'Lay-flat notebooks and planners.'),
    (106, 'Textiles', 'textiles', None, 'Soft cushions, throws and towels in natural fibres.'),
    (118, 'Cushions', 'cushions', 106, 'Cushion covers with a hidden zip.'),
    (124, 'Throws & Blankets', 'throws-blankets', 106, 'Knitted and woven layers for the sofa and the bed.'),
    (125, 'Towels', 'towels', 106, 'Soft cotton towels that dry fast.'),
    (107, 'Candles', 'candles', None, 'Slow-burning soy candles in reusable glass.'),
    (119, 'Soy Candles', 'soy-candles', 107, 'Hand-poured, in jars you can reuse.'),
]

BRANDS = [(20, 'Nordhem'), (21, 'Oak & Co'), (22, 'Lumen'), (23, 'Kiln'), (24, 'Paperleaf')]

# (name, leaf category, brand id, price, special price or None, Pexels photo id, blurb, (material, size, care))
PRODUCTS = [
    ('Stoneware Mug - White', 108, 23, 18, None, 11075707, 'A 350 ml mug with a thick, comfortable handle. Dishwasher and microwave safe.', ('Stoneware', '350 ml', 'Dishwasher safe')),
    ('Grey Stoneware Mug', 108, 23, 18, None, 606542, 'A calm grey glaze with a soft matte finish and a wide, easy-to-hold handle.', ('Stoneware', '330 ml', 'Dishwasher safe')),
    ('Breakfast Mug', 108, 23, 16, 12, 4065894, 'A tall mug that fits a full filter coffee and sits well on a crowded desk.', ('Ceramic', '400 ml', 'Dishwasher safe')),
    ('Marbled Ceramic Cup', 108, 23, 24, None, 7674564, 'Hand-glazed in a marbled blue and cream. Every cup is slightly different.', ('Ceramic', '300 ml', 'Hand wash')),
    ('Espresso Cup & Saucer', 120, 23, 14, None, 685527, 'A thick-walled espresso cup that keeps the crema warm, with a matching saucer.', ('Porcelain', '80 ml', 'Dishwasher safe')),
    ('Porcelain Cup & Saucer', 120, 23, 21, None, 31391514, 'A wide, shallow cup for tea or a flat white, finished with a clear glaze.', ('Porcelain', '180 ml', 'Dishwasher safe')),
    ('Donut Vase', 109, 23, 42, None, 4207892, 'A ring-shaped vase in matte white. Holds a few stems of dried grass or a single branch.', ('Ceramic', '22 cm high', 'Wipe clean')),
    ('Ribbed Bud Vase', 109, 23, 29, None, 6794691, 'A small ribbed vase for a single stem. Glazed inside, so it holds water.', ('Ceramic', '16 cm high', 'Wipe clean')),
    ('Tall White Vase', 109, 23, 38, None, 10006407, 'A slim 28 cm vase with a narrow neck, good for long stems and branches.', ('Ceramic', '28 cm high', 'Wipe clean')),
    ('Terracotta Vase Set', 109, 23, 58, 49, 6801449, 'Two hand-finished vases in clay and sand. Sold as a pair.', ('Terracotta', '18 and 24 cm', 'Wipe clean')),
    ('Sculpted Vase', 109, 23, 64, None, 10295066, 'A flowing, sculptural form in soft blush. A piece you can leave empty.', ('Ceramic', '30 cm high', 'Wipe clean')),
    ('Glass Bud Vase', 121, 23, 19, None, 7815537, 'A small round-bottomed vase for gypsophila and single stems.', ('Glass', '12 cm high', 'Hand wash')),
    ('Round Glass Vase', 121, 23, 26, None, 8582271, 'A wide bowl vase that suits daisies, peonies and short bunches.', ('Glass', '16 cm high', 'Hand wash')),
    ('Glass Jug Vase', 121, 23, 24, None, 15489813, 'A glass jug with a handle, equally good for water and for flowers.', ('Glass', '20 cm high', 'Dishwasher safe')),
    ('Desk Lamp', 111, 22, 64, None, 34636335, 'A compact adjustable lamp with a wooden arm and a dimmable warm-white bulb.', ('Steel and oak', '45 cm high', 'Wipe clean')),
    ('Globe Table Lamp', 110, 22, 79, None, 6825324, 'A frosted glass globe on a polished base. Soft, even light for shelves and consoles.', ('Glass and steel', '22 cm high', 'Wipe clean')),
    ('Mushroom Table Lamp', 110, 22, 89, 69, 34237532, 'A rounded dome lamp that casts a warm pool of light on the table.', ('Steel and glass', '38 cm high', 'Wipe clean')),
    ('Arc Floor Lamp', 112, 22, 119, None, 112811, 'A 190 cm arc lamp with a weighted base and a pull-chain switch.', ('Steel', '190 cm high', 'Wipe clean')),
    ('Glass Pendant Light', 122, 22, 72, None, 2123435, 'A scalloped glass shade on a braided cord with a brass fitting.', ('Glass and brass', '25 cm wide', 'Wipe clean')),
    ('Globe Pendant', 122, 22, 56, None, 5716776, 'A simple white globe on a thin cord. Needs an E27 bulb (not included).', ('Opal glass', '30 cm wide', 'Wipe clean')),
    ('Cage Pendant Lights', 122, 22, 48, None, 973505, 'A geometric wire cage that shows off a filament bulb. Sold as a pair.', ('Black steel', '20 cm wide', 'Wipe clean')),
    ('Wishbone Chair', 113, 21, 149, 119, 7303151, 'Solid oak frame with a woven paper-cord seat. Light enough to move with one hand.', ('Oak and paper cord', '55 x 52 x 76 cm', 'Wipe clean')),
    ('Rattan Lounge Chair', 113, 21, 229, None, 6800945, 'A low lounge chair with a woven rattan seat and a solid wood frame.', ('Rattan and teak', '60 x 70 x 70 cm', 'Dust, wipe clean')),
    ('Round Side Table', 115, 21, 79, None, 9774369, 'A 55 cm table with a smooth top and three splayed birch legs.', ('Birch', '55 cm wide', 'Wipe clean')),
    ('Walnut Stool', 115, 21, 59, None, 4219038, 'A solid walnut stool with a round seat. Works as a side table too.', ('Walnut', '45 cm high', 'Wipe clean')),
    ('Reclaimed Wood Bench', 115, 21, 139, None, 11112743, 'A short bench made from reclaimed timber. Each one has its own marks and colours.', ('Reclaimed wood', '70 x 28 x 40 cm', 'Dust, wipe clean')),
    ('Leather Tote', 116, 20, 98, None, 8148587, 'A soft vegetable-tanned leather tote with room for a laptop and a lunch.', ('Leather', '38 x 32 x 14 cm', 'Leather balm')),
    ('Canvas Tote - Black', 116, 20, 24, None, 4068320, 'Heavy 14 oz canvas with an inside pocket and reinforced handles.', ('Cotton canvas', '38 x 42 cm', 'Machine wash 30 C')),
    ('Printed Tote', 116, 20, 22, None, 3185223, 'A natural cotton tote with a hand-drawn print. Folds flat into a pocket.', ('Cotton', '36 x 40 cm', 'Machine wash 30 C')),
    ('Cotton Weekend Bag', 116, 20, 54, None, 2081332, 'A roomy cotton bag with a drawstring top, big enough for a weekend away.', ('Cotton', '45 x 35 x 20 cm', 'Machine wash 30 C')),
    ('Everyday Backpack', 123, 20, 68, None, 3731256, 'A 20 litre backpack with a padded laptop sleeve and a water-resistant finish.', ('Recycled polyester', '20 litres', 'Wipe clean')),
    ('Lay-Flat Notebook', 117, 24, 14, None, 5797900, 'A5, 160 pages of 100 gsm paper that is smooth, ink-friendly and acid-free.', ('Paper', 'A5', 'Keep dry')),
    ('Spiral Notebook', 117, 24, 9, None, 733852, 'A kraft-cover spiral notebook with 120 ruled pages.', ('Paper', 'A5', 'Keep dry')),
    ('Notebook Set', 117, 24, 24, 19, 3944416, 'Three notebooks in natural cover tones, plain, ruled and dotted.', ('Paper', 'A5, set of 3', 'Keep dry')),
    ('Leather Planner', 117, 24, 39, None, 17794493, 'A refillable week-to-view planner with a leather cover and an elastic closure.', ('Leather and paper', 'A5', 'Leather balm')),
    ('Desk Stationery Set', 117, 24, 32, None, 7718788, 'A notebook, a pen, a pencil and a sticky-note pad in one tidy set.', ('Paper and steel', 'Gift set', 'Keep dry')),
    ('Fringed Linen Cushion', 118, 20, 29, None, 4577539, 'Stonewashed linen cover with a hand-tied fringe and a hidden zip. Insert included.', ('Linen', '45 x 45 cm', 'Machine wash 30 C')),
    ('Printed Cushion Cover', 118, 20, 22, None, 1248583, 'A cotton cover with a botanical print. Machine washable.', ('Cotton', '50 x 50 cm', 'Machine wash 30 C')),
    ('Knitted Throw - Teal', 124, 20, 59, None, 10373508, 'A chunky knit throw in deep teal, warm enough for the sofa in winter.', ('Acrylic and cotton', '130 x 170 cm', 'Machine wash 30 C')),
    ('Wool Blankets', 124, 20, 89, None, 1937336, 'Soft folded blankets in cream, rose and mustard. Sold individually.', ('Wool blend', '140 x 200 cm', 'Dry clean')),
    ('Cotton Bath Towels', 125, 20, 34, None, 4210372, 'A set of two thick cotton towels in sand. They get softer with every wash.', ('Cotton', '70 x 140 cm, set of 2', 'Machine wash 40 C')),
    ('White Terry Towels', 125, 20, 28, None, 7691101, 'A stack of three terry towels: bath, hand and face.', ('Cotton terry', 'Set of 3', 'Machine wash 40 C')),
    ('Soy Candle - Vanilla', 119, 22, 22, None, 6755744, 'Hand-poured soy wax with a wooden wick, in a glass jar you can reuse. About 45 hours.', ('Soy wax', '180 g', 'Trim the wick to 5 mm')),
    ('Amber Glass Candle', 119, 22, 26, None, 6311848, 'Soy wax in a green glass tumbler with a cedar-wood scent and a blank label.', ('Soy wax', '220 g', 'Trim the wick to 5 mm')),
    ('Soy Candle Jar', 119, 22, 19, None, 374681, 'A clean, unscented candle in a jar with a lid. Burns for about 40 hours.', ('Soy wax', '200 g', 'Trim the wick to 5 mm')),
    ('Candle Trio', 119, 22, 44, 36, 7407315, 'Three candles in glass on a marble tray: a small gift set.', ('Soy wax', '3 x 90 g', 'Trim the wick to 5 mm')),
    ('Lavender Candle', 119, 22, 21, None, 5816995, 'A calming lavender candle with dried flowers set in the wax.', ('Soy wax', '150 g', 'Trim the wick to 5 mm')),
    ('Travel Candle Tin', 119, 22, 15, None, 4502459, 'A small candle in a dark tin with a lid, made to travel in a bag.', ('Soy wax', '80 g', 'Trim the wick to 5 mm')),
]

# Pictures of the home page slider (Pexels id, how the slide links): a wide scene each.
SLIDE_PHOTOS = [7303151, 34636335, 6755744]
ABOUT_PHOTO = 3097112

BADGES = [(1, 'Bestseller', '#2457d6', ['Stoneware Mug - White', 'Wishbone Chair', 'Soy Candle - Vanilla']),
          (2, 'New', '', ['Mushroom Table Lamp', 'Rattan Lounge Chair', 'Sculpted Vase']),
          (3, 'Hit', '#d92d20', ['Lay-Flat Notebook', 'Fringed Linen Cushion'])]

REVIEWS = [
    ('Stoneware Mug - White', 'Olena K.', 5, 'The mug feels even better than it looks. Arrived well packed and two days early.', 3),
    ('Wishbone Chair', 'Andrii M.', 5, 'Bought two for our kitchen - sturdy, light and assembled in minutes.', 6),
    ('Desk Lamp', 'Marta S.', 4, 'Great lamp. Support answered my question about bulbs the same afternoon.', 9),
    ('Donut Vase', 'Ivan P.', 5, 'Heavier than expected in a good way, and the white is exactly as in the photo.', 14),
    ('Fringed Linen Cushion', 'Natalia D.', 5, 'Soft linen, the fringe is lovely. Washed twice, no shrinking.', 20),
    ('Soy Candle - Vanilla', 'Oleh B.', 4, 'Burns evenly and smells good without being heavy. The jar is nice enough to reuse.', 27),
]

FEATURED_NAMES = ['Stoneware Mug - White', 'Donut Vase', 'Wishbone Chair', 'Desk Lamp']


# --------------------------------------------------------------------------
# Photos
# --------------------------------------------------------------------------

def photo(pid):
    """The original photo (1600 px wide), downloaded once into the cache."""
    os.makedirs(CACHE, exist_ok=True)
    path = os.path.join(CACHE, '%d.jpg' % pid)
    if not os.path.isfile(path):
        url = 'https://images.pexels.com/photos/%d/pexels-photo-%d.jpeg?auto=compress&cs=tinysrgb&w=1600' % (pid, pid)
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=60) as r, open(path, 'wb') as fh:
            fh.write(r.read())
    return Image.open(path).convert('RGB')


def save(im, filename, size, centering=(0.5, 0.5), quality=84):
    ImageOps.fit(im, size, Image.LANCZOS, centering=centering).save(os.path.join(IMG_DIR, filename), 'JPEG', quality=quality, optimize=True, progressive=True)


# Photos whose subject is not in the middle: where to centre the square crop (x, y, 0..1).
FOCUS = {7303151: (0.86, 0.5)}


def detail_crop(im, focus=(0.5, 0.42)):
    """A zoomed crop around the focus point: the second picture of a product's gallery."""
    w, h = im.size
    side = int(min(w, h) * 0.62)
    left = int((w - side) * focus[0])
    top = int((h - side) * focus[1])
    return im.crop((left, top, left + side, top + side))


def main():
    if len(sys.argv) != 2:
        sys.exit(__doc__)

    out_sql = sys.argv[1]
    os.makedirs(IMG_DIR, exist_ok=True)
    sql = ['SET NAMES utf8mb4;', 'START TRANSACTION;']

    # --- wipe the stock catalog (orders/customers untouched) -------------
    for table in [
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
    ]:
        sql.append('DELETE FROM %s;' % table)
    sql.append("DELETE FROM oc_seo_url WHERE query LIKE 'path=%' OR query LIKE 'product_id=%' OR query LIKE 'manufacturer_id=%' OR query LIKE 'category_id=%';")

    # --- brands -----------------------------------------------------------
    for bid, name in BRANDS:
        sql.append('INSERT INTO oc_manufacturer (manufacturer_id, name, image, sort_order) VALUES (%d, %s, NULL, %d);' % (bid, q(name), bid))
        sql.append('INSERT INTO oc_manufacturer_to_store (manufacturer_id, store_id) VALUES (%d, 0);' % bid)
        sql.append("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'manufacturer_id=%d', %s);" % (bid, q(slug(name))))

    # --- categories ---------------------------------------------------------
    parent_of = {cid: parent for cid, _n, _k, parent, _b in CATEGORIES}

    def ancestors(cid):
        chain = []
        while cid is not None:
            chain.append(cid)
            cid = parent_of[cid]
        return list(reversed(chain))  # root ... cid

    for i, (cid, name, kw, parent, blurb) in enumerate(CATEGORIES):
        top = 1 if parent is None else 0
        sql.append("INSERT INTO oc_category (category_id, image, parent_id, top, `column`, sort_order, status, date_added, date_modified) VALUES (%d, NULL, %d, %d, 1, %d, 1, NOW(), NOW());" % (cid, parent or 0, top, i))
        sql.append('INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword) VALUES (%d, 1, %s, %s, %s, %s, \'\');' % (cid, q(esc(name)), q(esc('<p>%s</p>' % blurb)), q(esc(name)), q(blurb)))
        for level, anc in enumerate(ancestors(cid)):
            sql.append('INSERT INTO oc_category_path (category_id, path_id, level) VALUES (%d, %d, %d);' % (cid, anc, level))
        sql.append('INSERT INTO oc_category_to_store (category_id, store_id) VALUES (%d, 0);' % cid)
        sql.append('INSERT INTO oc_category_to_layout (category_id, store_id, layout_id) VALUES (%d, 0, 0);' % cid)
        sql.append("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'path=%d', %s);" % (cid, q(kw)))

    # --- specification attributes --------------------------------------------
    sql.append("INSERT INTO oc_attribute_group (attribute_group_id, sort_order) VALUES (100, 1);")
    sql.append("INSERT INTO oc_attribute_group_description (attribute_group_id, language_id, name) VALUES (100, 1, 'Details');")
    for aid, aname in ((100, 'Material'), (101, 'Size'), (102, 'Care')):
        sql.append("INSERT INTO oc_attribute (attribute_id, attribute_group_id, sort_order) VALUES (%d, 100, %d);" % (aid, aid))
        sql.append("INSERT INTO oc_attribute_description (attribute_id, language_id, name) VALUES (%d, 1, %s);" % (aid, q(aname)))

    # --- products -----------------------------------------------------------
    product_id = {}
    credits = []
    for position, (name, leaf, brand, price, special, pexels_id, blurb, (material, size, care)) in enumerate(PRODUCTS):
        pid = 100 + position
        product_id[name] = pid
        n = position + 1
        im = photo(pexels_id)
        main_img, alt_img = 'p%d.jpg' % pid, 'p%d-2.jpg' % pid
        focus = FOCUS.get(pexels_id, (0.5, 0.5))
        save(im, main_img, (900, 900), centering=focus)
        save(detail_crop(im, focus), alt_img, (900, 900))
        credits.append('%-28s pexels.com/photo/%d' % (name, pexels_id))
        desc = '<p>%s</p>' % blurb
        sql.append(
            "INSERT INTO oc_product (product_id, model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, viewed, date_added, date_modified) "
            "VALUES (%d, %s, %s, '', '', '', '', '', '', 40, 7, %s, %d, 1, %.4f, 0, 9, CURDATE(), 0.5, 2, 0, 0, 0, 1, 1, 1, %d, 1, 0, DATE_SUB(NOW(), INTERVAL %d DAY), NOW());"
            % (pid, q('MC-%03d' % n), q('MC-%03d' % n), q(IMG_PREFIX + main_img), brand, price, n, (position * 11) % len(PRODUCTS) + 1))  # added 1-48 days ago, spread over the categories
        sql.append("INSERT INTO oc_product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (%d, 1, %s, %s, '', %s, %s, '');" % (pid, q(esc(name)), q(esc(desc)), q(esc(name)), q(blurb)))
        sql.append('INSERT INTO oc_product_to_store (product_id, store_id) VALUES (%d, 0);' % pid)
        sql.append('INSERT INTO oc_product_to_layout (product_id, store_id, layout_id) VALUES (%d, 0, 0);' % pid)
        # In every category on the way up, like the stock OpenCart demo does,
        # so parent category pages list their subcategories' products too.
        for cat in ancestors(leaf):
            sql.append('INSERT INTO oc_product_to_category (product_id, category_id) VALUES (%d, %d);' % (pid, cat))
        sql.append("INSERT INTO oc_product_image (product_id, image, sort_order) VALUES (%d, %s, 0);" % (pid, q(IMG_PREFIX + alt_img)))
        sql.append("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'product_id=%d', %s);" % (pid, q(slug(name))))
        for aid, value in ((100, material), (101, size), (102, care)):
            sql.append("INSERT INTO oc_product_attribute (product_id, attribute_id, language_id, text) VALUES (%d, %d, 1, %s);" % (pid, aid, q(value)))
        if special:
            sql.append("INSERT INTO oc_product_special (product_id, customer_group_id, priority, price, date_start, date_end) VALUES (%d, 1, 1, %.4f, '2020-01-01', '2099-12-31');" % (pid, special))

    # --- reviews, badges, featured module, brand carousel ----------------------
    for pname, author, rating, text, days in REVIEWS:
        sql.append("INSERT INTO oc_review (product_id, customer_id, author, text, rating, status, date_added, date_modified) VALUES (%d, 0, %s, %s, %d, 1, DATE_SUB(NOW(), INTERVAL %d DAY), NOW());" % (product_id[pname], q(author), q(text), rating, days))
    for bid, label, color, names in BADGES:
        sql.append("INSERT INTO oc_mc_badge (badge_id, label, color, sort_order, date_added) VALUES (%d, %s, %s, %d, NOW());" % (bid, q(label), q(color), bid))
        for pname in names:
            sql.append("INSERT INTO oc_mc_product_badge (product_id, badge_id) VALUES (%d, %d);" % (product_id[pname], bid))
    feat = ','.join('"%d"' % product_id[n] for n in FEATURED_NAMES)
    sql.append("UPDATE oc_module SET setting = %s WHERE module_id = 28;" % q('{"name":"Home Page","product":[%s],"limit":"4","width":"600","height":"600","status":"1"}' % feat))

    sql.append('DELETE FROM oc_banner_image WHERE banner_id IN (6, 7, 8);')  # 6 = stock category-page banner, 7 = stock slider, 8 = brands
    sql.append("DELETE FROM oc_layout_module WHERE code IN ('banner.30', 'banner.31', 'slideshow.27') AND layout_id IN (1, 3);")
    for i, (bid, name) in enumerate(BRANDS):
        fn = 'brand%d.jpg' % bid
        render_brand(os.path.join(IMG_DIR, fn), name)
        sql.append("INSERT INTO oc_banner_image (banner_id, language_id, title, link, image, sort_order) VALUES (8, 1, %s, %s, %s, %d);" % (q(name), q('index.php?route=product/manufacturer/info&manufacturer_id=%d' % bid), q(IMG_PREFIX + fn), i))
    # Home layout order: builder blocks, then featured products + brands (content_bottom).
    sql.append("UPDATE oc_layout_module SET position = 'content_bottom' WHERE code IN ('featured.28', 'carousel.29') AND layout_id = 1;")

    # One picture per top category: its first product's photo.
    sql.append("UPDATE oc_category c SET c.image = (SELECT p.image FROM oc_product p JOIN oc_product_to_category x ON x.product_id = p.product_id WHERE x.category_id = c.category_id ORDER BY p.product_id LIMIT 1) WHERE c.parent_id = 0;")

    # --- slider / about pictures: cropped to the 760x600 (about 4:3) the block asks for ---
    for i, pexels_id in enumerate(SLIDE_PHOTOS, 1):
        save(photo(pexels_id), 'slide%d.jpg' % i, (1520, 1200))
        credits.append('%-28s pexels.com/photo/%d' % ('slide %d' % i, pexels_id))
    save(photo(ABOUT_PHOTO), 'about.jpg', (1600, 1262))
    credits.append('%-28s pexels.com/photo/%d' % ('about', ABOUT_PHOTO))

    # --- home page blocks (Homepage Builder: mc_page 'home') -------------
    sql.append("INSERT INTO oc_mc_page (code, status, date_added, date_modified) VALUES ('home', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = 1;")
    sql.append("SET @home_page = (SELECT page_id FROM oc_mc_page WHERE code = 'home');")
    sql.append("DELETE d FROM oc_mc_block_description d JOIN oc_mc_block b ON b.block_id = d.block_id WHERE b.page_id = @home_page;")
    sql.append("DELETE FROM oc_mc_block WHERE page_id = @home_page;")

    def block(order, btype, settings, content):
        sql.append("INSERT INTO oc_mc_block (page_id, type, sort_order, status, settings_json, date_added, date_modified) VALUES (@home_page, %s, %d, 1, %s, NOW(), NOW());" % (q(btype), order, q(json.dumps(settings, ensure_ascii=False))))
        sql.append("INSERT INTO oc_mc_block_description (block_id, language_id, content_json) VALUES (LAST_INSERT_ID(), 1, %s);" % q(json.dumps(content, ensure_ascii=False)))

    cat = 'index.php?route=product/category&path='
    block(5, 'slider',
          {'layout': 'split', 'autoplay': 6000, 'items': [
              {'image': IMG_PREFIX + 'slide1.jpg', 'link': cat + '103', 'btn2_link': cat + '103_113', 'color': 'navy'},
              {'image': IMG_PREFIX + 'slide2.jpg', 'link': cat + '102', 'btn2_link': 'index.php?route=product/special', 'color': 'navy'},
              {'image': IMG_PREFIX + 'slide3.jpg', 'link': cat + '107', 'btn2_link': cat + '106', 'color': 'navy'}]},
          {'items': [
              {'eyebrow': 'Furniture', 'title': 'Small pieces, <em>built to last</em>', 'subtitle': 'Solid oak and birch, assembled in two minutes.', 'price': 'from $59', 'btn_text': 'Browse furniture', 'btn2_text': 'Chairs'},
              {'eyebrow': 'Lighting', 'title': 'A softer kind of <em>light</em>', 'subtitle': 'Lamps and pendants for slow evenings.', 'price': '$69', 'old_price': '$89', 'btn_text': 'See lighting', 'btn2_text': 'All specials'},
              {'eyebrow': 'Candles', 'title': 'Slow evenings, <em>well lit</em>', 'subtitle': 'Hand-poured soy candles in glass you can reuse.', 'price': 'from $15', 'btn_text': 'Shop candles', 'btn2_text': 'Textiles'}]})
    block(10, 'benefits',
          {'items': [{'icon': 'truck'}, {'icon': 'arrow-repeat'}, {'icon': 'shield-check'}, {'icon': 'headset'}]},
          {'title': '', 'items': [
              {'title': 'Free delivery', 'description': 'On orders over $60'},
              {'title': '30-day returns', 'description': 'Changed your mind? Easy'},
              {'title': 'Secure payment', 'description': 'Card, bank transfer or cash'},
              {'title': 'Friendly support', 'description': 'We reply within a day'}]})
    block(20, 'categories', {'parent_id': 0, 'limit': 8}, {'title': 'Shop by category'})
    block(30, 'products', {'limit': 8, 'sort': 'p.date_added', 'order': 'DESC'}, {'title': 'New arrivals'})
    block(40, 'text-image', {'image': IMG_PREFIX + 'about.jpg', 'image_width': 800, 'image_height': 631, 'image_position': 'left'},
          {'title': 'Things made to be used', 'body': '<p>We pick a small number of well-made things for the home and keep them in stock. Stoneware from small kilns, solid oak, linen and soy wax - no filler.</p><p>Every product is photographed as it really is, and every order ships in recyclable packaging.</p>', 'image_alt': 'Our home collection'})
    block(50, 'testimonials', {'source': 'reviews', 'limit': 3}, {'title': 'What customers say'})
    block(70, 'faq', {}, {'title': 'Questions and answers', 'items': [
        {'question': 'How long does delivery take?', 'answer': 'Usually 1-3 working days within the country. You get a tracking number by email.'},
        {'question': 'Can I return an item?', 'answer': 'Yes, within 30 days if it is unused. Write to us and we will arrange a pickup.'},
        {'question': 'Which payment methods do you accept?', 'answer': 'Bank cards, bank transfer and cash on delivery.'}]})
    block(80, 'cta', {}, {'title': 'Not sure what to choose?', 'text': 'Tell us about your room and we will suggest a few things that go well together.', 'cta_label': 'Contact us', 'cta_url': 'index.php?route=information/contact'})

    sql.append('COMMIT;')

    with open(out_sql, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(sql) + '\n')

    with open(os.path.join(IMG_DIR, 'CREDITS.txt'), 'w', encoding='utf-8') as fh:
        fh.write('Demo photos. Source: Pexels (Pexels License: free for commercial use, attribution not required).\n'
                 'Replace with your own photography before launch.\n\n' + '\n'.join(credits) + '\n')

    print('products:', len(PRODUCTS), 'images:', len(os.listdir(IMG_DIR)), 'sql statements:', len(sql))


if __name__ == '__main__':
    main()
