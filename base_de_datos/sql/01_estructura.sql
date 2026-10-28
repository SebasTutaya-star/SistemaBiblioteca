-- =====================================================================
-- Sistema de Gestión Bibliotecaria - I.E.P. San Juan (Ayacucho)
-- Estructura de la base de datos (MySQL 5.7+ / MariaDB 10.3+)
-- Importar desde phpMyAdmin: pestaña "Importar" > seleccionar este archivo.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS renovaciones;
DROP TABLE IF EXISTS prestamos;
DROP TABLE IF EXISTS movimientos_inventario;
DROP TABLE IF EXISTS materiales;
DROP TABLE IF EXISTS tipos_material;
DROP TABLE IF EXISTS recuperacion_clave;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracion;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuarios (RF-01, RF-02, RF-03)
-- rol: admin = Administrador, bibliotecaria, auxiliar, lector
-- tipo_lector: subtipo del Lector (estudiante / docente)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dni                VARCHAR(15)  NOT NULL,
  nombres            VARCHAR(100) NOT NULL,
  apellidos          VARCHAR(100) NOT NULL,
  correo             VARCHAR(150) NOT NULL,
  clave_hash         VARCHAR(255) NOT NULL,
  rol                ENUM('admin','bibliotecaria','auxiliar','lector') NOT NULL,
  tipo_lector        ENUM('estudiante','docente') NULL,
  grado              VARCHAR(20)  NULL,
  seccion            VARCHAR(10)  NULL,
  periodo            VARCHAR(10)  NULL COMMENT 'Periodo académico del padrón (ej. 2026)',
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  debe_cambiar_clave TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos  INT          NOT NULL DEFAULT 0,
  bloqueado_hasta    DATETIME     NULL,
  ultimo_acceso      DATETIME     NULL,
  creado_en          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuarios_dni (dni),
  UNIQUE KEY uk_usuarios_correo (correo),
  KEY idx_usuarios_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recuperación de contraseña por correo (RF-01)
CREATE TABLE recuperacion_clave (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expira_en   DATETIME     NOT NULL,
  usado       TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token_hash),
  CONSTRAINT fk_rec_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configuración general y reglas de préstamo (RF-13)
