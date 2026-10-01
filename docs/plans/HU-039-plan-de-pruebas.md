# Plan de Pruebas — HU-039: Abrir una venta de mostrador dentro del turno de caja

**Historia de Usuario:** HU-039 (Sprint 4, Módulo VTA, 3 SP)  
**Alcance:** Apertura de venta ligada exclusivamente al turno de caja abierto del usuario autenticado, bloqueo de modificaciones sobre ventas de turnos cerrados, e identificación del turno en listado y detalle.  
**Dependencias previas:** HU-057 (Abrir caja con fondo inicial) mergeada en `master`, HU-021 (Clientes / Consumidor Final).

---

## 1. Criterios de Aceptación y Matriz de Cobertura

| ID Criterio | Criterio de Aceptación (`product-backlog.md`) | Tipo de Prueba | Archivo de Prueba | Caso de Prueba |
| :--- | :--- | :--- | :--- | :--- |
| **AC-01** | **Datos:** Venta registra turno de caja, punto de venta y sucursal (derivados del turno), canal (siempre mostrador), cajero (usuario logueado), cliente Consumidor Final, fecha/hora y estado `abierta`. | Feature / Integración | `OpenSaleTest.php` | `test_apertura_exitosa_toma_datos_estrictos_del_turno_abierto` |
| **AC-02** | **Validación Turno:** No se puede abrir una venta sin turno de caja abierto del usuario autenticado (sin turno o con turno cerrado). | Feature / Validación | `OpenSaleTest.php` | `test_no_se_puede_abrir_venta_sin_turno_de_caja_abierto`<br>`test_no_se_puede_abrir_venta_con_turno_de_caja_cerrado` |
| **AC-03** | **Validación PDV:** La venta toma el punto de venta del turno; el cajero no lo elige. Si el request envía `point_of_sale_id`, `customer_id` o `channel` forjados, son ignorados. | Feature / Seguridad | `OpenSaleTest.php` | `test_request_ignora_payload_forjado_y_deriva_exclusivamente_del_turno` |
| **AC-04** | **Validación PDV Activo:** Si el punto de venta del turno fue desactivado con posterioridad a la apertura de la caja, la venta es rechazada. | Feature / Validación | `OpenSaleTest.php` | `test_no_se_puede_abrir_venta_si_punto_de_venta_fue_desactivado` |
| **AC-05** | **Validación Cliente Defecto:** Si el cliente Consumidor Final no existe o no está activo, la apertura es rechazada de forma controlada. | Feature / Validación | `OpenSaleTest.php` | `test_rechaza_apertura_si_cliente_consumidor_final_no_esta_disponible` |
| **AC-06** | **Inmutabilidad por Turno Cerrado (Descarte):** Una venta abierta solo puede descartarse si su turno sigue abierto. Si el turno cerró, se rechaza. | Feature / Regla de negocio | `OpenSaleTest.php` | `test_venta_abierta_se_puede_descartar_con_turno_abierto`<br>`test_venta_abierta_no_se_puede_descartar_si_el_turno_esta_cerrado` |
| **AC-07** | **Inmutabilidad por Turno Cerrado (Líneas de Venta):** No se pueden agregar artículos, modificar cantidades ni eliminar líneas si el turno de la venta está cerrado. | Feature / Regla de negocio | `SaleItemsTest.php` | `test_no_se_puede_agregar_articulo_si_turno_de_venta_esta_cerrado`<br>`test_no_se_puede_modificar_cantidad_si_turno_de_venta_esta_cerrado`<br>`test_no_se_puede_eliminar_linea_si_turno_de_venta_esta_cerrado` |
| **AC-08** | **Inmutabilidad por Turno Cerrado (Cambio de Cliente):** No se puede cambiar de cliente si el turno de la venta está cerrado. | Feature / Regla de negocio | `ChangeSaleCustomerTest.php` | `test_no_se_puede_cambiar_cliente_si_turno_de_venta_esta_cerrado` |
| **AC-09** | **Listado de Ventas (Filtros y Turno):** El listado muestra la columna Turno (`Turno #N`), y filtra correctamente por estado, caja (`point_of_sale_id`) y fecha (`opened_at`), combinados o aislados. | Feature / Inertia | `SaleScreensTest.php` | `test_listado_muestra_turno_y_aplica_filtros_de_estado_caja_y_fecha` |
| **AC-10** | **Detalle de Venta (Flags y Modo Lectura):** El detalle expone `cash_session_id`, `is_open` y `accepts_changes`. Si el turno está cerrado, `accepts_changes` es `false`. | Feature / Inertia | `SaleScreensTest.php` | `test_detalle_expone_turno_y_flag_accepts_changes_segun_estado_de_caja` |
| **AC-11** | **UI / CTA sin Turno:** Sin turno abierto, la pantalla de ventas no ofrece el botón "Abrir venta" ni selector de caja, sino un CTA hacia "Abrir caja" (`sales.cash-sessions.create`). | Frontend / Manual / Inertia | `SaleScreensTest.php` + Manual | Verificación en `sales/sales/index.tsx` de la prop compartida `cashSession` |

