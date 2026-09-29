# HU-063 — Discriminar el IVA por alícuota en la venta

Desglosa el importe neto y el IVA por alícuota en cada línea de venta y agrupado a nivel de la venta completa, asegurando la consistencia aritmética exacta (neto + IVA = total) y congelando las alícuotas e importes al agregar cada ítem. Depende de `HU-007` (alícuotas obligatorias y activas en artículos) y `HU-041` (cálculo de precios de venta).

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** alícuota de IVA del artículo; por línea de venta, alícuota, importe neto e IVA; por venta, neto e IVA agrupados por alícuota.
2. **Validaciones:**
   - todo artículo activo tiene una alícuota activa; la alícuota vuelve a ser obligatoria en el ABM de artículos (cumplido en `HU-007`).
   - un artículo sin alícuota no se puede agregar a una venta, y el mensaje lo nombra.
   - neto más IVA de cada línea es exactamente el total de la línea, sin diferencias de redondeo.
3. **Comportamiento:**
   - el precio de lista es final con IVA incluido: el neto se obtiene dividiendo el total de la línea por (1 + alícuota) y el IVA es la diferencia.
   - la alícuota y los importes quedan fijos en la línea al agregarla; un cambio posterior de alícuota no la modifica.
   - discriminar el IVA no cambia el total de la venta.
