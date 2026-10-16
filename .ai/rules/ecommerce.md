---
paths:
  - 'app/Actions/Ecommerce/**'
---

# Ecommerce

## Reservas de stock antes de habilitar el pago online
HU-048: reservar stock dentro de una transacción antes de crear el checkout, bloqueando pedido, artículos ordenados y sus saldos. El plazo por defecto es 15 minutos (ECOMMERCE_RESERVATION_MINUTES). Una reserva vencida sigue descontando disponibilidad hasta que ReconcileWebOrderReservation cierre los checkouts y verifique todos los pagos: conservarla ante estados intermedios o errores remotos. Cada renovación usa una referencia pedido:UUID para que una reconciliación vieja no cierre un checkout nuevo. No reintentar automáticamente el POST de creación de preferencias: ante un timeout se desconoce su resultado; recuperar por referencia y reutilizar el checkout vigente.

## Reservas vencidas se reconcilian bajo demanda, sin scheduler
La app de producción es una demo que hiberna en Laravel Cloud cuando nadie la usa; no agregar tareas a Schedule (Cloud la despierta para cada tarea y queda activa todo el sleep timeout; everyMinute impide hibernar). Las reservas vencidas las libera el middleware Ecommerce\ReconcileExpiredReservations en las rutas /tienda: como máximo una vez por minuto (Cache::add) y con defer(), después de enviar la respuesta. El comando ecommerce:reconcile-reservations queda solo para ejecución manual. Un test verifica que schedule:list no lo incluya.