---

## 2. Detalle de Casos de Prueba Automatizados

### Suite A: `tests/Feature/Sales/OpenSaleTest.php`

1. **`test_apertura_exitosa_toma_datos_estrictos_del_turno_abierto`**
   - **GIVEN:** Un usuario logueado con un turno de caja abierto en el PDV 1 de la Sucursal Central, y existe el cliente Consumidor Final (`is_default = true`).
   - **WHEN:** Envía `POST` a `route('sales.sales.store')` con payload vacío `[]`.
   - **THEN:**
     - Redirige a `route('sales.sales.show', $sale)`.
     - Se crea exactamente una venta en BD.
     - `cash_session_id` coincide con el turno abierto.
     - `point_of_sale_id` coincide con el PDV del turno.
     - `user_id` coincide con el usuario autenticado.
     - `customer_id` coincide con el Consumidor Final.
     - `channel` es `SaleChannel::Mostrador`.
     - `status` es `SaleStatus::Open`.
     - `total_amount` es `'0.00'`.
     - `opened_at` no es nulo y coincide con la fecha/hora actual.

2. **`test_request_ignora_payload_forjado_y_deriva_exclusivamente_del_turno`**
   - **GIVEN:** Un usuario logueado con turno abierto en PDV 1; existen otro PDV 2, otro cliente X y otro usuario Y.
   - **WHEN:** Envía `POST` a `route('sales.sales.store')` con:
     `['point_of_sale_id' => $pdv2->id, 'customer_id' => $clienteX->id, 'user_id' => $userY->id, 'channel' => 'online', 'status' => 'confirmada', 'total_amount' => '9999.00']`.
   - **THEN:**
     - La venta se crea con los datos del turno del usuario logueado (PDV 1, usuario logueado, Consumidor Final, mostrador, abierta, 0.00). Los campos forjados son ignorados por completo.

3. **`test_no_se_puede_abrir_venta_sin_turno_de_caja_abierto`**
   - **GIVEN:** Un usuario logueado que no posee ningún turno de caja.
   - **WHEN:** Envía `POST` a `route('sales.sales.store')`.
   - **THEN:**
     - Error de validación en la sesión con clave `'cash_session'`.
     - No se crea ninguna venta en base de datos (`Sale::count() === 0`).

4. **`test_no_se_puede_abrir_venta_con_turno_de_caja_cerrado`**
   - **GIVEN:** Un usuario logueado que posee únicamente un turno cerrado (`status = 'cerrada'`).
   - **WHEN:** Envía `POST` a `route('sales.sales.store')`.
   - **THEN:**
     - Error de validación con clave `'cash_session'`.
     - `Sale::count() === 0`.

5. **`test_no_se_puede_abrir_venta_si_punto_de_venta_fue_desactivado`**
   - **GIVEN:** Un usuario con turno de caja abierto, pero el PDV del turno fue desactivado (`is_active = false`).
   - **WHEN:** Envía `POST` a `route('sales.sales.store')`.
   - **THEN:**
     - Error de validación informando que el punto de venta está inactivo.
     - `Sale::count() === 0`.

