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

## Auditoría de estabilidad y animaciones

- 126 combinaciones de módulos/opciones, roles y tamaños con JavaScript ralentizado y caché desactivada.
- CLS máximo registrado: 0. Sin desbordamientos ni errores de JavaScript.
- 12 grupos de interacción verificados, incluyendo movimiento reducido, pulsaciones repetidas, descargas y dimensiones del diálogo durante la carga.
- Los 17 flujos funcionales originales se repitieron satisfactoriamente tras añadir los efectos.
- Backend, controladores y SQL siguen preservados según `tools/check_scope.py`.

La regresión final de iconos y carga fría pasó en 14 pantallas con fuentes bloqueadas y caché desactivada. Se sustituyó la transición nativa entre documentos por opacidad CSS para evitar errores al interrumpir navegaciones.

## Firma animada de navegación

- 62 recorridos mediante controles visibles en los cinco perfiles, en escritorio y móvil.
- Entrada de módulos, envío de formularios, filtros automáticos, búsquedas y navegación atrás verificados con sus animaciones correspondientes.
- 12 grupos de interacción con y sin movimiento reducido, y 14 vistas con fuentes bloqueadas y caché desactivada, sin errores de JavaScript.
- Comprobación adicional de CLS en acceso y vistas animadas de escritorio y móvil.
- Se verificó que los enlaces internos al historial cierren el menú móvil.

Informe: `artifacts/qa/navigation-effects.json`. Script: `tools/verify_navigation_effects.py`.

## Bienvenida institucional

Diez accesos correctos verificados: cinco perfiles en escritorio y móvil, con nombre y rol reales. También se verificaron movimiento reducido, cierre con Escape, credenciales inválidas, ausencia al recargar, restauración del foco y salida con Continuar. La pantalla cubre el viewport completo y no modifica los permisos ni la lógica de autenticación.
