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
        "CREATE TABLE IF NOT EXISTS tomas_fisicas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            numero_toma VARCHAR(20) NOT NULL UNIQUE,
            agencia VARCHAR(120) NOT NULL,
            fecha_toma DATE NOT NULL,
            nombre_toma VARCHAR(220) NOT NULL,
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

    ensure_column_exists($pdo, 'conteos', 'toma_id', 'toma_id INT UNSIGNED NULL AFTER id');

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

    $oldAdminHash = '$2y$10$4bG34LfURR5Ua9DRXo.UneDnfgM6fAF/xyKi6jSEqhm2A8psnHPOC';
    $currentAdminHash = '$2y$10$fqF1pDCz79WKhYMwU8rsneZN.HEboXW0Whd8hxfoKGVQlx/eESn0q';
    $stmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE usuario = ? AND password = ?');
    $stmt->execute([$currentAdminHash, 'admin', $oldAdminHash]);
}
