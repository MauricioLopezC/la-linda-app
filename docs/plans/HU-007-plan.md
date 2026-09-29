# HU-007 — Administrar las alícuotas de IVA

Reincorpora la asignación obligatoria de alícuotas de IVA a los artículos activos del catálogo y reactiva la protección de baja (`VatRate::isInUse()`) para impedir desactivar alícuotas que estén siendo utilizadas en artículos u operaciones.

## Criterios de aceptación (de `product-backlog.md`)

1. **Datos:** descripción, porcentaje, estado (activa o inactiva).
2. **Validaciones:**
   - el porcentaje es mayor o igual a cero y no se repite entre alícuotas.
   - una alícuota asignada a algún artículo no se puede desactivar.
3. **Comportamiento:**
   - solo las alícuotas activas se ofrecen al asignarla a un artículo (`HU-063`).
   - las alícuotas ya guardadas en ventas y facturas no cambian si después se edita la alícuota.
4. **Verificación:** se intenta desactivar el 21% con artículos que la usan y se rechaza; una alícuota sin artículos se desactiva.

## Investigación del código existente

| Artefacto | Ubicación | Propósito |
|---|---|---|
| Modelo VatRate | `app/Models/Pricing/VatRate.php` | ABM existente de alícuotas. `isInUse()` actualmente retorna `false` (stub). |
| Modelo Article | `app/Models/Catalog/Article.php` | Ya posee la columna y relación `vatRate(): BelongsTo` desde el PR #59. |
| Actions VatRate | `app/Actions/Pricing/ToggleVatRateStatus.php`, `UpdateVatRate.php` | Ya validan `$vatRate->isInUse()` pero requieren el método real. |
| Requests Artículo | `app/Http/Requests/Catalog/StoreArticleRequest.php`, `UpdateArticleRequest.php` | Falta validar `vat_rate_id` (obligatorio para artículos activos, debe existir y estar activa). |
| Actions Artículo | `app/Actions/Catalog/CreateArticle.php`, `UpdateArticle.php` | Falta persistir `vat_rate_id` al crear o actualizar el artículo. |
| Data Object Artículo | `app/Data/Catalog/ArticleData.php` | Falta exponer `vat_rate_id`, `vat_rate_percentage` y `vat_rate_description`. |
| Controlador Artículo | `app/Http/Controllers/Catalog/ArticleController.php` | Falta incluir `vatRate` en el eager load y enviar `vatRates` (activas) al frontend. |
| Vista Artículos | `resources/js/pages/catalog/articles/index.tsx` | Falta selector de alícuota en modales de alta/edición y columna de IVA en la tabla. |
| Tests VatRate | `tests/Feature/Pricing/VatRateTest.php` | Pruebas del ABM de alícuotas; falta cubrir el rechazo de desactivación de alícuotas en uso. |
| Tests Artículo | `tests/Feature/Catalog/ArticleTest.php` | Pruebas de artículos; actualizar payload con `vat_rate_id` y testear validaciones de IVA. |

## Proposed Changes

### Backend — Pricing (VatRate)

#### [MODIFY] `app/Models/Pricing/VatRate.php`
- Agregar relación `articles(): HasMany` hacia `Article::class`.
- Agregar relación `saleItems(): HasMany` hacia `SaleItem::class`.
- Implementar `isInUse(): bool`:
  ```php
  public function isInUse(): bool
  {
      return $this->articles()->exists() || $this->saleItems()->exists();
  }
  ```

#### [MODIFY] `app/Actions/Pricing/ToggleVatRateStatus.php`
- Ajustar mensaje de excepción en caso de que esté en uso para reflejar claramente la regla de negocio:
  `"No se puede desactivar una alícuota de IVA que está asignada a uno o más artículos."`

#### [MODIFY] `app/Actions/Pricing/UpdateVatRate.php`
- Mantener la misma validación y mensaje consistente si se intenta cambiar `is_active` a `false`.

---

### Backend — Catalog (Article)

#### [MODIFY] `app/Http/Requests/Catalog/StoreArticleRequest.php` y `UpdateArticleRequest.php`
- Agregar validación de `vat_rate_id`:
  - Obligatorio si el estado es activo:
    ```php
    'vat_rate_id' => [
        Rule::requiredIf(fn () => $this->input('status', ArticleStatus::Active->value) === ArticleStatus::Active->value),
        'nullable',
        'integer',
        Rule::exists('vat_rates', 'id')->where('is_active', true),
    ],
    ```
