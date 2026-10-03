#!/bin/sh
set -e

php artisan migrate --force

# Datos iniciales solo la primera vez (base vacia), para no duplicarlos en cada deploy.
if [ "$(php artisan tinker --execute='echo \App\Models\Sala::count();' | tail -n 1)" = "0" ]; then
    php artisan db:seed --force
fi

php artisan l5-swagger:generate

# Libera las butacas de reservas vencidas (reservas:liberar-vencidas, cada minuto).
php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}" --no-reload
