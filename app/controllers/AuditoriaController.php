<?php
/**
 * Auditoría de consumo prestacional.
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/AuditoriaModel.php';
require_once __DIR__ . '/../models/PadronModel.php';

class AuditoriaController extends Controller
{
    public function controlPrestadores()
    {
        $this->requerirLogin();

        $obras = array();
        $periodos = array();
        $errorCarga = '';

        try {
            $padron = new PadronModel();
            $obras = $padron->getObrasSociales();
            $modelo = new AuditoriaModel();
            $periodos = $modelo->getPeriodosDisponibles();
        } catch (Exception $e) {
            error_log('AuditoriaController::controlPrestadores — ' . $e->getMessage());
            $errorCarga = 'No se pudo preparar la auditoría de consumo.';
        }

        $this->render('auditoria/control_prestadores', array(
            'pageTitle'  => 'COMEDICA — Auditoría de Consumo Prestacional',
            'menuActivo' => 'auditoria',
            'obras'      => $obras,
            'periodos'   => $periodos,
            'errorCarga' => $errorCarga,
            'breadcrumb' => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Auditoría de Consumo Prestacional'),
            ),
        ));
    }

    public function ajaxGetMaster()
    {
        $filtro = $this->leerFiltro(true);
        try {
            $modelo = new AuditoriaModel();
            if ($filtro['modalidad'] === 'prestador') {
                $datos = $modelo->getConsumoPorPrestador($filtro['obrasoc'], $filtro['desde'], $filtro['hasta']);
            } else {
                $datos = $modelo->getConsumoPorAfiliado($filtro['obrasoc'], $filtro['desde'], $filtro['hasta']);
            }
        } catch (Exception $e) {
            error_log('AuditoriaController::ajaxGetMaster — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo armar el cuadro de sobrefrecuencia.',
            ));
        }

        $this->responder($datos);
    }

    public function ajaxGetExplosion1()
    {
        $filtro = $this->leerFiltro(true);
        $afiliado = $this->textoPost('afiliado', 20);
        $practica = $this->textoPost('practica', 12);
        $prestador = $this->textoPost('prestador', 16);

        try {
            $modelo = new AuditoriaModel();
            if ($filtro['modalidad'] === 'prestador') {
                if ($prestador === '') {
                    $this->json(array(
                        'status'  => 'error',
                        'message' => 'Seleccione un prestador en el cuadro principal.',
                    ));
                }
                $datos = $modelo->getAfiliadosDePrestadorPractica(
                    $filtro['obrasoc'],
                    $prestador,
                    $practica,
                    $filtro['desde'],
                    $filtro['hasta']
                );
            } else {
                if ($afiliado === '') {
                    $this->json(array(
                        'status'  => 'error',
                        'message' => 'Seleccione un afiliado en el cuadro principal.',
                    ));
                }
                $datos = $modelo->getPrestadoresDeAfiliadoPractica(
                    $filtro['obrasoc'],
                    $afiliado,
                    $practica,
                    $filtro['desde'],
                    $filtro['hasta']
                );
            }
        } catch (Exception $e) {
            error_log('AuditoriaController::ajaxGetExplosion1 — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo abrir el detalle del consumo.',
            ));
        }

        $this->responder($datos);
    }

    public function ajaxGetExplosion2()
    {
        $filtro = $this->leerFiltro(true);
        $afiliado = $this->textoPost('afiliado', 20);
        $practica = $this->textoPost('practica', 12);
        $prestador = $this->textoPost('prestador', 16);

        if ($afiliado === '' || $prestador === '') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione el afiliado y el prestador para ver el detalle.',
            ));
        }

        try {
            $modelo = new AuditoriaModel();
            if ($filtro['modalidad'] === 'prestador') {
                $datos = $modelo->getDetalleCronologicoPrestador(
                    $filtro['obrasoc'],
                    $prestador,
                    $practica,
                    $afiliado,
                    $filtro['desde'],
                    $filtro['hasta']
                );
            } else {
                $datos = $modelo->getDetalleCronologicoAfiliado(
                    $filtro['obrasoc'],
                    $afiliado,
                    $practica,
                    $prestador,
                    $filtro['desde'],
                    $filtro['hasta']
                );
            }
        } catch (Exception $e) {
            error_log('AuditoriaController::ajaxGetExplosion2 — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo armar el detalle cronológico.',
            ));
        }

        $this->responder($datos);
    }

    public function ajaxGetFicha()
    {
        $filtro = $this->leerFiltro(false);
        $afiliado = $this->textoPost('afiliado', 20);
        if ($afiliado === '') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione un afiliado para ver la ficha.',
            ));
        }

        try {
            $modelo = new AuditoriaModel();
            $datos = $modelo->getFichaAfiliado($filtro['obrasoc'], $afiliado);
        } catch (Exception $e) {
            error_log('AuditoriaController::ajaxGetFicha — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo leer la ficha del afiliado.',
            ));
        }

        if (!is_array($datos)) {
            $datos = array();
        }
        $datos['status'] = 'success';
        $this->json($datos);
    }

    public function ajaxGetHistoriaAfiliado()
    {
        $this->exigirPost();
        $obra = isset($_POST['obrasoc']) ? strtoupper(trim((string) $_POST['obrasoc'])) : '';
        if ($obra === '' || !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obra)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social.',
            ));
        }

        $nro = $this->textoPost('nro_afiliado', 20);
        if ($nro === '') {
            $nro = $this->textoPost('afiliado', 20);
        }
        if ($nro === '') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione un afiliado para ver la historia.',
            ));
        }

        try {
            $modelo = new AuditoriaModel();
            $datos = $modelo->getHistoriaClinicaAfiliado($obra, $nro);
        } catch (Exception $e) {
            error_log('AuditoriaController::ajaxGetHistoriaAfiliado — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo leer la historia clínica del afiliado.',
            ));
        }

        $this->responder($datos);
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

    private function responder($datos)
    {
        if (!is_array($datos)) {
            $datos = array();
        }
        $datos['status'] = 'success';
        if (!isset($datos['message'])) {
            $datos['message'] = '';
        }
        $this->json($datos);
    }

    private function leerFiltro($conModalidad)
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

        $obra = isset($_POST['obrasoc']) ? strtoupper(trim((string) $_POST['obrasoc'])) : '';
        if ($obra === '' || !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obra)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social.',
            ));
        }

        $desde = isset($_POST['desde']) ? trim((string) $_POST['desde']) : '';
        $hasta = isset($_POST['hasta']) ? trim((string) $_POST['hasta']) : '';
        if (!$this->periodoValido($desde) || !$this->periodoValido($hasta)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El período debe tener formato aa/mm.',
            ));
        }

        if ($this->ordenPeriodo($desde) > $this->ordenPeriodo($hasta)) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }

        if ($this->spanMeses($desde, $hasta) > 11) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione un rango de hasta 12 meses.',
            ));
        }

        $modalidad = 'afiliado';
        if ($conModalidad) {
            $modalidad = isset($_POST['modalidad']) ? trim((string) $_POST['modalidad']) : '';
            if ($modalidad !== 'afiliado' && $modalidad !== 'prestador') {
                $this->json(array(
                    'status'  => 'error',
                    'message' => 'La modalidad no es válida.',
                ));
            }
        }

        return array(
            'obrasoc'    => $obra,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'modalidad'  => $modalidad,
        );
    }

    private function textoPost($clave, $maximo)
    {
        $valor = isset($_POST[$clave]) ? trim((string) $_POST[$clave]) : '';
        if ($valor !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,' . (int) $maximo . '}$/', $valor)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Hay un código de la fila que no es válido.',
            ));
        }
        return $valor;
    }

    private function periodoValido($periodo)
    {
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $periodo, $partes)) {
            return false;
        }
        $mes = (int) $partes[2];
        return $mes >= 1 && $mes <= 12;
    }

    private function ordenPeriodo($periodo)
    {
        $partes = explode('/', $periodo);
        $anio = (int) $partes[0];
        $mes = (int) $partes[1];
        if ($anio < 100) {
            $anio += 2000;
        }
        return ($anio * 100) + $mes;
    }

    private function spanMeses($desde, $hasta)
    {
        $origen = $this->ordenPeriodo($desde);
        $destino = $this->ordenPeriodo($hasta);
        $anio1 = (int) floor($origen / 100);
        $mes1 = $origen % 100;
        $anio2 = (int) floor($destino / 100);
        $mes2 = $destino % 100;
        return (($anio2 - $anio1) * 12) + ($mes2 - $mes1);
    }
}
