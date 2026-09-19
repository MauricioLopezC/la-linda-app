# HU-015 — Asociar artículos a sus proveedores

**Módulo:** Catálogo / Compras (`Catalog` / `Purchasing` · `ART-04`) · **Estimación:** 5 SP · **Estado:** Planificación · **Sprint:** 3 · **Depende de:** HU-013

Permite relacionar cada artículo con los proveedores que lo abastecen, registrando el código interno propio que utiliza cada proveedor y el último costo de compra conocido (que será actualizado automáticamente al recibir comprobantes de proveedor en `HU-038`). La gestión debe ser bidireccional: administrable tanto desde la ficha de artículo como desde la ficha del proveedor.

---

## Criterios de Aceptación (de `product-backlog.md`)

- **Datos:** artículo, proveedor, código del artículo en el proveedor, último costo de compra conocido.
- **Validaciones:**
  - Un artículo puede tener varios proveedores y un proveedor puede abastecer varios artículos (relación N:N).
  - La combinación artículo más proveedor no se repite (`UNIQUE(article_id, supplier_id)`).
  - El código del proveedor es único dentro de ese mismo proveedor (`UNIQUE(supplier_id, supplier_article_code_normalized)`).
  - El costo debe ser mayor a cero cuando se informa (`last_cost > 0`, nullable cuando no se indica).
- **Comportamiento:**
  - La asociación se administra tanto desde la ficha del artículo como desde la ficha del proveedor.
  - El último costo se actualizará automáticamente al registrar comprobantes de proveedor (`HU-038`).

---

## Investigación del Código Existente

| Componente | Archivo de Referencia | Patrón a Replicar |
| :--- | :--- | :--- |
| **Modelos & Unicidad Normalizada** | `app/Models/Catalog/Article.php`, `app/Concerns/NormalizesUniqueAttributes.php` | Uso de `NormalizesUniqueAttributes` para persistir `supplier_article_code_normalized` y garantizar unicidad insensible a mayúsculas y espacios. |
| **Reglas de Validación Scoped** | `app/Rules/UniqueNormalizedValue.php` | Validación de unicidad normalizada con soporte de constraints (`['supplier_id' => $supplierId]`) e `ignoreId`. |
| **Acciones de Dominio** | `app/Actions/Catalog/CreateArticle.php`, `app/Actions/Purchasing/CreateSupplier.php` | Clases invocables con validación de reglas de negocio y mutación en transacción de base de datos. |
| **DTOs (Data)** | `app/Data/Catalog/ArticleData.php`, `app/Data/Purchasing/SupplierData.php` | Clases `Spatie\LaravelData\Data` para tipado estricto hacia Inertia y generación de tipos TypeScript vía `npm run types:generate`. |
| **Form Requests** | `app/Http/Requests/Catalog/StoreArticleRequest.php`, `app/Http/Requests/Purchasing/StoreSupplierRequest.php` | Validación en el servidor antes de ingresar al controlador, con atributos en español y sanitización en `prepareForValidation`. |
| **Vistas de UI** | `resources/js/pages/catalog/articles/index.tsx`, `resources/js/pages/purchasing/suppliers/index.tsx` | Uso de diálogos (`Dialog`) con tablas y formularios compactos, badges de estado, feedbacks vía toasts con `sonner`. |

---

## Decisiones de Arquitectura y Diseño de Datos

1. **Tabla pivote con clave primaria propia (`article_supplier`)**:
   - Se crea la migración `create_article_supplier_table` con:
     - `id`: bigserial PK.
     - `article_id`: FK a `articles`, `restrictOnDelete()` para evitar borrar artículos con vínculos comerciales activos.
     - `supplier_id`: FK a `suppliers`, `restrictOnDelete()` para evitar borrar proveedores vinculados.
     - `supplier_article_code`: `string(100)` no nulo.
     - `supplier_article_code_normalized`: `string(100)` no nulo.
     - `last_cost`: `rawColumn('last_cost', 'decimal(12, 2) check (last_cost is null or last_cost > 0)')->nullable()`.
     - `notes`: `text` nullable.
     - `created_at`, `updated_at`: timestamps.
   - Restricciones de unicidad:
     - `$table->unique(['article_id', 'supplier_id'], 'article_supplier_unique');`
     - `$table->unique(['supplier_id', 'supplier_article_code_normalized'], 'supplier_article_code_unique');`

