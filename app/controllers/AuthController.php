<?php
/**
 * Login, autenticación AJAX y cierre de sesión.
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class AuthController extends Controller
{
    const MAX_INTENTOS = 5;
    const BLOQUEO_SEGUNDOS = 600;

    public function login()
    {
        if ($this->estaLogueado()) {
            $this->redirigir('index.php?c=dashboard&a=evolucionPadrones');
        }

        $csrf = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        $salida = isset($_GET['salida']) && $_GET['salida'] === '1';

        header('Cache-Control: no-store, no-cache, must-revalidate');
        require APP_PATH . '/views/auth/login.php';
    }

    public function ajaxLogin()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Método no permitido.',
            ));
        }

        $tokenSesion = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
        $tokenPost = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
        if ($tokenSesion === '' || $tokenPost === '' || !hash_equals($tokenSesion, $tokenPost)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La sesión del formulario venció. Recargue la página.',
            ));
        }

        if ($this->bloqueoVigente()) {
            $this->json(array(
                'status'  => 'blocked',
                'message' => 'Demasiados intentos fallidos. El acceso queda bloqueado unos minutos.',
            ));
        }

        $login = '';
        if (isset($_POST['usuario'])) {
            $login = trim((string) $_POST['usuario']);
        } elseif (isset($_POST['username'])) {
            $login = trim((string) $_POST['username']);
        } elseif (isset($_POST['email'])) {
            $login = trim((string) $_POST['email']);
        }

        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        if ($login === '' || $password === '') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Complete usuario y contraseña.',
            ));
        }

        if (!preg_match('/^[A-Za-z0-9._@\-]{1,150}$/', $login)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El usuario o el correo no tiene un formato válido.',
            ));
        }

        if (strlen($password) > 128) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La contraseña supera el largo permitido.',
            ));
        }

        try {
            $modelo = new UsuarioModel();
            $resultado = $modelo->autenticar($login, $password);
        } catch (Exception $e) {
            error_log('AuthController::ajaxLogin — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo validar el acceso. Intente nuevamente.',
            ));
        }

        if (!is_array($resultado) || empty($resultado['ok'])) {
            $motivo = (is_array($resultado) && isset($resultado['motivo'])) ? $resultado['motivo'] : 'invalidas';
            if ($motivo === 'bloqueado') {
                $this->json(array(
                    'status'  => 'blocked',
                    'message' => 'Su acceso se encuentra bloqueado. Comuníquese con administración.',
                ));
            }

            if ($this->registrarFallo()) {
                $this->json(array(
                    'status'  => 'blocked',
                    'message' => 'Demasiados intentos fallidos. El acceso queda bloqueado unos minutos.',
                ));
            }

            $this->json(array(
                'status'  => 'error',
                'message' => 'Usuario o contraseña incorrectos.',
            ));
        }

        $this->iniciarSesion($resultado['usuario']);

        $nombre = isset($resultado['usuario']['nombre']) ? $resultado['usuario']['nombre'] : '';
        $this->json(array(
            'status'   => 'success',
            'message'  => 'Bienvenido, ' . $nombre . '.',
            'redirect' => 'index.php?c=dashboard&a=evolucionPadrones',
        ));
    }

    public function logout()
    {
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: index.php?c=auth&a=login&salida=1');
        exit;
    }

    private function iniciarSesion($usuario)
    {
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = (int) $usuario['usuario_id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['categ'] = $usuario['categ'];
        $_SESSION['codigo_prestador'] = isset($usuario['codigo_prestador']) ? $usuario['codigo_prestador'] : '';
        $_SESSION['tipo_correo'] = isset($usuario['tipo_correo']) ? $usuario['tipo_correo'] : '';
        $_SESSION['permisos'] = isset($usuario['permisos']) && is_array($usuario['permisos']) ? $usuario['permisos'] : array();
        $_SESSION['ver_pagos'] = isset($usuario['ver_pagos']) ? (int) $usuario['ver_pagos'] : 0;
        $_SESSION['login_intentos'] = 0;
        unset($_SESSION['login_bloqueo_hasta']);
    }

    private function bloqueoVigente()
    {
        $hasta = isset($_SESSION['login_bloqueo_hasta']) ? (int) $_SESSION['login_bloqueo_hasta'] : 0;
        if ($hasta > time()) {
            return true;
        }
        if ($hasta > 0) {
            unset($_SESSION['login_bloqueo_hasta']);
        }
        return false;
    }

    /**
     * @return bool true cuando este fallo dispara el bloqueo temporal
     */
    private function registrarFallo()
    {
        $intentos = isset($_SESSION['login_intentos']) ? (int) $_SESSION['login_intentos'] : 0;
        $intentos++;
        $_SESSION['login_intentos'] = $intentos;

        if ($intentos >= self::MAX_INTENTOS) {
            $_SESSION['login_bloqueo_hasta'] = time() + self::BLOQUEO_SEGUNDOS;
            $_SESSION['login_intentos'] = 0;
            return true;
        }

        return false;
    }
}
