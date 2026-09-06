"""Verify release metadata and a clean, installable theme archive."""
import re
import sys
import zipfile
from pathlib import Path
with zipfile.ZipFile(sys.argv[1]) as archive:
    files = set(archive.namelist())
    assert 'expert/' in files, 'explicit expert/ directory entry'
    for required in ('style.css', 'functions.php', 'index.php', 'screenshot.png', 'LICENSE', 'readme.txt', 'readme.md'):
        assert f'expert/{required}' in files, required
    assert all(name.startswith('expert/') for name in files)
    assert not any('/.git/' in name or '/tests/' in name or name.endswith('.zip') for name in files)
    css = archive.read('expert/style.css').decode()
    version = re.search(r'^Version:\s*(\S+)', css, re.M)[1]
    functions = archive.read('expert/functions.php').decode()
    assert re.search(r"EXPERT_VERSION',\s*'" + re.escape(version) + "'", functions)
    assert f'Stable tag: {version}' in archive.read('expert/readme.txt').decode()
    for name in files:
        if name.endswith('.css'):
            assert not re.search(r'\b[\d.]+px\b', archive.read(name).decode()), name
print(f'PASS: Theme ZIP structure, required files, version {version}, no development files or authored px units')
