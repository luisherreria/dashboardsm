<?php
/**
 * Controlador base del front controller.
 * Compatible con PHP 5.6.
 */

class Controller
{
    protected function estaLogueado()
    {
        return isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0;
    }

    protected function requerirLogin()
    {
        if (!$this->estaLogueado()) {
            $this->redirigir('index.php?c=auth&a=login');
        }
    }

    protected function redirigir($ruta)
    {
        header('Location: ' . $ruta);
        exit;
    }

    protected function json($datos)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $opciones = 0;
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $opciones = JSON_UNESCAPED_UNICODE;
        }
        $json = json_encode($datos, $opciones);
        if ($json === false) {
            $json = '{"status":"error","message":"No se pudo preparar la respuesta."}';
        }
        echo $json;
        exit;
    }

    protected function render($vista, $datos)
    {
        if (!is_array($datos)) {
            $datos = array();
        }

        extract($datos, EXTR_SKIP);

        require APP_PATH . '/views/layouts/header.php';
        require APP_PATH . '/views/' . $vista . '.php';
        require APP_PATH . '/views/layouts/footer.php';
    }
}
