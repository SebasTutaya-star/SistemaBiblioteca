-- =====================================================================
-- DATOS FICTICIOS DE DEMOSTRACIÓN (opcional)
-- Importar SOLO en el entorno de pruebas, después de 01_estructura.sql.
-- No representan el padrón ni el inventario real del colegio.
-- Las fechas se calculan desde el día de importación.
-- =====================================================================
SET NAMES utf8mb4;

-- Cuentas de prueba (contraseña = DNI, salvo el admin de prueba)
INSERT INTO usuarios (dni, nombres, apellidos, correo, clave_hash, rol, tipo_lector, grado, seccion, periodo, debe_cambiar_clave) VALUES
 ('10000001','Admin','Pruebas','admin@sanjuan.edu.pe','$2y$10$EzcugWRGA3YMx0u9kCTup.8ioI14yWB/yfQMWG5ni9HX6Q1VHRntW','admin',NULL,NULL,NULL,NULL,0),
 ('70000001','Rosa','Quispe Huamán','biblioteca@sanjuan.edu.pe','$2y$10$dx693qLV6Q2pJzmhiYbCAeBADKRoF95GTs0uUx1N7hcrwEArna9lC','bibliotecaria',NULL,NULL,NULL,NULL,0),
 ('70000002','Carlos','Mendoza Ccori','auxiliar@sanjuan.edu.pe','$2y$10$1Wz5G9c1Esw1Q62u2jVLQegROCEy7406Eg4TCZ6NNobbeW..1448K','auxiliar',NULL,NULL,NULL,NULL,0),
 ('74561230','Lucía','Pariona Tito','estudiante@sanjuan.edu.pe','$2y$10$VlSY/MixKSiAEi3pBeeDx.BCcEZF0Hdz0u/wBa//ccSXipvfj8uEe','lector','estudiante','4to','A','2026',0),
 ('70112233','Jorge','Gamboa Ruiz','docente@sanjuan.edu.pe','$2y$10$WpWg5iyeHgad0okBhmtdB.zcxKddTBVzpYerCUJGTGTW6YOEwSigm','lector','docente',NULL,NULL,'2026',0),
 ('71234567','Diego','Huamaní Soto','diego.huamani@sanjuan.edu.pe','$2y$10$AXJ9pNFkiS13gFce23Dke.ICKLBYd0zaXbjgYvRDg2yUC.ghPe0OG','lector','estudiante','5to','B','2026',0),
 ('72345678','Ana','Cárdenas Vila','ana.cardenas@sanjuan.edu.pe','$2y$10$XcSn.KpHLPzehNh/IsgSsuz9bwyxx4uVaGjJnt4wI4cINYw8uKNvC','lector','estudiante','3ro','A','2026',0);

SET @bib = (SELECT id FROM usuarios WHERE correo='biblioteca@sanjuan.edu.pe');
SET @aux = (SELECT id FROM usuarios WHERE correo='auxiliar@sanjuan.edu.pe');
SET @est = (SELECT id FROM usuarios WHERE correo='estudiante@sanjuan.edu.pe');
SET @doc = (SELECT id FROM usuarios WHERE correo='docente@sanjuan.edu.pe');
SET @die = (SELECT id FROM usuarios WHERE correo='diego.huamani@sanjuan.edu.pe');
SET @ana = (SELECT id FROM usuarios WHERE correo='ana.cardenas@sanjuan.edu.pe');

INSERT INTO materiales (codigo, titulo, autor, editorial, anio, area, nivel, tema, descripcion, tipo_material_id, cantidad_total, ubicacion, creado_por) VALUES
 ('COM-CUE-2023','Cuentos peruanos','Autores varios','Santillana',2023,'Comunicación','Secundaria','Literatura peruana','Antología de cuentos de autores peruanos de los siglos XX y XXI.',1,5,'Estante C-02',@bib),
 ('MAT-ALG-2022','Álgebra básica','A. Baldor','Patria',2022,'Matemática','Secundaria','Álgebra','Ejercicios y teoría de álgebra elemental.',4,2,'Estante M-01',@bib),
 ('CTA-ECO-2024','Ecología y ambiente','J. Salazar','Norma',2024,'Ciencia y Tecnología','4to','Contaminación ambiental','Conceptos de ecología, ecosistemas y cuidado del ambiente.',2,1,'Estante CT-03',@bib),
 ('HIS-PER-2021','Historia del Perú Republicano','C. Contreras','IEP',2021,'Historia','5to','Historia republicana','Panorama de la historia del Perú desde la independencia.',2,3,'Estante H-02',@bib),
 ('COM-TXT-2026','Comunicación 4 - Texto escolar','MINEDU','MINEDU',2026,'Comunicación','4to','Comprensión lectora','Texto escolar oficial del área de Comunicación.',3,30,'Depósito T-01',@bib),
 ('COM-RIO-1958','Los ríos profundos','José María Arguedas','Losada',1958,'Comunicación','Secundaria','Novela indigenista','Novela sobre la infancia de Ernesto entre el mundo andino y el criollo.',2,4,'Estante C-01',@bib);

SET @m1 = (SELECT id FROM materiales WHERE codigo='COM-CUE-2023');
SET @m2 = (SELECT id FROM materiales WHERE codigo='MAT-ALG-2022');
SET @m4 = (SELECT id FROM materiales WHERE codigo='HIS-PER-2021');
SET @m6 = (SELECT id FROM materiales WHERE codigo='COM-RIO-1958');

INSERT INTO movimientos_inventario (material_id, tipo, cantidad, observacion, usuario_id)
 SELECT id, 'alta', cantidad_total, 'Registro inicial de demostración', @bib FROM materiales;

-- Préstamos: uno vencido (Diego), uno próximo a vencer (Lucía), uno vigente (docente), uno ya devuelto
INSERT INTO prestamos (material_id, lector_id, fecha_prestamo, fecha_vencimiento, registrado_por, estado) VALUES
 (@m4, @die, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), @aux, 'activo'),
 (@m1, @est, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), @bib, 'activo'),
 (@m6, @doc, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), @bib, 'activo');

INSERT INTO prestamos (material_id, lector_id, fecha_prestamo, fecha_vencimiento, registrado_por, estado, fecha_devolucion, recibido_por, condicion_devolucion) VALUES
 (@m2, @ana, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), @bib, 'devuelto', DATE_SUB(NOW(), INTERVAL 11 DAY), @aux, 'bueno'),
 (@m1, @ana, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 18 DAY), @aux, 'devuelto', DATE_SUB(NOW(), INTERVAL 17 DAY), @bib, 'bueno');
