---
paths:
  - 'app/Models/Catalog/**'
  - 'app/Actions/Catalog/**'
  - 'database/seeders/Catalog/**'
  - 'resources/js/pages/catalog/**'
---

# Catalog

## Standardized Article Description Format
To ensure consistency, readability and clarity at POS checkout (cajas), stock auditing, purchasing, and e-commerce, every article description (`description`) must follow the standardized supermarket retail naming formula:

`[Producto Base] [Variedad / Sabor / Tipo] [Marca (si aplica)] [Tipo de Envase] [Contenido / Gramaje]`

### Rules and Conventions:
1. **Formula structure**:
   - `Producto Base`: Common name of the product (e.g. *Harina de Trigo*, *Duraznos en Almíbar*, *Leche Entera*, *Gaseosa Sabor Cola*).
   - `Variedad / Sabor / Tipo`: Specific subtype or flavor (e.g. *000 Ultrarefinada*, *en Mitades*, *Clásica*, *Pomelo Sin Gas*, *Homogeneizada 3% Grasa*).
   - `Marca`: Brand name if specified on the packaging (e.g. *Arcor*, *La Serenísima*).
   - `Tipo de Envase`: Packaging container type (e.g. *Lata*, *Botella*, *Paquete*, *Bolsa*, *Tetra Brik*, *Pote*, *Doypack*).
   - `Contenido / Gramaje`: Measurement with standard ISO spacing (e.g. *820 g*, *300 g*, *1 kg*, *1.5 L*, *2 L*, *500 ml*).
2. **Capitalization**: Use Title Case / Initial capitalization for readability.
3. **Unit spacing**: Always separate number and unit with a single space (`1 kg`, `820 g`, `1.5 L`). Do not use informal formats like `x 1kg`, `x1kg`, or `820g`.
4. **Examples**:
   - `Duraznos en Almíbar en Mitades Arcor Lata 820 g`
   - `Choclo Amarillo en Grano Entero Arcor Lata 300 g`
   - `Harina de Trigo 000 Ultrarefinada Paquete 1 kg`
   - `Gaseosa Sabor Cola Clásica Botella 1.5 L`
   - `Leche Entera Homogeneizada 3% Grasa La Serenísima Tetra Brik 1 L`

## Article unit_of_measure reflects how it's sold, not the package's weight/volume
The unit of measure is about the sale/stock-adjustment granularity, not the number in the description. A product sold as a sealed, fixed-weight/volume package (`Paquete`, `Bolsa`, `Lata`, `Botella`, `Tetra Brik`, `Pote`, `Doypack`, ...) must use `Unidad` even if its description says "1 kg" or "1 L" — that figure is packaging info, not the sale unit. Only articles actually weighed/measured/dispensed at sale time (carnicería, fiambrería, frutas y verduras, granel) should use `Kilogramo`/`Litro`. Caught in ArticleSeeder: Harina, Arroz, Leche, Yerba and Azúcar were wrongly seeded as Kilogramo/Litro despite being closed packages — fixed to Unidad. Regression covered by tests/Feature/Catalog/ArticleSeederTest.php, which fails if any seeded article with closed-packaging wording in its description resolves to a decimal-allowing unit.

## Whether a unit allows fractional quantities is an explicit column, not inferred from its name
`units_of_measure.allows_decimal_quantity` (admin-editable in the ABM) is what `UnitOfMeasure::allowsDecimals()` / `Article::allowsDecimalQuantity()` read, and it's what `StoreStockAdjustmentRequest` enforces on stock adjustments. It used to be guessed by string-matching the unit's `name`/`abbreviation` against a hardcoded discrete-units list — a parche that silently misclassified any new unit not on that list as "allows decimals" (unsafe default). Don't reintroduce name-based inference: when seeding or creating a unit, set `allows_decimal_quantity` explicitly (`false` for `Unidad`/`Pack`/`Bulto`/`Docena`-like discrete units, `true` only for units actually weighed/measured at sale time like `Kilogramo`/`Litro`).
