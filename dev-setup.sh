#!/bin/bash
# Switch to local development mode (uses sibling ../light-db path)
# Run once after cloning: ./dev-setup.sh
# Git will ignore your local composer.json changes after this.

set -e

echo "→ Adding local path repository for light-db..."
composer config repositories.light-db '{"type":"path","url":"../light-db"}'
composer require mathsgod/light-db:@dev --no-update

echo "→ Running composer install..."
composer install

echo "→ Hiding composer.json changes from git..."
git update-index --skip-worktree composer.json

echo "✓ Dev mode active. composer.json now uses local ../light-db"
echo "  To release: run ./release.sh <version>"
