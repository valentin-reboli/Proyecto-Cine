<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservas:liberar-vencidas')->everyMinute();
Schedule::command('comprobantes:reenviar')->everyTenMinutes();
