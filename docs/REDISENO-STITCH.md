# Rediseño de la biblioteca escolar

Trabajo aislado en la rama `codex/biblioteca-stitch-ui`, dentro del worktree `biblioteca-stitch-ui/SistemaBiblioteca`. La carpeta original permanece en `main`; no se hizo push, merge ni despliegue.

## Referencia y alcance

Proyecto Stitch: **Sistema de Biblioteca Escolar**, ID `146936167618910708`. El [inventario de referencias](REFERENCIAS-STITCH.md) identifica las 24 pantallas/recursos y las cuatro variantes no exportables.

Se trasladaron la composición institucional, los menús del personal y del lector, la insignia, las tarjetas, los paneles, las tablas, las fichas, el acceso y el asistente de préstamos. Los módulos sin pantalla propia exportable usan la misma paleta y los componentes compartidos.

- Azul institucional: `#1e3a8a`; azul profundo: `#00236f`.
- Naranja: `#fe932c`; naranja de texto: `#904d00`.
- Fondo: `#f8f9ff`; superficies: blanco; paneles suaves: `#eff4ff` y `#e5eeff`.
- Inter servido localmente e iconos SVG incluidos en el HTML, sin dependencia de fuentes de iconos.
- Cabecera y menú lateral en escritorio; tarjetas y navegación inferior en móvil; adaptación intermedia para tablet y laptop.

## Módulos

Acceso y recuperación de contraseña; cuenta y cambio de clave; paneles de bibliotecaria, auxiliar, administrador y lector (estudiante/docente); catálogo y ficha en diálogo; registro/edición de materiales; estados y cantidades; préstamos, devolución, renovación y reposición; historial; alertas; reportes; importación de catálogo; usuarios, roles y padrón.

Cada grupo se registró en su propio commit. El commit `b25a7a0` preserva los archivos originales de `public_html`, que estaban sin seguimiento en la carpeta inicial. `config.php` quedó excluido de Git.

## Compatibilidad con el backend existente

Los archivos centrales de autenticación, permisos, conexión, reglas de préstamos, catálogo, reportes, exportación y enrutamiento permanecen idénticos. Los bloques de lógica anteriores a la presentación de cada controlador se conservaron. El nuevo panel utiliza consultas `SELECT` mediante los helpers existentes para mostrar información real; no contiene operaciones de escritura.

No se editaron los SQL del repositorio ni la base institucional. Para las pruebas se creó **otra instancia** de MariaDB, limitada a `127.0.0.1:3307`, con la base ficticia `biblioteca_ui_test`, inicializada a partir de los dos SQL originales. Las operaciones funcionales se ejecutaron únicamente sobre esa copia de demostración.

Se adaptaron los controles simulados del prototipo a acciones existentes:

| Prototipo | Implementación funcional |
|---|---|
| Acceso por DNI/código | Acceso por correo institucional, como exige la autenticación original |
| Recordar sesión | Recordar correo, sin almacenar contraseñas ni cambiar la duración de sesión |
| Mi historial | Pestaña de devoluciones de `mis_prestamos`, enlazable mediante `#historial` |
| Mi cuenta | Identidad del usuario y formulario existente para cambiar contraseña |
| Ficha bibliográfica | Diálogo accesible con la vista real y URL navegable como alternativa |
| Registro en tres pasos | Selección real de lector/material, revisión y envío al controlador original |
| Renovación del lector | Instrucciones de solicitud presencial; autorización mediante el flujo del personal |
| Estadísticas y cupos | Datos actuales, sin copiar números ficticios del prototipo |

Los simuladores de roles y los controles de reserva, respaldo, adquisiciones o envío masivo que no tienen backend existente no se añadieron como acciones ficticias. Los permisos reales siguen determinando la navegación. Las portadas exportadas se usan cuando coincide el título; los demás materiales reciben una cubierta tipográfica sin agregar columnas a la base.

## Ejecutar la copia local

La instancia está preparada en esta máquina:

```powershell
.\tools\start-local.ps1
```

