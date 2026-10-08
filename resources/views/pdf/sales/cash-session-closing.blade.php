@php
    $money = fn (string $amount): string => '$ '.number_format((float) $amount, 2, ',', '.');
    $difference = function (string $amount) use ($money): string {
        $cents = (int) round((float) $amount * 100);

        return match (true) {
            $cents === 0 => 'Cuadrado',
            $cents > 0 => 'Sobrante '.$money($amount),
            default => 'Faltante '.$money(ltrim($amount, '-')),
        };
    };
    $differenceClass = fn (string $amount): string => match (true) {
        (int) round((float) $amount * 100) === 0 => 'diff-balanced',
        (float) $amount > 0 => 'diff-surplus',
        default => 'diff-shortage',
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Rendición de caja · Turno #{{ $cashSession->id }}</title>
    <style>
        {!! $stylesheet !!}
        .closing-table th, .closing-table td { padding: 7px 6px; font-size: 8.5px; }
        .closing-table td.method { font-weight: 700; }
        .closing-table .batch { font-size: 7.5px; color: #475569; font-weight: 400; margin-top: 2px; }
        .closing-table tfoot td { border-top: 1.2px solid #111; font-weight: 700; background: #f8fafc; }
        .diff-balanced { color: #166534; font-weight: 700; }
        .diff-surplus { color: #92400e; font-weight: 700; }
        .diff-shortage { color: #991b1b; font-weight: 700; }
        .parties-table .count-table td { padding: 4px 8px; font-size: 9px; width: auto; border-right: none; border-bottom: 1px solid #e2e8f0; }
        .parties-table .count-table tr:last-child td { border-bottom: none; }
        .footer-table td { padding: 7px 14px; font-size: 8.5px; color: #64748b; }
        .signature-table td { padding: 40px 24px 10px; text-align: center; font-size: 9px; color: #334155; }
        .signature-line { border-top: 1px solid #111; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="document">
        <div class="header-badge">RENDICIÓN DE CAJA — CIERRE DE TURNO</div>

        <table class="masthead-table">
            <tr>
                <td class="brand-cell">
                    <h1 class="brand-name">{{ $company['name'] }}</h1>
                    <p class="brand-kicker">Supermercados La Linda · Salta, Argentina</p>
                    <div class="brand-info">
                        <p><span class="field-label">Sucursal:</span> {{ $cashSession->pointOfSale->warehouse->branch->name }}</p>
                        <p class="field-line"><span class="field-label">Caja:</span> {{ $cashSession->pointOfSale->number }}</p>
                        <p class="field-line"><span class="field-label">Cajero:</span> {{ $cashSession->user->name }}</p>
                    </div>
                </td>
                <td class="order-cell">
                    <h2 class="order-title">RENDICIÓN</h2>
                    <p><span class="field-label">Turno:</span> <span class="order-number">#{{ $cashSession->id }}</span></p>
                    <p class="field-line"><span class="field-label">Apertura:</span> {{ $cashSession->opened_at->format('d/m/Y H:i') }}</p>
                    <p class="field-line"><span class="field-label">Cierre:</span> {{ $cashSession->closed_at?->format('d/m/Y H:i') }}</p>
                    <p class="field-line"><span class="field-label">Resultado:</span>
                        <span class="{{ $differenceClass($totals['difference']) }}">{{ $difference($totals['difference']) }}</span>
                    </p>
                </td>
            </tr>
        </table>

        <table class="items-table closing-table">
            <thead>
                <tr>
                    <th>Medio de pago</th>
                    <th class="amount">Fondo inicial</th>
                    <th class="amount">Ventas</th>
                    <th class="amount">Ingresos</th>
                    <th class="amount">Egresos</th>
                    <th class="amount">Esperado</th>
                    <th class="amount">Declarado</th>
                    <th class="amount">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="method">
                            {{ $row['payment_method'] }}
                            @if ($row['batch_reference'])
                                <div class="batch">Lote POSNET {{ $row['batch_reference'] }}</div>
                            @endif
                        </td>
                        <td class="amount">{{ $money($row['opening_amount']) }}</td>
                        <td class="amount">{{ $money($row['sales_amount']) }}</td>
                        <td class="amount">{{ $money($row['income_amount']) }}</td>
                        <td class="amount">{{ $money($row['expense_amount']) }}</td>
                        <td class="amount">{{ $money($row['expected_amount']) }}</td>
                        <td class="amount">{{ $money($row['declared_amount']) }}</td>
                        <td class="amount {{ $differenceClass($row['difference']) }}">{{ $difference($row['difference']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="amount">{{ $money($totals['opening_amount']) }}</td>
                    <td class="amount">{{ $money($totals['sales_amount']) }}</td>
                    <td class="amount">{{ $money($totals['income_amount']) }}</td>
                    <td class="amount">{{ $money($totals['expense_amount']) }}</td>
                    <td class="amount">{{ $money($totals['expected_amount']) }}</td>
                    <td class="amount">{{ $money($totals['declared_amount']) }}</td>
                    <td class="amount {{ $differenceClass($totals['difference']) }}">{{ $difference($totals['difference']) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="parties-table">
            <tr>
                <td>
                    <div class="section-label">Conteo de efectivo</div>
                    @if ($cashSession->closingCounts->isEmpty())
                        <p>No se contaron billetes.</p>
                    @else
                        <table class="count-table">
                            @foreach ($cashSession->closingCounts as $count)
                                <tr>
                                    <td>{{ $count->denomination()->label() }}</td>
                                    <td class="amount" style="text-align: right;">× {{ $count->quantity }}</td>
                                    <td class="amount" style="text-align: right;">{{ $money(number_format($count->denomination()->value * $count->quantity, 2, '.', '')) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
                <td>
                    <div class="section-label">Observaciones</div>
                    <p>{!! nl2br(e($cashSession->closing_notes ?? 'Sin observaciones.')) !!}</p>
                </td>
            </tr>
        </table>

        <table class="signature-table">
            <tr>
                <td><div class="signature-line">Firma del cajero</div></td>
                <td><div class="signature-line">Firma de quien recibe</div></td>
            </tr>
        </table>

        <table class="footer-table">
            <tr>
                <td>{{ $company['name'] }} — Rendición de caja</td>
                <td style="text-align: right;">Generado el {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
