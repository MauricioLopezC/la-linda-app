# Lineamientos de diseño (frontend)

Design system del frontend: colores, tipografías y demás decisiones visuales que no son
derivables del código (Tailwind config, componentes) pero deben guiarlo.

Sistema de gestión integral para supermercado (inventario, POS, e-commerce, empleados). Tono: fresco de mercado, cálido y confiable. Densidad balanceada (tarjetas y tablas espaciadas). Soporta modo claro y oscuro.

## Cómo usar este fichero

- Mantenerlo como fuente de verdad de la _intención_ de diseño; si Tailwind config o los
  componentes ya reflejan una decisión, no hace falta duplicarla aquí en detalle, solo referenciarla.
- Actualizar cuando cambie una decisión de diseño, no solo cuando se agregue una nueva.

## Colores

Definir en OKLCH. Nunca grises fríos azulados — todos los neutrales llevan un leve tinte cálido (hue ~75).

**Primario — verde fresco** (confianza, frescura de producto)

- 50: `oklch(97% 0.02 152)`
- 100: `oklch(93% 0.045 152)`
- 300: `oklch(76% 0.09 152)`
- 500 (base): `oklch(54% 0.115 152)`
- 600: `oklch(46% 0.11 152)`
- 700: `oklch(38% 0.095 152)`
- 900: `oklch(22% 0.05 152)`

**Acento — terracota cálido** (calidez, llamados a la acción secundarios, ej. botón "Cobrar")

- 100: `oklch(94% 0.05 55)`
- 300: `oklch(80% 0.11 50)`
- 500 (base): `oklch(66% 0.16 45)`
- 700: `oklch(48% 0.15 40)`

