#!/usr/bin/env bash
# Optional contributor setup: use sibling v2 sources instead of published packages.
set -euo pipefail
cd "$(dirname "$0")"
python3 - <<'PY'
import json
from pathlib import Path
p = Path('composer.json')
data = json.loads(p.read_text())
names = {'mathsgod/light-db': '../light-db-new', 'mathsgod/light-db-graphqlite-mappers': '../light-db-graphqlite-mappers'}
repositories = data.get('repositories', [])
if isinstance(repositories, dict):
    repositories = list(repositories.values())
repositories = [repo for repo in repositories if repo.get('url') not in names.values()]
local = []
for name, path in names.items():
    local.append({'type': 'path', 'url': path, 'options': {'versions': {name: '2.0.x-dev'}}})
    data['require'][name] = '^2.0@dev'
data['repositories'] = local + repositories
p.write_text(json.dumps(data, indent=4) + '\n')
PY
composer update mathsgod/light-db mathsgod/light-db-graphqlite-mappers --with-dependencies --minimal-changes --no-interaction
