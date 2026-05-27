<?php

declare(strict_types=1);

final class ConteoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findActiveDraftForUser(int $conteoId, int $usuarioId, bool $lock = false): ?array
    {
        $lockSql = $lock ? ' FOR UPDATE' : '';
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.toma_id
             FROM conteos c
             INNER JOIN tomas_fisicas t ON t.id = c.toma_id
             WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'
             {$lockSql}"
        );
        $stmt->execute([$conteoId, $usuarioId]);
        $conteo = $stmt->fetch();

        return $conteo ?: null;
    }

    public function lockToma(int $tomaId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tomas_fisicas WHERE id = ? FOR UPDATE');
        $stmt->execute([$tomaId]);

        return (bool) $stmt->fetch();
    }

    public function finalizarConteo(int $conteoId, string $archivoExcel): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE conteos
             SET estado = 'finalizado', fecha_finalizacion = COALESCE(fecha_finalizacion, NOW()), archivo_excel = ?
             WHERE id = ?"
        );
        $stmt->execute([$archivoExcel, $conteoId]);
    }

    public function finalizarAsignacion(int $tomaId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare("UPDATE toma_usuarios SET estado = 'finalizado' WHERE toma_id = ? AND usuario_id = ?");
        $stmt->execute([$tomaId, $usuarioId]);
    }

    public function cerrarTomaSiCompleta(int $tomaId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS asignados,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
             FROM toma_usuarios
             WHERE toma_id = ?"
        );
        $stmt->execute([$tomaId]);
        $avance = $stmt->fetch();
        if (!$avance || (int) $avance['asignados'] <= 0 || (int) $avance['asignados'] !== (int) $avance['finalizados']) {
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE tomas_fisicas SET estado = 'finalizada', fecha_finalizacion = NOW() WHERE id = ? AND estado = 'abierta'");
        $stmt->execute([$tomaId]);
    }
}