- Agregar traducción en `attributes()`: `'vat_rate_id' => 'alícuota de IVA'`.

#### [MODIFY] `app/Actions/Catalog/CreateArticle.php` y `UpdateArticle.php`
- Persistir `'vat_rate_id' => ! empty($data['vat_rate_id']) ? (int) $data['vat_rate_id'] : null,` en la creación y actualización.

#### [MODIFY] `app/Data/Catalog/ArticleData.php`
- Agregar propiedades tipadas:
  - `public ?int $vat_rate_id`
  - `public ?float $vat_rate_percentage`
  - `public ?string $vat_rate_description`
- Mapearlas en `fromModel($article)`.

#### [MODIFY] `app/Http/Controllers/Catalog/ArticleController.php`
- En `index()`:
  - Cargar la relación en `Article::query()->with([... 'vatRate'])`.
  - Enviar prop `'vatRates' => VatRateData::collect(VatRate::query()->active()->orderBy('percentage')->get())`.

---

### Frontend — Catálogo de Artículos

#### [MODIFY] `resources/js/pages/catalog/articles/index.tsx`
- Extender `Props` con `vatRates: App.Data.Pricing.VatRateData[]`.
- Extender `ArticleFormData` con `vat_rate_id: string`.
- Actualizar `emptyForm` con `vat_rate_id: ''`.
- Actualizar `openEditModal` asignando `vat_rate_id: article.vat_rate_id ? String(article.vat_rate_id) : ''`.
- En `renderFormFields`: agregar el `<Select>` para seleccionar la alícuota de IVA (solo opciones activas de `vatRates`), con su `<Label>` y `<InputError>`.
- En la tabla de artículos: agregar columna "IVA" con el porcentaje formateado (`{article.vat_rate_percentage !== null ? `${article.vat_rate_percentage}%` : '-'}`).

---

### Tests

#### [MODIFY] `tests/Feature/Pricing/VatRateTest.php`
- Test: rechazar desactivación vía `toggle` si la alícuota está asignada a un artículo (`assertSessionHasErrors(['vat_rate'])`).
- Test: rechazar desactivación vía `update` si la alícuota está asignada a un artículo.
- Test: permitir desactivación si ningún artículo la utiliza.

#### [MODIFY] `tests/Feature/Catalog/ArticleTest.php`
- Actualizar `articlePayload()` para incluir `vat_rate_id`.
- Test: la creación de un artículo activo falla si no se provee `vat_rate_id`.
- Test: la creación falla si se intenta asignar una alícuota inactiva.
- Test: el artículo se guarda y expone su alícuota correctamente en el listado.

## Verificación de la Definition of Done

| Criterio | Cómo se verifica |
|---|---|
| Alícuota obligatoria en artículo activo | `StoreArticleRequestTest` / validación rechaza activo sin `vat_rate_id`. |
| Solo alícuotas activas asignables | Validación rechaza `vat_rate_id` con `is_active = false`. |
| Protección de baja de alícuota en uso | `ToggleVatRateStatus` lanza excepción si `Article::where('vat_rate_id', ...)->exists()`. |
| Selector y columna en frontend | Selector visible en formulario y columna "IVA" en la grilla de artículos. |
| Inmutabilidad en ventas | `sale_items` congela `vat_rate_id` y `vat_rate` (probado en tests de esquema). |
| Tests automatizados en verde | `php artisan test --compact tests/Feature/Pricing/VatRateTest.php tests/Feature/Catalog/ArticleTest.php`. |
| Formato y estática | Pint, TypeScript y PHPStan sin errores. |

## Verification Plan

### Automated Tests
- `php artisan test --compact tests/Feature/Pricing/VatRateTest.php`
- `php artisan test --compact tests/Feature/Catalog/ArticleTest.php`
- `npm run types:generate`
- `composer run ci:check`

### Manual Verification
- Ingresar al ABM de artículos y constatar el selector de alícuota de IVA en creación y edición.
- Constatar la visualización de la alícuota en la tabla.
- Intentar desactivar una alícuota en uso desde Parámetros Comerciales > Alícuotas de IVA y verificar el mensaje de rechazo.
