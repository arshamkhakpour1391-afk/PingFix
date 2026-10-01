#!/usr/bin/env python3
"""Validate root layout, nested theme, source manifest, assets and no data leaks."""
from pathlib import Path
from io import BytesIO
import hashlib
import json
import sys
import zipfile

archive_path = Path(sys.argv[1] if len(sys.argv) > 1 else 'artifacts/hamrah-shop-1.0.0.zip')
forbidden_parts = {'.cache', '.git', 'node_modules', 'tests', 'database', 'uploads', 'sample-data'}
forbidden_endings = {'.sql', '.sqlite', '.csv', '.db', '.log', '.env'}
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None, 'ZIP CRC'
    assert len(set(archive.namelist())) == len(archive.namelist()), 'Duplicate paths'
    for name in archive.namelist():
        p = Path(name)
        assert p.parts[0] == 'hamrah-shop' and '..' not in p.parts and not p.is_absolute(), name
        assert not (set(p.parts) & forbidden_parts), name
        assert p.suffix.lower() not in forbidden_endings and p.name != 'wp-config.php', name
    manifest = json.loads(archive.read('hamrah-shop/manifest.json'))
    assert set(archive.namelist()) == {'hamrah-shop/manifest.json'} | {'hamrah-shop/'+x for x in manifest['files']}, 'Unmanifested content'
    for name, digest in manifest['files'].items():
        assert hashlib.sha256(archive.read('hamrah-shop/'+name)).hexdigest() == digest, name
    entrypoint = archive.read('hamrah-shop/hamrah-shop.php').decode()
    assert 'Requires Plugins: woocommerce' in entrypoint
    for asset in ['assets/js/store.js', 'assets/css/store.css', 'assets/css/admin.css', 'docs/راهنمای-نصب.html', 'docs/گزارش-آزمون.html']:
        assert len(archive.read('hamrah-shop/'+asset)) > 100, asset
    with zipfile.ZipFile(BytesIO(archive.read('hamrah-shop/resources/hamrah-shop-theme.zip'))) as theme:
        assert theme.testzip() is None
        assert set(theme.namelist()) == {'hamrah-shop/'+x for x in manifest['theme_files']}, 'Theme manifest'
        for name, digest in manifest['theme_files'].items():
            assert hashlib.sha256(theme.read('hamrah-shop/'+name)).hexdigest() == digest, name
        for required in ['style.css', 'index.php', 'functions.php', 'header.php', 'footer.php', 'front-page.php', 'page.php', 'woocommerce.php', 'theme.json', 'assets/navigation.js', 'assets/css/theme.css', 'assets/fonts/OFL.txt', 'assets/image-unavailable.svg']:
            assert len(theme.read('hamrah-shop/'+required)) > 0, required
        assert not any('/woocommerce/' in name for name in theme.namelist()), 'Copied commerce template overrides'
        config = json.loads(theme.read('hamrah-shop/theme.json'))
        assert config['version'] == 3
print('PASS: installable plugin root, nested complete theme, all hashes/CRCs/assets, and no databases/fixtures/configurations.')
