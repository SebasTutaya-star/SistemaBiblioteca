# Resultado de validación

- 270 combinaciones iniciales de pantalla/ancho completadas tras corregir y verificar Usuarios a 320 px.
- 52 revisiones focalizadas posteriores a los últimos ajustes, incluyendo tablet, móvil y escritorio.
- Sin errores de JavaScript, errores PHP, imágenes rotas o desbordamientos de la página en las verificaciones finales.
- 44 archivos PHP pasaron comprobación de sintaxis.
- 13 archivos centrales de backend y SQL idénticos a la base; 23 bloques de controladores y los endpoints conservados.

## Flujos funcionales comprobados

- Búsqueda real de catálogo
- Sugerencia de código y registro de material
- Asistente completo de préstamo con búsquedas reales
- Renovación real del préstamo
- Devolución en buen estado
- Pérdida y reposición del material
- Actualización de estados del inventario
- Importación CSV con validación y confirmación
- Descarga real XLSX
- Descarga real PDF
- Creación de usuario con selección de rol
- Activación y desactivación de cuentas
- Restablecimiento administrativo de contraseña
- Importación de padrón escolar
- Cambio obligatorio de contraseña en primer ingreso
- Cierre de sesión real
- Recuperación con respuesta genérica sin envío externo

## Casos adicionales

- Correos largos en usuarios: 320, 360, 390 y 768 px sin desbordamiento
- Ficha en diálogo: apertura y cierre por toque, fuentes e iconos locales
- Único ejemplar: advertencia de servidor y confirmación funcional
- Lector con vencidos: reglas originales impiden el préstamo
- Enlace de recuperación: nueva contraseña y acceso real sin correo externo

Las pruebas funcionales operaron exclusivamente sobre `biblioteca_ui_test` en `127.0.0.1:3307`. No se enviaron mensajes externos ni se utilizó la base institucional.

Los informes detallados y capturas están disponibles localmente en `artifacts/qa/`; se excluyen de Git.
