#!/bin/sh

# Si la base no responde, cortar a los 10 segundos en vez de quedar colgado.
export PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT:-10}"

# Migraciones y datos iniciales.
preparar_base() {
    php artisan migrate --force || return 1

    # Datos iniciales solo la primera vez (base vacia), para no duplicarlos en cada deploy.
    if [ "$(php artisan tinker --execute='echo \App\Models\Sala::count();' | tail -n 1)" = "0" ]; then
        php artisan db:seed --force || return 1
    fi

    # Admin definido con ADMIN_EMAIL / ADMIN_PASSWORD (no hace nada si no estan cargadas).
    php artisan db:seed --class=AdminSeeder --force
}

# La base se prepara en segundo plano: la API levanta igual y, si algo falla,
# /api/estado muestra el error en vez de que el sitio quede sin responder.
(
    intentos=1
    until preparar_base; do
        if [ "$intentos" -ge 5 ]; then
            echo "ERROR: no se pudo preparar la base de datos. Revisar DB_CONNECTION y DB_URL; detalle en /api/estado." >&2
            break
        fi
        intentos=$((intentos + 1))
        echo "La base no respondio, reintento $intentos de 5..." >&2
        sleep 5
    done

    php artisan l5-swagger:generate || echo "ERROR: no se pudo generar la documentacion de Swagger." >&2
) &

# Libera las butacas de reservas vencidas (reservas:liberar-vencidas, cada minuto).
php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}" --no-reload