Abrir: [http://127.0.0.1:8080/index.php?p=login](http://127.0.0.1:8080/index.php?p=login).

El lanzador requiere PHP y MariaDB portátiles preparados en `%TEMP%`, y la configuración local ignorada por Git. Acepta `-PhpPath`, `-MariaDbHome` y `-DataPath` para indicar otras rutas. No instala servicios de Windows ni escucha conexiones externas. Si se vacía la carpeta temporal, habrá que preparar de nuevo esos runtimes y la copia de demostración. El servidor integrado de PHP se usa solo para desarrollo.

### Cuentas ficticias de la instancia local

| Perfil | Correo | Contraseña local |
|---|---|---|
| Bibliotecaria | `biblioteca@sanjuan.edu.pe` | `70000001` |
| Auxiliar | `auxiliar@sanjuan.edu.pe` | `70000002` |
| Estudiante | `estudiante@sanjuan.edu.pe` | `74561230` |
| Docente | `docente@sanjuan.edu.pe` | `70112233` |
| Administrador | `admin@iesanjuan.edu.pe` | `BibliotecaUI2026!` |

La clave inicial del administrador fue cambiada mediante la interfaz durante QA. Estas cuentas pertenecen exclusivamente a los datos ficticios locales.

## Verificación

- 270 combinaciones de pantalla y ancho (1920, 1440, 1024, 768, 390 y 320 px), con los cinco perfiles y las pantallas públicas. Se corrigió el único desbordamiento detectado y se volvió a verificar.
- 17 flujos funcionales: acceso, búsqueda, códigos, registro de material, préstamo, renovación, devolución, pérdida/reposición, inventario, importación de catálogo, PDF/XLSX, usuarios, activación, restablecimiento de clave, padrón, cambio obligatorio, salida y recuperación genérica.
- Casos adicionales: diálogo por toque, último ejemplar con confirmación, bloqueo de lector con vencidos y enlace válido de restablecimiento sin enviar correo externo.
- Pruebas con solicitudes HTTPS externas bloqueadas para comprobar fuentes, iconos y portadas locales.
- Comprobación de PHP, JavaScript, permisos por rol y preservación del backend/SQL.

Scripts: `tools/check_scope.py`, `tools/verify_ui.py`, `tools/verify_flows.py`, `tools/verify_edges.py`, `tools/verify_final.py`. Las suites funcionales esperan la base ficticia preparada y escriben registros de prueba; no deben ejecutarse sobre producción. La prueba de recuperación válida necesita un token temporal creado para una cuenta ficticia. El envío real de correo no se invocó.

Informes JSON y capturas: `artifacts/qa/` (ignorados por Git). El envío de correo real depende de la configuración existente del alojamiento. No se cambió esa integración.

## Estabilidad y movimiento

La interfaz entrega las tablas móviles y sus etiquetas desde el servidor, junto con el estado inicial del asistente y los campos del usuario. Así evita cambiar la composición cuando termina de cargar JavaScript. El acceso directo al historial también respeta el fragmento `#historial` desde el primer render.

Los cambios entre páginas usan una entrada breve de opacidad en CSS, sin depender de las transiciones nativas de navegación del navegador. Botones, foco, validación, menús, fichas, pestañas, ayudas y formularios responden con efectos discretos. Las acciones conservan sus textos y endpoints. Las descargas no dejan indicadores de envío activos y el regreso con el navegador limpia los estados de carga.

Las fichas mantienen dimensiones estables mientras llegan sus datos. Las búsquedas y fichas cancelan solicitudes anteriores para evitar respuestas fuera de orden. Las animaciones interrumpidas restauran el tamaño y el desbordamiento del contenedor. Todo respeta `prefers-reduced-motion`.

Auditoría: `tools/audit_motion.py --output motion-final.json` y `tools/verify_motion_interactions.py`. Se recorrieron 126 combinaciones de vistas/opciones, roles y tamaños, con caché desactivada y JavaScript ralentizado. Los informes están en `artifacts/qa/motion-final.json` y `artifacts/qa/motion-interactions.json`. Los flujos funcionales se repitieron únicamente en la copia local de demostración.
