<?php

declare(strict_types=1);

function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new InvalidArgumentException('Nombre de tabla o columna invalido');
    }

    $columnLike = $pdo->quote($column);
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE {$columnLike}");
    if ($stmt->fetch()) {
        return;
    }

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            usuario VARCHAR(60) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            rol ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
            estado TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS productos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(80) NOT NULL UNIQUE,
            descripcion VARCHAR(255) NOT NULL,
            estado TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_productos_descripcion (descripcion),
            INDEX idx_productos_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS agencias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL UNIQUE,
            estado TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agencias_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tomas_fisicas (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS conteos (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS conteo_detalle (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS toma_usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            toma_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            estado ENUM('asignado', 'en_proceso', 'finalizado') NOT NULL DEFAULT 'asignado',
            fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_toma_usuarios_toma FOREIGN KEY (toma_id) REFERENCES tomas_fisicas(id) ON DELETE CASCADE,
            CONSTRAINT fk_toma_usuarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
            UNIQUE KEY uq_toma_usuario (toma_id, usuario_id),
            INDEX idx_toma_usuarios_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(120) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            intentos INT UNSIGNED NOT NULL DEFAULT 0,
            bloqueado_hasta DATETIME NULL,
            ultimo_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_login_attempt (usuario, ip),
            INDEX idx_login_attempts_bloqueo (bloqueado_hasta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column_exists($pdo, 'conteos', 'toma_id', 'toma_id INT UNSIGNED NULL AFTER id');
    ensure_column_exists($pdo, 'tomas_fisicas', 'fecha_habilitacion', 'fecha_habilitacion DATE NULL AFTER fecha_toma');
    ensure_column_exists($pdo, 'tomas_fisicas', 'fecha_cierre', 'fecha_cierre DATE NULL AFTER fecha_habilitacion');
    ensure_column_exists($pdo, 'tomas_fisicas', 'hora_inicio', 'hora_inicio TIME NULL AFTER fecha_cierre');
    ensure_column_exists($pdo, 'tomas_fisicas', 'hora_fin', 'hora_fin TIME NULL AFTER hora_inicio');

    try {
        $pdo->exec('ALTER TABLE tomas_fisicas MODIFY agencia VARCHAR(120) NULL');
    } catch (Throwable $exception) {
        // Instalaciones antiguas pueden conservar la definicion previa hasta una migracion manual.
    }

    try {
        $pdo->exec('ALTER TABLE tomas_fisicas MODIFY nombre_toma VARCHAR(500) NOT NULL');
    } catch (Throwable $exception) {
        // La columna puede estar ya actualizada.
    }

    try {
        $pdo->exec('ALTER TABLE conteos ADD INDEX idx_conteos_toma (toma_id)');
    } catch (Throwable $exception) {
        // El indice ya puede existir en instalaciones actualizadas.
    }

    try {
        $pdo->exec('ALTER TABLE conteos ADD UNIQUE KEY uq_conteo_toma_usuario (toma_id, usuario_id)');
    } catch (Throwable $exception) {
        // La restriccion ya puede existir o los datos antiguos pueden impedirla.
    }

    try {
        $pdo->exec('ALTER TABLE conteos ADD CONSTRAINT fk_conteos_toma FOREIGN KEY (toma_id) REFERENCES tomas_fisicas(id)');
    } catch (Throwable $exception) {
        // La llave foranea ya puede existir o no estar disponible en datos antiguos.
    }

    $seedAdminUser = trim((string) env_value('APP_SEED_ADMIN_USER', ''));
    $seedAdminPassword = (string) env_value('APP_SEED_ADMIN_PASSWORD', '');
    if ($seedAdminUser !== '' && strlen($seedAdminPassword) >= 12) {
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, usuario, password, rol, estado)
             VALUES (?, ?, ?, 'admin', 1)
             ON DUPLICATE KEY UPDATE usuario = usuario"
        );
        $stmt->execute(['Administrador', $seedAdminUser, password_hash($seedAdminPassword, PASSWORD_DEFAULT)]);
    }
}


