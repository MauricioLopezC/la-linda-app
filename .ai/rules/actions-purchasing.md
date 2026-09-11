---
paths:
  - 'app/Actions/Purchasing/**'
---

# Actions Purchasing

## NC→factura: asociación solo en el alta (HU-054)
La vinculación de una nota de crédito a una factura (modelo mixto de HU-054) ocurre SOLO durante el alta de la NC: `StoreSupplierVoucherRequest` acepta `associated_invoice_id` + `associated_amount` (con `prohibited_unless:type,nota_credito`), y `CreateSupplierVoucher` delega en `AssociateCreditNoteToInvoice` dentro de su transacción. No hay pantalla ni ruta de reimputación posterior; una NC libre o su remanente se compensa recién en la orden de pago (HU-027).

`AssociateCreditNoteToInvoice` es el único camino de escritura de `voucher_applications`: transacción con `lockForUpdate` sobre NC y factura, valida mismo proveedor, target = factura no anulada, e importe ≤ min(saldo factura, disponible NC); luego `RecalculateVoucherBalanceStatus` sobre ambos. La fila es inmutable (sin `updated_at`, sin update/delete): un error se corrige anulando la NC.
