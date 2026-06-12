#!/usr/bin/env bash
set -e
[ -f .env ] || cp .env.example .env
composer install
php artisan key:generate
php artisan storage:link || true
npm install
npm run build
echo "Install complete. Import database/angelprintshop.sql into MySQL, confirm .env DB details, then run: php artisan serve"
