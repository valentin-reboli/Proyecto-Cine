<?php

namespace App\Services;

use App\Models\Descuento;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

// Aplica a una reserva el estado de un pago consultado en Mercado Pago. Lo usan el
// cobro con tarjeta, la vuelta de Checkout Pro y el webhook, asi que puede llegar
// mas de una vez el mismo pago: se guarda por su id de Mercado Pago y se procesa una sola vez.
class PagoService
{
    public function __construct(
        private MercadoPagoService $mercadoPago,
        private DescuentoService $descuentos,
        private CompraNotifier $notificador,
    ) {}

    // Devuelve el Pago guardado. Su estado es aprobado, rechazado, pendiente o reembolsado.
    public function registrar(Reserva $reserva, array $mp): Pago
    {
        // Nunca se toca (ni se reembolsa) un pago que no es de esta reserva.
        if ((string) $mp['external_reference'] !== (string) $reserva->id) {
            throw new InvalidArgumentException('El pago '.$mp['id'].' no corresponde a la reserva '.$reserva->id.'.');
        }

        // Si se termino el tiempo pero el cron todavia no paso, se libera ahora.
        ReservaAsiento::liberarVencidas($reserva->funcion_id);

        [$pago, $confirmada, $reembolsar] = DB::transaction(function () use ($reserva, $mp) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->first();
            $pago = Pago::where('mp_pago_id', $mp['id'])->first() ?? new Pago([
                'reserva_id' => $reserva->id,
                'mp_pago_id' => $mp['id'],
                'monto' => $mp['transaction_amount'],
            ]);

            // Un pago ya cerrado no se vuelve a procesar (el webhook puede llegar varias veces).
            if (in_array($pago->estado, ['aprobado', 'reembolsado'], true)) {
                return [$pago, false, false];
            }

            $pago->fill([
                'metodo' => $mp['payment_method_id'] ?? 'mercadopago',
                'detalle' => $mp['status_detail'],
            ]);

            if ($mp['status'] !== 'approved') {
                $pago->estado = match ($mp['status']) {
                    'pending', 'in_process', 'authorized' => 'pendiente',
                    'refunded', 'charged_back' => 'reembolsado',
                    default => 'rechazado',
                };
                $pago->save();

                return [$pago, false, false];
            }

            $motivo = $this->motivoParaNoConfirmar($reserva, $mp);

            if ($motivo) {
                // Se cobro pero la compra no se puede completar: se devuelve el dinero.
                $pago->fill(['estado' => 'reembolsado', 'detalle' => $motivo, 'pagado_en' => now()])->save();

                return [$pago, false, true];
            }

            ReservaAsiento::where('reserva_id', $reserva->id)->update(['estado_bloqueo' => 'ocupado']);

            if ($reserva->descuento_id) {
                Descuento::whereKey($reserva->descuento_id)->update(['usado_en' => now()]);
            }

            $reserva->update([
                'estado' => 'pagada',
                'codigo_qr' => (string) Str::uuid(),
            ]);

            $pago->fill(['estado' => 'aprobado', 'pagado_en' => now()])->save();

            return [$pago, true, false];
        }, 3);

        if ($reembolsar) {
            $this->reembolsar($pago);
        }

        if ($confirmada) {
            // Descuento de regalo para la proxima compra + comprobante y QR por mensaje (n8n).
            $reserva = $reserva->fresh();
            $this->descuentos->generarPara($reserva);
            $this->notificador->enviar($reserva->fresh());
        }

        return $pago;
    }

    // Controles antes de dar por buena una compra con un pago aprobado.
    private function motivoParaNoConfirmar(Reserva $reserva, array $mp): ?string
    {
        if (abs($mp['transaction_amount'] - $this->mercadoPago->montoACobrar($reserva)) > 0.01) {
            return 'monto_incorrecto';
        }

        if ($reserva->estado !== 'pendiente') {
            // Vencio el bloqueo (expirada) o ya estaba pagada con otro pago.
            return 'reserva_'.$reserva->estado;
        }

        // Solo se ocupan butacas cuyo bloqueo sigue vigente.
        $total = ReservaAsiento::where('reserva_id', $reserva->id)->count();
        $vigentes = ReservaAsiento::where('reserva_id', $reserva->id)
            ->where('estado_bloqueo', 'temporal')
            ->where('expira_en', '>', now())
            ->count();

        if ($total === 0 || $vigentes !== $total) {
            return 'bloqueo_vencido';
        }

        // El codigo de descuento se consume recien al pagar (y una sola vez).
        if ($reserva->descuento_id && Descuento::whereKey($reserva->descuento_id)->whereNotNull('usado_en')->exists()) {
            return 'descuento_usado';
        }

        return null;
    }

    private function reembolsar(Pago $pago): void
    {
        try {
            $this->mercadoPago->reembolsar($pago->mp_pago_id);
        } catch (Throwable $e) {
            // Queda registrado para devolverlo a mano desde el panel de Mercado Pago.
            Log::error('No se pudo reembolsar el pago '.$pago->mp_pago_id.' de Mercado Pago: '.$e->getMessage());
        }
    }
}
