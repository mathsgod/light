#!/bin/bash
# Release a new version of light
# Usage: ./release.sh <version>   e.g. ./release.sh v2.0.0

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
import json

with open('composer.json') as f:
    d = json.load(f)

# Remove path repositories
if 'repositories' in d:
    repos = d['repositories']
    d['repositories'] = ([v for v in repos if v.get('type') != 'path']
                         if isinstance(repos, list)
                         else {k: v for k, v in repos.items() if v.get('type') != 'path'})
    if not d['repositories']:
        del d['repositories']

# Set stable light-db
if 'mathsgod/light-db' in d.get('require', {}):
    d['require']['mathsgod/light-db'] = '^2.2.1'
    d['require']['mathsgod/light-db-graphqlite-mappers'] = '^2.0'

with open('composer.json', 'w') as f:
    json.dump(d, f, indent=4, ensure_ascii=False)
    f.write('\n')

print("  composer.json updated to stable versions")
EOF

# Resolve published packages before creating a release; missing v2 tags fail here.
composer update mathsgod/light-db mathsgod/light-db-graphqlite-mappers --with-dependencies --minimal-changes --no-interaction

# Commit the release manifests
git add composer.json composer.lock
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
gh release create "$VERSION" --title "$VERSION" --notes "$NOTES" --target "$(git rev-parse HEAD)"

echo "✓ Released $VERSION"

# Restore the sibling v2 source packages for continued development.
./dev-setup.sh
echo "✓ Local v2 development setup restored"
