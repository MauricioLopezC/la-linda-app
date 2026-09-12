<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Pago {{ $order->order_number }}</title>
    <style>{!! $stylesheet !!}</style>
</head>
<body>
    <div class="document">
        <div class="header-badge">ORDEN DE PAGO A PROVEEDOR — COMPROBANTE OFICIAL</div>

        <table class="masthead-table">
            <tr>
                <td class="brand-cell">
                    <h1 class="brand-name">{{ $company['name'] }}</h1>
                    <p class="brand-kicker">Supermercados La Linda · Salta, Argentina</p>
                    <div class="brand-info">
                        <p><span class="field-label">Razón social:</span> {{ $company['name'] }}</p>
                        <p class="field-line"><span class="field-label">CUIT:</span> {{ $company['tax_id'] }}</p>
                        <p class="field-line"><span class="field-label">Domicilio comercial:</span> {{ $company['address'] }}</p>
                        <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $company['tax_condition'] }}</p>
                    </div>
                </td>
                <td class="order-cell">
                    <h2 class="order-title">ORDEN DE PAGO</h2>
                    <p><span class="field-label">Nro. de orden:</span> <span class="order-number">{{ $order->order_number }}</span></p>
                    <p class="field-line"><span class="field-label">Fecha de emisión:</span> {{ $order->date->format('d/m/Y') }}</p>
                    <p class="field-line">
                        <span class="field-label">Estado:</span>
                        <span class="status-pill status-{{ $order->status->value }}">{{ $order->status->label() }}</span>
                    </p>
                    <p class="field-line"><span class="field-label">Emisor responsable:</span> {{ $order->user?->name ?? 'Administración' }}</p>
                </td>
            </tr>
        </table>

        <table class="parties-table">
            <tr>
                <td>
                    <div class="section-label">Proveedor</div>
                    <p><span class="field-label">Razón social:</span> {{ $order->supplier->business_name }}</p>
                    <p class="field-line"><span class="field-label">CUIT:</span> {{ $supplierTaxId }}</p>
                    <p class="field-line"><span class="field-label">Condición fiscal:</span> {{ $order->supplier->tax_condition->label() }}</p>
                </td>
                <td>
                    <div class="section-label">Observaciones</div>
                    <p>{{ $order->notes ?? 'Sin observaciones particulares.' }}</p>
                    @if($order->status === \App\Enums\Purchasing\PaymentOrderStatus::Cancelled)
                        <p style="margin-top: 8px; color: #991b1b; font-weight: bold;">
                            [ORDEN ANULADA]
                        </p>
                    @endif
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="code-col">Comprobante</th>
                    <th class="desc-col">N° Comprobante</th>
                    <th class="unit-col">Emisión</th>
                    <th class="qty-col amount">Monto Total</th>
                    <th class="total-col amount">Imputado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td class="code-col">{{ $item->voucher->type->label() }}</td>
                        <td class="desc-col">{{ $item->voucher->letter->value }} {{ str_pad($item->voucher->point_of_sale, 4, '0', STR_PAD_LEFT) }}-{{ str_pad($item->voucher->number, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="unit-col">{{ $item->voucher->issue_date->format('d/m/Y') }}</td>
                        <td class="qty-col amount">$ {{ number_format((float) $item->voucher->total_amount, 2, ',', '.') }}</td>
                        <td class="total-col amount">$ {{ number_format((float) $item->amount_applied, 2, ',', '.') }}
                            @if($item->voucher->type->isCreditNote())
                                (NC)
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b; padding: 16px;">Sin comprobantes imputados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="items-table" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th class="desc-col">Forma de pago</th>
                    <th class="code-col">Referencia</th>
                    <th class="total-col amount">Monto Aplicado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->paymentMethods as $pm)
                    <tr>
                        <td class="desc-col">{{ $pm->paymentMethod->name }}</td>
                        <td class="code-col">{{ $pm->reference ?? '-' }}</td>
                        <td class="total-col amount">$ {{ number_format((float) $pm->amount, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: #64748b; padding: 16px;">Sin medios de pago detallados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-table">
                <tr>
                    <td class="notes-cell">
                        <div class="notes-title">Total Neto a Pagar:</div>
                        <div class="tax-note">Este importe equivale a la suma de Facturas y Notas de Débito, menos las Notas de Crédito, y debe coincidir con la suma de las formas de pago.</div>
                    </td>
                    <td class="total-cell">
                        <div class="total-title">Importe Total:</div>
                        <div class="total-value">$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-bar">
            <span>Supermercados La Linda S.A. — Orden de Pago</span>
            <span>Generado el {{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
