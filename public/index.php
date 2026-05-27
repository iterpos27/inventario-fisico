<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

$pages = [
    'login' => APP_PATH . '/auth/login.php',
    'logout' => APP_PATH . '/auth/logout.php',
    'dashboard' => APP_PATH . '/pages/dashboard.php',
    'conteo' => APP_PATH . '/pages/conteo.php',
    'historial_conteos' => APP_PATH . '/pages/historial_conteos.php',
    'productos' => APP_PATH . '/pages/productos.php',
    'estadisticas' => APP_PATH . '/pages/estadisticas.php',
    'agencias' => APP_PATH . '/pages/agencias.php',
    'tendencias' => APP_PATH . '/pages/tendencias.php',
    'toma_detalle' => APP_PATH . '/pages/toma_detalle.php',
    'usuarios' => APP_PATH . '/pages/usuarios.php',
    'agregar_producto' => APP_PATH . '/actions/agregar_producto.php',
    'importar_productos' => APP_PATH . '/actions/importar_productos.php',
    'exportar' => APP_PATH . '/actions/exportar.php',
    'configuracion' => APP_PATH . '/actions/configuracion.php',
    'reportes' => APP_PATH . '/reports/reportes.php',
    'reportes_diarios' => APP_PATH . '/reports/reportes_diarios.php',
    'reportes_mensuales' => APP_PATH . '/reports/reportes_mensuales.php',
];

$actions = [
    'agregar_producto' => APP_PATH . '/actions/agregar_producto.php',
    'asignar_usuarios_toma' => APP_PATH . '/actions/asignar_usuarios_toma.php',
    'actualizar_agencia' => APP_PATH . '/actions/actualizar_agencia.php',
    'actualizar_usuario' => APP_PATH . '/actions/actualizar_usuario.php',
    'buscar_producto' => APP_PATH . '/actions/buscar_producto.php',
    'cambiar_estado_agencia' => APP_PATH . '/actions/cambiar_estado_agencia.php',
    'cambiar_estado_toma' => APP_PATH . '/actions/cambiar_estado_toma.php',
    'crear_conteo' => APP_PATH . '/actions/crear_conteo.php',
    'crear_usuario' => APP_PATH . '/actions/crear_usuario.php',
    'descargar_consolidado' => APP_PATH . '/actions/descargar_consolidado.php',
    'descargar_excel' => APP_PATH . '/actions/descargar_excel.php',
    'editar_producto' => APP_PATH . '/actions/editar_producto.php',
    'eliminar_producto' => APP_PATH . '/actions/eliminar_producto.php',
    'eliminar_agencia' => APP_PATH . '/actions/eliminar_agencia.php',
    'eliminar_toma' => APP_PATH . '/actions/eliminar_toma.php',
    'eliminar_usuario' => APP_PATH . '/actions/eliminar_usuario.php',
    'editar_toma' => APP_PATH . '/actions/editar_toma.php',
    'finalizar_conteo' => APP_PATH . '/actions/finalizar_conteo.php',
    'guardar_agencia' => APP_PATH . '/actions/guardar_agencia.php',
    'guardar_borrador' => APP_PATH . '/actions/guardar_borrador.php',
    'generar_consolidado' => APP_PATH . '/actions/generar_consolidado.php',
    'habilitar_conteo_usuario' => APP_PATH . '/actions/habilitar_conteo_usuario.php',
    'importar_productos' => APP_PATH . '/actions/importar_productos.php',
    'importar_productos_procesar' => APP_PATH . '/actions/importar_productos_procesar.php',
    'iniciar_conteo' => APP_PATH . '/actions/iniciar_conteo.php',
    'login_procesar' => APP_PATH . '/actions/login_procesar.php',
    'logo_procesar' => APP_PATH . '/actions/logo_procesar.php',
];

$apiRoutes = [
    'login' => APP_PATH . '/api/login.php',
    'logout' => APP_PATH . '/api/logout.php',
    'tomas' => APP_PATH . '/api/tomas.php',
    'iniciar_conteo' => APP_PATH . '/api/iniciar_conteo.php',
    'detalle_conteo' => APP_PATH . '/api/detalle_conteo.php',
    'productos' => APP_PATH . '/api/productos.php',
    'guardar_borrador' => APP_PATH . '/api/guardar_borrador.php',
    'finalizar_conteo' => APP_PATH . '/api/finalizar_conteo.php',
];

$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
$segments = $requestPath === '' ? [] : explode('/', $requestPath);
$file = basename(end($segments) ?: '');
$parent = count($segments) > 1 ? $segments[count($segments) - 2] : '';
$route = $file;
$baseFolder = trim(basename(BASE_URL), '/');
if ($route === $baseFolder || $route === 'public') {
    $route = '';
}

$action = (string) ($_GET['action'] ?? '');
if ($action === '' && $parent === 'actions') {
    $action = basename($route, '.php');
}

$apiRoute = '';
if ($parent === 'api') {
    $apiRoute = basename($route, '.php');
}

if ($apiRoute !== '') {
    if (!isset($apiRoutes[$apiRoute])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Endpoint no encontrado']);
        exit;
    }
    define('CURRENT_ROUTE', 'api/' . $apiRoute);
    require $apiRoutes[$apiRoute];
    exit;
}

if ($action !== '') {
    if (!isset($actions[$action])) {
        http_response_code(404);
        exit('Accion no encontrada.');
    }
    define('CURRENT_ROUTE', $action);
    require $actions[$action];
    exit;
}

$page = '';
if ($route !== '' && $route !== 'index.php') {
    $page = basename($route, '.php');
} else {
    $page = (string) ($_GET['page'] ?? '');
}
if ($page === '') {
    $page = is_logged_in() ? (current_user_role() === 'admin' ? 'dashboard' : 'conteo') : 'login';
}

if (!isset($pages[$page])) {
    http_response_code(404);
    exit('Pagina no encontrada.');
}

define('CURRENT_ROUTE', $page);
$_SERVER['PHP_SELF'] = BASE_URL . '/' . $page . '.php';
require $pages[$page];


