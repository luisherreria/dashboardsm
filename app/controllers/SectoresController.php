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

        if ($modo === 'estado' && $estado !== 'AUTORIZADA' && $estado !== 'RECHAZADA' && $estado !== 'AMBAS') {
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
                $filas = $modelo->explosionEstado($dia, $estado, $origen);
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

    public function ajaxExplosionPracticas()
    {
        $this->exigirPost();
        $origen = isset($_POST['origen']) ? trim((string) $_POST['origen']) : '';
        $nro = isset($_POST['conroauto']) ? trim((string) $_POST['conroauto']) : '';

        if ($origen !== 'ambulatorio' && $origen !== 'sanatorial' && $origen !== 'medicamentos') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El origen de la orden no es válido.',
            ));
        }
        if ($nro === '' || !preg_match('/^[0-9]{1,14}$/', $nro)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La orden no tiene un número de autorización para buscar las prácticas.',
            ));
        }

        try {
            $modelo = new SectoresModel();
            $filas = $modelo->practicasDeOrden($origen, $nro);
        } catch (Exception $e) {
            error_log('SectoresController::ajaxExplosionPracticas — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudieron leer las prácticas de la orden.',
            ));
        }

        $this->json(array(
            'status'  => 'success',
            'message' => '',
            'filas'   => $filas,
            'total'   => count($filas),
        ));
    }

    public function ajaxResumenFacturacion()
    {
        $rango = $this->leerRango();
        try {
            $modelo = new SectoresModel();
            $resumen = $modelo->resumenFacturacion($rango['desde'], $rango['hasta_exclusivo']);
        } catch (Exception $e) {
            error_log('SectoresController::ajaxResumenFacturacion — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo contar la carga de facturación.',
            ));
        }

        $resumen['status'] = 'success';
        $resumen['message'] = '';
        $resumen['desde'] = $rango['desde'];
        $resumen['hasta'] = $rango['hasta'];
        $this->json($resumen);
    }

    public function ajaxDetalleFacturacion()
    {
        $rango = $this->leerRango();
        $usuario = isset($_POST['usuario']) ? strtoupper(trim((string) $_POST['usuario'])) : '';
        $dia = isset($_POST['dia']) ? trim((string) $_POST['dia']) : '';
        if ($usuario !== '' && !preg_match('/^[A-Z0-9]{1,10}$/', $usuario)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El usuario de data entry no es válido.',
            ));
        }
        if ($dia !== '' && !$this->fechaValida($dia)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El día de la fila no es válido.',
            ));
        }
        if ($usuario === '' && $dia === '') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Elija un usuario o un día.',
            ));
        }

        try {
            $modelo = new SectoresModel();
            $detalle = $modelo->detalleFacturacion($rango['desde'], $rango['hasta_exclusivo'], $usuario, $dia);
        } catch (Exception $e) {
            error_log('SectoresController::ajaxDetalleFacturacion — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo abrir el detalle de la carga.',
            ));
        }

        $this->json(array(
            'status'  => 'success',
            'message' => '',
            'filas'   => $detalle['filas'],
            'total'   => $detalle['total'],
            'recorte' => $detalle['recorte'],
        ));
    }

    public function ajaxAnalisisCuadro()
    {
        $rango = $this->leerRango();
        $cuadro = isset($_POST['cuadro']) ? trim((string) $_POST['cuadro']) : '';
        if ($cuadro !== 'cargadas' && $cuadro !== 'estados' && $cuadro !== 'facturacion') {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El cuadro a analizar no es válido.',
            ));
        }

        try {
            $modelo = new SectoresModel();
            if ($cuadro === 'facturacion') {
                $filas = $modelo->resumenFacturacion($rango['desde'], $rango['hasta_exclusivo']);
                $titulo = 'Carga de facturación por data entry';
                $sistema = 'Sos un analista de productividad de data entry de una obra social. '
                    . 'Los registros salen de vfpmedicos.detconsu, filtrados por COFECHACAR y agrupados por COUSUARIO. '
                    . 'COUSUARIO NOE no está: es auditora y sus filas son ajustes. No la cuentes ni la menciones como carga. '
                    . 'La jornada de cálculo es 8 horas con 60 minutos de descanso: 7 horas netas y 420 minutos por día en que la persona cargó. '
                    . 'por_hora es el promedio diario dividido 7. minutos es 420 dividido el promedio diario. '
                    . 'Escribí en español, directo, sin saludos, en HTML simple con <p> y <b>. '
                    . 'Usá solo estos números. No inventes causas ni horarios reales.';
            } elseif ($cuadro === 'cargadas') {
                $filas = $modelo->resumenCargadas($rango['desde'], $rango['hasta_exclusivo']);
                $titulo = 'Órdenes cargadas por día';
                $sistema = 'Sos un analista de órdenes de una obra social. El cuadro cuenta filas cargadas por COFECHA. '
                    . 'Columnas: ambulatorio (cartilla.ordenes), sanatorial (cartilla.sanorden), medicamentos (cartilla.ordenes_medicamentos) y total. '
                    . 'Escribí en español, directo, sin saludos, en HTML simple con <p> y <b>. '
                    . 'Usá solo estos números. No hables de autorizaciones, rechazos ni prácticas. No inventes causas.';
            } else {
                $filas = $modelo->resumenEstados($rango['desde'], $rango['hasta_exclusivo']);
                $titulo = 'Autorizadas y rechazadas por día';
                $sistema = 'Sos un analista de órdenes de una obra social. Este cuadro no incluye medicamentos. '
                    . 'AMB y SAN son órdenes con estado AUTORIZADA y fecha de autorización COFECAUTO, aunque CONROAUTO esté vacío: sin_numero cuenta esas. '
                    . 'RECH son órdenes con estado RECHAZADA y fecha de carga COFECHA, ambulatorias y sanatoriales juntas. '
                    . 'TOTPRAC son prácticas de detauto y sanautod ligadas a un número de autorización. '
                    . 'TOTAL suma autorizadas y rechazadas. '
                    . 'Escribí en español, directo, sin saludos, en HTML simple con <p> y <b>. '
                    . 'Usá solo estos números. No los compares con las órdenes cargadas. No inventes causas.';
            }
        } catch (Exception $e) {
            error_log('SectoresController::ajaxAnalisisCuadro — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo leer el cuadro para el análisis.',
            ));
        }

        require_once __DIR__ . '/../services/AiService.php';
        $servicio = new AiService();
        $pedido = 'Analizá el cuadro "' . $titulo . '" del ' . $rango['desde'] . ' al ' . $rango['hasta'] . '. '
            . 'Señalá el total del rango, el día más alto y, si se distingue, el día más bajo. Si hay empate, nombrá todos esos días. Máximo tres párrafos.';
        $resultado = $servicio->enviarConsulta(
            array(array('role' => 'user', 'text' => $pedido)),
            array(
                'cuadro' => $titulo,
                'desde'  => $rango['desde'],
                'hasta'  => $rango['hasta'],
                'filas'  => $filas,
            ),
            null,
            $sistema
        );
        if (empty($resultado['ok'])) {
            $this->json(array(
                'status'  => 'error',
                'message' => isset($resultado['texto']) ? $resultado['texto'] : 'La IA no respondió.',
            ));
        }

        $this->json(array(
            'status'  => 'success',
            'message' => '',
            'html'    => $resultado['texto'],
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
                'texto'  => 'Registros cargados por data entry, por usuario y por día.',
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
            if ($reporte === 'facturacion') {
                $rango = $modelo->rangoFacturacion();
            } else {
                $rango = $modelo->rangoSugerido();
            }
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
