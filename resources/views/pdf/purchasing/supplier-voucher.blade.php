<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->type->label() }} {{ $voucher->letter->value }} {{ $voucher->point_of_sale }}-{{ $voucher->number }}</title>
    <style>{!! $stylesheet !!}</style>
</head>
<body>
    <div class="document">
        <div class="copy-label">ORIGINAL</div>

        <table class="masthead-table">
            <tr>
                <td class="brand-cell">
                    <h1 class="brand-name">{{ $company['name'] }}</h1>
                    <p class="brand-kicker">Gestión de compras · Salta, Argentina</p>
                    <div class="brand-note">
                        <p><span class="field-label">Razón social:</span> {{ $company['name'] }}</p>
                        <p class="field-line"><span class="field-label">Domicilio comercial:</span> {{ $company['address'] }}</p>
                        <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $company['tax_condition'] }}</p>
                    </div>
                </td>
                <td class="letter-cell">
                    <div class="letter-value">{{ $voucher->letter->value }}</div>
                    <div class="letter-code">REG. INTERNO</div>
                </td>
                <td class="voucher-cell">
                    <h2 class="voucher-title">{{ $voucher->type->label() }} {{ $voucher->letter->value }}</h2>
                    <p><span class="field-label">Punto de venta:</span> <span class="fiscal-number">{{ $voucher->point_of_sale }}</span></p>
                    <p class="field-line"><span class="field-label">Nro. comprobante:</span> <span class="fiscal-number">{{ $voucher->number }}</span></p>
                    <p class="field-line"><span class="field-label">Fecha de emisión:</span> {{ $voucher->issue_date->format('d/m/Y') }}</p>
                    <p class="field-line"><span class="field-label">CUIT receptor:</span> {{ $company['tax_id'] }}</p>
                    <p class="field-line muted">Registro interno #{{ $voucher->id }}</p>
                </td>
            </tr>
        </table>

        <table class="dates-table">
            <tr>
                <td><span class="field-label">Fecha de emisión:</span> {{ $voucher->issue_date->format('d/m/Y') }}</td>
                <td><span class="field-label">Fecha de vencimiento:</span> {{ $voucher->due_date?->format('d/m/Y') ?? 'No informada' }}</td>
                <td><span class="field-label">Moneda:</span> Pesos argentinos (ARS)</td>
            </tr>
        </table>

        <table class="parties-table">
            <tr>
                <td>
                    <div class="section-label">Proveedor emisor</div>
                    <p><span class="field-label">Razón social:</span> {{ $voucher->supplier->business_name }}</p>
                    <p class="field-line"><span class="field-label">CUIT:</span> {{ $supplierTaxId }}</p>
                    <p class="field-line"><span class="field-label">Domicilio:</span> {{ $voucher->supplier->address ?? 'No informado' }}</p>
                    <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $voucher->supplier->tax_condition->label() }}</p>
                </td>
                <td>
                    <div class="section-label">Receptor / registro interno</div>
                    <p><span class="field-label">Razón social:</span> {{ $company['name'] }}</p>
                    <p class="field-line"><span class="field-label">CUIT:</span> {{ $company['tax_id'] }}</p>
                    <p class="field-line"><span class="field-label">Domicilio:</span> {{ $company['address'] }}</p>
                    <p class="field-line"><span class="field-label">Condición frente al IVA:</span> {{ $company['tax_condition'] }}</p>
                </td>
            </tr>
        </table>

        <table class="concept-table">
            <thead>
                <tr>
                    <th>Concepto / detalle del registro</th>
                    <th class="amount">Importe total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="concept-description">
                        <strong>{{ $voucher->type->label() }} de proveedor</strong><br>
                        <span class="notes">{{ $voucher->notes ?? 'Sin observaciones informadas.' }}</span>
                    </td>
                    <td class="amount">$ {{ number_format((float) $voucher->total_amount, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-wrap">
            <div class="internal-state">
                <p><span class="field-label">Estado:</span> {{ $voucher->status->label() }}</p>
                <p><span class="field-label">Saldo pendiente:</span> $ {{ number_format((float) $voucher->pendingBalance(), 2, ',', '.') }}</p>
                <p class="muted">Los estados y saldos se calculan automáticamente por el sistema.</p>
            </div>

            <table class="summary-table">
                <tr><th>Importe neto gravado: $</th><td>{{ number_format((float) $voucher->net_amount, 2, ',', '.') }}</td></tr>
                <tr><th>{{ $voucher->letter->discriminatesVat() ? 'IVA 21%' : 'IVA discriminado' }}: $</th><td>{{ number_format((float) $voucher->vat_amount, 2, ',', '.') }}</td></tr>
                <tr><th>Otros tributos: $</th><td>{{ number_format((float) $voucher->other_taxes_amount, 2, ',', '.') }}</td></tr>
                <tr class="total-row"><th>Importe total: $</th><td>{{ number_format((float) $voucher->total_amount, 2, ',', '.') }}</td></tr>
            </table>
        </div>

        <table class="footer-table">
            <tr>
                <td class="footer-brand">LA LINDA</td>
                <td class="footer-page">Pág. 1/1</td>
                <td class="footer-disclaimer">
                    Constancia interna generada el {{ now()->format('d/m/Y H:i') }}.<br>
                    No reemplaza ni modifica el comprobante fiscal original emitido por el proveedor.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