4. **Verificación:** una venta con un artículo al 21% y otro al 10,5% muestra el neto y el IVA de cada alícuota, y neto más IVA suma el total.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Migración `revise_sale_items_for_vat` | `database/migrations/2026_09_28_100007_revise_sale_items_for_vat.php` | Esquema ya aplicado en `master` (PR #59). Define en `sale_items`: `vat_rate_id`, `vat_rate`, `net_amount`, `vat_amount` con CHECK `abs(net_amount + vat_amount - line_total) < 0.005`. |
| Modelo `SaleItem` | `app/Models/Sales/SaleItem.php` | Ya posee `calculateVatBreakdown()`, hook `booted()` en `saving` que deriva `net_amount` y `vat_amount`, casts decimales y relación `vatRate()`. |
| Modelo `Sale` | `app/Models/Sales/Sale.php` | Maneja la venta de mostrador y `recalculateTotal()`. Requiere métodos para derivar `netAmount()`, `vatAmount()` y `getVatBreakdown()`. |
| Action `AddArticleToSale` | `app/Actions/Sales/AddArticleToSale.php` | Agrega artículos a la venta abierta y congela precio y alícuota. Requiere reforzar la validación de alícuota activa. |
| Data object `SaleVatBreakdownData` | `app/Data/Sales/SaleVatBreakdownData.php` | [NUEVO] Data object para tipar cada fila del desglose por alícuota de la venta. |
| Data object `SaleItemData` | `app/Data/Sales/SaleItemData.php` | Expone los datos de la línea al frontend. Requiere incorporar `vat_rate_id`, `vat_rate`, `net_amount`, `vat_amount`. |
| Data object `SaleData` | `app/Data/Sales/SaleData.php` | Expone la venta al frontend. Requiere incorporar `net_amount`, `vat_amount` y `vat_breakdown` (lista de `SaleVatBreakdownData`). |
| Controlador de Venta | `app/Http/Controllers/Sales/SaleController.php` | Invoca `SaleData::fromModel($sale)` en `show()`. Requiere incluir `items.vatRate` en el eager loading de `SaleData`. |
| Vista de Venta | `resources/js/pages/sales/sales/show.tsx` | Pantalla de atención y escaneo. Requiere mostrar IVA y neto en la tabla de ítems, totales discriminados y tabla de desglose por alícuota. |
| Tests de Ítems de Venta | `tests/Feature/Sales/SaleItemsTest.php` | Tests de líneas de venta. Cubre ya el congelamiento de alícuota y cálculo neto/IVA; requiere ampliar para rechazo de alícuota inactiva y verificación de múltiples alícuotas. |
| Tests de Pantalla de Venta | `tests/Feature/Sales/SaleScreensTest.php` | Tests de la respuesta Inertia de `sales.sales.show`. Requiere verificar props `vat_breakdown`, `net_amount`, `vat_amount` y datos de IVA en líneas. |

*Nota sobre el diagrama Mermaid de `sprint-backlog-4.md`:* El código existente coincide al 100% con la especificación y el DER acordados para `sale_items` y `sales`.

## Proposed Changes

### Backend — Model (`Sale.php`)

#### [MODIFY] `app/Models/Sales/Sale.php`
- Agregar métodos auxiliares con cómputo en enteros (centavos) para evitar derivas de coma flotante:
  - `netAmount(): string`: suma de `net_amount` de todas las líneas.
  - `vatAmount(): string`: suma de `vat_amount` de todas las líneas.
  - `getVatBreakdown(): array`: agrupa las líneas por `vat_rate_id`, suma en centavos neto, IVA y total de cada grupo, y retorna un array ordenado por alícuota descendente:
    ```php
    [
        'vat_rate_id' => int,
        'vat_rate' => string,
        'vat_rate_description' => string,
        'net_amount' => string,
        'vat_amount' => string,
        'total_amount' => string,
    ]
    ```

---

### Backend — Action (`AddArticleToSale.php`)

#### [MODIFY] `app/Actions/Sales/AddArticleToSale.php`
- Reforzar validación antes de crear la línea:
  ```php
  if ($article->vatRate === null) {
      throw ValidationException::withMessages([
          $field => "El artículo \"{$article->description}\" no tiene alícuota de IVA asignada.",
      ]);
  }

  if (! $article->vatRate->is_active) {
      throw ValidationException::withMessages([
          $field => "La alícuota de IVA del artículo \"{$article->description}\" no está activa.",
      ]);
  }
  ```

---

### Backend — Data Objects

#### [NEW] `app/Data/Sales/SaleVatBreakdownData.php`
- Data object de `spatie/laravel-data` para tipar cada ítem del desglose agrupado:
  ```php
  namespace App\Data\Sales;

  use Spatie\LaravelData\Data;

  class SaleVatBreakdownData extends Data
  {
      public function __construct(
          public int $vat_rate_id,
          public string $vat_rate,
          public string $vat_rate_description,
          public string $net_amount,
          public string $vat_amount,
          public string $total_amount,
      ) {}
  }
  ```

#### [MODIFY] `app/Data/Sales/SaleItemData.php`
- Agregar propiedades:
  - `public int $vat_rate_id`
  - `public string $vat_rate`
  - `public string $net_amount`
  - `public string $vat_amount`
- Asignarlas en `fromModel(SaleItem $item)`.

#### [MODIFY] `app/Data/Sales/SaleData.php`
- Agregar propiedades:
  - `public string $net_amount`
  - `public string $vat_amount`
  - `/** @var array<int, SaleVatBreakdownData> */ public array $vat_breakdown`
- En `fromModel(Sale $sale)`:
  - Agregar `'items.vatRate'` a `loadMissing()`.
  - Mapear `net_amount: $sale->netAmount()`, `vat_amount: $sale->vatAmount()`, y `vat_breakdown: SaleVatBreakdownData::collect($sale->getVatBreakdown())->all()`.

---

### Frontend — Vista de Venta (`show.tsx`)

#### [MODIFY] `resources/js/pages/sales/sales/show.tsx`
- En la tabla de artículos de la venta:
  - Agregar columnas de cabecera: `Alícuota IVA`, `Neto`, `IVA`, manteniendo `Total` al final.
  - En `SaleItemRow`: renderizar el badge o texto de la alícuota (ej. `21%`), el importe neto (`formatCurrency(item.net_amount)`), el IVA discriminado (`formatCurrency(item.vat_amount)`), y el total de línea.
  - En `TableFooter`: agregar desglose de filas para `Subtotal neto`, `IVA discriminado`, y `Total general`.
- Debajo de la tabla de artículos:
  - Cuando `sale.items.length > 0`, incorporar card/sección "Desglose de IVA por alícuota" con una tabla que muestre para cada alícuota presente: Alícuota / Descripción, Neto gravado, IVA y Subtotal, con totales al pie coincidentes con la venta.

---

### Types y Generación

- Ejecutar `npm run types:generate` para actualizar `resources/js/types/generated.d.ts` con los nuevos campos de `SaleItemData`, `SaleData` y el nuevo `SaleVatBreakdownData`.

---

### Tests

#### [MODIFY] `tests/Feature/Sales/SaleItemsTest.php`
- Agregar test para rechazar artículo cuya alícuota de IVA no esté activa.
- Agregar test con múltiples artículos de distintas alícuotas (21% y 10,5%), verificando que cada línea calcule su neto e IVA exactos y que neto + IVA = total de línea sin discrepancias.

#### [MODIFY] `tests/Feature/Sales/SaleScreensTest.php`
- Actualizar test de `show` para verificar que la página reciba `sale.net_amount`, `sale.vat_amount`, y `sale.vat_breakdown` con la agrupación correcta por alícuota (21% y 10,5%), comprobando que la suma de netos más la suma de IVAs coincide con el total de la venta.

---

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Todo artículo activo tiene alícuota activa | Validado en `AddArticleToSale` y verificado con test que rechaza alícuota inactiva. |
| Artículo sin alícuota no se agrega a venta nombrando al artículo | Validado en `AddArticleToSale` y verificado en `SaleItemsTest.php`. |
| Neto + IVA = total por línea sin diferencias de redondeo | Garantizado por `SaleItem::calculateVatBreakdown()` y CHECK de base de datos; verificado con tests automatizados. |
| Precio de lista incluye IVA; neto = total / (1 + tasa), IVA = total - neto | Verificado por fórmulas en `SaleItem` y tests de escenarios con 21% y 10,5%. |
| Alícuota e importes fijos al agregar la línea | Verificado por test existente `a line freezes the article VAT rate and splits its total into net and VAT`. |
| Discriminar IVA no cambia el total de la venta | Verificado en test con múltiples alícuotas donde la suma de subtotales es igual a `sale.total_amount`. |
| Venta con 21% y 10,5% muestra neto e IVA de cada alícuota y suma el total | Verificado en test de Feature sobre `sales.sales.show` inspeccionando props de Inertia. |

## Verification Plan

### Automated Tests
- Ejecutar la suite de tests del módulo de ventas:
  `php artisan test --compact tests/Feature/Sales/SaleItemsTest.php tests/Feature/Sales/SaleScreensTest.php tests/Feature/Sales/SalePriceScenariosTest.php`
- Ejecutar la suite completa para descartar regresiones:
  `php artisan test --compact`

### Manual Verification
- Ingresar a la pantalla de una venta abierta.
- Agregar un producto con alícuota 21% y otro con 10,5%.
- Comprobar en la grilla que cada línea muestra su alícuota, neto e IVA.
- Comprobar la tabla resumen de desglose por alícuota al pie con los subtotales por tasa y los totales generales cuadrados.

### CI checks
- Formato PHP: `vendor/bin/pint --dirty --format agent`
- Tipado estático PHP: `composer run types:check`
- Linter frontend: `npm run lint:check`
- Formato frontend: `npm run format:check`
- Tipado frontend: `npm run types:check`
- Suite de tests: `php artisan test --compact`
