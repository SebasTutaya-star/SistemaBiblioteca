# Catálogo fotográfico del inventario 2026

Las imágenes se sirven desde `public_html/assets/catalogo/INV-2026-NNN/` y se vinculan al código del material mediante `catalogo.json`. Cada ficha presenta sus fotografías; el catálogo muestra la primera. No se modifica el esquema de la base ni se consulta Drive durante la navegación.

## Datos y revisión

- Se excluye la carpeta **No clasificados** y todo su contenido.
- Se revisan las imágenes de las carpetas con archivos: algunas estaban en carpetas de otro título. Se asocian localmente al registro que muestra la fotografía, no al nombre incorrecto de la carpeta de origen.
- `base_de_datos/importaciones/revision-imagenes.json` documenta las asociaciones adicionales y los archivos pendientes de identificación. Los pendientes no aparecen como portadas de libros equivocados.
- Las cantidades proceden del inventario; varias fotografías no significan varios ejemplares. Los códigos conservan el número de registro. Los registros repetidos del inventario se mantienen separados; no se suman existencias automáticamente.
- Nombre y autor proceden del inventario, con correcciones evidentes contrastadas con las cubiertas. Las descripciones identifican el registro y su procedencia; no son sinopsis inventadas. Año y editorial quedan vacíos cuando no se verificaron.
- Las imágenes se convierten técnicamente a WebP de hasta 1600 píxeles y se corrige su orientación. No hay generación de imágenes. Los originales permanecen en Drive y en la caché local ignorada por Git.

## Prueba local

1. Ejecutar `tools/start-local.ps1` con el entorno de pruebas ya configurado.
2. Ejecutar `php tools/importar-catalogo-drive.php`. Solo admite `biblioteca_ui_test` en `127.0.0.1:3307`. Repetirlo no duplica códigos ni sobrescribe materiales existentes.
3. Abrir `http://127.0.0.1:8080/index.php?p=login`, ingresar y buscar `INV-2026-` en Catálogo. Abrir una ficha para ver su galería.

## Llevarlo a cPanel

1. Respaldar los archivos actuales y exportar la base existente desde phpMyAdmin.
2. Descargar la rama `codex/catalogo-portadas-inventario` desde GitHub.
3. Subir el **contenido** de `public_html/` a la raíz web del alojamiento. Conservar el `config/config.php`, `.user.ini`, `php.ini` y las credenciales actuales del servidor. No subir `.git`, `tools`, `outputs` ni la caché local.
4. Comprobar que `assets/catalogo/catalogo.json` y todas las subcarpetas `INV-2026-NNN` se extrajeron correctamente.
5. Abrir phpMyAdmin y seleccionar expresamente la base de datos actual del sistema. Antes de importar, revisar si alguno de esos mismos títulos ya está registrado con **otro código**: el importador evita duplicados por código, pero no fusiona títulos ni préstamos existentes. Resolver esas coincidencias antes de importar si corresponde.
6. En **Importar**, seleccionar `base_de_datos/importaciones/catalogo-drive.sql`. Este archivo solo añade los códigos que falten. No ejecutar los scripts de estructura ni de datos de demostración: estos no forman parte de esta actualización.
7. Recargar el catálogo y abrir una ficha. La importación SQL puede repetirse sin duplicar sus códigos ni modificar cantidades existentes. Las fotos se vinculan mediante esos códigos.

El CSV es una alternativa de intercambio de metadatos. Para esta carga completa se recomienda el SQL adjunto: la pantalla CSV del sistema rechaza algunos títulos/autores repetidos que el inventario enumera como registros distintos.
