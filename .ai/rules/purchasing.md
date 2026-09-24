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

## OC: pendiente a recibir y a facturar, derivados del tipo de comprobante
Cada renglón de OC tiene dos circuitos independientes, ambos derivados de `purchase_order_voucher_imputations.quantity_applied` filtrando por `supplier_vouchers.type` (no anulados): remito → recibido, factura → facturado. NC y ND nunca se imputan a OC (`SupplierVoucherType::canImputeToPurchaseOrder()`). Usar `quantityPendingFor($type)` para validar e imputar, y el scope `PurchaseOrderItem::withImputedQuantities()` en listados/locks para evitar N+1.
La OC pasa a `cumplida` solo con doble condición (todas las líneas recibidas Y facturadas, `isFullyReceivedAndInvoiced()`), vía `EvaluatePurchaseOrderFulfillment`; nunca a mano. Así la factura y el remito se imputan en cualquier orden a una OC `emitida`.
