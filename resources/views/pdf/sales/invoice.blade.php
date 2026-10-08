@php
    $money = fn (string|float|null $amount): string => '$ '.number_format((float) $amount, 2, ',', '.');
    $trimmed = fn (string $value, int $decimals): string => rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
    $isTypeA = $invoice->type === \App\Enums\Sales\InvoiceType::A;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->voucherLabel() }}</title>
    <style>{!! $stylesheet !!}</style>
</head>
<body>
    <div class="document">
        <div class="original-badge">Original</div>

        <table class="masthead-table">
            <tr>
                <td class="issuer-cell">
                    <h1 class="brand-name">{{ $issuer['name'] }}</h1>
                    <div class="brand-info">
                        <p><span class="field-label">Razón social:</span> {{ $issuer['name'] }}</p>
                        <p class="field-line"><span class="field-label">Domicilio comercial:</span> {{ $issuer['address'] }}</p>
                        <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $issuer['tax_condition'] }}</p>
                    </div>
                </td>
                <td class="letter-cell">
                    <div class="letter-box">
                        <div class="letter">{{ $invoice->type->value }}</div>
                        <div class="letter-code">COD. {{ $invoice->type->voucherCode() }}</div>
                    </div>
                </td>
                <td class="voucher-cell">
                    <h2 class="voucher-title">FACTURA</h2>
                    <p>
                        <span class="field-label">Punto de venta:</span>
                        <span class="voucher-number">{{ sprintf('%04d', $invoice->point_of_sale_number) }}</span>
                        &nbsp;
                        <span class="field-label">Comp. Nro:</span>
                        <span class="voucher-number">{{ sprintf('%08d', $invoice->number) }}</span>
                    </p>
                    <p class="field-line"><span class="field-label">Fecha de emisión:</span> {{ $invoice->issued_at->format('d/m/Y H:i') }}</p>
                    <p class="field-line"><span class="field-label">CUIT:</span> {{ $issuer['cuit'] }}</p>
                    <p class="field-line"><span class="field-label">Ingresos Brutos:</span> {{ $issuer['gross_income'] }}</p>
                    <p class="field-line"><span class="field-label">Inicio de actividades:</span> {{ $issuer['activity_start_date'] }}</p>
                </td>
            </tr>
        </table>

        <table class="customer-table">
            <tr>
                <td>
                    <div class="section-label">Cliente</div>
                    <p><span class="field-label">Apellido y nombre / Razón social:</span> {{ $invoice->customer_name }}</p>
                    <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $customerTaxCondition }}</p>
                </td>
                <td>
                    <div class="section-label">&nbsp;</div>
                    <p><span class="field-label">Documento:</span> {{ $customerDocument ?? 'Sin identificar' }}</p>
                    <p class="field-line"><span class="field-label">Domicilio:</span> {{ $invoice->customer_address ?? '-' }}</p>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="amount">Cant.</th>
                    <th>Descripción</th>
                    <th class="amount">Precio unit.</th>
                    @if ($isTypeA)
                        <th class="amount">Alíc. IVA</th>
                        <th class="amount">Subtotal neto</th>
                    @endif
                    <th class="amount">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="amount">{{ $trimmed($item->quantity, 3) }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="amount">{{ $money($item->unit_price) }}</td>
                        @if ($isTypeA)
                            <td class="amount">{{ $trimmed($item->vat_rate, 2) }} %</td>
                            <td class="amount">{{ $money($item->net_amount) }}</td>
                        @endif
                        <td class="amount">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td class="payments-cell">
                    <div class="section-label">Medios de pago</div>
                    <table class="payments-table">
                        @foreach ($payments as $payment)
                            <tr>
                                <td>
                                    {{ $payment->payment_method_name }}
                                    @if ($payment->tendered_amount !== null)
                                        <div class="payment-detail">
                                            Entregado: {{ $money($payment->tendered_amount) }}
                                            @if ($payment->change_amount !== null && (float) $payment->change_amount > 0)
                                                · Vuelto: {{ $money($payment->change_amount) }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="amount">{{ $money($payment->amount) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
                <td class="amounts-cell">
                    <table class="amounts-table">
                        @if ($isTypeA)
                            <tr>
                                <td>Importe neto gravado</td>
                                <td class="amount">{{ $money($invoice->net_amount) }}</td>
                            </tr>
                            @foreach ($vatBreakdown as $vat)
                                <tr>
                                    <td>IVA {{ $trimmed($vat['vat_rate'], 2) }} %</td>
                                    <td class="amount">{{ $money($vat['vat_amount']) }}</td>
                                </tr>
                            @endforeach
                        @endif
                        <tr class="total-row">
                            <td>Total</td>
                            <td class="amount">{{ $money($invoice->total_amount) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @unless ($isTypeA)
            <div class="transparency-note">
                <span class="field-label">Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</span><br>
                IVA contenido: {{ $money($invoice->vat_amount) }}
            </div>
        @endunless

        <div class="no-cae">
            Comprobante sin CAE — documento interno, no válido como factura hasta la integración con ARCA
        </div>

        <div class="footer-bar">
            Venta N° {{ $invoice->sale_id }} · Emitida por {{ $invoice->user?->name ?? '-' }}
        </div>
    </div>
</body>
</html>
