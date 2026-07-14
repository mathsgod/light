#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${LIGHT_TEST_MYSQL_PORT:-3307}"
JWT_SECRET="${JWT_SECRET:-local_test_jwt_secret_key_$(openssl rand -hex 16)}"

cleanup() {
  echo "Stopping MySQL container..."
  docker compose -f compose.yml down -v --remove-orphans 2>/dev/null || true
}
trap cleanup EXIT

echo "Starting MySQL on host port ${PORT}..."
LIGHT_TEST_MYSQL_PORT="${PORT}" docker compose -f compose.yml up -d --wait

export DATABASE_HOSTNAME=127.0.0.1
export DATABASE_DATABASE=light_test
export DATABASE_USERNAME=root
export DATABASE_PASSWORD=test_password
export DATABASE_PORT="${PORT}"
export JWT_SECRET="${JWT_SECRET}"
export TZ=UTC

echo "Creating database schema..."
php -d variables_order=EGPCS tests/ci-setup.php

echo "Running PHPUnit..."
php -d variables_order=EGPCS ./vendor/bin/phpunit --no-coverage "$@"
