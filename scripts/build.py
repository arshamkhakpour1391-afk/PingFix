#!/usr/bin/env python3
"""Build the one uploadable WordPress plugin ZIP; no runtime/dev data allowed."""
from pathlib import Path
from io import BytesIO
import hashlib
import json
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'plugin' / 'hamrah-shop'
THEME = ROOT / 'theme' / 'hamrah-shop'
OUTPUT = ROOT / 'artifacts'
STAMP = (2026, 10, 1, 0, 0, 0)
ROOT_FILES = {'hamrah-shop.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'THIRD_PARTY.md'}
DIRECTORIES = {'includes', 'templates', 'assets', 'languages', 'docs', 'resources'}
FORBIDDEN_PARTS = {'.git', '.cache', 'node_modules', 'tests', 'sample-data', 'uploads', 'database', '__pycache__'}
FORBIDDEN_ENDINGS = {'.sql', '.sqlite', '.db', '.log', '.csv', '.env', '.map'}


def add(archive, name, data):
    item = zipfile.ZipInfo(name, STAMP)
    item.create_system = 3
    item.external_attr = 0o100644 << 16
    item.compress_type = zipfile.ZIP_DEFLATED
    archive.writestr(item, data, compresslevel=9)


def sources(directory):
    files = []
    for path in sorted(directory.rglob('*')):
        if not path.is_file():
            continue
        if path.is_symlink():
            raise RuntimeError(f'Symlink not permitted: {path}')
        rel = path.relative_to(directory)
        if set(rel.parts) & FORBIDDEN_PARTS or path.suffix.lower() in FORBIDDEN_ENDINGS:
            raise RuntimeError(f'Test/private/data artifact found: {path}')
        if path.name == 'wp-config.php':
            raise RuntimeError('Never distribute hosting configuration or credentials')
        files.append((rel.as_posix(), path.read_bytes()))
    return files


def main():
    version = re.search(r'^ \* Version: ([0-9.]+)$', (PLUGIN / 'hamrah-shop.php').read_text(), re.M).group(1)
    for needed in ('docs/راهنمای-نصب.html', 'docs/گزارش-آزمون.html', 'LICENSE', 'readme.txt', 'THIRD_PARTY.md'):
        if not (PLUGIN / needed).is_file() or not (PLUGIN / needed).stat().st_size:
            raise RuntimeError(f'Missing real deliverable: {needed}')
    theme_buffer = BytesIO()
    with zipfile.ZipFile(theme_buffer, 'w') as archive:
        for name, data in sources(THEME):
            add(archive, 'hamrah-shop/' + name, data)
    (PLUGIN / 'resources').mkdir(exist_ok=True)
    (PLUGIN / 'resources' / 'hamrah-shop-theme.zip').write_bytes(theme_buffer.getvalue())
    files = sources(PLUGIN)
    for name, _ in files:
        parts = Path(name).parts
        if not (name in ROOT_FILES or (len(parts) > 1 and parts[0] in DIRECTORIES)):
            raise RuntimeError(f'Unexpected production file: {name}')
    manifest = {
        'name': 'hamrah-shop', 'version': version,
        'wordpress_min': '6.8', 'php_min': '8.1', 'woocommerce_min': '10.0',
        'data_policy': 'No sample/demo business data or users. Existing real data is preserved.',
        'files': {name: hashlib.sha256(data).hexdigest() for name, data in files},
        'theme_files': {name: hashlib.sha256(data).hexdigest() for name, data in sources(THEME)},
    }
    OUTPUT.mkdir(exist_ok=True)
    destination = OUTPUT / f'hamrah-shop-{version}.zip'
    with zipfile.ZipFile(destination, 'w') as archive:
        for name, data in files:
            add(archive, 'hamrah-shop/' + name, data)
        add(archive, 'hamrah-shop/manifest.json', json.dumps(manifest, ensure_ascii=False, indent=2).encode('utf-8'))
    with zipfile.ZipFile(destination) as archive:
        if archive.testzip() is not None:
            raise RuntimeError('ZIP CRC verification failed')
    print(destination.relative_to(ROOT))
    print(f'Bytes: {destination.stat().st_size:,}')
    print('SHA256: ' + hashlib.sha256(destination.read_bytes()).hexdigest())


if __name__ == '__main__':
    main()