2. **Modelo `App\Models\Catalog\ArticleSupplier`**:
   - Modelo Eloquent que utiliza el trait `NormalizesUniqueAttributes`.
   - Normaliza `supplier_article_code` en `supplier_article_code_normalized`.
   - Relaciones `belongsTo` a `Article` y a `Supplier`.
   - Relaciones en `Article`: `$this->belongsToMany(Supplier::class, 'article_supplier')->using(ArticleSupplier::class)->withPivot(['id', 'supplier_article_code', 'last_cost', 'notes'])->withTimestamps();` y `articleSuppliers(): HasMany`.
   - Relaciones en `Supplier`: `$this->belongsToMany(Article::class, 'article_supplier')->using(ArticleSupplier::class)->withPivot(['id', 'supplier_article_code', 'last_cost', 'notes'])->withTimestamps();` y `articleSuppliers(): HasMany`.

3. **Data Transfer Object `ArticleSupplierData`**:
   - Ubicado en `app/Data/Catalog/ArticleSupplierData.php`.
   - Modela la información completa para ambos sentidos: id del pivot, datos del artículo (código, descripción, código de barras), datos del proveedor (razón social, CUIT), código del artículo en el proveedor, último costo formateado (`$150.00` o null) y notas.

4. **Acciones de Dominio (`app/Actions/Catalog/`)**:
   - `AttachSupplierToArticle`: Asocia un artículo con un proveedor, asegurando que ambos estén activos, que no exista asociación previa y que el código del proveedor sea único para ese proveedor. Valida `last_cost > 0` si se envía.
   - `UpdateArticleSupplier`: Actualiza código en proveedor, último costo y notas de una asociación existente.
   - `DetachSupplierFromArticle`: Desasocia el artículo del proveedor eliminando la fila del pivote.

5. **Rutas y Controladores**:
   - `App\Http\Controllers\Catalog\ArticleSupplierController`:
     - Rutas bajo `catalog/articles/{article}/suppliers`:
       - `POST /` (`catalog.articles.suppliers.store`): asocia proveedor a artículo.
       - `PUT /{supplier}` (`catalog.articles.suppliers.update`): actualiza datos de la asociación.
       - `DELETE /{supplier}` (`catalog.articles.suppliers.destroy`): desasocia.
     - Rutas bajo `purchasing/suppliers/{supplier}/articles`:
       - `POST /` (`purchasing.suppliers.articles.store`): asocia artículo a proveedor.
       - `PUT /{article}` (`purchasing.suppliers.articles.update`): actualiza datos de la asociación.
       - `DELETE /{article}` (`purchasing.suppliers.articles.destroy`): desasocia.

6. **Interfaz de Usuario (Bidireccional)**:
   - **En `catalog/articles/index.tsx`**:
     - Botón de acción con icono de camión/proveedor en cada fila de artículo: "Proveedores".
     - Abre diálogo modal `ManageArticleSuppliersDialog`:
       - Tabla con los proveedores asociados a ese artículo (Razón social, CUIT, Código en proveedor, Último costo, Notas, acciones Editar / Desasociar).
       - Formulario para asociar un nuevo proveedor (selector de proveedores disponibles no asociados aún, código del proveedor, último costo opcional, notas).
   - **En `purchasing/suppliers/index.tsx`**:
     - Botón de acción con icono de caja/catálogo en cada fila de proveedor: "Artículos provistos".
     - Abre diálogo modal `ManageSupplierArticlesDialog`:
       - Tabla con los artículos provistos (Código interno, Descripción, Código de barras, Código en proveedor, Último costo, Notas, acciones Editar / Desasociar).
       - Formulario para asociar un nuevo artículo (selector de artículos disponibles no provistos aún, código del proveedor, último costo opcional, notas).

---

## Plan de Pruebas Automatizadas (Pest)

Archivo: `tests/Feature/Catalog/ArticleSupplierTest.php`

1. **Unicidad de asociación (N:N)**:
   - Permite que un artículo tenga múltiples proveedores.
   - Permite que un proveedor abastezca múltiples artículos.
   - Rechaza duplicar la asociación del mismo artículo con el mismo proveedor.
2. **Unicidad del código de artículo en el proveedor**:
   - Rechaza asociar dos artículos distintos al mismo proveedor con el mismo código de proveedor (insensible a mayúsculas y espacios: `PROV-001` vs `prov-001`).
   - Permite que el mismo código de proveedor sea usado en proveedores diferentes.
3. **Validación de último costo**:
   - Acepta asociar sin costo (`last_cost` null).
   - Acepta asociar con costo mayor a cero (`last_cost = 150.50`).
   - Rechaza asociar con costo igual a cero o negativo.
4. **Edición y Desasociación**:
   - Permite actualizar el código del proveedor y el costo.
   - Permite desasociar un proveedor de un artículo.
5. **Bidireccionalidad desde la UI / HTTP**:
   - Endpoint desde la perspectiva de artículo (`catalog.articles.suppliers.*`).
   - Endpoint desde la perspectiva de proveedor (`purchasing.suppliers.articles.*`).
   - La carga en las páginas principales renderiza correctamente las relaciones.
