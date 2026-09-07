<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Compra {{ $order->order_number }}</title>
    <style>{!! $stylesheet !!}</style>
</head>
<body>
    <div class="document">
        <div class="header-badge">ORDEN DE COMPRA — DOCUMENTO DE GESTIÓN INTERNA</div>

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
                    <h2 class="order-title">ORDEN DE COMPRA</h2>
                    <p><span class="field-label">Nro. de orden:</span> <span class="order-number">{{ $order->order_number }}</span></p>
                    <p class="field-line"><span class="field-label">Fecha de emisión:</span> {{ $order->issue_date->format('d/m/Y') }}</p>
                    <p class="field-line"><span class="field-label">Fecha esperada entrega:</span> {{ $order->expected_delivery_date?->format('d/m/Y') ?? 'A convenir' }}</p>
                    <p class="field-line">
                        <span class="field-label">Estado:</span>
                        <span class="status-pill status-{{ $order->status->value }}">{{ $order->status->label() }}</span>
                    </p>
                    <p class="field-line"><span class="field-label">Condición de pago:</span> {{ $order->payment_terms ?? 'Cuenta corriente comercial' }}</p>
                </td>
            </tr>
        </table>

        <table class="parties-table">
            <tr>
                <td>
                    <div class="section-label">Proveedor adjudicado</div>
                    <p><span class="field-label">Razón social:</span> {{ $order->supplier->business_name }}</p>
                    <p class="field-line"><span class="field-label">CUIT:</span> {{ $supplierTaxId }}</p>
                    <p class="field-line"><span class="field-label">Condición fiscal:</span> {{ $order->supplier->tax_condition->label() }}</p>
                    <p class="field-line"><span class="field-label">Domicilio comercial:</span> {{ $order->supplier->address ?? 'No informado' }}</p>
                    @if($order->supplier->commercial_terms)
                        <p class="field-line"><span class="field-label">Términos pactados:</span> {{ $order->supplier->commercial_terms }}</p>
                    @endif
                </td>
                <td>
                    <div class="section-label">Lugar de entrega / Depósito receptor</div>
                    <p><span class="field-label">Depósito destino:</span> {{ $order->warehouse->name }}</p>
                    <p class="field-line"><span class="field-label">Sucursal:</span> {{ $order->warehouse->branch->name ?? 'Casa Central' }}</p>
                    @if($order->warehouse->branch?->address)
                        <p class="field-line"><span class="field-label">Dirección entrega:</span> {{ $order->warehouse->branch->address }}</p>
                    @endif
                    <p class="field-line"><span class="field-label">Emisor responsable:</span> {{ $order->user?->name ?? 'Administración de Compras' }}</p>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="code-col">Código</th>
                    <th class="desc-col">Descripción del artículo</th>
                    <th class="unit-col">U.M.</th>
                    <th class="qty-col amount">Cantidad</th>
                    <th class="price-col amount">Precio unit.</th>
                    <th class="total-col amount">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td class="code-col">{{ $item->article->internal_code }}</td>
                        <td class="desc-col">{{ $item->article->description }}</td>
                        <td class="unit-col">{{ $item->article->unitOfMeasure?->abbreviation ?? $item->article->unitOfMeasure?->name ?? 'u' }}</td>
                        <td class="qty-col amount">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                        <td class="price-col amount">$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                        <td class="total-col amount">$ {{ number_format((float) $item->line_total, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 16px;">Sin renglones registrados en la orden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-table">
                <tr>
                    <td class="notes-cell">
                        <div class="notes-title">Observaciones / Instrucciones para el proveedor:</div>
                        <p>{{ $order->notes ?? 'Sin observaciones particulares. Entregar en el horario habitual de recepción de mercadería con remito oficial.' }}</p>
                        @if($order->isCancelled() && $order->cancellation_reason)
                            <p style="margin-top: 8px; color: #991b1b; font-weight: bold;">
                                [ORDEN CANCELADA] Motivo: {{ $order->cancellation_reason }} ({{ $order->cancelled_at?->format('d/m/Y H:i') }})
                            </p>
                        @endif
                    </td>
                    <td class="total-cell">
                        <div class="total-title">Total Pactado de la Orden:</div>
                        <div class="total-value">$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</div>
                        <div class="tax-note">Suma neta de subtotales pactados. Impuestos y percepciones se aplicarán en el comprobante fiscal del proveedor.</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-bar">
            <span>Supermercados La Linda S.A. — Documento oficial de solicitud de compra</span>
            <span>Generado el {{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
