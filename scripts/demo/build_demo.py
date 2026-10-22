#!/usr/bin/env python3
"""
MintCart demo content: a small home-goods store.

Generates the product / banner / brand images (Pillow) into
upload/image/catalog/demo/shop/ and writes a SQL file that REPLACES the stock
OpenCart demo catalog (38 categories, 20 products, 6 brands) with:

  8 root categories with 12 subcategories (two third-level), 30 products,
  5 brands, specials,
  2 home slides, a brand carousel, no category-page banner, the Featured module's product list,
  and Homepage Builder blocks on the 'home' page (benefits, categories, new arrivals, about,
  testimonials, FAQ, CTA).

Usage:
    python scripts/demo/build_demo.py <out.sql>
    mysql -uroot <database> --default-character-set=utf8mb4 < <out.sql>

The SQL deletes the existing catalog rows first (orders and customers are
left alone). Take a database dump before applying it. Product ids 100+,
category ids 100+, brand ids 20+, so the content is easy to recognise and
remove again.
"""

import html
import json
import os
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMG_DIR = os.path.join(ROOT, 'upload', 'image', 'catalog', 'demo', 'shop')
IMG_PREFIX = 'catalog/demo/shop/'
S = 2  # supersampling

COLORS = {
    'Charcoal': '#3b3e44', 'Stone': '#9ca1a8', 'White': '#f2f2f0',
    'Clay': '#b4694a', 'Sage': '#7d9985', 'Navy': '#2e4766',
    'Mustard': '#d2a52e', 'Black': '#222427', 'Oat': '#d8d2c4',
}

# (id, name, slug, parent id or None, blurb) - 8 roots, 12 subcategories,
# two of them third-level.
CATEGORIES = [
    (100, 'Mugs & Cups', 'mugs-cups', None, 'Everyday stoneware for coffee, tea and slow mornings.'),
    (108, 'Coffee Mugs', 'coffee-mugs', 100, 'Big, comfortable mugs for the first cup of the day.'),
    (101, 'Vases', 'vases', None, 'Simple hand-finished vases for a single stem or a full bunch.'),
    (109, 'Ceramic Vases', 'ceramic-vases', 101, 'Glazed inside, so they hold water.'),
    (102, 'Lighting', 'lighting', None, 'Warm, quiet light for desks, shelves and bedside tables.'),
    (110, 'Table Lamps', 'table-lamps', 102, 'Lamps for desks and side tables.'),
    (111, 'Desk Lamps', 'desk-lamps', 110, 'Compact lamps that stay out of the way of your work.'),
    (112, 'Floor Lamps', 'floor-lamps', 102, 'Tall lamps that light up a corner.'),
    (103, 'Furniture', 'furniture', None, 'Small pieces of furniture that are made to last and easy to move.'),
    (113, 'Chairs', 'chairs', 103, 'Chairs for the kitchen, the hall and the desk.'),
    (114, 'Side Chairs', 'side-chairs', 113, 'Light, stackable chairs in solid oak.'),
    (115, 'Side Tables', 'side-tables', 103, 'Small tables for a book and a cup.'),
    (104, 'Bags', 'bags', None, 'Sturdy everyday bags with room for a laptop and a lunch.'),
    (116, 'Tote Bags', 'tote-bags', 104, 'Canvas totes for shopping and the daily commute.'),
    (105, 'Stationery', 'stationery', None, 'Notebooks that lie flat and paper that takes ink well.'),
    (117, 'Notebooks', 'notebooks', 105, 'Lay-flat notebooks in a handy A5 size.'),
    (106, 'Textiles', 'textiles', None, 'Soft cushions and covers in washable natural fibres.'),
    (118, 'Cushions', 'cushions', 106, 'Linen cushion covers with a hidden zip.'),
    (107, 'Candles', 'candles', None, 'Slow-burning soy candles in reusable glass jars.'),
    (119, 'Soy Candles', 'soy-candles', 107, 'Hand-poured, in glass jars you can reuse.'),
]

BRANDS = [(20, 'Nordhem'), (21, 'Oak & Co'), (22, 'Lumen'), (23, 'Kiln'), (24, 'Paperleaf')]

