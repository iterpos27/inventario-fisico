<?php

function normalizar_codigo_producto(mixed $value): string
{
    $codigo = trim((string) $value);
    $codigo = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], '', $codigo);

    $sinEspacios = preg_replace('/\s+/u', '', $codigo);
    if (is_string($sinEspacios)) {
        $codigo = $sinEspacios;
    }

    $sinInvisibles = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $codigo);
    if (is_string($sinInvisibles)) {
        $codigo = $sinInvisibles;
    }

    if (preg_match('/^\d+\.0+$/', $codigo)) {
        $codigo = preg_replace('/\.0+$/', '', $codigo) ?? $codigo;
    }

    return $codigo;
}
