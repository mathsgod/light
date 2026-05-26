#!/bin/bash
# Release a new version of light
# Usage: ./release.sh <version>   e.g. ./release.sh 1.37.0

set -e

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Usage: ./release.sh <version>"
    exit 1
fi

LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

echo "→ Preparing release $VERSION (previous: ${LAST_TAG:-none})..."

# Temporarily un-hide composer.json so we can commit changes
git update-index --no-skip-worktree composer.json

# Update composer.json: remove path repo, set stable light-db version
python3 - <<'EOF'
import json, sys

with open('composer.json') as f:
    d = json.load(f)

# Remove path repositories
if 'repositories' in d:
    d['repositories'] = {k: v for k, v in d['repositories'].items() if v.get('type') != 'path'}
    if not d['repositories']:
        del d['repositories']

# Set stable light-db
if 'mathsgod/light-db' in d.get('require', {}):
    import subprocess
    # Get latest released version from Packagist
    result = subprocess.run(
        ['composer', 'show', 'mathsgod/light-db', '--no-interaction', '-q', '--format=json'],
        capture_output=True, text=True
    )
    d['require']['mathsgod/light-db'] = '^1.7.0'

with open('composer.json', 'w') as f:
    json.dump(d, f, indent=4, ensure_ascii=False)
    f.write('\n')

print("  composer.json updated to stable versions")
EOF

# Commit the release composer.json
git add composer.json
git commit -m "chore: release $VERSION"
git push

# Build release notes
if [ -n "$LAST_TAG" ]; then
    NOTES=$(git log ${LAST_TAG}..HEAD --pretty=format:'- %s')
else
    NOTES=$(git log --pretty=format:'- %s' | head -20)
fi

# Create GitHub release
echo "→ Creating GitHub release $VERSION..."
gh release create "$VERSION" --title "$VERSION" --notes "$NOTES" --target main

echo "✓ Released $VERSION"

# Restore local dev mode
echo "→ Restoring local dev setup..."
composer config repositories.light-db '{"type":"path","url":"../light-db"}'
python3 - <<'EOF'
import json

with open('composer.json') as f:
    d = json.load(f)

d.setdefault('repositories', {})['light-db'] = {"type": "path", "url": "../light-db"}
d.setdefault('require', {})['mathsgod/light-db'] = '@dev'

with open('composer.json', 'w') as f:
    json.dump(d, f, indent=4, ensure_ascii=False)
    f.write('\n')

print("  composer.json restored to @dev + path repo")
EOF

git update-index --skip-worktree composer.json
echo "✓ Local dev mode restored (git ignoring composer.json again)"
