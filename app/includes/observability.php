<?php

declare(strict_types=1);

function app_log(PDO $pdo, string $level, string $event, string $message, array $context = []): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO app_logs (level, event, message, context, ip, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $level,
            $event,
            mb_substr($message, 0, 1000),
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null,
        ]);
    } catch (Throwable $exception) {
        error_log("[{$level}] {$event}: {$message}");
    }
}

function audit_log(PDO $pdo, string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (usuario_id, action, entity, entity_id, details, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null,
            $action,
            $entity,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $exception) {
        error_log("Audit log failed: {$action} {$entity}");
    }
}

function monitor_duration(PDO $pdo, string $event, float $startedAt, int $thresholdMs = 800, array $context = []): void
{
    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    if ($durationMs >= $thresholdMs) {
        app_log($pdo, 'warning', $event, "Operacion lenta: {$durationMs} ms", $context + ['duration_ms' => $durationMs]);
    }
}
