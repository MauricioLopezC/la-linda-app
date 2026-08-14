# Roles y permisos

Intención de negocio detrás de quién puede hacer qué. Este fichero explica el *por qué*;
la implementación exacta (Policies, Gates, middleware) vive en el código y es la fuente de verdad
sobre el estado actual — si este documento y el código no coinciden, el código gana y hay que
actualizar este fichero.

## Cómo usar este fichero

- Un rol por sección, con su propósito y qué puede/no puede hacer a alto nivel.
- Anotar reglas de negocio no obvias (ej. excepciones, casos límite, jerarquías entre roles).
- No listar aquí cada permiso técnico uno por uno; eso se consulta en las Policies/Gates del código.

## Roles

<!-- Ejemplo:
### Administrador
Acceso total al sistema. Único rol que puede gestionar otros usuarios y su asignación de roles.

### Operador
Gestiona el día a día (clientes, órdenes). No puede modificar configuración del sistema ni ver reportes financieros.
-->