6. **`test_rechaza_apertura_si_cliente_consumidor_final_no_esta_disponible`**
   - **GIVEN:** Un usuario con turno abierto, pero el cliente Consumidor Final no existe o tiene `is_active = false`.
   - **WHEN:** Envía `POST` a `route('sales.sales.store')`.
   - **THEN:**
     - Error de validación informando la indisponibilidad del cliente por defecto.
     - `Sale::count() === 0`.

7. **`test_venta_abierta_se_puede_descartar_con_turno_abierto`**
   - **GIVEN:** Una venta abierta cuyo turno de caja está abierto.
   - **WHEN:** Se envía `POST` a `route('sales.sales.discard', $sale)`.
   - **THEN:**
     - Redirige al listado (`sales.sales.index`).
     - El estado de la venta pasa a `SaleStatus::Discarded`.

8. **`test_venta_abierta_no_se_puede_descartar_si_el_turno_esta_cerrado`**
   - **GIVEN:** Una venta en estado `Open` cuyo turno de caja asociado fue cerrado (`closed()`).
   - **WHEN:** Se envía `POST` a `route('sales.sales.discard', $sale)`.
   - **THEN:**
     - Error de validación en la sesión (`'sale'`).
     - La venta permanece en estado `Open`.

---

### Suite B: `tests/Feature/Sales/SaleItemsTest.php`

1. **`test_no_se_puede_agregar_articulo_si_turno_de_venta_esta_cerrado`**
   - **GIVEN:** Una venta abierta con un artículo con precio configurado, pero el turno de caja de la venta se encuentra cerrado (`CashSessionStatus::Closed`).
   - **WHEN:** Se intenta agregar un artículo vía `POST` a `route('sales.sales.items.store', $sale)`.
   - **THEN:**
     - Retorna error de validación indicando que la venta o el turno no admiten cambios.
     - No se crea ningún `SaleItem`.

2. **`test_no_se_puede_modificar_cantidad_si_turno_de_venta_esta_cerrado`**
   - **GIVEN:** Una venta con una línea cargada (cantidad 2), y su turno de caja es cerrado posteriormente.
   - **WHEN:** Se intenta modificar la cantidad vía `PATCH` a `route('sales.sales.items.update', [$sale, $item])` con `quantity = 5`.
   - **THEN:**
     - Retorna error de validación.
     - La cantidad del `SaleItem` permanece intacta en 2.

3. **`test_no_se_puede_eliminar_linea_si_turno_de_venta_esta_cerrado`**
   - **GIVEN:** Una venta con una línea existente, y su turno de caja es cerrado.
   - **WHEN:** Se intenta eliminar la línea vía `DELETE` a `route('sales.sales.items.destroy', [$sale, $item])`.
   - **THEN:**
     - Retorna error de validación.
     - La línea no se borra de la base de datos.

---

### Suite C: `tests/Feature/Sales/ChangeSaleCustomerTest.php`

1. **`test_no_se_puede_cambiar_cliente_si_turno_de_venta_esta_cerrado`**
   - **GIVEN:** Una venta abierta con un cliente y líneas cargadas, cuyo turno de caja se cierra.
   - **WHEN:** Se envía `PATCH` a `route('sales.sales.customer.update', $sale)` con otro cliente activo.
   - **THEN:**
     - Retorna error de validación en `'customer_id'`.
     - El cliente de la venta y los precios de sus líneas no se modifican.

---

### Suite D: `tests/Feature/Sales/SaleScreensTest.php`

