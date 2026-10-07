#!/bin/bash
# Switch to local development mode (uses sibling light-db and light-oauth2 paths)
# Run once after cloning: ./dev-setup.sh
# Git will ignore your local composer.json changes after this.

set -e

echo "→ Adding local path repository for light-db..."
composer config repositories.light-db '{"type":"path","url":"../light-db"}'
composer require mathsgod/light-db:@dev --no-update

echo "→ Adding local path repository for light-oauth2..."
composer config repositories.light-oauth2 '{"type":"path","url":"../light-oauth2","options":{"symlink":true}}'
composer require mathsgod/light-oauth2:@dev --no-update

# light-oauth2 depends on a stable Light version; identify this dev checkout
# by its latest release while resolving the circular root dependency.
LIGHT_DEV_ROOT_VERSION=${COMPOSER_ROOT_VERSION:-$(git describe --tags --abbrev=0)}
LIGHT_DEV_ROOT_VERSION=${LIGHT_DEV_ROOT_VERSION#v}
echo "→ Updating local development dependencies..."
COMPOSER_ROOT_VERSION="$LIGHT_DEV_ROOT_VERSION" composer update mathsgod/light-db mathsgod/light-oauth2 --with-dependencies --minimal-changes --no-interaction

echo "→ Hiding composer.json changes from git..."
git update-index --skip-worktree composer.json

echo "✓ Dev mode active. composer.json now uses local light-db and light-oauth2"
echo "  To release: run ./release.sh <version>"