Este color es exclusivo de la CTA de pago/cobro y usos puntuales (máx. 1-2 por pantalla). **No** se mapea al token `--accent` de shadcn/ui — shadcn usa `--accent`/`--accent-foreground` para estados de hover/selección de baja énfasis (ítems de menú, `Select`, `DropdownMenu`), un rol distinto y de mayor frecuencia de uso. Ver [Integración con shadcn/ui](#integración-con-shadcnui).

**Neutrales — gris cálido** (hue 75)

- 0: `oklch(100% 0 0)`
- 50: `oklch(98% 0.004 75)`
- 100: `oklch(94% 0.006 75)`
- 200: `oklch(90% 0.008 75)`
- 300: `oklch(80% 0.01 75)`
- 400: `oklch(70% 0.012 75)`
- 500: `oklch(58% 0.012 75)`
- 600: `oklch(45% 0.012 75)`
- 700: `oklch(35% 0.011 75)`
- 800: `oklch(25% 0.009 75)`
- 900: `oklch(17% 0.007 75)`

Escala completa (10 pasos) para que ningún uso de `neutral-*` caiga en el gris frío por defecto de Tailwind.

**Semánticos** (estado de inventario / feedback)

- Éxito: bg `oklch(95% 0.05 152)`, fg `oklch(38% 0.095 152)` — en stock, confirmado
- Advertencia: bg `oklch(95% 0.05 85)`, fg `oklch(50% 0.13 80)` — stock bajo, por vencer
- Error: bg `oklch(95% 0.04 25)`, fg `oklch(48% 0.19 25)` — agotado, rechazado
- Info: bg `oklch(95% 0.02 240)`, fg `oklch(45% 0.09 240)` — avisos generales

### Temas

| Token       | Claro                                  | Oscuro                            |
| ----------- | -------------------------------------- | --------------------------------- |
| bg          | `oklch(98% 0.004 75)`                  | `oklch(19% 0.01 75)`              |
| surface     | `oklch(100% 0 0)`                      | `oklch(24% 0.01 75)`              |
| surface2    | `oklch(96% 0.006 75)`                  | `oklch(28% 0.012 75)`             |
| border      | `oklch(90% 0.008 75)`                  | `oklch(34% 0.012 75)`             |
| text        | `oklch(17% 0.007 75)`                  | `oklch(95% 0.005 75)`             |
| muted       | `oklch(50% 0.012 75)`                  | `oklch(68% 0.01 75)`              |
| primary     | `oklch(54% 0.115 152)`                 | `oklch(64% 0.11 152)`             |
| primaryText | `oklch(100% 0 0)`                      | `oklch(14% 0.03 152)`             |
| accent      | `oklch(66% 0.16 45)`                   | `oklch(70% 0.13 48)`              |
| shadow      | `0 8px 24px oklch(40% 0.02 75 / 0.12)` | `0 8px 24px oklch(8% 0 0 / 0.45)` |

## Integración con shadcn/ui

El proyecto usa **shadcn/ui** (`components.json`, style "new-york") sobre Radix UI. shadcn no lee los tokens de este
documento directamente: sus componentes (`Button`, `Card`, `Select`, `Sidebar`, etc.) consumen variables CSS propias
(`--background`, `--primary`, `--accent`, ...) definidas en `:root` / `.dark`. La tabla siguiente es la referencia
para mapear cada variable de shadcn a los tokens definidos arriba, a seguir al actualizar `app.css` en la próxima
iteración.

| Variable shadcn                              | Token          | Nota                                                               |
| --------------------------------------------- | -------------- | ------------------------------------------------------------------- |
| `--background`                                | `bg`           |                                                                       |
| `--foreground`                                | `text`         |                                                                       |
| `--card`, `--popover`                         | `surface`      |                                                                       |
| `--card-foreground`, `--popover-foreground`   | `text`         |                                                                       |
| `--primary`                                   | `primary`      |                                                                       |
| `--primary-foreground`                        | `primaryText`  |                                                                       |
| `--secondary`                                 | `surface2`     | mismo fondo que "Botón secundario"                                   |
| `--secondary-foreground`                      | `text`         |                                                                       |
| `--muted`                                     | `surface2`     | fondo sutil (distinto del token `muted`, que es solo texto)          |
| `--muted-foreground`                          | `muted`        |                                                                       |
| `--accent`                                    | `surface2`     | **no** es el terracota — hover/selección de baja énfasis, ver nota en Acento |
| `--accent-foreground`                         | `text`         |                                                                       |
| `--destructive`                               | `oklch(48% 0.19 25)` | mismo valor que fg de Error / "Botón peligro"                  |
| `--destructive-foreground`                    | `oklch(100% 0 0)`    | blanco — distinto de `--destructive`                            |
| `--border`, `--input`                         | `border`       |                                                                       |
| `--ring`                                      | `primary`      | halo de foco, igual que el spec de Input                             |

### Gráficos (`--chart-1` … `--chart-5`)

Reusar la paleta existente en vez de introducir colores nuevos:

1. `primary-500`
2. `accent-500`
3. `info-fg` — `oklch(45% 0.09 240)`
4. `warning-fg` — `oklch(50% 0.13 80)`
5. `neutral-600`

### Sidebar (`--sidebar*`)

| Variable                     | Token                                    |
| ----------------------------- | ------------------------------------------ |
| `--sidebar`                    | `surface2`                                  |
| `--sidebar-foreground`         | `text`                                       |
| `--sidebar-primary`            | `primary`                                    |
| `--sidebar-primary-foreground` | `primaryText`                                |
| `--sidebar-accent`             | `surface2` (hover, igual que `--accent`)     |
| `--sidebar-accent-foreground`  | `text`                                       |
| `--sidebar-border`             | `border`                                     |
| `--sidebar-ring`               | `primary`                                    |

## Tipografía

Un solo tipo de letra: **Instrument Sans** (ya integrada en el proyecto vía `resources/css/app.css` / `@fonts`), sans-serif como fallback.

| Uso     | Tamaño / peso | Notas                                         |
| ------- | ------------- | --------------------------------------------- |
| Display | 40px / 700    | letter-spacing -0.02em                        |
| H1      | 28px / 700    | letter-spacing -0.01em                        |
| H2      | 20px / 600    |                                               |
| Body    | 16px / 400    | line-height 1.5                               |
| Small   | 14px / 500    |                                               |
| Caption | 12px / 600    | uppercase, letter-spacing 0.06em, color muted |

## Espaciado

Escala de 4px: 4, 8, 12, 16, 24, 32, 48, 64.

## Radios

- sm: 6px (inputs pequeños, chips internos)
- md: 10px (botones, inputs, tabs)
- lg: 14–16px (tarjetas, tablas, paneles)
- pill: 999px (badges de estado, tags)

shadcn deriva `--radius-lg/md/sm` de un único `--radius` base con `calc(var(--radius) - 2px/4px)`; esa fórmula no
reproduce esta escala (6/10/14–16/999). En `app.css`, definir `--radius-sm`, `--radius-md` y `--radius-lg` como
valores directos (6px/10px/16px) en vez de calcularlos a partir de una sola variable, y agregar `--radius-pill: 999px`
(shadcn no trae esa variable por defecto).

## Sombra

Una sola sombra de tarjeta, tinte cálido, nunca negro puro: ver tabla de temas arriba (`shadow`).

## Componentes

- **Botón primario**: bg `primary`, texto `primaryText`, sin borde, radio 10px, padding 11px 20px, 14px/600.
- **Botón secundario**: bg `surface2`, texto `text`, borde 1px `border`.
- **Botón ghost**: transparente, texto `primary`, sin borde.
- **Botón peligro**: bg error fg (`oklch(48% 0.19 25)`), texto blanco.
- **Botón de cobro/pago** (POS y checkout): bg `accent`, texto blanco, radio 10px, padding 14px 20px, 15px/700 — se distingue del primario para la acción de mayor peso.
- **Botón deshabilitado**: bg `surface2`, texto `muted`, opacity 0.6, cursor not-allowed.
- **Input**: bg `surface`, borde 1px `border`, radio 10px, padding 10px 14px; focus: borde `primary` + halo `primary` al 15% opacidad.
- **Badge de estado**: pill, bg/fg semántico según tabla de arriba (éxito/advertencia/error/info).
- **Tabla**: header en `surface2` uppercase 12px/700 letter-spacing 0.04em; filas en `surface` con borde superior 1px `border`; radio 14px en el contenedor.
- **Tarjeta**: bg `surface`, borde 1px `border`, radio 14px, sombra de tema.
- **Tarjeta de producto (catálogo/e-commerce)**: imagen arriba, radio 14px, sombra; footer con precio en `primary` bold + botón "+ Añadir" pequeño (bg `primary`).

## Implementación en Tailwind CSS

Tailwind v4 acepta `oklch()` directamente como valor de token — no hace falta convertir a hex/HSL. Definir en `app.css` (o donde esté `@import "tailwindcss";`):

```css
@theme {
  --color-primary-50: oklch(97% 0.02 152);
  --color-primary-100: oklch(93% 0.045 152);
  --color-primary-300: oklch(76% 0.09 152);
  --color-primary-500: oklch(54% 0.115 152);
  --color-primary-600: oklch(46% 0.11 152);
  --color-primary-700: oklch(38% 0.095 152);
  --color-primary-900: oklch(22% 0.05 152);

  --color-accent-100: oklch(94% 0.05 55);
  --color-accent-300: oklch(80% 0.11 50);
  --color-accent-500: oklch(66% 0.16 45);
  --color-accent-700: oklch(48% 0.15 40);

  --color-neutral-0: oklch(100% 0 0);
  --color-neutral-50: oklch(98% 0.004 75);
  --color-neutral-100: oklch(94% 0.006 75);
  --color-neutral-200: oklch(90% 0.008 75);
  --color-neutral-300: oklch(80% 0.01 75);
  --color-neutral-400: oklch(70% 0.012 75);
  --color-neutral-500: oklch(58% 0.012 75);
  --color-neutral-600: oklch(45% 0.012 75);
  --color-neutral-700: oklch(35% 0.011 75);
  --color-neutral-800: oklch(25% 0.009 75);
  --color-neutral-900: oklch(17% 0.007 75);

  --color-success-bg: oklch(95% 0.05 152);
  --color-success-fg: oklch(38% 0.095 152);
  --color-warning-bg: oklch(95% 0.05 85);
  --color-warning-fg: oklch(50% 0.13 80);
  --color-error-bg: oklch(95% 0.04 25);
  --color-error-fg: oklch(48% 0.19 25);
  --color-info-bg: oklch(95% 0.02 240);
  --color-info-fg: oklch(45% 0.09 240);

  --font-sans: 'Instrument Sans', sans-serif;
}
```

Esto genera automáticamente utilidades `bg-primary-500`, `text-accent-700`, `border-neutral-200`, etc. Para modo oscuro, usar la variante `dark:` de Tailwind mapeando cada token semántico (`bg`, `surface`, `text`, `muted`, `border`) a su par claro/oscuro de la tabla de temas — por ejemplo con tokens propios `--color-bg`, `--color-surface`, redefinidos dentro de `.dark { … }` o vía `@media (prefers-color-scheme: dark)`, en lugar de repartir `dark:oklch(...)` inline por todo el código.

### Variables semánticas para shadcn/ui (`:root` / `.dark`)

Los componentes de `resources/js/components/ui/` no usan `bg-primary-500` directamente: leen las variables semánticas
de shadcn (`--background`, `--primary`, `--accent`, ...) vía las utilidades `bg-background`, `bg-primary`, etc. que
Tailwind genera a partir de `@theme { --color-background: var(--background); ... }`. Redefinir esas variables en
`:root` / `.dark` con los valores mapeados en [Integración con shadcn/ui](#integración-con-shadcnui):

```css
:root {
  --background: oklch(98% 0.004 75); /* bg */
  --foreground: oklch(17% 0.007 75); /* text */
  --card: oklch(100% 0 0); /* surface */
  --card-foreground: oklch(17% 0.007 75);
  --popover: oklch(100% 0 0);
  --popover-foreground: oklch(17% 0.007 75);
  --primary: oklch(54% 0.115 152);
  --primary-foreground: oklch(100% 0 0);
  --secondary: oklch(96% 0.006 75); /* surface2 */
  --secondary-foreground: oklch(17% 0.007 75);
  --muted: oklch(96% 0.006 75); /* surface2 */
  --muted-foreground: oklch(50% 0.012 75);
  --accent: oklch(96% 0.006 75); /* surface2 — hover/selección, NO el terracota */
  --accent-foreground: oklch(17% 0.007 75);
  --destructive: oklch(48% 0.19 25);
  --destructive-foreground: oklch(100% 0 0);
  --border: oklch(90% 0.008 75);
  --input: oklch(90% 0.008 75);
  --ring: oklch(54% 0.115 152); /* primary */
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
  --radius-pill: 999px;
}

.dark {
  --background: oklch(19% 0.01 75);
  --foreground: oklch(95% 0.005 75);
  --card: oklch(24% 0.01 75);
  --card-foreground: oklch(95% 0.005 75);
  --popover: oklch(24% 0.01 75);
  --popover-foreground: oklch(95% 0.005 75);
  --primary: oklch(64% 0.11 152);
  --primary-foreground: oklch(14% 0.03 152);
  --secondary: oklch(28% 0.012 75);
  --secondary-foreground: oklch(95% 0.005 75);
  --muted: oklch(28% 0.012 75);
  --muted-foreground: oklch(68% 0.01 75);
  --accent: oklch(28% 0.012 75);
  --accent-foreground: oklch(95% 0.005 75);
  --destructive: oklch(48% 0.19 25);
  --destructive-foreground: oklch(100% 0 0);
  --border: oklch(34% 0.012 75);
  --input: oklch(34% 0.012 75);
  --ring: oklch(64% 0.11 152);
}
```

El terracota (`accent-500`/`accent-700`) no aparece en este bloque: se aplica explícitamente (`bg-accent-500`) solo en
componentes puntuales como el botón de cobro, nunca vía la variable `--accent` de shadcn.

No usar los colores por defecto de Tailwind (`blue-500`, `gray-500`, etc.) — siempre los tokens `primary-*`, `accent-*`, `neutral-*` y semánticos definidos arriba.

## Principios

1. Nunca negro/blanco puro para texto o fondo — siempre neutrales con tinte cálido.
2. El verde es la acción principal; el terracota se reserva para pagos/checkout y acentos puntuales (máx. 1-2 usos por pantalla).
3. Los estados de inventario (en stock / stock bajo / agotado / por vencer) siempre usan los 4 colores semánticos, consistentes en toda la app (dashboard, inventario, catálogo).
4. Un solo tipo de letra (Inter); jerarquía por peso y tamaño, no por familia.
5. Layout con flex/grid + gap, nunca márgenes manuales entre hermanos.
6. Ambos temas (claro/oscuro) deben mantener el mismo contraste relativo — no solo invertir bg/fg.