# (leaf category id, shape, name base, brand id, base price, [(color, special price or None)], blurb)
# Product id = 100 + position * 3 + colour index.
PRODUCTS = [
    (108, 'mug', 'Stoneware Mug', 23, 18, [('White', None), ('Charcoal', 14), ('Sage', None)],
     'A 350 ml mug with a thick, comfortable handle. Dishwasher and microwave safe.'),
    (109, 'vase', 'Tall Ceramic Vase', 23, 42, [('White', None), ('Clay', None), ('Navy', 34)],
     'A 28 cm vase with a narrow neck. Glazed inside, so it holds water.'),
    (111, 'lamp', 'Desk Lamp', 22, 64, [('Black', None), ('White', None), ('Mustard', None)],
     'A compact lamp with a fabric cord and a dimmable warm-white bulb.'),
    (114, 'chair', 'Oak Side Chair', 21, 149, [('Oat', None), ('Charcoal', 119), ('Sage', None)],
     'Solid oak frame, stackable, assembled with no tools in two minutes.'),
    (116, 'bag', 'Canvas Tote', 21, 38, [('Oat', None), ('Navy', None), ('Black', None)],
     'Heavy 14 oz canvas with an inside pocket and reinforced handles.'),
    (117, 'notebook', 'Lay-Flat Notebook', 24, 14, [('Black', None), ('Stone', None), ('Clay', None)],
     'A5, 160 pages of 100 gsm paper that is smooth, ink-friendly and acid-free.'),
    (118, 'pillow', 'Linen Cushion', 20, 29, [('Oat', None), ('Sage', None), ('Charcoal', 23)],
     'Stonewashed linen cover with a hidden zip. Insert included, cover machine washable.'),
    (119, 'candle', 'Soy Candle', 22, 22, [('White', None), ('Stone', None), ('Clay', None)],
     'Hand-poured soy wax in a reusable glass jar. About 45 hours of burn time.'),
    (112, 'floorlamp', 'Arc Floor Lamp', 22, 119, [('Black', None), ('White', None), ('Navy', 99)],
     'A 150 cm floor lamp with a weighted base and a pull-chain switch.'),
    (115, 'table', 'Round Side Table', 21, 79, [('Oat', None), ('Charcoal', None), ('Sage', None)],
     'A 40 cm table with a solid top and three splayed legs.'),
]

FEATURED = [100, 106, 109, 115]


def hex_rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def shade(rgb, f):
    return tuple(max(0, min(255, int(c * f))) for c in rgb)


def font(size, bold=False):
    for name in (['seguisb.ttf', 'segoeuib.ttf'] if bold else ['segoeui.ttf']):
        try:
            return ImageFont.truetype(os.path.join('C:/Windows/Fonts', name), size)
        except OSError:
            continue
    return ImageFont.load_default()


class Canvas:
    def __init__(self, w, h, bg):
        self.w, self.h = w * S, h * S
        self.img = Image.new('RGB', (self.w, self.h), bg)
        self.d = ImageDraw.Draw(self.img, 'RGBA')

    def sc(self, box):
        return [v * S for v in box]

    def shadow(self, cx, cy, rx, ry):
        layer = Image.new('RGBA', self.img.size, (0, 0, 0, 0))
        ImageDraw.Draw(layer).ellipse(self.sc([cx - rx, cy - ry, cx + rx, cy + ry]), fill=(0, 0, 0, 70))
        layer = layer.filter(ImageFilter.GaussianBlur(14 * S))
        self.img.paste(layer, (0, 0), layer)
        self.d = ImageDraw.Draw(self.img, 'RGBA')

    def rrect(self, box, r, fill):
        self.d.rounded_rectangle(self.sc(box), r * S, fill=fill)

    def ell(self, box, fill=None, outline=None, width=0):
        self.d.ellipse(self.sc(box), fill=fill, outline=outline, width=width * S)

    def poly(self, pts, fill):
        self.d.polygon([(x * S, y * S) for x, y in pts], fill=fill)

    def rect(self, box, fill):
        self.d.rectangle(self.sc(box), fill=fill)

    def result(self, size):
        return self.img.resize(size, Image.LANCZOS)


