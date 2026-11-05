<?php

namespace App\Services;

use App\Models\Reserva;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

// Datos del comprobante de una compra pagada y su version en PDF.
// Los mismos datos se le mandan a n8n para el correo de confirmacion (ver CompraNotifier).
class ComprobanteService
{
    public function __construct(private QrService $qr) {}

    public function datos(Reserva $reserva): array
    {
        $reserva->loadMissing([
            'funcion.pelicula', 'funcion.sala', 'funcion.formato', 'reservaAsientos.asiento',
            'descuentoGenerado', 'descuentoAplicado', 'combos', 'pagos',
        ]);

        $funcion = $reserva->funcion;
        $momento = Carbon::parse($funcion->fecha_hora);
        $butacas = $reserva->reservaAsientos->map(fn ($ra) => $ra->asiento->fila.$ra->asiento->numero)->sort()->values();
        $pago = $reserva->pagos->firstWhere('estado', 'aprobado');
        $regalo = $reserva->descuentoGenerado;
        $aplicado = $reserva->descuentoAplicado;
        $combosTotal = (float) $reserva->combos_total;
        $descuentoMonto = (float) $reserva->descuento_monto;
        $total = (float) $reserva->total;
        $base = rtrim(config('app.url'), '/').'/api/entradas';

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
            'butacas' => $butacas->all(),
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
            // Codigo de regalo para la proxima compra.
            'descuento' => $regalo ? [
                'codigo' => $regalo->codigo,
                'porcentaje' => $regalo->porcentaje,
                'vence' => $regalo->vence_en->format('d/m/Y'),
            ] : null,
            'pago' => [
                'metodo' => $pago?->metodo,
                // pagado_en se guarda en UTC; se muestra en hora argentina.
                'fecha' => $pago?->pagado_en ? Carbon::parse($pago->pagado_en)->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') : null,
            ],
            // APP_URL (no el host del request): detras de Caddy el host que ve Laravel es interno.
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