-- ---------------------------------------------------------------------
CREATE TABLE configuracion (
  clave        VARCHAR(60)  PRIMARY KEY,
  valor        VARCHAR(255) NOT NULL,
  descripcion  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de material con su periodo de préstamo (RF-13)
CREATE TABLE tipos_material (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre             VARCHAR(80)  NOT NULL,
  dias_prestamo      INT          NOT NULL DEFAULT 2,
  hasta_fin_periodo  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = vence al cierre del periodo académico (textos escolares)',
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uk_tipo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Materiales bibliográficos (RF-04 a RF-10)
-- Un registro = un título; las copias se controlan por cantidades (RF-07).
-- Disponibles = total - deteriorados - perdidos - prestados (calculado).
-- ---------------------------------------------------------------------
CREATE TABLE materiales (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo               VARCHAR(40)  NOT NULL,
  titulo               VARCHAR(255) NOT NULL,
  autor                VARCHAR(200) NULL,
  editorial            VARCHAR(150) NULL,
  anio                 SMALLINT     NULL,
  area                 VARCHAR(80)  NULL,
  nivel                VARCHAR(40)  NULL,
  tema                 VARCHAR(150) NULL,
  descripcion          TEXT         NULL,
  tipo_material_id     INT UNSIGNED NULL,
  cantidad_total       INT          NOT NULL DEFAULT 1,
  cantidad_deteriorada INT          NOT NULL DEFAULT 0,
  cantidad_perdida     INT          NOT NULL DEFAULT 0,
  ubicacion            VARCHAR(80)  NULL COMMENT 'Solo visible para Bibliotecaria y Auxiliar',
  activo               TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'RF-05: 0 = inactivo, se conserva el historial',
  creado_por           INT UNSIGNED NULL,
  creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_materiales_codigo (codigo),
  KEY idx_mat_titulo (titulo),
  KEY idx_mat_autor (autor),
  KEY idx_mat_area (area, nivel),
  CONSTRAINT fk_mat_tipo FOREIGN KEY (tipo_material_id) REFERENCES tipos_material(id),
  CONSTRAINT fk_mat_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trazabilidad de cambios de cantidad y estado (RF-05, RF-07, RF-08)
CREATE TABLE movimientos_inventario (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id  INT UNSIGNED NOT NULL,
  tipo         ENUM('alta','importacion','ajuste_cantidad','deterioro','reparado','perdida','reposicion','inactivar','activar','edicion') NOT NULL,
  cantidad     INT          NOT NULL DEFAULT 0,
  observacion  VARCHAR(255) NULL,
  usuario_id   INT UNSIGNED NOT NULL,
  fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_material (material_id, fecha),
  CONSTRAINT fk_mov_material FOREIGN KEY (material_id) REFERENCES materiales(id),
  CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Préstamos y devoluciones (RF-11 a RF-18)
-- estado: activo | devuelto | perdido (pendiente de reposición) | repuesto
-- ---------------------------------------------------------------------
CREATE TABLE prestamos (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id          INT UNSIGNED NOT NULL,
  lector_id            INT UNSIGNED NOT NULL,
  fecha_prestamo       DATETIME     NOT NULL,
  fecha_vencimiento    DATE         NOT NULL,
  registrado_por       INT UNSIGNED NOT NULL COMMENT 'RF-17: responsable del préstamo',
  estado               ENUM('activo','devuelto','perdido','repuesto') NOT NULL DEFAULT 'activo',
  fecha_devolucion     DATETIME     NULL,
  recibido_por         INT UNSIGNED NULL COMMENT 'RF-17: responsable de la devolución',
  condicion_devolucion ENUM('bueno','deteriorado') NULL,
  fecha_reposicion     DATETIME     NULL,
  reposicion_por       INT UNSIGNED NULL,
  aviso_unico_ejemplar TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'RF-14: se prestó el último ejemplar disponible',
  renovaciones         INT          NOT NULL DEFAULT 0,
  observacion          VARCHAR(255) NULL,
  KEY idx_pre_estado (estado, fecha_vencimiento),
  KEY idx_pre_lector (lector_id, estado),
  KEY idx_pre_material (material_id, estado),
  KEY idx_pre_fecha (fecha_prestamo),
  KEY idx_pre_dev (fecha_devolucion),
  CONSTRAINT fk_pre_material FOREIGN KEY (material_id)    REFERENCES materiales(id),
  CONSTRAINT fk_pre_lector   FOREIGN KEY (lector_id)      REFERENCES usuarios(id),
  CONSTRAINT fk_pre_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_pre_recibido FOREIGN KEY (recibido_por)   REFERENCES usuarios(id),
  CONSTRAINT fk_pre_repos    FOREIGN KEY (reposicion_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renovaciones / ampliaciones de plazo con justificación (RF-15)
CREATE TABLE renovaciones (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prestamo_id      INT UNSIGNED NOT NULL,
  fecha_anterior   DATE         NOT NULL,
  fecha_nueva      DATE         NOT NULL,
  justificacion    VARCHAR(255) NOT NULL,
  autorizado_por   INT UNSIGNED NOT NULL,
  fecha            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ren_prestamo (prestamo_id),
  CONSTRAINT fk_ren_prestamo FOREIGN KEY (prestamo_id)    REFERENCES prestamos(id),
  CONSTRAINT fk_ren_usuario  FOREIGN KEY (autorizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Datos iniciales obligatorios
-- ---------------------------------------------------------------------
INSERT INTO configuracion (clave, valor, descripcion) VALUES
 ('max_prestamos_estudiante', '2',  'Máximo de libros simultáneos por estudiante'),
 ('max_prestamos_docente',    '0',  'Máximo de libros simultáneos por docente (0 = sin límite)'),
 ('bloquear_con_vencidos',    '1',  '1 = no se presta a lectores con préstamos vencidos'),
 ('bloquear_con_perdidos',    '1',  '1 = no se presta a lectores con libros perdidos sin reponer'),
 ('dias_alerta_proximo',      '2',  'Días de anticipación para alertar préstamos próximos a vencer'),
 ('fin_periodo_academico',    '2026-12-18', 'Fecha de cierre para préstamos de textos escolares'),
 ('patron_codigo',            '',   'Expresión regular opcional que deben cumplir los códigos (vacío = libre)'),
 ('periodo_actual',           '2026', 'Periodo académico vigente');

INSERT INTO tipos_material (nombre, dias_prestamo, hasta_fin_periodo) VALUES
 ('Lectura corta',  2, 0),
 ('Obra literaria', 5, 0),
 ('Texto escolar',  0, 1),
 ('Consulta / referencia', 1, 0);

-- Cuenta inicial del Administrador.
-- Correo: admin@iesanjuan.edu.pe   Contraseña temporal: Admin2026!
-- Se exige cambiarla en el primer ingreso.
INSERT INTO usuarios (dni, nombres, apellidos, correo, clave_hash, rol, activo, debe_cambiar_clave) VALUES
 ('00000000', 'Administrador', 'del Sistema', 'admin@iesanjuan.edu.pe',
  '$2y$10$IkiFuIrHi7U.eEHBJcDmpu5o1NZ/dDD3ezgcQzI4vge5o9emI1JSa', 'admin', 1, 1);