def draw_shape(c, kind, rgb):
    base, dark, light = rgb, shade(rgb, 0.8), shade(rgb, 1.15)
    if kind == 'mug':
        c.shadow(400, 600, 190, 22)
        c.ell([495, 330, 615, 500], outline=dark, width=30)
        c.rrect([235, 250, 525, 590], 44, base)
        c.rect([260, 290, 300, 560], (255, 255, 255, 38))
        c.ell([235, 232, 525, 290], fill=light)
        c.ell([252, 240, 508, 282], fill=shade(rgb, 0.45))
    elif kind == 'vase':
        c.shadow(400, 640, 150, 20)
        c.rect([352, 150, 448, 320], base)
        c.ell([262, 270, 538, 645], fill=base)
        c.ell([348, 138, 452, 170], fill=light)
        c.ell([360, 144, 440, 164], fill=shade(rgb, 0.5))
        c.ell([300, 330, 345, 560], fill=(255, 255, 255, 40))
    elif kind == 'lamp':
        c.shadow(400, 626, 150, 18)
        c.rect([392, 360, 408, 610], dark)
        c.ell([315, 596, 485, 636], fill=base)
        c.poly([(290, 190), (510, 190), (580, 365), (220, 365)], base)
        c.poly([(290, 190), (350, 190), (330, 365), (220, 365)], (255, 255, 255, 40))
        c.ell([290, 178, 510, 204], fill=light)
        c.ell([330, 346, 470, 384], fill=(255, 244, 200, 255))
    elif kind == 'chair':
        c.shadow(400, 650, 190, 20)
        c.rect([256, 420, 282, 645], dark)
        c.rect([518, 420, 544, 645], dark)
        c.rect([268, 345, 292, 425], dark)
        c.rect([508, 345, 532, 425], dark)
        c.rrect([235, 160, 565, 340], 26, base)
        c.rrect([220, 380, 580, 436], 18, light)
        c.rect([252, 180, 290, 320], (255, 255, 255, 36))
    elif kind == 'bag':
        c.shadow(400, 646, 200, 20)
        c.ell([300, 170, 500, 420], outline=dark, width=26)
        c.poly([(225, 320), (575, 320), (610, 640), (190, 640)], base)
        c.rrect([205, 308, 595, 372], 20, light)
        c.poly([(225, 320), (275, 320), (252, 640), (190, 640)], (255, 255, 255, 34))
        c.rrect([345, 430, 455, 470], 10, shade(rgb, 0.7))
    elif kind == 'notebook':
        c.shadow(400, 656, 150, 16)
        c.rrect([270, 140, 535, 650], 16, base)
        c.rect([270, 140, 304, 650], dark)
        c.rect([482, 140, 496, 650], (0, 0, 0, 55))
        c.rrect([342, 230, 450, 330], 8, (255, 255, 255, 215))
        c.rect([362, 258, 430, 266], (0, 0, 0, 70))
        c.rect([362, 282, 410, 290], (0, 0, 0, 50))
    elif kind == 'floorlamp':
        c.shadow(400, 690, 130, 16)
        c.ell([320, 668, 480, 706], fill=base)
        c.rect([392, 250, 408, 680], dark)
        c.poly([(330, 130), (470, 130), (520, 270), (280, 270)], base)
        c.poly([(330, 130), (370, 130), (350, 270), (280, 270)], (255, 255, 255, 40))
        c.ell([330, 120, 470, 142], fill=light)
        c.ell([350, 254, 450, 284], fill=(255, 244, 200, 255))
    elif kind == 'table':
        c.shadow(400, 650, 190, 18)
        c.poly([(330, 400), (350, 400), (300, 640), (278, 640)], dark)
        c.poly([(450, 400), (470, 400), (520, 640), (498, 640)], dark)
        c.poly([(392, 400), (408, 400), (408, 650), (392, 650)], dark)
        c.ell([230, 330, 570, 440], fill=base)
        c.ell([240, 335, 560, 425], fill=light)
        c.ell([300, 350, 420, 385], fill=(255, 255, 255, 50))
    elif kind == 'pillow':
        c.shadow(400, 586, 230, 20)
        c.rrect([190, 230, 610, 570], 100, base)
        c.poly([(190, 230), (230, 250), (215, 330), (190, 330)], (255, 255, 255, 30))
        c.d.line([(self_x * S, self_y * S) for self_x, self_y in [(210, 250), (400, 400), (590, 550)]],
                 fill=(0, 0, 0, 22), width=5 * S)
        c.d.line([(self_x * S, self_y * S) for self_x, self_y in [(590, 250), (400, 400), (210, 550)]],
                 fill=(0, 0, 0, 22), width=5 * S)
        c.ell([385, 385, 415, 415], fill=shade(rgb, 0.78))
    elif kind == 'candle':
        c.shadow(400, 632, 150, 18)
        c.rrect([285, 330, 515, 628], 34, (255, 255, 255, 120))
        c.rrect([293, 400, 507, 620], 28, base)
        c.ell([293, 385, 507, 425], fill=light)
        c.rect([396, 338, 404, 390], (40, 40, 40, 255))
        c.ell([380, 240, 420, 332], fill=(255, 190, 70, 235))
        c.ell([390, 276, 410, 326], fill=(255, 240, 190, 255))
        c.rect([305, 420, 330, 600], (255, 255, 255, 44))