1. **`test_listado_muestra_turno_y_aplica_filtros_de_estado_caja_y_fecha`**
   - **GIVEN:** 3 ventas creadas:
     - Venta 1: Abierta, Turno 10 (PDV 1), Fecha 2026-10-01 10:00:00.
     - Venta 2: Descartada, Turno 11 (PDV 2), Fecha 2026-10-01 11:00:00.
     - Venta 3: Abierta, Turno 12 (PDV 1), Fecha 2026-09-25 10:00:00.
   - **WHEN:** Se consulta `sales.sales.index` con diversos filtros:
     - Sin filtros: aparecen las 3 ventas; cada una expone `cash_session_id`.
     - Filtro `status = 'abierta'`: aparecen Venta 1 y Venta 3.
     - Filtro `point_of_sale_id = $pdv1->id`: aparecen Venta 1 y Venta 3.
     - Filtro `date = '2026-10-01'`: aparecen Venta 2 y Venta 1.
     - Filtro combinado `status = 'abierta'`, `point_of_sale_id = $pdv1->id`, `date = '2026-10-01'`: aparece únicamente Venta 1.
   - **THEN:**
     - Las aserciones de Inertia verifican conteo exacto de resultados y que `sales.data.0.cash_session_id` coincide con el turno.

2. **`test_detalle_expone_turno_y_flag_accepts_changes_segun_estado_de_caja`**
   - **GIVEN:** Venta A abierta con turno abierto, y Venta B abierta con turno cerrado.
   - **WHEN:** Se consultan los detalles vía `sales.sales.show`.
   - **THEN:**
     - Venta A: `is_open = true`, `accepts_changes = true`, `cash_session_id` correcto.
     - Venta B: `is_open = true`, `accepts_changes = false`, `cash_session_id` correcto.

---

## 3. Pruebas Manuales y de Frontend

1. **Flujo sin turno de caja:**
   - Iniciar sesión con un usuario sin turno abierto.
   - Navegar a `/sales/sales`.
   - **Verificar:** No debe figurar el botón "Abrir venta" ni ningún modal/selector de PDV.
   - **Verificar:** Debe figurar un aviso visible de que se requiere abrir caja y un botón "Abrir caja" que redirija a `/sales/cash-sessions/create`.
2. **Flujo con turno de caja abierto:**
   - Abrir un turno de caja desde la pantalla de apertura (`HU-057`).
   - Volver a `/sales/sales`.
   - **Verificar:** Ahora se visualiza el botón "Abrir venta".
   - Pulsar "Abrir venta": se realiza la apertura sin modales intermedios y redirige inmediatamente al detalle `/sales/sales/{id}`.
   - **Verificar:** En el detalle se muestra "Turno #N", el PDV y sucursal correspondientes al turno, el canal Mostrador y el cliente Consumidor Final.
3. **Filtros en el listado:**
   - Probar el selector rotulado como "Caja" (filtrado por `point_of_sale_id`), el selector de "Estado" y el campo "Fecha".
   - Verificar que la columna "Turno" muestra `Turno #N` en ventas de mostrador y `—` en ventas sin turno.
4. **Venta con turno cerrado (Modo Solo Lectura defensivo):**
   - Abrir una venta, cerrar la sesión de caja en base de datos o mediante el cierre de turno.
   - Recargar la pantalla de la venta:
     - **Verificar:** La venta muestra un banner informando que su turno está cerrado.
     - **Verificar:** Se deshabilitan o se ocultan los botones de descarte, agregar artículo, edición de cantidades, eliminación de líneas y cambio de cliente.

---

## 4. Criterios de Finalización (DoD) y Verificación CI

Para considerar la suite de pruebas completa y la HU lista para producción:
1. `php artisan test --compact tests/Feature/Sales/OpenSaleTest.php` -> 100% OK
2. `php artisan test --compact tests/Feature/Sales/SaleScreensTest.php` -> 100% OK
3. `php artisan test --compact tests/Feature/Sales/SaleItemsTest.php` -> 100% OK
4. `php artisan test --compact tests/Feature/Sales/ChangeSaleCustomerTest.php` -> 100% OK
5. `php artisan test --compact tests/Feature/Sales` -> Todos los tests del módulo Sales (100+ tests) en verde
6. `npm run types:generate` -> Tipos TypeScript sincronizados con los Data objects
7. `composer run ci:check` -> Pint, PHPStan (Larastan), ESLint, Prettier, TypeScript y Pest pasando sin ningún warning ni error.
