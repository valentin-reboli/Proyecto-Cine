#!/usr/bin/env bash
# Levanta el backend para los tests E2E con una base SQLite propia (no toca la base de desarrollo).
# Cada corrida arranca de cero: migraciones + seeders + un admin de prueba + docs de Swagger.
set -euo pipefail

cd "$(dirname "$0")/../../backend"

export APP_ENV=local
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/database/e2e.sqlite"
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export ADMIN_EMAIL=admin@e2e.test
export ADMIN_PASSWORD=admin-e2e-1234

rm -f "$DB_DATABASE"
touch "$DB_DATABASE"

php artisan migrate:fresh --seed --force
php artisan db:seed --class=AdminSeeder --force
php artisan l5-swagger:generate

# php -S directo (y no artisan serve) para que las variables de arriba lleguen al servidor.
export PHP_CLI_SERVER_WORKERS=4
cd public
exec php -q -S 127.0.0.1:8000 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
