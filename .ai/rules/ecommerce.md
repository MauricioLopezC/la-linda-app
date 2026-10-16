---
paths:
  - 'app/Actions/Ecommerce/**'
---

# Ecommerce

## Reservas de stock antes de habilitar el pago online
HU-048: reservar stock dentro de una transacción antes de crear el checkout, bloqueando pedido, artículos ordenados y sus saldos. El plazo por defecto es 15 minutos (ECOMMERCE_RESERVATION_MINUTES). Una reserva vencida sigue descontando disponibilidad hasta que ReconcileWebOrderReservation cierre los checkouts y verifique todos los pagos: conservarla ante estados intermedios o errores remotos. Cada renovación usa una referencia pedido:UUID para que una reconciliación vieja no cierre un checkout nuevo. No reintentar automáticamente el POST de creación de preferencias: ante un timeout se desconoce su resultado; recuperar por referencia y reutilizar el checkout vigente.
