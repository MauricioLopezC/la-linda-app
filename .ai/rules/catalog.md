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
