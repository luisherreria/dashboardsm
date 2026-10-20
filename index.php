<?php
/**
 * Front controller.
 *   index.php                         → login, o evolución de padrones si hay sesión
 *   index.php?c=auth&a=login
 *   index.php?c=auth&a=ajaxLogin      → POST JSON
 *   index.php?c=auth&a=logout
 *   index.php?c=dashboard&a=evolucionPadrones
 *   index.php?c=dashboard&a=administradores
 *
 * Compatible con PHP 5.6.
 */

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

$hostActual = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$esLocal = ($hostActual === 'localhost' || $hostActual === '127.0.0.1' || $hostActual === '::1' || strpos($hostActual, 'localhost:') === 0);
$accionPedida = isset($_GET['a']) ? strtolower((string) $_GET['a']) : '';
$esAjax = ($accionPedida === 'ajaxlogin' || $accionPedida === 'ajaxgetevoluciondata' || $accionPedida === 'ajaxgetdemografiayplanes');
if ($esLocal && !$esAjax) {
    ini_set('display_errors', '1');
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
if (version_compare(PHP_VERSION, '5.5.2', '>=')) {
    ini_set('session.use_strict_mode', '1');
}

$cookiePath = '/';
if (isset($_SERVER['SCRIPT_NAME'])) {
    $cookiePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
}
if ($cookiePath === '/' || $cookiePath === '.' || $cookiePath === '') {
    $cookiePath = '/';
} else {
    $cookiePath = rtrim($cookiePath, '/') . '/';
}

session_name('DASHBOARDSMSESSID');
session_set_cookie_params(0, $cookiePath);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = dm_token();
}

define('ROOT_PATH', dirname(__FILE__));
define('APP_PATH', ROOT_PATH . '/app');

require_once ROOT_PATH . '/config/database.php';
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Controller.php';
require_once APP_PATH . '/models/UsuarioModel.php';
require_once APP_PATH . '/controllers/AuthController.php';
require_once APP_PATH . '/controllers/DashboardController.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$controlador = isset($_GET['c']) ? strtolower(trim((string) $_GET['c'])) : '';
$accion = isset($_GET['a']) ? trim((string) $_GET['a']) : '';

if (!preg_match('/^[a-z]*$/', $controlador)) {
    $controlador = '';
}
if (!preg_match('/^[A-Za-z]*$/', $accion)) {
    $accion = '';
}

$mapa = array(
    'auth' => array(
        'clase'    => 'AuthController',
        'acciones' => array('login' => true, 'ajaxLogin' => true, 'logout' => true),
        'publico'  => array('login' => true, 'ajaxLogin' => true, 'logout' => true),
        'default'  => 'login',
    ),
    'dashboard' => array(
        'clase'    => 'DashboardController',
        'acciones' => array(
            'index' => true,
            'evolucionPadrones' => true,
            'ajaxGetEvolucionData' => true,
            'ajaxGetDemografiaYPlanes' => true,
            'administradores' => true,
        ),
        'publico'  => array(),
        'default'  => 'evolucionPadrones',
    ),
);

$logueado = isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0;

if ($controlador === '') {
    if ($logueado) {
        header('Location: index.php?c=dashboard&a=evolucionPadrones');
        exit;
    }
    $controlador = 'auth';
    $accion = 'login';
}

if (!isset($mapa[$controlador])) {
    dm_no_encontrado($logueado);
}

if ($accion === '') {
    $accion = $mapa[$controlador]['default'];
}

if (!isset($mapa[$controlador]['acciones'][$accion])) {
    dm_no_encontrado($logueado);
}

$esPublica = isset($mapa[$controlador]['publico'][$accion]);
if (!$logueado && !$esPublica) {
    header('Location: index.php?c=auth&a=login');
    exit;
}

if (!method_exists($mapa[$controlador]['clase'], $accion)) {
    dm_no_encontrado($logueado);
}

$clase = $mapa[$controlador]['clase'];
$instancia = new $clase();
$instancia->$accion();

function dm_token()
{
    $bytes = false;
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(16);
    }
    if ($bytes === false) {
        $bytes = md5(uniqid((string) mt_rand(), true), true);
    }
    return bin2hex($bytes);
}

function dm_no_encontrado($logueado)
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $destino = $logueado
        ? 'index.php?c=dashboard&a=evolucionPadrones'
        : 'index.php?c=auth&a=login';
    $texto = $logueado ? 'Volver al panel' : 'Ir al login';
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Página no encontrada</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script></head>';
    echo '<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">';
    echo '<div class="bg-white rounded-2xl shadow-lg p-8 max-w-md text-center">';
    echo '<h1 class="text-xl font-bold text-slate-800 mb-2">Página no encontrada</h1>';
    echo '<p class="text-slate-500 mb-6">La ruta solicitada no existe en el sistema.</p>';
    echo '<a class="inline-block bg-blue-700 text-white rounded-lg px-4 py-2" href="' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '">' . $texto . '</a>';
    echo '</div></body></html>';
    exit;
}
