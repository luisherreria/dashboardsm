<?php
/**
 * Panel posterior al login: evolución de padrones, accesos y administradores.
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../models/PadronModel.php';

class DashboardController extends Controller
{
    public function index()
    {
        $this->evolucionPadrones();
    }

    public function evolucionPadrones()
    {
        $this->requerirLogin();

        $avisoPermiso = isset($_GET['aviso']) && $_GET['aviso'] === 'permiso';
        $obras = array();
        $anios = array();
        $errorCarga = '';

        try {
            $modelo = new PadronModel();
            $obras = $modelo->getObrasSociales();
            $anios = $modelo->getAniosDisponibles();
        } catch (Exception $e) {
            error_log('DashboardController::evolucionPadrones — ' . $e->getMessage());
            $errorCarga = 'No se pudo leer el padrón.';
        }

        $this->render('dashboard/evolucion_padrones', array(
            'pageTitle'    => 'COMEDICA — Evolución de Padrones',
            'menuActivo'   => 'padrones',
            'avisoPermiso' => $avisoPermiso,
            'obras'        => $obras,
            'anios'        => $anios,
            'errorCarga'   => $errorCarga,
            'breadcrumb'   => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Evolución de Padrones'),
            ),
        ));
    }

    public function ajaxGetEvolucionData()
    {
        if (!$this->estaLogueado()) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La sesión expiró. Vuelva a ingresar.',
            ));
        }

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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        $anios = array();
        if (isset($_POST['anios']) && is_array($_POST['anios'])) {
            $anios = $_POST['anios'];
        } elseif (isset($_POST['anios']) && $_POST['anios'] !== '') {
            $anios = explode(',', (string) $_POST['anios']);
        }

        if (count($anios) > 8) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione hasta 8 años para comparar.',
            ));
        }

        try {
            $modelo = new PadronModel();
            $respuesta = $modelo->construirRespuesta($obrasoc, $anios);
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetEvolucionData — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la evolución del padrón.',
            ));
        }

        $this->json($respuesta);
    }

    public function ajaxGetDemografiaYPlanes()
    {
        if (!$this->estaLogueado()) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La sesión expiró. Vuelva a ingresar.',
            ));
        }

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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        try {
            $modelo = new PadronModel();
            $respuesta = $modelo->getDemografiaYPlanes($obrasoc);
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetDemografiaYPlanes — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la demografía del padrón.',
            ));
        }

        $this->json($respuesta);
    }

    public function ajaxGetEvolucionEspeciales()
    {
        if (!$this->estaLogueado()) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La sesión expiró. Vuelva a ingresar.',
            ));
        }

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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        $limite = 12;
        if (isset($_POST['limite'])) {
            $limite = (int) $_POST['limite'];
        }

        try {
            $modelo = new PadronModel();
            $datos = $modelo->getEvolucionProgramasEspeciales($obrasoc, $limite);
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetEvolucionEspeciales — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la evolución de los programas especiales.',
            ));
        }

        if (!is_array($datos)) {
            $datos = array();
        }
        $datos['status'] = 'success';
        if (!isset($datos['message'])) {
            $datos['message'] = '';
        }
        $this->json($datos);
    }

    public function administradores()
    {
        $this->requerirLogin();

        if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
            $this->redirigir('index.php?c=dashboard&a=evolucionPadrones&aviso=permiso');
        }

        $listado = array();
        $errorListado = '';

        try {
            $modelo = new UsuarioModel();
            $listado = $modelo->listarAdministradores();
        } catch (Exception $e) {
            error_log('DashboardController::administradores — ' . $e->getMessage());
            $errorListado = 'No se pudo leer el listado de administradores.';
        }

        $this->render('dashboard/administradores', array(
            'pageTitle'    => 'COMEDICA — Administradores',
            'menuActivo'   => 'admin',
            'listado'      => $listado,
            'errorListado' => $errorListado,
            'breadcrumb'   => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Administradores'),
            ),
        ));
    }
}
