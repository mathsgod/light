#!/usr/bin/env bash
# Develop against sibling light-auth without changing the distributable manifest/lock.
set -euo pipefail
cd "$(dirname "$0")/.."
python3 - <<'PY'
import json
from pathlib import Path
from shutil import copyfile

root = Path.cwd()
source = root.parent / 'light-auth'
if not (source / 'composer.json').is_file():
    raise SystemExit('Expected sibling ../light-auth checkout')
manifest = json.loads((root / 'composer.json').read_text())
repositories = manifest.get('repositories', [])
if isinstance(repositories, dict):
    repositories = list(repositories.values())
manifest['repositories'] = [{
    'type': 'path', 'url': '../light-auth',
    'options': {'symlink': True, 'versions': {'mathsgod/light-auth': 'dev-main'}},
}] + repositories
(root / '.composer-light-auth-dev.json').write_text(json.dumps(manifest, indent=4) + '\n')
lock = root / '.composer-light-auth-dev.lock'
if not lock.exists():
    copyfile(root / 'composer.lock', lock)
PY
COMPOSER=.composer-light-auth-dev.json composer update mathsgod/light-auth --with-dependencies --minimal-changes --no-interaction
