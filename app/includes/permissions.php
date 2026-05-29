<?php

declare(strict_types=1);

const APP_ROLE_ADMIN = 'admin';
const APP_ROLE_SUPERVISOR = 'supervisor';
const APP_ROLE_REPORTES = 'reportes';
const APP_ROLE_OPERADOR = 'operador';
const APP_ROLE_USUARIO = 'usuario';

function normalize_role(string $role): string
{
    return $role === APP_ROLE_OPERADOR ? APP_ROLE_USUARIO : $role;
}

function role_can(string $role, string $permission): bool
{
    $role = normalize_role($role);
    $matrix = [
        APP_ROLE_ADMIN => ['admin', 'reports', 'count', 'api_count'],
        APP_ROLE_SUPERVISOR => ['reports', 'count'],
        APP_ROLE_REPORTES => ['reports'],
        APP_ROLE_USUARIO => ['count', 'api_count'],
    ];

    return in_array($permission, $matrix[$role] ?? [], true);
}

function current_user_can(string $permission): bool
{
    return role_can(current_user_role(), $permission);
}

function require_permission(string $permission): void
{
    require_login();
    if (!current_user_can($permission)) {
        header('Location: ' . page_url(current_user_role() === APP_ROLE_USUARIO ? 'conteo' : 'dashboard'));
        exit;
    }
}
