<?php

declare(strict_types=1);

function validar_ventana_toma(array $toma, ?DateTimeImmutable $now = null): void
{
    $now ??= new DateTimeImmutable('now');

    $fechaInicio = trim((string) ($toma['fecha_habilitacion'] ?? ''));
    if ($fechaInicio !== '') {
        $horaInicio = trim((string) ($toma['hora_inicio'] ?? '00:00:00')) ?: '00:00:00';
        $start = new DateTimeImmutable("{$fechaInicio} {$horaInicio}");
        if ($now < $start) {
            throw new RuntimeException('La toma fisica no ha iniciado. Habilitada desde el: ' . $start->format('d/m/Y H:i'));
        }
    }

    $fechaFin = trim((string) ($toma['fecha_cierre'] ?? ''));
    if ($fechaFin !== '') {
        $horaFin = trim((string) ($toma['hora_fin'] ?? '23:59:59')) ?: '23:59:59';
        $end = new DateTimeImmutable("{$fechaFin} {$horaFin}");
        if ($now > $end) {
            throw new RuntimeException('El periodo habilitado de conteo para esta toma ha concluido.');
        }
    }
}
