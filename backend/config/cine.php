<?php

return [
    // Descuento que se regala con cada compra para la proxima (porcentaje y dias de vigencia).
    'descuento_porcentaje' => (int) env('DESCUENTO_PORCENTAJE', 10),
    'descuento_vigencia_dias' => (int) env('DESCUENTO_VIGENCIA_DIAS', 30),

    // Minutos sin usar el panel tras los cuales la sesion del admin se cierra sola (4 horas).
    'admin_inactividad_minutos' => (int) env('ADMIN_INACTIVIDAD_MINUTOS', 240),
];
