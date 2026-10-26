#!/usr/bin/env python3
"""
Switch the demo data of the local store.

    python scripts/demo/apply.py home          # publishable home-goods demo (Pexels photos)
    python scripts/demo/apply.py electronics   # local-only electronics demo (third-party photos)

(or `npm run demo:home` / `npm run demo:electronics`.)

Either demo REPLACES the catalogue (categories, products, brands, reviews, badges,
home page blocks); orders and customers are left alone. Before applying, the whole
database is dumped to scripts/demo/.backups/ (git-ignored) so the previous state can
be restored. The database name and credentials come from upload/config.php.
"""

import os
import re
import subprocess
import sys
import tempfile
import time

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
BIN = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin'
SCRIPTS = {'home': 'build_demo.py', 'electronics': 'build_local_electronics.py'}


def config(name):
    text = open(os.path.join(ROOT, 'upload', 'config.php'), encoding='utf-8').read()
    m = re.search(r"define\('%s',\s*'([^']*)'\)" % name, text)
    return m.group(1) if m else ''


def main():
    if len(sys.argv) != 2 or sys.argv[1] not in SCRIPTS:
        sys.exit(__doc__)

    kind = sys.argv[1]
    db, user, password = config('DB_DATABASE'), config('DB_USERNAME'), config('DB_PASSWORD')
    auth = ['-u' + user] + (['-p' + password] if password else [])

    os.makedirs(os.path.join(HERE, '.backups'), exist_ok=True)
    backup = os.path.join(HERE, '.backups', '%s-before-%s-%s.sql' % (db, kind, time.strftime('%Y%m%d-%H%M%S')))
    out_sql = os.path.join(tempfile.gettempdir(), 'mintcart-demo-%s.sql' % kind)

    print('1/3 building the %s demo ...' % kind)
    out_uk = os.path.join(tempfile.gettempdir(), 'mintcart-demo-%s-uk.sql' % kind)
    subprocess.run([sys.executable, os.path.join(HERE, SCRIPTS[kind]), out_sql] + ([out_uk] if kind == 'home' else []), check=True)

    print('2/3 backing up database "%s" to %s ...' % (db, backup))
    with open(backup, 'wb') as fh:
        subprocess.run([BIN + '/mysqldump.exe'] + auth + ['--default-character-set=utf8mb4', db], stdout=fh, check=True)

    print('3/3 applying ...')
    with open(out_sql, 'rb') as fh:
        subprocess.run([BIN + '/mysql.exe'] + auth + ['--default-character-set=utf8mb4', db], stdin=fh, check=True)

    if kind == 'home':
        print('   + Ukrainian text (only lands if the uk-ua language is installed) ...')
        with open(out_uk, 'rb') as fh:
            subprocess.run([BIN + '/mysql.exe'] + auth + ['--default-character-set=utf8mb4', db], stdin=fh, check=True)

    print('done. To undo: mysql %s < "%s"' % (db, backup))


if __name__ == '__main__':
    main()
