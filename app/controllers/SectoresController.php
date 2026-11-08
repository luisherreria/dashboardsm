<?php
/**
 * Control de sectores: órdenes, facturación y auditoría de facturas.
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/SectoresModel.php';

class SectoresController extends Controller
{
    public function index()
    {
        $this->ordenes();
    }

    public function ordenes()
    {
        $this->pantalla('ordenes');
    }

    public function facturacion()
    {
        $this->pantalla('facturacion');
    }

    public function auditoriaFacturas()
    {
        $this->pantalla('auditoria_facturas');
    }

    public function ajaxResumenOrdenes()
    {
        $rango = $this->leerRango();
        try {
            $modelo = new SectoresModel();
            $cargadas = $modelo->resumenCargadas($rango['desde'], $rango['hasta_exclusivo']);
            $estados = $modelo->resumenEstados($rango['desde'], $rango['hasta_exclusivo']);
        } catch (Exception $e) {
            error_log('SectoresController::ajaxResumenOrdenes — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo contar las órdenes del rango.',
            ));
        }

        $this->json(array(
            'status'   => 'success',
            'message'  => '',
            'desde'    => $rango['desde'],
            'hasta'    => $rango['hasta'],
            'cargadas' => $cargadas,
            'estados'  => $estados,
        ));
    }

    public function ajaxExplosionOrdenes()
    {
        $this->exigirPost();
        $dia = isset($_POST['dia']) ? trim((string) $_POST['dia']) : '';
        if (!$this->fechaValida($dia)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El día de la fila no es válido.',
            ));
        }

        $modo = isset($_POST['modo']) ? trim((string) $_POST['modo']) : '';
        $origen = isset($_POST['origen']) ? trim((string) $_POST['origen']) : '';
        $estado = isset($_POST['estado']) ? strtoupper(trim((string) $_POST['estado'])) : '';

        if ($modo !== 'cargadas' && $modo !== 'estado') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El detalle pedido no es válido.',
            ));
        }

        if ($origen !== '' && $origen !== 'ambulatorio' && $origen !== 'sanatorial' && $origen !== 'medicamentos') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El origen de la orden no es válido.',
            ));
        }

        if ($modo === 'estado' && $estado !== 'AUTORIZADA' && $estado !== 'RECHAZADA') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione autorizadas o rechazadas.',
            ));
        }

        try {
            $modelo = new SectoresModel();
            if ($modo === 'cargadas') {
                $filas = $modelo->explosionCargadas($dia, $origen);
            } else {
                $filas = $modelo->explosionEstado($dia, $estado);
            }
        } catch (Exception $e) {
            error_log('SectoresController::ajaxExplosionOrdenes — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo abrir el detalle de las órdenes.',
            ));
        }

        if (count($filas) > 2000) {
            $filas = array_slice($filas, 0, 2000);
        }

        $this->json(array(
            'status'  => 'success',
            'message' => '',
            'filas'   => $filas,
            'total'   => count($filas),
        ));
    }

    private function pantalla($reporte)
    {
        $this->requerirLogin();

        $titulos = array(
            'ordenes' => array(
                'titulo' => 'Órdenes y autorizaciones',
                'texto'  => 'Conteo diario de órdenes cargadas y de autorizadas o rechazadas.',
            ),
            'facturacion' => array(
                'titulo' => 'Carga de facturación',
                'texto'  => 'Data entry de facturación. El conteo se conecta en el paso siguiente.',
            ),
            'auditoria_facturas' => array(
                'titulo' => 'Auditoría de facturas',
                'texto'  => 'Control de facturas. El conteo se conecta en el paso siguiente.',
            ),
        );

        if (!isset($titulos[$reporte])) {
            $reporte = 'ordenes';
        }

        $rango = array('desde' => date('Y-m-d', strtotime('-13 days')), 'hasta' => date('Y-m-d'));
        $errorCarga = '';
        try {
            $modelo = new SectoresModel();
            $rango = $modelo->rangoSugerido();
        } catch (Exception $e) {
            error_log('SectoresController::pantalla — ' . $e->getMessage());
            $errorCarga = 'No se pudo preparar el rango de fechas.';
        }

        $menu = $reporte === 'auditoria_facturas' ? 'auditoria_facturas' : $reporte;

        $this->render('sectores/control', array(
            'pageTitle'   => 'COMEDICA — ' . $titulos[$reporte]['titulo'],
            'menuActivo'  => $menu,
            'reporte'     => $reporte,
            'titulo'      => $titulos[$reporte]['titulo'],
            'texto'       => $titulos[$reporte]['texto'],
            'desde'       => $rango['desde'],
            'hasta'       => $rango['hasta'],
            'errorCarga'  => $errorCarga,
            'breadcrumb'  => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Control de sectores', 'href' => 'index.php?c=sectores&a=ordenes'),
                array('label' => $titulos[$reporte]['titulo']),
            ),
        ));
    }

    private function exigirPost()
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
    }

    private function leerRango()
    {
        $this->exigirPost();

        $desde = isset($_POST['desde']) ? trim((string) $_POST['desde']) : '';
        $hasta = isset($_POST['hasta']) ? trim((string) $_POST['hasta']) : '';
        if (!$this->fechaValida($desde) || !$this->fechaValida($hasta)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Indique un rango de fechas válido.',
            ));
        }

        if ($desde > $hasta) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }

        $inicio = strtotime($desde . ' 00:00:00');
        $fin = strtotime($hasta . ' 00:00:00');
        $dias = (int) round(($fin - $inicio) / 86400);
        if ($dias > 366) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El rango máximo es de 12 meses.',
            ));
        }

        return array(
            'desde'            => $desde,
            'hasta'            => $hasta,
            'hasta_exclusivo'  => date('Y-m-d', strtotime($hasta . ' +1 day')),
        );
    }

    private function fechaValida($fecha)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return false;
        }
        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }
}
