<?php

namespace App\Services;

use App\Models\Reserva;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

// Datos del comprobante de una compra pagada y su version en PDF.
// Los mismos datos se le mandan a n8n para el correo de confirmacion (ver CompraNotifier).
class ComprobanteService
{
    // Las fechas que se guardan con now() estan en UTC; al comprador se le muestran en hora argentina.
    public const ZONA = 'America/Argentina/Buenos_Aires';

    public function __construct(private QrService $qr) {}

    // Datos de la compra que no dependen de que este pagada (los usan todos los avisos a n8n).
    public function resumen(Reserva $reserva): array
    {
        $reserva->loadMissing([
            'funcion.pelicula', 'funcion.sala', 'funcion.formato', 'reservaAsientos.asiento',
            'descuentoAplicado', 'combos',
        ]);

        $funcion = $reserva->funcion;
        $momento = Carbon::parse($funcion->fecha_hora);
        $aplicado = $reserva->descuentoAplicado;
        $combosTotal = (float) $reserva->combos_total;
        $descuentoMonto = (float) $reserva->descuento_monto;
        $total = (float) $reserva->total;

        return [
            'reserva' => [
                'id' => $reserva->id,
                'codigo' => $reserva->codigo_qr,
            ],
            'comprador' => [
                'nombre' => $reserva->invitado_nombre,
                'correo' => $reserva->invitado_correo,
            ],
            'pelicula' => $funcion->pelicula->titulo,
            'funcion' => [
                'fecha' => $momento->format('d/m/Y'),
                'hora' => $momento->format('H:i'),
                'fecha_hora' => $momento->format('Y-m-d\TH:i:s'),
                'sala' => $funcion->sala->nombre,
                'formato' => $funcion->formato->nombre,
                'idioma' => $funcion->idioma,
            ],
            'butacas' => $reserva->reservaAsientos->map(fn ($ra) => $ra->asiento->fila.$ra->asiento->numero)->sort()->values()->all(),
            'combos' => $reserva->combos->map(fn ($c) => [
                'nombre' => $c->nombre,
                'cantidad' => $c->cantidad,
                'precio_unitario' => (float) $c->precio_unitario,
            ])->values()->all(),
            'importes' => [
                'entradas' => round($total - $combosTotal + $descuentoMonto, 2),
                'combos' => $combosTotal,
                'descuento' => $descuentoMonto,
                'total' => $total,
            ],
            // Codigo que uso el comprador en esta compra.
            'descuento_aplicado' => $aplicado ? [
                'codigo' => $aplicado->codigo,
                'porcentaje' => $aplicado->porcentaje,
                'monto' => $descuentoMonto,
            ] : null,
            'sitio_url' => rtrim(config('app.url'), '/'),
        ];
    }

    // Datos completos del comprobante de una compra pagada.
    public function datos(Reserva $reserva): array
    {
        $reserva->loadMissing(['descuentoGenerado', 'pagos']);

        $pago = $reserva->pagos->firstWhere('estado', 'aprobado');
        $regalo = $reserva->descuentoGenerado;
        // APP_URL (no el host del request): detras de Caddy el host que ve Laravel es interno.
        $base = rtrim(config('app.url'), '/').'/api/entradas';

        return [
            ...$this->resumen($reserva),
            // Codigo de regalo para la proxima compra.
            'descuento' => $regalo ? [
                'codigo' => $regalo->codigo,
                'porcentaje' => $regalo->porcentaje,
                'vence' => $regalo->vence_en->format('d/m/Y'),
            ] : null,
            'pago' => [
                'metodo' => $pago?->metodo,
                // pagado_en se guarda en UTC; se muestra en hora argentina.
                'fecha' => $pago?->pagado_en ? Carbon::parse($pago->pagado_en)->setTimezone(self::ZONA)->format('d/m/Y H:i') : null,
            ],
            'qr_url' => "{$base}/qr/{$reserva->codigo_qr}.png",
            'comprobante_pdf_url' => "{$base}/comprobante/{$reserva->codigo_qr}.pdf",
        ];
    }

    public function pdf(Reserva $reserva): string
    {
        return Pdf::loadView('pdf.comprobante', [
            'c' => $this->datos($reserva),
            'qr' => $this->qr->dataUri($reserva->codigo_qr),
        ])->setPaper('a5')->output();
    }

    public function nombreArchivo(Reserva $reserva): string
    {
        return 'comprobante-cine-concordia-'.$reserva->id.'.pdf';
    }
}
