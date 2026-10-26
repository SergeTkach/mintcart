#!/usr/bin/env python3
"""
MintCart favicon: a mint-green rounded square with a white shopping cart.

Writes into upload/image/catalog/:
  mintcart-favicon.png   256x256 (the file the store setting "Favicon" points at)
  mintcart-favicon.ico   16/32/48 for browsers that ask for /favicon.ico

Usage: python scripts/brand/build_favicon.py
Needs Pillow. Drawn at 4x and scaled down so edges stay clean at 16 px.
"""

import os

from PIL import Image, ImageChops, ImageDraw

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.path.join(ROOT, 'upload', 'image', 'catalog')

MINT_TOP = (74, 214, 160)     # lighter mint
MINT_BOTTOM = (26, 164, 117)  # deeper mint
WHITE = (255, 255, 255)

SIZE = 1024  # drawing canvas; result is scaled down


def background():
    img = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    grad = Image.new('RGBA', (SIZE, SIZE))
    px = grad.load()
    for y in range(SIZE):
        t = y / (SIZE - 1)
        col = tuple(int(MINT_TOP[k] + (MINT_BOTTOM[k] - MINT_TOP[k]) * t) for k in range(3)) + (255,)
        for x in range(SIZE):
            px[x, y] = col
    mask = Image.new('L', (SIZE, SIZE), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, SIZE - 1, SIZE - 1], radius=int(SIZE * 0.22), fill=255)
    img.paste(grad, (0, 0), mask)
    return img


def cart(img):
    layer = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    w = int(SIZE * 0.065)  # stroke

    # handle + basket outline, one polyline: handle, down to the basket, across
    d.line([(210, 270), (320, 270), (400, 640)], fill=WHITE, width=w, joint='curve')
    # basket (a trapezoid, filled)
    d.polygon([(335, 360), (830, 360), (770, 600), (395, 600)], fill=WHITE)
    # basket bottom bar
    d.line([(395, 640), (760, 640)], fill=WHITE, width=w)
    for cx in (440, 720):  # wheels
        r = int(SIZE * 0.058)
        d.ellipse([cx - r, 735 - r, cx + r, 735 + r], fill=WHITE)

    # the cart spans x 210..830, y 270..795: centre it on the square
    img.alpha_composite(ImageChops.offset(layer, -8, -20))
    return img


def main():
    os.makedirs(OUT, exist_ok=True)
    big = cart(background())

    png = big.resize((256, 256), Image.LANCZOS)
    png.save(os.path.join(OUT, 'mintcart-favicon.png'))

    sizes = [(16, 16), (32, 32), (48, 48)]
    big.resize((256, 256), Image.LANCZOS).save(os.path.join(OUT, 'mintcart-favicon.ico'), sizes=sizes)
    print('wrote', os.path.join(OUT, 'mintcart-favicon.png'), 'and .ico')


if __name__ == '__main__':
    main()
