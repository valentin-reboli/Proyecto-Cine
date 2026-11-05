@php
    $pesos = fn ($monto) => '$'.number_format((float) $monto, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de compra Nº {{ $c['reserva']['id'] }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0; color: #b91c1c; }
        h2 { font-size: 15px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .encabezado { width: 100%; border-bottom: 2px solid #b91c1c; padding-bottom: 8px; margin-bottom: 12px; }
        .encabezado td { vertical-align: bottom; }
        .datos { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .datos th { text-align: left; width: 32%; color: #6b7280; font-weight: normal; padding: 3px 0; }
        .datos td { padding: 3px 0; font-weight: bold; }
        .importes { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .importes td { padding: 4px 0; border-bottom: 1px solid #e5e7eb; }
        .importes td.monto { text-align: right; }
        .importes tr.total td { border-bottom: none; font-size: 14px; font-weight: bold; padding-top: 6px; }
        .qr { text-align: center; margin: 8px 0; }
        .qr img { width: 170px; height: 170px; }
        .codigo { font-family: DejaVu Sans Mono, monospace; font-size: 9px; color: #6b7280; }
        .regalo { border: 1px dashed #b91c1c; padding: 8px 10px; margin-top: 10px; }
        .regalo strong { font-size: 14px; letter-spacing: 1px; }
        .pie { margin-top: 12px; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td><h1>Cine Concordia</h1><span class="muted">Comprobante de compra</span></td>
            <td style="text-align: right;">
                <strong>Reserva Nº {{ $c['reserva']['id'] }}</strong><br>
                <span class="muted">{{ $c['pago']['fecha'] }}</span>
            </td>
        </tr>
    </table>

    <h2>{{ $c['pelicula'] }}</h2>
    <table class="datos">
        <tr><th>Función</th><td>{{ $c['funcion']['fecha'] }} · {{ $c['funcion']['hora'] }} hs</td></tr>
        <tr><th>Sala</th><td>{{ $c['funcion']['sala'] }} · {{ $c['funcion']['formato'] }}@if ($c['funcion']['idioma']) · {{ $c['funcion']['idioma'] }}@endif</td></tr>
        <tr><th>Butacas</th><td>{{ implode(', ', $c['butacas']) }}</td></tr>
        <tr><th>Comprador</th><td>{{ $c['comprador']['nombre'] }}<br><span class="muted" style="font-weight: normal;">{{ $c['comprador']['correo'] }}</span></td></tr>
    </table>

    <table class="importes">
        <tr>
            <td>Entradas ({{ count($c['butacas']) }})</td>
            <td class="monto">{{ $pesos($c['importes']['entradas']) }}</td>
        </tr>
        @foreach ($c['combos'] as $combo)
            <tr>
                <td>{{ $combo['cantidad'] }} × {{ $combo['nombre'] }}</td>
                <td class="monto">{{ $pesos($combo['cantidad'] * $combo['precio_unitario']) }}</td>
            </tr>
        @endforeach
        @if ($c['descuento_aplicado'])
            <tr>
                <td>Descuento {{ $c['descuento_aplicado']['porcentaje'] }}% ({{ $c['descuento_aplicado']['codigo'] }})</td>
                <td class="monto">-{{ $pesos($c['importes']['descuento']) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total pagado</td>
            <td class="monto">{{ $pesos($c['importes']['total']) }}</td>
        </tr>
    </table>

    <div class="qr">
        <img src="{{ $qr }}" alt="Código QR de la entrada">
        <div>Presentá este código QR en la entrada de la sala.</div>
        <div class="codigo">{{ $c['reserva']['codigo'] }}</div>
    </div>

    @if ($c['descuento'])
        <div class="regalo">
            Tu regalo: <strong>{{ $c['descuento']['codigo'] }}</strong><br>
            {{ $c['descuento']['porcentaje'] }}% de descuento en tu próxima compra con este correo. Válido hasta el {{ $c['descuento']['vence'] }}.
        </div>
    @endif

    <div class="pie">Pago procesado con Mercado Pago en modo prueba. Este comprobante no tiene validez fiscal.</div>
</body>
</html>
