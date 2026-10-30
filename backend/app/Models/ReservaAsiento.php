<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservaAsiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'reserva_id',
        'funcion_id',
        'asiento_id',
        'precio_unitario',
        'estado_bloqueo',
        'expira_en',
    ];

    protected $casts = [
        'expira_en' => 'datetime',
    ];

    // Libera las butacas temporales que ya vencieron y marca sus reservas como
    // expiradas. Lo corre el cron cada minuto y tambien se llama antes de
    // bloquear o pagar, para no depender de que el cron haya pasado.
    public static function liberarVencidas(?int $funcionId = null): array
    {
        $vencidas = static::where('estado_bloqueo', 'temporal')
            ->where('expira_en', '<=', now())
            ->when($funcionId, fn ($query) => $query->where('funcion_id', $funcionId))
            ->get(['id', 'reserva_id']);

        if ($vencidas->isEmpty()) {
            return [0, 0];
        }

        $reservaIds = $vencidas->pluck('reserva_id')->unique();

        static::whereIn('id', $vencidas->pluck('id'))->update(['estado_bloqueo' => 'liberado']);

        Reserva::whereIn('id', $reservaIds)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'expirada']);

        return [$vencidas->count(), $reservaIds->count()];
    }

    public function asiento()
    {
        return $this->belongsTo(Asiento::class);
    }
}