def render_product(kind, color_name, path_main, path_alt):
    rgb = hex_rgb(COLORS[color_name])
    c = Canvas(800, 800, (255, 255, 255))
    draw_shape(c, kind, rgb)
    c.result((800, 800)).save(path_main, 'JPEG', quality=90)
    alt = Canvas(800, 800, (244, 244, 244))
    draw_shape(alt, kind, rgb)
    im = alt.result((800, 800)).transpose(Image.FLIP_LEFT_RIGHT)
    im.save(path_alt, 'JPEG', quality=90)


def render_banner(path, headline, sub, kinds, colors, grad):
    """1140x475 slide (25% taller than the first 1140x380 version): headline and button on the left, objects right."""
    c = Canvas(1140, 475, grad[0])
    dy = (475 - 380) // 2  # the old layout, centred in the taller canvas
    top, bottom = hex_rgb(grad[0]), hex_rgb(grad[1])
    px = c.img.load()
    for y in range(c.h):
        t = y / c.h
        col = tuple(int(top[k] + (bottom[k] - top[k]) * t) for k in range(3))
        for x in range(c.w):
            px[x, y] = col
    size = 340
    for i, (kind, color) in enumerate(zip(kinds, colors)):
        layer = Canvas(800, 800, (255, 255, 255))
        draw_shape(layer, kind, hex_rgb(COLORS[color]))
        big = layer.img.resize((size * S, size * S), Image.LANCZOS)
        mask = Image.eval(big.convert('L'), lambda v: 0 if v > 248 else 255).filter(ImageFilter.GaussianBlur(S))
        c.img.paste(big, ((500 + i * 170) * S, ((475 - size) // 2) * S), mask)
    c.d = ImageDraw.Draw(c.img, 'RGBA')
    lines = headline.split('|')
    y = 78 + dy
    for line in lines:
        c.d.text((70 * S, y * S), line, font=font(50 * S, True), fill=hex_rgb('#1d1f23'))
        y += 62
    c.d.text((72 * S, (y + 8) * S), sub, font=font(22 * S), fill=hex_rgb('#5f636a'))
    by = y + 62
    c.d.rounded_rectangle([72 * S, by * S, 252 * S, (by + 50) * S], 12 * S, fill=hex_rgb('#14806a'))
    c.d.text((104 * S, (by + 9) * S), 'Shop now', font=font(24 * S, True), fill=(255, 255, 255))
    c.result((1140, 475)).save(path, 'JPEG', quality=90)


def render_slide_media(path, kinds, colors, grad, size=(760, 600)):
    """Picture for a split slide of the Slider block: objects on a soft gradient."""
    w, h = size
    c = Canvas(w, h, grad[0])
    top, bottom = hex_rgb(grad[0]), hex_rgb(grad[1])
    px = c.img.load()
    for y in range(c.h):
        t = y / c.h
        col = tuple(int(top[k] + (bottom[k] - top[k]) * t) for k in range(3))
        for x in range(c.w):
            px[x, y] = col
    n = len(kinds)
    obj = int(min(h * 0.62, w * 0.5))
    step = (w - obj) // max(1, n - 1) if n > 1 else 0
    for i, (kind, color) in enumerate(zip(kinds, colors)):
        layer = Canvas(800, 800, (255, 255, 255))
        draw_shape(layer, kind, hex_rgb(COLORS[color]))
        big = layer.img.resize((obj * S, obj * S), Image.LANCZOS)
        mask = Image.eval(big.convert('L'), lambda v: 0 if v > 248 else 255).filter(ImageFilter.GaussianBlur(S))
        x = (w - obj) // 2 if n == 1 else int(i * step * 0.8 + (w - obj - (n - 1) * step * 0.8) / 2)
        c.img.paste(big, (x * S, ((h - obj) // 2) * S), mask)
    c.result((w, h)).save(path, 'JPEG', quality=90)


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
        'oc_product', 'oc_category_description', 'oc_category_path',
        'oc_category_to_store', 'oc_category_to_layout', 'oc_category_filter',
        'oc_coupon_category', 'oc_category', 'oc_manufacturer_to_store',
        'oc_manufacturer',
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

    # --- products -----------------------------------------------------------
    n = 0
    for position, (leaf, kind, base, brand, price, colors, blurb) in enumerate(PRODUCTS):
        for ci, (color, special) in enumerate(colors):
            pid = 100 + position * 3 + ci
            n += 1
            name = '%s - %s' % (base, color)
            main_img = 'p%d.jpg' % pid
            alt_img = 'p%d-alt.jpg' % pid
            render_product(kind, color, os.path.join(IMG_DIR, main_img), os.path.join(IMG_DIR, alt_img))
            desc = '<p>%s</p><p>Colour: %s.</p>' % (blurb, color)
            sql.append(
                "INSERT INTO oc_product (product_id, model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, viewed, date_added, date_modified) "
                "VALUES (%d, %s, %s, '', '', '', '', '', '', 40, 7, %s, %d, 1, %.4f, 0, 9, CURDATE(), 0.5, 2, 0, 0, 0, 1, 1, 1, %d, 1, 0, NOW(), NOW());"
                % (pid, q('MC-%03d' % n), q('MC-%03d' % n), q(IMG_PREFIX + main_img), brand, price, n))
            sql.append("INSERT INTO oc_product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (%d, 1, %s, %s, '', %s, %s, '');" % (pid, q(esc(name)), q(esc(desc)), q(esc(name)), q(blurb)))
            sql.append('INSERT INTO oc_product_to_store (product_id, store_id) VALUES (%d, 0);' % pid)
            sql.append('INSERT INTO oc_product_to_layout (product_id, store_id, layout_id) VALUES (%d, 0, 0);' % pid)
            # In every category on the way up, like the stock OpenCart demo does,
            # so parent category pages list their subcategories' products too.
            for cat in ancestors(leaf):
                sql.append('INSERT INTO oc_product_to_category (product_id, category_id) VALUES (%d, %d);' % (pid, cat))
            sql.append("INSERT INTO oc_product_image (product_id, image, sort_order) VALUES (%d, %s, 0);" % (pid, q(IMG_PREFIX + alt_img)))
            sql.append("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'product_id=%d', %s);" % (pid, q(slug(name))))
            if special:
                sql.append("INSERT INTO oc_product_special (product_id, customer_group_id, priority, price, date_start, date_end) VALUES (%d, 1, 1, %.4f, '2020-01-01', '2099-12-31');" % (pid, special))

    # --- home page: featured module, slides, brand carousel --------------
    feat = ','.join('"%d"' % p for p in FEATURED)
    sql.append("UPDATE oc_module SET setting = %s WHERE module_id = 28;" % q('{"name":"Home Page","product":[%s],"limit":"4","width":"600","height":"600","status":"1"}' % feat))

    sql.append("UPDATE oc_module SET setting = '{\"name\":\"Home Page\",\"banner_id\":\"7\",\"width\":\"1140\",\"height\":\"475\",\"status\":\"1\"}' WHERE module_id = 27;")  # home slider, 1140x475

    slides = [
        ('hero1.jpg', 'Everyday things,|made well', 'Simple pieces for a calm, comfortable home.', ['mug', 'vase', 'lamp'], ['White', 'Clay', 'Black'], ('#f3f4f6', '#e3e6ea'), 'path=100'),
        ('hero2.jpg', 'A softer|kind of light', 'Lamps and candles for slow evenings.', ['lamp', 'candle', 'candle'], ['Mustard', 'White', 'Stone'], ('#f6f6f4', '#e6e7e9'), 'path=102'),
    ]
    sql.append('DELETE FROM oc_banner_image WHERE banner_id IN (6, 7, 8);')  # 6 = stock category-page banner
    sql.append("DELETE FROM oc_layout_module WHERE code IN ('banner.30', 'banner.31');")  # its placement
    for i, (fn, head, sub, kinds, cols, grad, link) in enumerate(slides):
        render_banner(os.path.join(IMG_DIR, fn), head, sub, kinds, cols, grad)
        cid = int(link.split('=')[1])
        sql.append("INSERT INTO oc_banner_image (banner_id, language_id, title, link, image, sort_order) VALUES (7, 1, %s, %s, %s, %d);" % (q(head.replace('|', ' ')), q('index.php?route=product/category&path=%d' % cid), q(IMG_PREFIX + fn), i))
    for i, (bid, name) in enumerate(BRANDS):
        fn = 'brand%d.jpg' % bid
        render_brand(os.path.join(IMG_DIR, fn), name)
        sql.append("INSERT INTO oc_banner_image (banner_id, language_id, title, link, image, sort_order) VALUES (8, 1, %s, %s, %s, %d);" % (q(name), q('index.php?route=product/manufacturer/info&manufacturer_id=%d' % bid), q(IMG_PREFIX + fn), i))

    # --- root category pictures (used by the Categories home block) ------
    for cid, kind, color in [(100, 'mug', 'Clay'), (101, 'vase', 'Sage'), (102, 'lamp', 'Mustard'), (103, 'chair', 'Oat'),
                             (104, 'bag', 'Navy'), (105, 'notebook', 'Stone'), (106, 'pillow', 'Sage'), (107, 'candle', 'Clay')]:
        fn = 'category%d.jpg' % cid
        cv = Canvas(600, 600, (243, 243, 241))
        draw_shape(cv, kind, hex_rgb(COLORS[color]))
        cv.result((600, 600)).save(os.path.join(IMG_DIR, fn), 'JPEG', quality=90)
        sql.append("UPDATE oc_category SET image = %s WHERE category_id = %d;" % (q(IMG_PREFIX + fn), cid))

    # Home layout order: slider (content_top), builder blocks, then featured + brands (content_bottom).
    sql.append("UPDATE oc_layout_module SET position = 'content_bottom' WHERE code IN ('featured.28', 'carousel.29') AND layout_id = 1;")

    # --- manual product badges: the set (mc_badge) and which products carry them ---
    sql.append('DELETE FROM oc_mc_product_badge;')
    sql.append('DELETE FROM oc_mc_badge;')
    for bid, label, color, pids in [(1, 'Bestseller', '#2457d6', [100, 109]), (2, 'Eco', '', [106]), (3, 'Hit', '#d92d20', [118, 121])]:
        sql.append("INSERT INTO oc_mc_badge (badge_id, label, color, sort_order, date_added) VALUES (%d, %s, %s, %d, NOW());" % (bid, q(label), q(color), bid))
        for pid in pids:
            sql.append("INSERT INTO oc_mc_product_badge (product_id, badge_id) VALUES (%d, %d);" % (pid, bid))

    # --- home page blocks (Homepage Builder: mc_page 'home') -------------
    # The OpenCart banner/featured modules above keep working; these blocks
    # are rendered above them and turn the home page into a full storefront.
    render_banner(os.path.join(IMG_DIR, 'about.jpg'), 'Made to be|used every day', 'Small batches, honest materials.', ['mug', 'candle', 'pillow'], ['Clay', 'White', 'Sage'], ('#f6f4ef', '#e7e3da'))
    sql.append("INSERT INTO oc_mc_page (code, status, date_added, date_modified) VALUES ('home', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = 1;")
    sql.append("SET @home_page = (SELECT page_id FROM oc_mc_page WHERE code = 'home');")
    sql.append("DELETE d FROM oc_mc_block_description d JOIN oc_mc_block b ON b.block_id = d.block_id WHERE b.page_id = @home_page;")
    sql.append("DELETE FROM oc_mc_block WHERE page_id = @home_page;")

    def block(order, btype, settings, content):
        sql.append("INSERT INTO oc_mc_block (page_id, type, sort_order, status, settings_json, date_added, date_modified) VALUES (@home_page, %s, %d, 1, %s, NOW(), NOW());" % (q(btype), order, q(json.dumps(settings, ensure_ascii=False))))
        sql.append("INSERT INTO oc_mc_block_description (block_id, language_id, content_json) VALUES (LAST_INSERT_ID(), 1, %s);" % q(json.dumps(content, ensure_ascii=False)))

    slider_slides = [
        ('slide1.jpg', ['mug', 'vase', 'lamp'], ['White', 'Clay', 'Black'], ('#eef1f4', '#dfe4ea'), 'path=100',
         {'eyebrow': 'New season', 'title': 'Everyday things, <em>made well</em>', 'subtitle': 'Simple pieces for a calm, comfortable home.', 'price': 'from $14', 'btn_text': 'Shop now', 'btn2_text': 'All specials'}),
        ('slide2.jpg', ['lamp', 'candle', 'candle'], ['Mustard', 'White', 'Stone'], ('#f6f2ea', '#ebe3d3'), 'path=102',
         {'eyebrow': 'Lighting', 'title': 'A softer kind of <em>light</em>', 'subtitle': 'Lamps and candles for slow evenings.', 'price': '$22', 'old_price': '$29', 'btn_text': 'See lighting', 'btn2_text': 'Candles'}),
        ('slide3.jpg', ['chair', 'table'], ['Oat', 'Sage'], ('#eef3ee', '#dde7de'), 'path=103',
         {'eyebrow': 'Furniture', 'title': 'Small pieces, <em>built to last</em>', 'subtitle': 'Solid oak, assembled in two minutes.', 'price': 'from $79', 'btn_text': 'Browse furniture', 'btn2_text': 'Chairs'}),
    ]
    slider_settings, slider_content = [], []
    for fn, kinds, cols, grad, link, text in slider_slides:
        render_slide_media(os.path.join(IMG_DIR, fn), kinds, cols, grad)
        slider_settings.append({'image': IMG_PREFIX + fn, 'link': 'index.php?route=product/category&' + link, 'btn2_link': 'index.php?route=product/special' if 'specials' in text['btn2_text'].lower() else 'index.php?route=product/category&' + link, 'color': 'navy'})
        slider_content.append(text)
    block(5, 'slider', {'layout': 'split', 'autoplay': 6000, 'items': slider_settings}, {'items': slider_content})
    sql.append("DELETE FROM oc_layout_module WHERE code = 'slideshow.27' AND layout_id = 1;")  # the Slider block replaces the stock slideshow on the home page

    block(10, 'benefits',
          {'items': [{'icon': 'truck'}, {'icon': 'arrow-repeat'}, {'icon': 'shield-check'}, {'icon': 'headset'}]},
          {'title': '', 'items': [
              {'title': 'Free delivery', 'description': 'On orders over $60'},
              {'title': '30-day returns', 'description': 'Changed your mind? No problem'},
              {'title': 'Secure payment', 'description': 'Card, bank transfer or cash'},
              {'title': 'Friendly support', 'description': 'We reply within a day'}]})
    block(20, 'categories', {'parent_id': 0, 'limit': 8}, {'title': 'Shop by category'})
    block(30, 'products', {'limit': 8, 'sort': 'p.date_added', 'order': 'DESC'}, {'title': 'New arrivals'})
    block(40, 'text-image',
          {'image': IMG_PREFIX + 'about.jpg', 'image_width': 800, 'image_height': 267, 'image_position': 'left'},
          {'title': 'Things made to be used', 'body': '<p>We pick a small number of well-made things for the home and keep them in stock. Stoneware from small kilns, solid oak, linen and soy wax - no filler.</p><p>Every product is photographed as it really is, and every order ships in recyclable packaging.</p>', 'image_alt': 'Our home collection'})
    block(50, 'testimonials', {'source': 'reviews', 'limit': 3}, {'title': 'What customers say'})
    block(70, 'faq', {},
          {'title': 'Questions and answers', 'items': [
              {'question': 'How long does delivery take?', 'answer': 'Usually 1-3 working days within the country. You get a tracking number by email.'},
              {'question': 'Can I return an item?', 'answer': 'Yes, within 30 days if it is unused. Write to us and we will arrange a pickup.'},
              {'question': 'Which payment methods do you accept?', 'answer': 'Bank cards, bank transfer and cash on delivery.'}]})
    block(80, 'cta', {}, {'title': 'Not sure what to choose?', 'text': 'Tell us about your room and we will suggest a few things that go well together.', 'cta_label': 'Contact us', 'cta_url': 'index.php?route=information/contact'})

    sql.append('COMMIT;')

    with open(out_sql, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(sql) + '\n')

    print('images:', len(os.listdir(IMG_DIR)), 'sql statements:', len(sql))


if __name__ == '__main__':
    main()
