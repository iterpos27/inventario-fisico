CREATE DATABASE IF NOT EXISTS centro_ruliman_inventario
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE centro_ruliman_inventario;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  usuario VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
  estado TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NOT NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_productos_descripcion (descripcion),
  INDEX idx_productos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agencias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_agencias_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tomas_fisicas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero_toma VARCHAR(20) NOT NULL UNIQUE,
  agencia VARCHAR(120) NULL,
  fecha_toma DATE NOT NULL,
  fecha_habilitacion DATE NULL,
  fecha_cierre DATE NULL,
  hora_inicio TIME NULL,
  hora_fin TIME NULL,
  nombre_toma VARCHAR(500) NOT NULL,
  estado ENUM('abierta', 'finalizada') NOT NULL DEFAULT 'abierta',
  creado_por INT UNSIGNED NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_finalizacion DATETIME NULL,
  archivo_excel VARCHAR(255) NULL,
  CONSTRAINT fk_tomas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  INDEX idx_tomas_estado (estado),
  INDEX idx_tomas_fecha (fecha_toma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS toma_usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  toma_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  estado ENUM('asignado', 'en_proceso', 'finalizado') NOT NULL DEFAULT 'asignado',
  fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_toma_usuarios_toma FOREIGN KEY (toma_id) REFERENCES tomas_fisicas(id) ON DELETE CASCADE,
  CONSTRAINT fk_toma_usuarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  UNIQUE KEY uq_toma_usuario (toma_id, usuario_id),
  INDEX idx_toma_usuarios_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  toma_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NOT NULL,
  nombre_conteo VARCHAR(160) NOT NULL,
  estado ENUM('borrador', 'finalizado') NOT NULL DEFAULT 'borrador',
  fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_finalizacion DATETIME NULL,
  archivo_excel VARCHAR(255) NULL,
  CONSTRAINT fk_conteos_toma FOREIGN KEY (toma_id) REFERENCES tomas_fisicas(id),
  CONSTRAINT fk_conteos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_conteos_estado (estado),
  INDEX idx_conteos_toma (toma_id),
  INDEX idx_conteos_usuario (usuario_id),
  UNIQUE KEY uq_conteo_toma_usuario (toma_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteo_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conteo_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  codigo VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_detalle_conteo FOREIGN KEY (conteo_id) REFERENCES conteos(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  UNIQUE KEY uq_conteo_producto (conteo_id, producto_id),
  INDEX idx_detalle_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (nombre, usuario, password, rol, estado)
VALUES (
  'Administrador',
  'admin',
  '$2y$10$fqF1pDCz79WKhYMwU8rsneZN.HEboXW0Whd8hxfoKGVQlx/eESn0q',
  'admin',
  1
)
ON DUPLICATE KEY UPDATE usuario = usuario;
