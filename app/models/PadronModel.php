<?php
/**
 * Evolución mensual de padrones.
 *
 * El corte de cada mes está en vfpmedicos.padrosoc (CANTREG). Ese total
 * coincide con COUNT(*) de vfpmedicos.t_padron en los períodos que esa
 * tabla todavía tiene (hasta 22/01). padrosoc sigue hasta el corte vigente.
 * El período se publica como aa/mm, el mismo formato de t_padron.PERIODO.
 *
 * ESTADO = 'A' no está cargado en todas las obras (en varias el estado
 * viene vacío y el padrón importado es el total del corte).
 *
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../../config/database.php';

class PadronModel
{
    private $db;
    private $cortes = null;
    private $nombres = null;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    public function getObrasSociales()
    {
        $vistos = array();
        $lista = array();

        foreach ($this->leerCortes() as $fila) {
            $codigo = $fila['obrasoc'];
            if (isset($vistos[$codigo])) {
                continue;
            }
            $vistos[$codigo] = true;
            $nombre = $this->nombreObra($codigo);
            $etiqueta = $codigo;
            if ($nombre !== '') {
                $etiqueta .= ' — ' . $nombre;
            }
            $lista[] = array(
                'codigo'   => $codigo,
                'nombre'   => $nombre,
                'etiqueta' => $etiqueta,
            );
        }

        usort($lista, array($this, 'compararCodigo'));
        return $lista;
    }

    public function getAniosDisponibles()
    {
        $mapa = array();
        foreach ($this->leerCortes() as $fila) {
            $mapa[(int) $fila['anio']] = true;
        }
        $anios = array();
        foreach ($mapa as $anio => $marca) {
            $anios[] = (int) $anio;
        }
        rsort($anios, SORT_NUMERIC);
        return $anios;
    }

    public function getKpisUltimoPeriodo($obrasoc)
    {
        $serie = $this->agregarPorPeriodo($this->filtrarCortes($obrasoc, array()));
        return $this->calcularKpis($serie);
    }

    public function getDatosInteranuales($obrasoc, $aniosArray)
    {
        $anios = $this->normalizarAnios($aniosArray);
        $cortes = $this->filtrarCortes($obrasoc, $anios);
        $serie = $this->agregarPorPeriodo($cortes);

        $porAnio = array();
        foreach ($anios as $anio) {
            $datos = array();
            for ($mes = 0; $mes < 12; $mes++) {
                $datos[] = null;
            }
            $porAnio[(int) $anio] = $datos;
        }

        foreach ($serie as $punto) {
            $anio = (int) $punto['anio'];
            $mes = (int) $punto['mes'];
            if (!isset($porAnio[$anio]) || $mes < 1 || $mes > 12) {
                continue;
            }
            $porAnio[$anio][$mes - 1] = (int) $punto['total'];
        }

        ksort($porAnio);
        $series = array();
        foreach ($porAnio as $anio => $datos) {
            $series[] = array(
                'anio' => (int) $anio,
                'data' => $datos,
            );
        }

        return array(
            'labels' => array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'),
            'series' => $series,
        );
    }

    public function getDatosHistoricosContinuos($obrasoc, $limitePeriodos = 24)
    {
        $limite = (int) $limitePeriodos;
        if ($limite < 1) {
            $limite = 24;
        }
        if ($limite > 240) {
            $limite = 240;
        }

        $serie = $this->agregarPorPeriodo($this->filtrarCortes($obrasoc, array()));
        $cantidad = count($serie);
        if ($cantidad > $limite) {
            $serie = array_slice($serie, $cantidad - $limite);
        }

        return $this->empaquetarHistorico($serie);
    }

    public function construirRespuesta($obrasoc, $aniosArray)
    {
        $anios = $this->normalizarAnios($aniosArray);
        if (count($anios) === 0) {
            $disponibles = $this->getAniosDisponibles();
            $toma = 0;
            foreach ($disponibles as $anio) {
                if ($toma >= 4) {
                    break;
                }
                $anios[] = (int) $anio;
                $toma++;
            }
            sort($anios, SORT_NUMERIC);
        }

        $detalle = $this->filtrarCortes($obrasoc, $anios);
        $serie = $this->agregarPorPeriodo($detalle);
        $historico = $this->empaquetarHistorico($serie);
        $movimiento = $this->empaquetarMovimiento($serie);
        $diagnostico = $this->getEstadisticasDiagnostico($obrasoc);

        return array(
            'status'     => 'success',
            'message'    => count($serie) === 0 ? 'No hay cortes para el filtro elegido.' : '',
            'anios'      => $anios,
            'kpis'       => $this->calcularKpis($serie),
            'interanual' => $this->getDatosInteranuales($obrasoc, $anios),
            'historico'  => $historico,
            'movimiento' => $movimiento,
            'tabla'      => $this->armarTabla($detalle),
            'insights'   => $this->armarInsights($serie, $diagnostico),
        );
    }

    /**
     * Diagnóstico del padrón de una obra (o de todas, si $obrasoc va vacío).
     * Máximo, mínimo y comparativa interanual salen de la serie completa.
     * Las marcas especiales salen del corte activo en padron_activos.enrol1.
     */
    public function getEstadisticasDiagnostico($obrasoc)
    {
        $serie = $this->agregarPorPeriodo($this->filtrarCortes($obrasoc, array()));
        $extremos = $this->extremosSerie($serie);

        return array(
            'serie'      => $serie,
            'maximo'     => $extremos['maximo'],
            'minimo'     => $extremos['minimo'],
            'tendencia'  => $extremos['tendencia'],
            'caida'      => $extremos['caida'],
            'interanual' => $this->compararInteranual($serie, $serie),
            'movimiento' => $this->diagnosticarMovimiento($serie),
            'especiales' => $this->leerMarcasEspeciales($obrasoc),
        );
    }

    private function leerCortes()
    {
        if ($this->cortes !== null) {
            return $this->cortes;
        }

        $sql = "SELECT TRIM(p.OBRASOC) AS obrasoc,
                       YEAR(p.DESDE) AS anio,
                       MONTH(p.DESDE) AS mes,
                       p.CANTREG AS total
                  FROM vfpmedicos.padrosoc p
                  INNER JOIN (
                        SELECT MAX(id) AS max_id
                          FROM vfpmedicos.padrosoc
                         WHERE DESDE IS NOT NULL
                           AND YEAR(DESDE) BETWEEN 2000 AND 2099
                           AND CANTREG IS NOT NULL
                           AND TRIM(OBRASOC) <> ''
                         GROUP BY TRIM(OBRASOC), YEAR(DESDE), MONTH(DESDE)
                  ) u ON u.max_id = p.id";

        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        $this->cortes = array();

        if (!is_array($filas)) {
            return $this->cortes;
        }

        foreach ($filas as $fila) {
            $anio = isset($fila['anio']) ? (int) $fila['anio'] : 0;
            $mes = isset($fila['mes']) ? (int) $fila['mes'] : 0;
            $codigo = isset($fila['obrasoc']) ? strtoupper(trim((string) $fila['obrasoc'])) : '';
            if ($codigo === '' || $anio < 2000 || $mes < 1 || $mes > 12) {
                continue;
            }
            $this->cortes[] = array(
                'obrasoc' => $codigo,
                'anio'    => $anio,
                'mes'     => $mes,
                'total'   => (int) $fila['total'],
                'periodo' => $this->formatearPeriodo($anio, $mes),
                'orden'   => ($anio * 100) + $mes,
            );
        }

        return $this->cortes;
    }

    private function filtrarCortes($obrasoc, $anios)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $mapaAnios = array();
        if (is_array($anios)) {
            foreach ($anios as $anio) {
                $mapaAnios[(int) $anio] = true;
            }
        }

        $salida = array();
        foreach ($this->leerCortes() as $fila) {
            if ($obra !== '' && $fila['obrasoc'] !== $obra) {
                continue;
            }
            if (count($mapaAnios) > 0 && !isset($mapaAnios[(int) $fila['anio']])) {
                continue;
            }
            $salida[] = $fila;
        }
        return $salida;
    }

    private function agregarPorPeriodo($cortes)
    {
        $mapa = array();
        foreach ($cortes as $fila) {
            $clave = sprintf('%04d%02d', $fila['anio'], $fila['mes']);
            if (!isset($mapa[$clave])) {
                $mapa[$clave] = array(
                    'anio'    => (int) $fila['anio'],
                    'mes'     => (int) $fila['mes'],
                    'periodo' => $fila['periodo'],
                    'orden'   => (int) $fila['orden'],
                    'total'   => 0,
                );
            }
            $mapa[$clave]['total'] += (int) $fila['total'];
        }
        ksort($mapa);
        $lista = array();
        foreach ($mapa as $punto) {
            $lista[] = $punto;
        }
        return $lista;
    }

    private function calcularKpis($serie)
    {
        $vacio = array(
            'total'            => 0,
            'variacion'        => null,
            'variacion_pct'    => null,
            'periodo'          => '',
            'periodo_anterior' => '',
        );

        $cantidad = count($serie);
        if ($cantidad === 0) {
            return $vacio;
        }

        $actual = $serie[$cantidad - 1];
        $vacio['total'] = (int) $actual['total'];
        $vacio['periodo'] = $actual['periodo'];

        if ($cantidad < 2) {
            return $vacio;
        }

        $anterior = $serie[$cantidad - 2];
        $variacion = (int) $actual['total'] - (int) $anterior['total'];
        $vacio['variacion'] = $variacion;
        $vacio['periodo_anterior'] = $anterior['periodo'];
        if ((int) $anterior['total'] !== 0) {
            $vacio['variacion_pct'] = round(($variacion / (int) $anterior['total']) * 100, 2);
        }

        return $vacio;
    }

    private function empaquetarHistorico($serie)
    {
        $labels = array();
        $totales = array();
        $minimo = null;
        $maximo = null;

        foreach ($serie as $punto) {
            $labels[] = $punto['periodo'];
            $totales[] = (int) $punto['total'];
            if ($minimo === null || (int) $punto['total'] < (int) $minimo['total']) {
                $minimo = $punto;
            }
            if ($maximo === null || (int) $punto['total'] > (int) $maximo['total']) {
                $maximo = $punto;
            }
        }

        return array(
            'labels'  => $labels,
            'totales' => $totales,
            'min'     => $minimo === null ? null : array(
                'periodo' => $minimo['periodo'],
                'total'   => (int) $minimo['total'],
            ),
            'max'     => $maximo === null ? null : array(
                'periodo' => $maximo['periodo'],
                'total'   => (int) $maximo['total'],
            ),
        );
    }

    private function empaquetarMovimiento($serie)
    {
        $labels = array();
        $totales = array();
        $altas = array();
        $bajas = array();
        $variacionPct = array();
        $anterior = null;

        foreach ($serie as $punto) {
            $alta = 0;
            $baja = 0;
            $pct = null;
            if ($anterior !== null) {
                $delta = (int) $punto['total'] - (int) $anterior['total'];
                if ($delta > 0) {
                    $alta = $delta;
                } elseif ($delta < 0) {
                    $baja = abs($delta);
                }
                if ((int) $anterior['total'] !== 0) {
                    $pct = round(($delta / (int) $anterior['total']) * 100, 2);
                }
            }

            $labels[] = $punto['periodo'];
            $totales[] = (int) $punto['total'];
            $altas[] = $alta;
            $bajas[] = $baja;
            $variacionPct[] = $pct;
            $anterior = $punto;
        }

        return array(
            'labels'        => $labels,
            'totales'       => $totales,
            'altas'         => $altas,
            'bajas'         => $bajas,
            'variacion_pct' => $variacionPct,
        );
    }

    private function armarTabla($detalle)
    {
        $indice = array();
        $ultimo = array();
        foreach ($this->leerCortes() as $fila) {
            $indice[$fila['obrasoc'] . '|' . $fila['anio'] . '|' . $fila['mes']] = (int) $fila['total'];
            if (!isset($ultimo[$fila['obrasoc']]) || (int) $fila['orden'] > (int) $ultimo[$fila['obrasoc']]) {
                $ultimo[$fila['obrasoc']] = (int) $fila['orden'];
            }
        }

        $tabla = array();
        foreach ($detalle as $fila) {
            $prev = $this->mesAnterior((int) $fila['anio'], (int) $fila['mes']);
            $clavePrev = $fila['obrasoc'] . '|' . $prev['anio'] . '|' . $prev['mes'];
            $variacion = null;
            $pct = null;
            if (isset($indice[$clavePrev])) {
                $variacion = (int) $fila['total'] - (int) $indice[$clavePrev];
                if ((int) $indice[$clavePrev] !== 0) {
                    $pct = round(($variacion / (int) $indice[$clavePrev]) * 100, 2);
                }
            }

            $nombre = $this->nombreObra($fila['obrasoc']);
            $obra = $fila['obrasoc'];
            if ($nombre !== '') {
                $obra .= ' — ' . $nombre;
            }

            $vigente = isset($ultimo[$fila['obrasoc']]) && (int) $ultimo[$fila['obrasoc']] === (int) $fila['orden'];

            $tabla[] = array(
                'periodo'       => $fila['periodo'],
                'obrasoc'       => $fila['obrasoc'],
                'obra'          => $obra,
                'total'         => (int) $fila['total'],
                'variacion'     => $variacion,
                'variacion_pct' => $pct,
                'estado'        => $vigente ? 'Vigente' : 'Cerrado',
                'orden'         => (int) $fila['orden'],
            );
        }

        usort($tabla, array($this, 'compararOrdenDesc'));
        return $tabla;
    }

    private function normalizarAnios($aniosArray)
    {
        $mapa = array();
        if (!is_array($aniosArray)) {
            return array();
        }
        foreach ($aniosArray as $anio) {
            $valor = (int) $anio;
            if ($valor >= 2000 && $valor <= 2099) {
                $mapa[$valor] = true;
            }
        }
        $lista = array();
        foreach ($mapa as $anio => $marca) {
            $lista[] = (int) $anio;
        }
        sort($lista, SORT_NUMERIC);
        return $lista;
    }

    private function mesAnterior($anio, $mes)
    {
        if ($mes <= 1) {
            return array('anio' => $anio - 1, 'mes' => 12);
        }
        return array('anio' => $anio, 'mes' => $mes - 1);
    }

    private function formatearPeriodo($anio, $mes)
    {
        $aa = ((int) $anio) % 100;
        return sprintf('%02d/%02d', $aa, (int) $mes);
    }

    private function nombreObra($codigo)
    {
        $mapa = $this->mapaNombres();
        if (isset($mapa[$codigo])) {
            return $mapa[$codigo];
        }
        return '';
    }

    private function mapaNombres()
    {
        if ($this->nombres !== null) {
            return $this->nombres;
        }

        $this->nombres = array();
        $sql = "SELECT TRIM(TACODIGO) AS codigo, TRIM(TADESCRIP) AS nombre
                  FROM vfpmedicos.obrasoc
                 WHERE TACODIGO IS NOT NULL
                   AND TRIM(TACODIGO) <> ''";
        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            return $this->nombres;
        }

        foreach ($filas as $fila) {
            $codigo = strtoupper(trim((string) $fila['codigo']));
            $nombre = trim((string) $fila['nombre']);
            if ($codigo === '') {
                continue;
            }
            if (!isset($this->nombres[$codigo]) || strlen($nombre) > strlen($this->nombres[$codigo])) {
                $this->nombres[$codigo] = $nombre;
            }
        }

        return $this->nombres;
    }

    private function armarInsights($serieVisible, $diagnostico)
    {
        $serieCompleta = isset($diagnostico['serie']) && is_array($diagnostico['serie'])
            ? $diagnostico['serie']
            : array();
        $extremos = $this->extremosSerie($serieVisible);
        $interanual = $this->compararInteranual($serieVisible, $serieCompleta);
        $movimiento = $this->diagnosticarMovimiento($serieVisible);
        $especiales = isset($diagnostico['especiales']) && is_array($diagnostico['especiales'])
            ? $diagnostico['especiales']
            : array('disponible' => false, 'lineas' => array('No hay corte activo con marcas especiales.'));

        return array(
            'interanual' => array(
                'lineas' => $this->lineasInteranual($interanual),
            ),
            'historico' => array(
                'lineas' => $this->lineasHistorico($extremos),
            ),
            'movimiento' => array(
                'lineas' => $this->lineasMovimiento($movimiento),
            ),
            'especiales' => array(
                'lineas' => isset($especiales['lineas']) && is_array($especiales['lineas'])
                    ? $especiales['lineas']
                    : array('No hay corte activo con marcas especiales.'),
            ),
        );
    }

    private function compararInteranual($serieVisible, $serieCompleta)
    {
        $vacio = array('disponible' => false);
        $cantidad = count($serieVisible);
        if ($cantidad === 0) {
            return $vacio;
        }

        $actual = $serieVisible[$cantidad - 1];
        $previo = $this->buscarPunto($serieCompleta, (int) $actual['anio'] - 1, (int) $actual['mes']);
        if ($previo === null) {
            return array(
                'disponible' => false,
                'mes'        => $this->nombreMes((int) $actual['mes']),
                'periodo'    => $actual['periodo'],
            );
        }

        $diferencia = (int) $actual['total'] - (int) $previo['total'];
        $pct = null;
        if ((int) $previo['total'] !== 0) {
            $pct = round(($diferencia / (int) $previo['total']) * 100, 1);
        }

        return array(
            'disponible'       => true,
            'mes'              => $this->nombreMes((int) $actual['mes']),
            'periodo'          => $actual['periodo'],
            'periodo_anterior' => $previo['periodo'],
            'actual'           => (int) $actual['total'],
            'anterior'         => (int) $previo['total'],
            'diferencia'       => $diferencia,
            'diferencia_pct'   => $pct,
        );
    }

    private function extremosSerie($serie)
    {
        $vacio = array(
            'maximo'    => null,
            'minimo'    => null,
            'tendencia' => '',
            'inicio'    => null,
            'cierre'    => null,
            'caida'     => null,
        );
        $cantidad = count($serie);
        if ($cantidad === 0) {
            return $vacio;
        }

        $maximo = null;
        $minimo = null;
        $peor = null;
        $anterior = null;

        foreach ($serie as $punto) {
            if ($maximo === null || (int) $punto['total'] > (int) $maximo['total']) {
                $maximo = $punto;
            }
            if ($minimo === null || (int) $punto['total'] < (int) $minimo['total']) {
                $minimo = $punto;
            }
            if ($anterior !== null) {
                $delta = (int) $punto['total'] - (int) $anterior['total'];
                if ($delta < 0 && ($peor === null || $delta < (int) $peor['delta'])) {
                    $peor = array(
                        'periodo' => $punto['periodo'],
                        'desde'   => $anterior['periodo'],
                        'delta'   => $delta,
                    );
                }
            }
            $anterior = $punto;
        }

        $inicio = $serie[0];
        $cierre = $serie[$cantidad - 1];

        return array(
            'maximo'    => $maximo,
            'minimo'    => $minimo,
            'tendencia' => $this->etiquetaTendencia((int) $inicio['total'], (int) $cierre['total']),
            'inicio'    => $inicio,
            'cierre'    => $cierre,
            'caida'     => $peor,
        );
    }

    private function diagnosticarMovimiento($serie)
    {
        $cantidad = count($serie);
        if ($cantidad === 0) {
            return array('disponible' => false);
        }

        $actual = $serie[$cantidad - 1];
        if ($cantidad < 2) {
            return array(
                'disponible' => true,
                'periodo'    => $actual['periodo'],
                'total'      => (int) $actual['total'],
                'saldo'      => null,
                'churn'      => null,
            );
        }

        $anterior = $serie[$cantidad - 2];
        $saldo = (int) $actual['total'] - (int) $anterior['total'];
        $bajas = $saldo < 0 ? abs($saldo) : 0;
        $churn = null;
        if ((int) $anterior['total'] > 0) {
            $churn = round(($bajas / (int) $anterior['total']) * 100, 1);
        }

        return array(
            'disponible'       => true,
            'periodo'          => $actual['periodo'],
            'periodo_anterior' => $anterior['periodo'],
            'total'            => (int) $actual['total'],
            'total_anterior'   => (int) $anterior['total'],
            'saldo'            => $saldo,
            'bajas'            => $bajas,
            'churn'            => $churn,
        );
    }

    private function leerMarcasEspeciales($obrasoc)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $where = '';
        $params = array();
        if ($obra !== '') {
            $where = ' WHERE UPPER(TRIM(OBRASOC)) = :obra ';
            $params[':obra'] = $obra;
        }

        $sqlTotal = "SELECT COUNT(*) AS total, TRIM(PERIODO) AS periodo
                       FROM vfpmedicos.padron_activos"
                  . $where
                  . " GROUP BY TRIM(PERIODO)";
        $stmt = $this->db->prepare($sqlTotal);
        $stmt->execute($params);
        $cortes = $stmt->fetchAll();

        $total = 0;
        $periodo = '';
        $orden = 0;
        if (is_array($cortes)) {
            foreach ($cortes as $corte) {
                $clave = $this->ordenPeriodo(isset($corte['periodo']) ? $corte['periodo'] : '');
                if ($clave >= $orden) {
                    $orden = $clave;
                    $total = (int) $corte['total'];
                    $periodo = trim((string) $corte['periodo']);
                }
            }
        }

        if ($total === 0) {
            return array(
                'disponible' => false,
                'lineas'     => array('El corte activo no tiene afiliados cargados para esta obra social, así que no se puede medir la incidencia de enrol1.'),
            );
        }

        $sqlMarcas = "SELECT TRIM(ENROL1) AS marca, COUNT(*) AS cantidad
                        FROM vfpmedicos.padron_activos
                       WHERE CHAR_LENGTH(TRIM(IFNULL(ENROL1, ''))) > 0";
        if ($obra !== '') {
            $sqlMarcas .= ' AND UPPER(TRIM(OBRASOC)) = :obra';
        }
        if ($periodo !== '') {
            $sqlMarcas .= ' AND TRIM(PERIODO) = :periodo';
        }
        $sqlMarcas .= ' GROUP BY TRIM(ENROL1) ORDER BY cantidad DESC';

        $paramsMarcas = $params;
        if ($periodo !== '') {
            $paramsMarcas[':periodo'] = $periodo;
        }
        $stmtMarcas = $this->db->prepare($sqlMarcas);
        $stmtMarcas->execute($paramsMarcas);
        $filas = $stmtMarcas->fetchAll();

        $marcados = 0;
        $detalle = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $cantidad = (int) $fila['cantidad'];
                $marcados += $cantidad;
                $detalle[] = array(
                    'marca'    => trim((string) $fila['marca']),
                    'cantidad' => $cantidad,
                );
            }
        }

        $pct = round(($marcados / $total) * 100, 1);
        $alcance = $obra === '' ? 'del padrón activo cargado' : 'del padrón total de la obra social';
        $lineas = array();
        $lineas[] = 'Los casos con marca especial (enrol1: CUD, oncología, crónicos y otros programas) representan el '
            . $this->formatoPorcentaje($pct)
            . ' '
            . $alcance
            . ' ('
            . $this->formatoEntero($marcados)
            . ' de '
            . $this->formatoEntero($total)
            . ' en '
            . $periodo
            . ').';

        if (count($detalle) === 0) {
            $lineas[] = 'En ese corte no hay afiliados con enrol1 informado.';
        } else {
            $tope = 0;
            foreach ($detalle as $item) {
                if ($tope >= 6) {
                    break;
                }
                $lineas[] = $item['marca'] . ': ' . $this->formatoEntero($item['cantidad']) . ' afiliados.';
                $tope++;
            }
        }

        return array(
            'disponible' => true,
            'marcados'   => $marcados,
            'total'      => $total,
            'porcentaje' => $pct,
            'periodo'    => $periodo,
            'detalle'    => $detalle,
            'lineas'     => $lineas,
        );
    }

    private function lineasInteranual($dato)
    {
        if (!is_array($dato) || empty($dato['disponible'])) {
            if (is_array($dato) && isset($dato['mes']) && $dato['mes'] !== '') {
                return array('En el mes de ' . $dato['mes'] . ' (' . $dato['periodo'] . ') no hay corte del mismo mes en el año anterior para comparar.');
            }
            return array('No hay un mes cerrado en el rango elegido para calcular la variación interanual.');
        }

        $pct = 'sin base de comparación';
        if ($dato['diferencia_pct'] !== null) {
            $pct = $this->formatoPorcentajeFirmado($dato['diferencia_pct']);
        }

        return array(
            'En el mes actual (' . $dato['mes'] . ', ' . $dato['periodo'] . '), la variación interanual respecto al año anterior (' . $dato['periodo_anterior'] . ') es de ' . $this->formatoFirmado($dato['diferencia']) . ' afiliados (' . $pct . ').',
            'El mismo mes del año previo tenía ' . $this->formatoEntero($dato['anterior']) . ' afiliados y el corte auditado tiene ' . $this->formatoEntero($dato['actual']) . '.',
        );
    }

    private function lineasHistorico($extremos)
    {
        if (!is_array($extremos) || $extremos['maximo'] === null || $extremos['minimo'] === null) {
            return array('No hay serie histórica en el rango auditado.');
        }

        $lineas = array(
            'Pico máximo alcanzado en ' . $extremos['maximo']['periodo'] . ' con ' . $this->formatoEntero($extremos['maximo']['total']) . ' afiliados.',
            'Piso mínimo registrado en ' . $extremos['minimo']['periodo'] . ' con ' . $this->formatoEntero($extremos['minimo']['total']) . ' afiliados.',
            'Tendencia general del rango auditado: ' . $extremos['tendencia'] . ' (de ' . $this->formatoEntero($extremos['inicio']['total']) . ' en ' . $extremos['inicio']['periodo'] . ' a ' . $this->formatoEntero($extremos['cierre']['total']) . ' en ' . $extremos['cierre']['periodo'] . ').',
        );

        if (is_array($extremos['caida'])) {
            $lineas[] = 'La mayor caída del rango fue en ' . $extremos['caida']['periodo'] . ', con ' . $this->formatoFirmado($extremos['caida']['delta']) . ' afiliados respecto de ' . $extremos['caida']['desde'] . '.';
        }

        return $lineas;
    }

    private function lineasMovimiento($dato)
    {
        if (!is_array($dato) || empty($dato['disponible'])) {
            return array('No hay movimiento para diagnosticar en el rango elegido.');
        }
        if ($dato['saldo'] === null) {
            return array('El período ' . $dato['periodo'] . ' es el primero de la serie, así que todavía no hay saldo contra un mes anterior.');
        }

        $sentido = 'neutro';
        if ((int) $dato['saldo'] > 0) {
            $sentido = 'positivo';
        } elseif ((int) $dato['saldo'] < 0) {
            $sentido = 'negativo';
        }

        $lineas = array(
            'El último período cerrado (' . $dato['periodo'] . ') tuvo saldo neto ' . $sentido . ': ' . $this->formatoFirmado($dato['saldo']) . ' afiliados frente a ' . $dato['periodo_anterior'] . '.',
        );

        if ($dato['churn'] === null) {
            $lineas[] = 'No se puede calcular la tasa de rotación porque el padrón del mes anterior es cero.';
        } elseif ((int) $dato['bajas'] === 0) {
            $lineas[] = 'La tasa de rotación (bajas netas sobre el padrón anterior) es 0,0 %. El cierre no muestra cancelación neta.';
        } else {
            $lineas[] = 'La tasa de rotación (bajas netas sobre el padrón de ' . $dato['periodo_anterior'] . ', ' . $this->formatoEntero($dato['total_anterior']) . ' afiliados) es ' . $this->formatoPorcentaje($dato['churn']) . '.';
        }

        return $lineas;
    }

    private function buscarPunto($serie, $anio, $mes)
    {
        foreach ($serie as $punto) {
            if ((int) $punto['anio'] === (int) $anio && (int) $punto['mes'] === (int) $mes) {
                return $punto;
            }
        }
        return null;
    }

    private function etiquetaTendencia($inicial, $final)
    {
        if ((int) $inicial <= 0) {
            if ((int) $final > (int) $inicial) {
                return 'Creciente';
            }
            if ((int) $final < (int) $inicial) {
                return 'En declive';
            }
            return 'Estable';
        }

        $ratio = (int) $final / (int) $inicial;
        if ($ratio >= 1.02) {
            return 'Creciente';
        }
        if ($ratio <= 0.98) {
            return 'En declive';
        }
        return 'Estable';
    }

    private function ordenPeriodo($periodo)
    {
        $periodo = trim((string) $periodo);
        $partes = explode('/', $periodo);
        if (count($partes) !== 2) {
            return 0;
        }
        $anio = (int) $partes[0];
        $mes = (int) $partes[1];
        if ($anio < 100) {
            $anio += 2000;
        }
        return ($anio * 100) + $mes;
    }

    private function nombreMes($mes)
    {
        $nombres = array(
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        );
        $mes = (int) $mes;
        if (isset($nombres[$mes])) {
            return $nombres[$mes];
        }
        return '';
    }

    private function formatoEntero($numero)
    {
        return number_format((int) $numero, 0, ',', '.');
    }

    private function formatoFirmado($numero)
    {
        $numero = (int) $numero;
        $texto = number_format(abs($numero), 0, ',', '.');
        if ($numero > 0) {
            return '+' . $texto;
        }
        if ($numero < 0) {
            return '-' . $texto;
        }
        return '0';
    }

    private function formatoPorcentaje($numero)
    {
        return number_format((float) $numero, 1, ',', '.') . ' %';
    }

    private function formatoPorcentajeFirmado($numero)
    {
        $numero = (float) $numero;
        $texto = number_format(abs($numero), 1, ',', '.') . ' %';
        if ($numero > 0) {
            return '+' . $texto;
        }
        if ($numero < 0) {
            return '-' . $texto;
        }
        return '0,0 %';
    }

    private function compararCodigo($a, $b)
    {
        return strcmp($a['codigo'], $b['codigo']);
    }

    private function compararOrdenDesc($a, $b)
    {
        if ((int) $a['orden'] === (int) $b['orden']) {
            return strcmp($a['obrasoc'], $b['obrasoc']);
        }
        if ((int) $a['orden'] < (int) $b['orden']) {
            return 1;
        }
        return -1;
    }
}
