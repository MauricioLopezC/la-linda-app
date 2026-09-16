---
paths:
  - 'app/Models/Purchasing/**'
---

# Purchasing

## Saldo de comprobantes: derivado, nunca columna
El saldo de un `SupplierVoucher` no se guarda. Se deriva en el modelo:
`pendingBalance()` (facturas y ND) = total − Σ payment_order_items.amount_applied − Σ NC aplicadas por `voucher_applications`;
`unappliedAmount()` (NC) = total − Σ voucher_applications.amount cuya source es la NC;
las ND no usan `voucher_applications`: su deuda solo baja por órdenes de pago;
`outstandingAmount()` despacha por tipo. Aritmética en centavos enteros (trait `ConvertsMoneyToCents`).
En listados usar el scope `withBalanceAggregates()` para evitar N+1 (adjunta los sumatorios como subselects; los métodos los detectan solos).
El `status` se recalcula con la Action `RecalculateVoucherBalanceStatus` (respeta `anulada` como terminal), nunca a mano.
