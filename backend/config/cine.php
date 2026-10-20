<?php

return [
    // Descuento que se regala con cada compra para la proxima (porcentaje y dias de vigencia).
    'descuento_porcentaje' => (int) env('DESCUENTO_PORCENTAJE', 10),
    'descuento_vigencia_dias' => (int) env('DESCUENTO_VIGENCIA_DIAS', 30),
];
