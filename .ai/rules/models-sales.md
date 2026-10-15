---
paths:
  - 'app/Models/Sales/**'
---

# Models Sales

## IVA del envío independiente del catálogo de alícuotas
El envío online lleva IVA contenido al 21% por regla de EPIC-15 aunque no exista una alícuota de 21% en el catálogo. Al incorporarlo al desglose, buscar el porcentaje congelado en la venta y usar una clave interna sin colisiones; nunca asumir un ID (1, 0, etc.). Si no existe registro de catálogo, vat_rate_id es null solo en el resumen de salida (SaleVatBreakdownData), no en sale_items. La lectura del resumen no crea alícuotas.
