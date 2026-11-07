<?php
/**
 * Evolución mensual de padrones.
 *
 * El corte de cada mes está en vfpmedicos.padrosoc (CANTREG).
 * Si t_padron tiene un período posterior al último de padrosoc, ese
 * corte entra con COUNT(*). El período se publica como aa/mm.
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
    private $detalleObra = array();
    private $vigentesObra = array();

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    /**
     * Obras vigentes de cartilla.obrasoc para el filtro.
     * Vigente: TAFECHAFIN posterior a hoy, nula, o fecha cero de FoxPro
     * (1899-12-30 y cualquier valor hasta 1900-01-01).
     * De las variantes OSSEG solo queda OSSEG U.
     */
    public function getObrasSociales()
    {
        $sql = "SELECT DISTINCT TRIM(o.TACODIGO) AS obrasoc, TRIM(o.TADESCRIP) AS nombre
                  FROM cartilla.obrasoc o
                 WHERE (o.TAFECHAFIN > CURDATE()
                        OR o.TAFECHAFIN IS NULL
                        OR o.TAFECHAFIN <= '1900-01-01')
                   AND TRIM(o.TACODIGO) NOT IN ('OSSEG S', 'OSSEG E', 'OSSEG B', 'OSSEGB21')
                   AND o.TACODIGO IS NOT NULL
                   AND TRIM(o.TACODIGO) <> ''
                 ORDER BY nombre ASC";

        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        $lista = array();
        $vistos = array();

        if (!is_array($filas)) {
            return $lista;
        }

        foreach ($filas as $fila) {
            $codigo = strtoupper(trim((string) $fila['obrasoc']));
            if ($codigo === '' || isset($vistos[$codigo])) {
                continue;
            }
            $vistos[$codigo] = true;
            $nombre = trim((string) $fila['nombre']);
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
        return $this->calcularKpis($serie, $obrasoc);
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
            'kpis'       => $this->calcularKpis($serie, $obrasoc),
            'interanual' => $this->getDatosInteranuales($obrasoc, $anios),
            'historico'  => $historico,
            'movimiento' => $movimiento,
            'movimiento_real' => $this->movimientoPersonas($obrasoc, $anios),
            'tabla'      => $this->armarTabla($detalle),
            'insights'   => $this->armarInsights($serie, $diagnostico),
        );
    }

    /**
     * Diagnóstico del padrón de una obra (o de todas, si $obrasoc va vacío).
     * Máximo, mínimo y comparativa interanual salen de la serie completa.
     * Las marcas especiales salen de t_padron.enrol1 en el último período real.
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

        $reales = array();
        if ($obra !== '') {
            foreach ($this->leerCortesDetalle($obra) as $real) {
                $reales[$real['periodo']] = true;
            }
        }
        $hayReales = count($reales) > 0;

        $salida = array();
        $ordenMax = -1;
        foreach ($this->leerCortes() as $fila) {
            if ($obra !== '' && $fila['obrasoc'] !== $obra) {
                continue;
            }
            if (count($mapaAnios) > 0 && !isset($mapaAnios[(int) $fila['anio']])) {
                continue;
            }
            if ($hayReales && !isset($reales[$fila['periodo']]) && (int) $fila['total'] <= 1) {
                continue;
            }
            $salida[] = $fila;
            if ((int) $fila['orden'] > $ordenMax) {
                $ordenMax = (int) $fila['orden'];
            }
        }

        if ($obra !== '') {
            foreach ($this->leerCortesDetalle($obra) as $fila) {
                if ((int) $fila['orden'] <= $ordenMax) {
                    continue;
                }
                if (count($mapaAnios) > 0 && !isset($mapaAnios[(int) $fila['anio']])) {
                    continue;
                }
                $salida[] = $fila;
            }
        }

        return $salida;
    }

    private function leerCortesDetalle($obra)
    {
        if (isset($this->detalleObra[$obra])) {
            return $this->detalleObra[$obra];
        }

        $sql = "SELECT TRIM(PERIODO) AS periodo, COUNT(*) AS total
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PERIODO <> ''
                 GROUP BY PERIODO";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $lista = array();

        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $corte = $this->corteDesdePeriodo(
                    $obra,
                    $this->valorFila($fila, 'periodo'),
                    $this->valorFila($fila, 'total')
                );
                if ($corte !== null) {
                    $lista[] = $corte;
                }
            }
        }

        $this->detalleObra[$obra] = $lista;
        return $lista;
    }

    private function corteDesdePeriodo($obra, $periodo, $total)
    {
        $periodo = trim((string) $periodo);
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $periodo, $partes)) {
            return null;
        }
        $anio = 2000 + (int) $partes[1];
        $mes = (int) $partes[2];
        if ($mes < 1 || $mes > 12) {
            return null;
        }

        return array(
            'obrasoc' => $obra,
            'anio'    => $anio,
            'mes'     => $mes,
            'total'   => (int) $total,
            'periodo' => $this->formatearPeriodo($anio, $mes),
            'orden'   => ($anio * 100) + $mes,
        );
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

    private function calcularKpis($serie, $obrasoc)
    {
        $vacio = array(
            'total'              => 0,
            'variacion'          => null,
            'variacion_pct'      => null,
            'periodo'            => '',
            'periodo_anterior'   => '',
            'es_padron_inicial'  => true,
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
        $totalAnterior = (int) $anterior['total'];
        $hayPrevio = $this->hayCorteAnterior($obrasoc, $actual['periodo']);
        if (!$hayPrevio || $totalAnterior <= 0) {
            return $vacio;
        }

        $variacion = (int) $actual['total'] - $totalAnterior;
        $vacio['variacion'] = $variacion;
        $vacio['variacion_pct'] = round(($variacion / $totalAnterior) * 100, 1);
        $vacio['periodo_anterior'] = $anterior['periodo'];
        $vacio['es_padron_inicial'] = false;

        return $vacio;
    }

    private function hayCorteAnterior($obrasoc, $periodoActual)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return true;
        }

        $detalle = $this->leerCortesDetalle($obra);
        if (count($detalle) === 0) {
            return true;
        }

        $ordenActual = $this->ordenPeriodo($periodoActual);
        foreach ($detalle as $fila) {
            if ((int) $fila['orden'] < $ordenActual && (int) $fila['total'] > 0) {
                return true;
            }
        }

        return false;
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
                  FROM cartilla.obrasoc
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

    public function getUltimoPeriodoReal($obrasoc)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return null;
        }

        $mejor = null;
        foreach ($this->leerCortesDetalle($obra) as $fila) {
            if ($mejor === null || (int) $fila['orden'] > (int) $mejor['orden']) {
                $mejor = $fila;
            }
        }
        if ($mejor === null) {
            return null;
        }

        return $mejor['periodo'];
    }

    public function getUltimoPeriodo($obrasoc)
    {
        $serie = $this->agregarPorPeriodo($this->filtrarCortes($obrasoc, array()));
        $cantidad = count($serie);
        if ($cantidad === 0) {
            return array(
                'periodo' => '',
                'total'   => 0,
            );
        }

        $ultimo = $serie[$cantidad - 1];
        return array(
            'periodo' => $ultimo['periodo'],
            'total'   => (int) $ultimo['total'],
        );
    }

    private function leerMarcasEspeciales($obrasoc)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return array(
                'disponible' => false,
                'lineas'     => array('Seleccione una obra social para medir los programas especiales.'),
            );
        }

        $corte = $this->getUltimoPeriodo($obra);
        $periodo = $corte['periodo'];
        $total = (int) $corte['total'];
        if ($periodo === '' || $total <= 0) {
            return array(
                'disponible' => false,
                'lineas'     => array('El corte activo no tiene afiliados cargados para esta obra social, así que no se puede medir la incidencia de enrol1.'),
            );
        }

        $archivo = $this->archivoVigente($obra, $periodo);
        $sql = "SELECT TRIM(ENROL1) AS marca, COUNT(*) AS cantidad
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PERIODO = :periodo
                   AND ENROL1 IS NOT NULL
                   AND TRIM(ENROL1) != ''
                   AND TRIM(ENROL1) != '-'";
        $paramsMarcas = array(
            ':obra'    => $obra,
            ':periodo' => $periodo,
        );
        if ($archivo !== '') {
            $sql .= ' AND TRIM(PFILE) = :archivo';
            $paramsMarcas[':archivo'] = $archivo;
        }
        $sql .= ' GROUP BY TRIM(ENROL1) ORDER BY cantidad DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsMarcas);
        $filas = $stmt->fetchAll();

        $agrupado = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $nombre = $this->normalizarProgramaEspecial($obra, $this->valorFila($fila, 'marca'));
                $cantidad = (int) $this->valorFila($fila, 'cantidad');
                if ($nombre === '' || $cantidad === 0) {
                    continue;
                }
                if (!isset($agrupado[$nombre])) {
                    $agrupado[$nombre] = 0;
                }
                $agrupado[$nombre] += $cantidad;
            }
        }
        arsort($agrupado);

        $marcados = 0;
        $detalle = array();
        foreach ($agrupado as $nombre => $cantidad) {
            $marcados += (int) $cantidad;
            $detalle[] = array(
                'marca'    => $nombre,
                'cantidad' => (int) $cantidad,
            );
        }

        $pct = $total > 0 ? round(($marcados / $total) * 100, 1) : 0;
        $lineas = array();
        if (count($detalle) === 0) {
            $lineas[] = 'En el corte ' . $periodo . ' no hay afiliados con enrol1 informado.';
        } else {
            $tope = 0;
            foreach ($detalle as $item) {
                if ($tope >= 6) {
                    break;
                }
                $pctItem = round(($item['cantidad'] / $total) * 100, 1);
                $lineas[] = $item['marca'] . ': ' . $this->formatoEntero($item['cantidad'])
                    . ' afiliados (' . $this->formatoPorcentaje($pctItem)
                    . ' del padrón de ' . $this->formatoEntero($total)
                    . ' en ' . $periodo . ').';
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

    /**
     * Evolución mensual de enrol1 en t_padron.
     * El gráfico usa los últimos períodos con marca; el diagnóstico
     * compara el semestre reciente y la discapacidad contra el año previo.
     */
    public function getEvolucionProgramasEspeciales($obrasoc, $limitePeriodos = 12)
    {
        $limite = (int) $limitePeriodos;
        if ($limite < 2) {
            $limite = 12;
        }
        if ($limite > 36) {
            $limite = 36;
        }

        $obra = strtoupper(trim((string) $obrasoc));
        $sql = "SELECT TRIM(PERIODO) AS periodo, TRIM(PFILE) AS pfile, TRIM(ENROL1) AS marca, COUNT(*) AS total
                  FROM vfpmedicos.t_padron
                 WHERE ENROL1 IS NOT NULL
                   AND TRIM(ENROL1) != ''
                   AND TRIM(ENROL1) != '-'";
        $params = array();
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra';
            $params[':obra'] = $obra;
        }
        $sql .= ' GROUP BY PERIODO, PFILE, ENROL1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $matriz = array();
        $totales = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $periodo = trim((string) $this->valorFila($fila, 'periodo'));
                $pfile = strtoupper(trim((string) $this->valorFila($fila, 'pfile')));
                $marca = trim((string) $this->valorFila($fila, 'marca'));
                $cantidad = (int) $this->valorFila($fila, 'total');
                if ($periodo === '' || $marca === '' || $cantidad === 0) {
                    continue;
                }
                if ($obra !== '') {
                    $vigente = $this->archivoVigente($obra, $periodo);
                    if ($vigente !== '' && $pfile !== $vigente) {
                        continue;
                    }
                }
                $etiqueta = $this->normalizarProgramaEspecial($obra, $marca);
                if ($etiqueta === '') {
                    continue;
                }
                if (!isset($matriz[$periodo])) {
                    $matriz[$periodo] = array();
                }
                if (!isset($matriz[$periodo][$etiqueta])) {
                    $matriz[$periodo][$etiqueta] = 0;
                }
                $matriz[$periodo][$etiqueta] += $cantidad;
                if (!isset($totales[$etiqueta])) {
                    $totales[$etiqueta] = 0;
                }
                $totales[$etiqueta] += $cantidad;
            }
        }

        $periodos = array();
        foreach ($matriz as $periodo => $marcas) {
            $periodos[] = $periodo;
        }
        usort($periodos, array($this, 'compararPeriodoAsc'));

        $cantidadPeriodos = count($periodos);
        if ($cantidadPeriodos === 0) {
            return array(
                'message'  => 'No hay marcas enrol1 en el padrón histórico de esta obra.',
                'labels'   => array(),
                'datasets' => array(),
                'lineas'   => array('No hay marcas enrol1 en t_padron para esta obra social.'),
            );
        }

        $desde = 0;
        if ($cantidadPeriodos > $limite) {
            $desde = $cantidadPeriodos - $limite;
        }
        $labels = array();
        for ($i = $desde; $i < $cantidadPeriodos; $i++) {
            $labels[] = $periodos[$i];
        }

        arsort($totales);
        $principales = array();
        $vistos = 0;
        foreach ($totales as $etiqueta => $suma) {
            if ($vistos >= 6) {
                break;
            }
            $principales[] = $etiqueta;
            $vistos++;
        }

        $datasets = array();
        foreach ($principales as $etiqueta) {
            $serie = array();
            foreach ($labels as $periodo) {
                $valor = 0;
                if (isset($matriz[$periodo]) && isset($matriz[$periodo][$etiqueta])) {
                    $valor = (int) $matriz[$periodo][$etiqueta];
                }
                $serie[] = $valor;
            }
            $datasets[] = array(
                'label' => $etiqueta,
                'data'  => $serie,
            );
        }

        $lineas = $this->diagnosticarProgramas($labels, $datasets, $matriz, $periodos);

        return array(
            'message'  => '',
            'labels'   => $labels,
            'datasets' => $datasets,
            'lineas'   => $lineas,
        );
    }

    private function diagnosticarProgramas($labels, $datasets, $matriz, $periodos)
    {
        $lineas = array();
        $n = count($labels);
        if ($n === 0 || count($datasets) === 0) {
            return array('No hay series de enrol1 para diagnosticar.');
        }

        $ultimo = $labels[$n - 1];
        $baseSemestre = 0;
        if ($n > 6) {
            $baseSemestre = $n - 7;
        }
        $periodoBase = $labels[$baseSemestre];
        $mejor = null;
        foreach ($datasets as $serie) {
            $ahora = (int) $serie['data'][$n - 1];
            $antes = (int) $serie['data'][$baseSemestre];
            $delta = $ahora - $antes;
            if ($mejor === null || $delta > $mejor['delta']) {
                $mejor = array(
                    'label'  => $serie['label'],
                    'delta'  => $delta,
                    'ahora'  => $ahora,
                    'antes'  => $antes,
                );
            }
        }

        if ($mejor !== null) {
            if ($mejor['delta'] > 0) {
                $lineas[] = 'En el último semestre la patología que más creció fue ' . $mejor['label']
                    . ', con ' . $this->formatoFirmado($mejor['delta']) . ' casos (de '
                    . $this->formatoEntero($mejor['antes']) . ' en ' . $periodoBase . ' a '
                    . $this->formatoEntero($mejor['ahora']) . ' en ' . $ultimo . ').';
            } elseif ($mejor['delta'] < 0) {
                $lineas[] = 'En el último semestre ninguna patología creció. La menor caída fue '
                    . $mejor['label'] . ', con ' . $this->formatoFirmado($mejor['delta']) . ' casos (de '
                    . $this->formatoEntero($mejor['antes']) . ' en ' . $periodoBase . ' a '
                    . $this->formatoEntero($mejor['ahora']) . ' en ' . $ultimo . ').';
            } else {
                $lineas[] = 'En el último semestre ' . $mejor['label'] . ' se mantuvo en '
                    . $this->formatoEntero($mejor['ahora']) . ' casos, entre ' . $periodoBase . ' y ' . $ultimo . '.';
            }
        }

        $lineaCud = $this->variacionInteranualCud($matriz, $periodos, $ultimo);
        if ($lineaCud !== '') {
            $lineas[] = $lineaCud;
        }

        return $lineas;
    }

    private function variacionInteranualCud($matriz, $periodos, $ultimo)
    {
        $anterior = $this->periodoAnioAnterior($ultimo);
        if ($anterior === '') {
            return '';
        }

        $actual = $this->totalCudPeriodo($matriz, $ultimo);
        $previo = null;
        foreach ($periodos as $periodo) {
            if ($periodo === $anterior) {
                $previo = $this->totalCudPeriodo($matriz, $periodo);
                break;
            }
        }
        if ($previo === null) {
            return 'No hay corte de Discapacidad / CUD en ' . $anterior . ' para medir la variación interanual.';
        }
        if ($previo === 0) {
            return 'En ' . $anterior . ' no había casos de Discapacidad / CUD. En ' . $ultimo . ' hay '
                . $this->formatoEntero($actual) . '.';
        }

        $delta = $actual - $previo;
        $pct = round(($delta / $previo) * 100, 1);
        return 'La variación interanual de Discapacidad / CUD en ' . $ultimo . ' respecto de ' . $anterior
            . ' es de ' . $this->formatoFirmado($delta) . ' casos (' . $this->formatoPorcentajeFirmado($pct) . ').';
    }

    private function totalCudPeriodo($matriz, $periodo)
    {
        if (!isset($matriz[$periodo])) {
            return 0;
        }
        $total = 0;
        foreach ($matriz[$periodo] as $etiqueta => $cantidad) {
            if ($etiqueta === 'CUD (Discapacidad)' || $etiqueta === 'Discapacidad / CUD') {
                $total += (int) $cantidad;
            }
        }
        return $total;
    }

    private function periodoAnioAnterior($periodo)
    {
        $partes = explode('/', trim((string) $periodo));
        if (count($partes) !== 2) {
            return '';
        }
        $anio = (int) $partes[0];
        $mes = (int) $partes[1];
        if ($anio < 1 || $mes < 1 || $mes > 12) {
            return '';
        }
        $anio = $anio - 1;
        if ($anio < 0) {
            return '';
        }
        return sprintf('%02d/%02d', $anio, $mes);
    }

    private function normalizarProgramaEspecial($obrasoc, $codigoEnrol)
    {
        $codigo = strtoupper(trim((string) $codigoEnrol));
        if ($codigo === '' || $codigo === '-') {
            return '';
        }
        if ($codigo === 'A' || strpos($codigo, 'DISCAP') !== false) {
            return 'CUD (Discapacidad)';
        }

        return $this->etiquetaPrograma($codigoEnrol);
    }

    private function etiquetaPrograma($marca)
    {
        $texto = strtoupper(trim((string) $marca));
        if ($texto === 'COMUN' || $texto === 'COMUNES') {
            return 'Población general';
        }
        if (strpos($texto, 'MEDICAMENT') !== false) {
            return 'Discapacidad (medicamentos)';
        }
        if (strpos($texto, 'DISCAP') !== false || strpos($texto, 'CUD') !== false) {
            return 'Discapacidad / CUD';
        }
        if (strpos($texto, 'ONCO') !== false) {
            return 'Oncología';
        }
        if (strpos($texto, 'HELIOS') !== false) {
            return 'Plan Helios';
        }
        if (strpos($texto, 'MATERNO') !== false) {
            return 'Materno infantil';
        }
        if (strpos($texto, 'CRON') !== false) {
            return 'Crónicos';
        }
        return trim((string) $marca);
    }

    private function compararPeriodoAsc($a, $b)
    {
        $ordenA = $this->ordenPeriodo($a);
        $ordenB = $this->ordenPeriodo($b);
        if ($ordenA === $ordenB) {
            return 0;
        }
        if ($ordenA < $ordenB) {
            return -1;
        }
        return 1;
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

    /**
     * Demografía, planes y zona del último período real de t_padron.
     */
    public function getDemografiaYPlanes($obrasoc, $periodoReal = null)
    {
        $corte = $this->resolverCorteActivo($obrasoc);
        $forzado = trim((string) $periodoReal);
        if ($forzado !== '') {
            $corte['periodo'] = $forzado;
            $corte['disponible'] = true;
            $corte['fuente'] = 't_padron';
        }
        if (empty($corte['disponible'])) {
            $aviso = array('El corte activo no tiene afiliados cargados para esta obra social.');
            return array(
                'status'       => 'success',
                'message'      => 'El corte activo no tiene el detalle demográfico de esta obra social.',
                'corte'        => $corte,
                'familiar'     => $this->respuestaFamiliarVacia($aviso),
                'etario'       => $this->respuestaEtariaVacia($aviso),
                'planes'       => $this->respuestaPlanesVacia($aviso),
                'localidades'  => $this->getTopLocalidades($obrasoc, ''),
                'zonas'        => $this->respuestaZonasVacia($aviso),
            );
        }

        $periodo = $corte['periodo'];
        return array(
            'status'      => 'success',
            'message'     => '',
            'corte'       => $corte,
            'familiar'    => $this->getDistribucionFamiliar($obrasoc, $periodo),
            'etario'      => $this->getDistribucionEtariaYSexo($obrasoc, $periodo),
            'planes'      => $this->getMixPlanes($obrasoc, $periodo),
            'localidades' => $this->getTopLocalidades($obrasoc, $periodo),
            'zonas'       => $this->getDiscapacidadPorZona($obrasoc, $periodo),
        );
    }

    public function getDistribucionFamiliar($obrasoc, $periodo)
    {
        $pedido = trim((string) $periodo);
        $obra = strtoupper(trim((string) $obrasoc));

        $elegido = null;
        if ($pedido !== '') {
            $fila = $this->contarFamilia($obra, $pedido);
            if ($fila['total'] > 0) {
                $elegido = array(
                    'periodo'    => $pedido,
                    'titulares'  => $fila['titulares'],
                    'adherentes' => $fila['adherentes'],
                );
            }
        }
        if ($elegido === null) {
            $ultimoPeriodo = '';
            $ordenUltimo = -1;
            foreach ($this->listarPeriodosPadron($obra) as $clave) {
                $orden = $this->ordenPeriodo($clave);
                if ($orden > $ordenUltimo) {
                    $ordenUltimo = $orden;
                    $ultimoPeriodo = $clave;
                }
            }
            if ($ultimoPeriodo !== '') {
                $fila = $this->contarFamilia($obra, $ultimoPeriodo);
                if ($fila['total'] > 0) {
                    $elegido = array(
                        'periodo'    => $ultimoPeriodo,
                        'titulares'  => $fila['titulares'],
                        'adherentes' => $fila['adherentes'],
                    );
                }
            }
        }
        if ($elegido === null) {
            return $this->respuestaFamiliarVacia(array(
                'No hay marcas de titular o adherente en el padrón histórico de esta obra.',
            ));
        }

        $titulares = (int) $elegido['titulares'];
        $adherentes = (int) $elegido['adherentes'];
        $periodoUsado = $elegido['periodo'];
        $total = $titulares + $adherentes;
        $ratio = null;
        if ($titulares > 0) {
            $ratio = round($adherentes / $titulares, 2);
        }

        $lineas = array();
        if ($pedido !== '' && $pedido !== $periodoUsado) {
            $lineas[] = 'El corte ' . $pedido . ' no tiene CODADH en el padrón histórico. La distribución se toma de ' . $periodoUsado . '.';
        }
        if ($titulares === 0 && $adherentes === 0) {
            $lineas[] = 'El corte ' . $periodoUsado . ' no tiene afiliados para medir la carga familiar.';
        } elseif ($titulares === 0) {
            $lineas[] = 'No hay titulares en el corte ' . $periodoUsado . '. Los ' . $this->formatoEntero($adherentes) . ' afiliados son adherentes.';
        } else {
            $lineas[] = 'Hay ' . $this->formatoRatio($ratio) . ' adherentes por cada titular ('
                . $this->formatoEntero($adherentes) . ' adherentes y '
                . $this->formatoEntero($titulares) . ' titulares en ' . $periodoUsado . ').';
        }
        if ($total > 0) {
            $pctAdh = round(($adherentes / $total) * 100, 1);
            $lineas[] = 'Los adherentes representan el ' . $this->formatoPorcentaje($pctAdh) . ' del corte.';
        }

        return array(
            'labels'     => array('Titulares', 'Adherentes'),
            'data'       => array($titulares, $adherentes),
            'titulares'  => $titulares,
            'adherentes' => $adherentes,
            'ratio'      => $ratio,
            'periodo'    => $periodoUsado,
            'lineas'     => $lineas,
        );
    }

    private function contarFamilia($obra, $periodo)
    {
        $sql = "SELECT SUM(CASE
                           WHEN UPPER(TRIM(CODADH)) = 'ADH' THEN 1
                           WHEN TRIM(CODADH) != '' AND CODADH IS NOT NULL AND TRIM(CODADH) NOT IN ('00', '000', '0', 'TIT', 'TITU') THEN 1
                           ELSE 0
                       END) AS adherentes,
                       SUM(CASE
                           WHEN CODADH IS NULL THEN 1
                           WHEN TRIM(CODADH) = '' THEN 1
                           WHEN TRIM(CODADH) IN ('00', '000', '0', 'TIT', 'TITU') THEN 1
                           ELSE 0
                       END) AS titulares,
                       COUNT(*) AS total
                  FROM vfpmedicos.t_padron
                 WHERE PERIODO = :periodo";
        $params = array(':periodo' => $periodo);
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra';
            $params[':obra'] = $obra;
            $archivo = $this->archivoVigente($obra, $periodo);
            if ($archivo !== '') {
                $sql .= ' AND TRIM(PFILE) = :archivo';
                $params[':archivo'] = $archivo;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        if (!is_array($fila)) {
            return array('titulares' => 0, 'adherentes' => 0, 'total' => 0);
        }

        return array(
            'titulares'  => (int) $this->valorFila($fila, 'titulares'),
            'adherentes' => (int) $this->valorFila($fila, 'adherentes'),
            'total'      => (int) $this->valorFila($fila, 'total'),
        );
    }

    private function listarPeriodosPadron($obra)
    {
        $sql = "SELECT TRIM(PERIODO) AS periodo
                  FROM vfpmedicos.t_padron
                 WHERE PERIODO <> ''";
        $params = array();
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra';
            $params[':obra'] = $obra;
        }
        $sql .= ' GROUP BY PERIODO';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();
        $lista = array();
        if (!is_array($filas)) {
            return $lista;
        }
        foreach ($filas as $fila) {
            $clave = trim((string) $this->valorFila($fila, 'periodo'));
            if ($clave !== '') {
                $lista[] = $clave;
            }
        }
        return $lista;
    }

    public function getDistribucionEtariaYSexo($obrasoc, $periodo)
    {
        $corte = $this->asegurarCorte($obrasoc, $periodo);
        $rangos = $this->rangosEtarios();
        if (empty($corte['disponible'])) {
            return $this->respuestaEtariaVacia(array('El corte activo no tiene afiliados cargados para esta obra social.'));
        }

        $filtro = $this->filtroCorteActivo($obrasoc, $corte['periodo']);
        $fechaCorte = $this->fechaCorteDesdePeriodo($corte['periodo']);
        $params = $filtro['params'];
        $params[':corte'] = $fechaCorte;

        $sql = "SELECT rango, sexo, disca, COUNT(*) AS cantidad
                  FROM (
                        SELECT CASE
                                 WHEN edad IS NULL OR edad < 0 THEN 'Sin edad'
                                 WHEN edad <= 14 THEN '0-14 (Pediátrico)'
                                 WHEN edad <= 30 THEN '15-30 (Jóvenes)'
                                 WHEN edad <= 50 THEN '31-50 (Adultos)'
                                 WHEN edad <= 64 THEN '51-64 (Adultos Mayores)'
                                 ELSE '65+ (Pasivos)'
                               END AS rango,
                               CASE
                                 WHEN UPPER(TRIM(sexo_raw)) IN ('M', 'MASCULINO', 'H', 'V', '1') THEN 'M'
                                 WHEN UPPER(TRIM(sexo_raw)) IN ('F', 'FEMENINO', '2') THEN 'F'
                                 ELSE 'O'
                               END AS sexo,
                               CASE
                                 WHEN UPPER(TRIM(enrol_raw)) LIKE '%DISCAP%' THEN 1
                                 ELSE 0
                               END AS disca
                          FROM (
                                SELECT TIMESTAMPDIFF(YEAR, FECNACIM, :corte) AS edad,
                                       SEXO AS sexo_raw,
                                       ENROL1 AS enrol_raw
                                  FROM vfpmedicos.t_padron"
                     . $filtro['sql']
                     . "          ) base
                       ) clasificado
                 GROUP BY rango, sexo, disca";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $matriz = array();
        $discaMatriz = array();
        foreach ($rangos as $rango) {
            $matriz[$rango] = array('M' => 0, 'F' => 0, 'O' => 0);
            $discaMatriz[$rango] = array('M' => 0, 'F' => 0, 'O' => 0);
        }
        $sinEdad = 0;
        $sinEdadDisca = 0;
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $rango = (string) $fila['rango'];
                $sexo = (string) $fila['sexo'];
                $cantidad = (int) $fila['cantidad'];
                $esDisca = (int) $fila['disca'] === 1;
                if ($rango === 'Sin edad') {
                    $sinEdad += $cantidad;
                    if ($esDisca) {
                        $sinEdadDisca += $cantidad;
                    }
                    continue;
                }
                if (!isset($matriz[$rango])) {
                    continue;
                }
                if (!isset($matriz[$rango][$sexo])) {
                    $sexo = 'O';
                }
                $matriz[$rango][$sexo] += $cantidad;
                if ($esDisca) {
                    $discaMatriz[$rango][$sexo] += $cantidad;
                }
            }
        }

        $serieM = array();
        $serieF = array();
        $serieO = array();
        $serieDiscaM = array();
        $serieDiscaF = array();
        $serieDiscaO = array();
        $mayorRango = '';
        $mayorCantidad = -1;
        $mayorDiscaRango = '';
        $mayorDisca = -1;
        $total = $sinEdad;
        $totalDisca = $sinEdadDisca;
        $rangosDisca = array();
        foreach ($rangos as $rango) {
            $hombres = (int) $matriz[$rango]['M'];
            $mujeres = (int) $matriz[$rango]['F'];
            $otros = (int) $matriz[$rango]['O'];
            $discaH = (int) $discaMatriz[$rango]['M'];
            $discaMuj = (int) $discaMatriz[$rango]['F'];
            $discaOtros = (int) $discaMatriz[$rango]['O'];
            $serieM[] = $hombres;
            $serieF[] = $mujeres;
            $serieO[] = $otros;
            $serieDiscaM[] = $discaH;
            $serieDiscaF[] = $discaMuj;
            $serieDiscaO[] = $discaOtros;
            $suma = $hombres + $mujeres + $otros;
            $sumaDisca = $discaH + $discaMuj + $discaOtros;
            $total += $suma;
            $totalDisca += $sumaDisca;
            $rangosDisca[] = array(
                'rango' => $rango,
                'm'     => $discaH,
                'f'     => $discaMuj,
                'o'     => $discaOtros,
                'total' => $sumaDisca,
            );
            if ($suma > $mayorCantidad) {
                $mayorCantidad = $suma;
                $mayorRango = $rango;
            }
            if ($sumaDisca > $mayorDisca) {
                $mayorDisca = $sumaDisca;
                $mayorDiscaRango = $rango;
            }
        }

        $lineas = array();
        if ($total === 0 || $mayorCantidad < 0) {
            $lineas[] = 'No hay edades para diagnosticar en el corte ' . $corte['periodo'] . '.';
        } else {
            $pct = round(($mayorCantidad / $total) * 100, 1);
            $lineas[] = 'El rango etario mayoritario es ' . $mayorRango . ', con '
                . $this->formatoEntero($mayorCantidad) . ' afiliados ('
                . $this->formatoPorcentaje($pct) . ' del corte ' . $corte['periodo'] . ').';
        }
        if ($sinEdad > 0) {
            $lineas[] = $this->formatoEntero($sinEdad) . ' afiliados quedaron sin edad válida a partir de FECNACIM.';
        }
        if ($total > 0 && $totalDisca === 0) {
            $lineas[] = 'Este corte no informa discapacidad en enrol1.';
        } elseif ($totalDisca > 0) {
            $pctDisca = round(($totalDisca / $total) * 100, 1);
            $lineas[] = 'Con discapacidad: ' . $this->formatoEntero($totalDisca)
                . ' afiliados (' . $this->formatoPorcentaje($pctDisca)
                . ' del corte). Van dentro del total, no suman aparte.';
            if ($mayorDisca > 0) {
                $lineas[] = 'El rango con más discapacidad es ' . $mayorDiscaRango . ', con '
                    . $this->formatoEntero($mayorDisca) . ' afiliados.';
            }
        }

        return array(
            'labels' => $rangos,
            'series' => array(
                array('sexo' => 'M', 'label' => 'Masculino', 'data' => $serieM),
                array('sexo' => 'F', 'label' => 'Femenino', 'data' => $serieF),
                array('sexo' => 'O', 'label' => 'Otros', 'data' => $serieO),
                array('sexo' => 'DM', 'label' => 'Discapacidad masculina', 'data' => $serieDiscaM),
                array('sexo' => 'DF', 'label' => 'Discapacidad femenina', 'data' => $serieDiscaF),
                array('sexo' => 'DO', 'label' => 'Discapacidad otros', 'data' => $serieDiscaO),
            ),
            'discapacidad' => array(
                'total'      => $totalDisca,
                'porcentaje' => $total > 0 ? round(($totalDisca / $total) * 100, 1) : 0,
                'rangos'     => $rangosDisca,
                'sin_edad'   => $sinEdadDisca,
            ),
            'mayoritario' => array(
                'rango'    => $mayorRango,
                'cantidad' => $mayorCantidad < 0 ? 0 : $mayorCantidad,
            ),
            'lineas' => $lineas,
        );
    }

    public function getMixPlanes($obrasoc, $periodo)
    {
        $corte = $this->asegurarCorte($obrasoc, $periodo);
        if (empty($corte['disponible'])) {
            return $this->respuestaPlanesVacia(array('El corte activo no tiene afiliados cargados para esta obra social.'));
        }

        $filtro = $this->filtroCorteActivo($obrasoc, $corte['periodo']);
        $sql = "SELECT CASE
                         WHEN PLAN IS NULL OR TRIM(PLAN) = '' THEN 'Sin plan'
                         ELSE TRIM(PLAN)
                       END AS plan,
                       COUNT(*) AS cantidad
                  FROM vfpmedicos.t_padron"
             . $filtro['sql']
             . " GROUP BY plan
                 ORDER BY cantidad DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($filtro['params']);
        $filas = $stmt->fetchAll();

        $labels = array();
        $data = array();
        $total = 0;
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $cantidad = (int) $fila['cantidad'];
                $labels[] = (string) $fila['plan'];
                $data[] = $cantidad;
                $total += $cantidad;
            }
        }

        $lineas = array();
        if ($total === 0 || count($labels) === 0) {
            $lineas[] = 'El corte ' . $corte['periodo'] . ' no tiene planes para concentrar.';
            $principal = array('plan' => '', 'cantidad' => 0, 'porcentaje' => 0);
        } else {
            $pct = round(($data[0] / $total) * 100, 1);
            $principal = array(
                'plan'        => $labels[0],
                'cantidad'    => $data[0],
                'porcentaje'  => $pct,
            );
            $lineas[] = 'El plan ' . $labels[0] . ' concentra ' . $this->formatoEntero($data[0])
                . ' afiliados y representa el ' . $this->formatoPorcentaje($pct)
                . ' del padrón del corte ' . $corte['periodo'] . '.';
        }

        return array(
            'labels'     => $labels,
            'data'       => $data,
            'principal'  => $principal,
            'lineas'     => $lineas,
        );
    }

    public function getTopLocalidades($obrasoc, $periodo)
    {
        $origen = $this->periodoConLocalidad($obrasoc, $periodo);
        if ($origen['periodo'] === '') {
            return $this->respuestaLocalidadesVacia(array('No hay localidades informadas para esta obra social.'));
        }

        $obra = strtoupper(trim((string) $obrasoc));
        $sql = "SELECT TRIM(LOCALIDAD) AS localidad, COUNT(*) AS total
                  FROM vfpmedicos.t_padron
                 WHERE PERIODO = :periodo
                   AND LOCALIDAD <> ''";
        $params = array(':periodo' => $origen['periodo']);
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra';
            $params[':obra'] = $obra;
            $archivo = $this->archivoVigente($obra, $origen['periodo']);
            if ($archivo !== '') {
                $sql .= ' AND TRIM(PFILE) = :archivo';
                $params[':archivo'] = $archivo;
            }
        }
        $sql .= ' GROUP BY LOCALIDAD ORDER BY total DESC LIMIT 10';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $labels = array();
        $data = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $nombre = trim((string) $this->valorFila($fila, 'localidad'));
                if ($nombre === '') {
                    continue;
                }
                $labels[] = $nombre;
                $data[] = (int) $this->valorFila($fila, 'total');
            }
        }

        $totalPeriodo = (int) $origen['total'];
        if ($obra !== '' && isset($params[':archivo'])) {
            $sqlTotal = "SELECT COUNT(*) AS total
                           FROM vfpmedicos.t_padron
                          WHERE PERIODO = :periodo
                            AND LOCALIDAD <> ''
                            AND OBRASOC = :obra
                            AND TRIM(PFILE) = :archivo";
            $stmtTotal = $this->db->prepare($sqlTotal);
            $stmtTotal->execute($params);
            $filaTotal = $stmtTotal->fetch();
            if (is_array($filaTotal)) {
                $totalPeriodo = (int) $this->valorFila($filaTotal, 'total');
            }
        }
        $lineas = array();
        if (count($labels) === 0 || $totalPeriodo === 0) {
            $lineas[] = 'No hay localidades informadas para esta obra social.';
        } else {
            $pct = round(($data[0] / $totalPeriodo) * 100, 1);
            $lineas[] = 'La localidad con mayor concentración es ' . $labels[0] . ', con '
                . $this->formatoEntero($data[0]) . ' afiliados ('
                . $this->formatoPorcentaje($pct) . ' de los afiliados con localidad informada en '
                . $origen['periodo'] . ').';
        }

        return array(
            'titulo'  => 'Top 10 Localidades',
            'campo'   => 'LOCALIDAD',
            'periodo' => $origen['periodo'],
            'labels'  => $labels,
            'data'    => $data,
            'lineas'  => $lineas,
        );
    }

    /**
     * Discapacidad del archivo vigente del corte, agrupada por provincia.
     * Si el período tiene varios PFILE, se usa el de fecha más nueva
     * para no sumar dos veces a la misma persona.
     */
    public function getDiscapacidadPorZona($obrasoc, $periodo)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $periodo = trim((string) $periodo);
        if ($obra === '' || $periodo === '') {
            return $this->respuestaZonasVacia(array('Seleccione una obra social para ver la discapacidad por zona.'));
        }

        $archivo = $this->archivoVigente($obra, $periodo);
        $sql = "SELECT CASE
                         WHEN PROVINCIA IS NULL OR TRIM(PROVINCIA) = '' THEN 'Sin zona'
                         ELSE TRIM(PROVINCIA)
                       END AS zona,
                       TRIM(ENROL1) AS marca,
                       COUNT(*) AS cantidad
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PERIODO = :periodo
                   AND ENROL1 IS NOT NULL
                   AND TRIM(ENROL1) <> ''
                   AND TRIM(ENROL1) <> '-'";
        $params = array(
            ':obra'    => $obra,
            ':periodo' => $periodo,
        );
        if ($archivo !== '') {
            $sql .= ' AND TRIM(PFILE) = :archivo';
            $params[':archivo'] = $archivo;
        }
        $sql .= ' GROUP BY zona, TRIM(ENROL1)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $porZona = array();
        $totalZona = array();
        $totalCondicion = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $zona = trim((string) $this->valorFila($fila, 'zona'));
                $condicion = $this->etiquetaCondicion($this->valorFila($fila, 'marca'));
                $cantidad = (int) $this->valorFila($fila, 'cantidad');
                if ($zona === '' || $condicion === '' || $cantidad <= 0) {
                    continue;
                }
                if (!isset($porZona[$zona])) {
                    $porZona[$zona] = array();
                    $totalZona[$zona] = 0;
                }
                if (!isset($porZona[$zona][$condicion])) {
                    $porZona[$zona][$condicion] = 0;
                }
                $porZona[$zona][$condicion] += $cantidad;
                $totalZona[$zona] += $cantidad;
                if (!isset($totalCondicion[$condicion])) {
                    $totalCondicion[$condicion] = 0;
                }
                $totalCondicion[$condicion] += $cantidad;
            }
        }

        $labels = array_keys($porZona);
        usort($labels, function ($a, $b) use ($totalZona) {
            if ($totalZona[$a] === $totalZona[$b]) {
                return strcmp($a, $b);
            }
            return ($totalZona[$a] > $totalZona[$b]) ? -1 : 1;
        });

        $condiciones = array_keys($totalCondicion);
        usort($condiciones, array($this, 'compararCondicion'));

        $series = array();
        $data = array();
        foreach ($condiciones as $condicion) {
            $valores = array();
            foreach ($labels as $zona) {
                $valores[] = isset($porZona[$zona][$condicion]) ? (int) $porZona[$zona][$condicion] : 0;
            }
            $series[] = array(
                'label' => $condicion,
                'data'  => $valores,
                'color' => $this->colorCondicion($condicion),
            );
            if ($condicion === 'Discapacidad') {
                $data = $valores;
            }
        }

        $total = 0;
        foreach ($totalCondicion as $cantidad) {
            $total += (int) $cantidad;
        }

        $lineas = array();
        if ($total === 0 || count($labels) === 0) {
            $lineas[] = 'El corte ' . $periodo . ' no informa condiciones por provincia.';
        } else {
            $origen = $archivo !== '' ? ('el archivo ' . $archivo) : ('el corte ' . $periodo);
            $partes = array();
            foreach ($condiciones as $condicion) {
                $partes[] = $condicion . ' ' . $this->formatoEntero($totalCondicion[$condicion]);
            }
            $lineas[] = 'En ' . $origen . ': ' . implode(', ', $partes) . '.';
            $lineas[] = 'La zona con más condiciones es ' . $labels[0] . ', con '
                . $this->formatoEntero($totalZona[$labels[0]]) . ' afiliados.';
        }

        return array(
            'titulo'  => 'Condiciones por zona',
            'campo'   => 'PROVINCIA',
            'periodo' => $periodo,
            'archivo' => $archivo,
            'total'   => $total,
            'labels'  => $labels,
            'data'    => $data,
            'series'  => $series,
            'lineas'  => $lineas,
        );
    }

    private function etiquetaCondicion($marca)
    {
        $texto = strtoupper(trim((string) $marca));
        if ($texto === '' || $texto === '-' || $texto === 'COMUN' || $texto === 'COMUNES') {
            return '';
        }
        if (strpos($texto, 'DISCAP') !== false) {
            return 'Discapacidad';
        }
        if (strpos($texto, 'DIAB') !== false) {
            return 'Diabetes';
        }
        if (strpos($texto, 'HIV') !== false) {
            return 'HIV';
        }
        if (strpos($texto, 'ONCO') !== false) {
            return 'Oncología';
        }
        if (strpos($texto, 'ALTO') !== false) {
            return 'Alto valor';
        }
        if (strpos($texto, 'PMI') !== false || strpos($texto, 'MATERNO') !== false) {
            return 'PMI';
        }
        return trim((string) $marca);
    }

    private function compararCondicion($a, $b)
    {
        $orden = array(
            'Discapacidad' => 1,
            'Diabetes'     => 2,
            'HIV'          => 3,
            'Oncología'    => 4,
            'Alto valor'   => 5,
            'PMI'          => 6,
        );
        $ordenA = isset($orden[$a]) ? $orden[$a] : 50;
        $ordenB = isset($orden[$b]) ? $orden[$b] : 50;
        if ($ordenA === $ordenB) {
            return strcmp($a, $b);
        }
        return ($ordenA < $ordenB) ? -1 : 1;
    }

    private function colorCondicion($etiqueta)
    {
        $colores = array(
            'Discapacidad' => '#00695c',
            'Diabetes'     => '#ef6c00',
            'HIV'          => '#c2185b',
            'Oncología'    => '#6a1b9a',
            'Alto valor'   => '#1565c0',
            'PMI'          => '#00838f',
        );
        return isset($colores[$etiqueta]) ? $colores[$etiqueta] : '#546e7a';
    }

    private function archivoVigente($obra, $periodo)
    {
        $mapa = $this->mapaArchivosVigentes($obra);
        $periodo = trim((string) $periodo);
        return isset($mapa[$periodo]) ? $mapa[$periodo] : '';
    }

    private function mapaArchivosVigentes($obra)
    {
        $obra = strtoupper(trim((string) $obra));
        if (isset($this->vigentesObra[$obra])) {
            return $this->vigentesObra[$obra];
        }

        $sql = "SELECT TRIM(PERIODO) AS periodo, TRIM(PFILE) AS pfile
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PERIODO <> ''
                   AND PFILE IS NOT NULL
                   AND TRIM(PFILE) <> ''
                 GROUP BY TRIM(PERIODO), TRIM(PFILE)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $porPeriodo = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $periodo = trim((string) $this->valorFila($fila, 'periodo'));
                $pfile = strtoupper(trim((string) $this->valorFila($fila, 'pfile')));
                if ($periodo === '' || $pfile === '') {
                    continue;
                }
                if (!isset($porPeriodo[$periodo])) {
                    $porPeriodo[$periodo] = array();
                }
                $porPeriodo[$periodo][] = $pfile;
            }
        }

        $mapa = array();
        foreach ($porPeriodo as $periodo => $archivos) {
            $mapa[$periodo] = $this->elegirArchivoVigente($archivos, $periodo);
        }
        $this->vigentesObra[$obra] = $mapa;
        return $mapa;
    }

    private function elegirArchivoVigente($archivos, $periodo)
    {
        $fechados = array();
        foreach ($archivos as $pfile) {
            if ($this->archivoTieneFecha($pfile)) {
                $fechados[] = $pfile;
            }
        }
        $candidatos = count($fechados) > 0 ? $fechados : $archivos;
        $mejor = '';
        $ordenMejor = -1;
        foreach ($candidatos as $pfile) {
            $orden = $this->ordenArchivo($pfile, $periodo);
            if ($orden > $ordenMejor || ($orden === $ordenMejor && strcmp($pfile, $mejor) > 0)) {
                $ordenMejor = $orden;
                $mejor = $pfile;
            }
        }
        return $mejor;
    }

    private function respuestaZonasVacia($lineas)
    {
        return array(
            'titulo'  => 'Discapacidad por zona',
            'campo'   => 'PROVINCIA',
            'periodo' => '',
            'archivo' => '',
            'total'   => 0,
            'labels'  => array(),
            'data'    => array(),
            'lineas'  => $lineas,
        );
    }

    /**
     * Último período de t_padron con LOCALIDAD cargada.
     * Si el período pedido también tiene localidades, se usa ese.
     */
    private function periodoConLocalidad($obrasoc, $periodoPreferido)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $sql = "SELECT TRIM(PERIODO) AS periodo, COUNT(*) AS total
                  FROM vfpmedicos.t_padron
                 WHERE LOCALIDAD <> ''";
        $params = array();
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra';
            $params[':obra'] = $obra;
        }
        $sql .= ' GROUP BY PERIODO';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $preferido = trim((string) $periodoPreferido);
        $mejorPeriodo = '';
        $mejorOrden = -1;
        $mejorTotal = 0;
        $totalPreferido = 0;

        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $periodo = trim((string) $this->valorFila($fila, 'periodo'));
                $total = (int) $this->valorFila($fila, 'total');
                if ($periodo === '' || $total === 0) {
                    continue;
                }
                if ($periodo === $preferido) {
                    $totalPreferido = $total;
                }
                $orden = $this->ordenPeriodo($periodo);
                if ($orden > $mejorOrden) {
                    $mejorOrden = $orden;
                    $mejorPeriodo = $periodo;
                    $mejorTotal = $total;
                }
            }
        }

        if ($totalPreferido > 0) {
            return array('periodo' => $preferido, 'total' => $totalPreferido);
        }

        return array('periodo' => $mejorPeriodo, 'total' => $mejorTotal);
    }

    private function valorFila($fila, $clave)
    {
        if (!is_array($fila)) {
            return null;
        }
        if (isset($fila[$clave])) {
            return $fila[$clave];
        }
        $mayuscula = strtoupper($clave);
        if (isset($fila[$mayuscula])) {
            return $fila[$mayuscula];
        }
        $minuscula = strtolower($clave);
        if (isset($fila[$minuscula])) {
            return $fila[$minuscula];
        }
        return null;
    }

    private function resolverCorteActivo($obrasoc)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $periodo = $this->getUltimoPeriodoReal($obra);
        if ($periodo === null || $periodo === '') {
            return array(
                'disponible' => false,
                'periodo'    => '',
                'total'      => 0,
                'fuente'     => 't_padron',
                'obra'       => $obra,
            );
        }

        $total = 0;
        foreach ($this->leerCortesDetalle($obra) as $fila) {
            if ($fila['periodo'] === $periodo) {
                $total = (int) $fila['total'];
                break;
            }
        }

        return array(
            'disponible' => $total > 0,
            'periodo'    => $periodo,
            'total'      => $total,
            'fuente'     => 't_padron',
            'obra'       => $obra,
        );
    }

    private function asegurarCorte($obrasoc, $periodo)
    {
        $corte = $this->resolverCorteActivo($obrasoc);
        $periodo = trim((string) $periodo);
        if ($periodo !== '' && !empty($corte['disponible'])) {
            $corte['periodo'] = $periodo;
        }
        return $corte;
    }

    /**
     * Altas y bajas persona a persona entre cortes consecutivos de t_padron.
     * Alta: documento que está en el mes y no estaba en el anterior.
     * Baja: documento que estaba y no está.
     * Discapacidad: enrol1 contiene DISCAP. COMUN de OSPACA no entra.
     * Toma como máximo los últimos 12 movimientos del filtro.
     */
    private function movimientoPersonas($obrasoc, $anios)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return array(
                'disponible' => false,
                'filas'      => array(),
                'mensaje'    => 'Seleccione una obra social para ver altas y bajas por persona.',
            );
        }

        if ($this->hayPadronPfile($obra)) {
            return $this->movimientoPorArchivo($obra, $anios);
        }

        $personas = $this->leerMovimientoUnicos($obra);
        if (count($personas) === 0) {
            return array(
                'disponible' => false,
                'filas'      => array(),
                'mensaje'    => 'Unicos no tiene afiliados de ' . $obra . '.',
            );
        }

        $mapaAnios = array();
        if (is_array($anios)) {
            foreach ($anios as $anio) {
                $mapaAnios[(int) $anio] = true;
            }
        }

        $disca = $this->documentosConDiscapacidad($obra);
        $periodos = array();
        foreach ($personas as $persona) {
            if ($persona['periodo'] !== '' && $this->anioDentro($persona['periodo'], $mapaAnios)) {
                $periodos[$persona['periodo']] = true;
            }
            if ($persona['fbaja'] !== '' && $this->anioDentro($persona['fbaja'], $mapaAnios)) {
                $periodos[$persona['fbaja']] = true;
            }
        }
        $lista = array_keys($periodos);
        usort($lista, array($this, 'compararPeriodoAsc'));
        if (count($lista) === 0) {
            return array(
                'disponible' => false,
                'filas'      => array(),
                'mensaje'    => 'Unicos no tiene altas ni bajas de ' . $obra . ' en los años elegidos.',
            );
        }

        $filas = array();
        $stockAnterior = null;
        foreach ($lista as $periodo) {
            $altas = $this->bolsaMovimiento();
            $bajas = $this->bolsaMovimiento();
            $periodoDiscaBaja = $this->periodoDiscapacidadAnterior($disca['periodos'], $periodo);
            foreach ($personas as $persona) {
                if ($persona['periodo'] === $periodo) {
                    $persona['disca'] = isset($disca['porPeriodo'][$periodo][$persona['dni']]);
                    $persona['rango'] = $this->rangoPorNacimiento($persona['nacimiento'], $periodo);
                    $this->sumarPersona($altas, $persona);
                }
                if ($persona['fbaja'] === $periodo) {
                    $persona['disca'] = $periodoDiscaBaja !== '' && isset($disca['porPeriodo'][$periodoDiscaBaja][$persona['dni']]);
                    $persona['rango'] = $this->rangoPorNacimiento($persona['nacimiento'], $periodo);
                    $this->sumarPersona($bajas, $persona);
                }
            }

            $stock = $this->stockUnicosHasta($personas, $periodo);
            $saldo = (int) $altas['total'] - (int) $bajas['total'];
            $variacion = null;
            if ($stockAnterior !== null && $stockAnterior > 0) {
                $variacion = round(($saldo / $stockAnterior) * 100, 2);
            }

            $filas[] = array(
                'periodo'        => $periodo,
                'contra'         => 'unicos',
                'total'          => $stock,
                'total_anterior' => $stockAnterior === null ? 0 : $stockAnterior,
                'altas'          => $altas['total'],
                'altas_disca'    => $altas['disca'],
                'bajas'          => $bajas['total'],
                'bajas_disca'    => $bajas['disca'],
                'saldo'          => $saldo,
                'variacion_pct'  => $variacion,
                'altas_edad'     => $altas['rangos'],
                'bajas_edad'     => $bajas['rangos'],
            );
            $stockAnterior = $stock;
        }

        return array(
            'disponible' => true,
            'fuente'     => 'unicos',
            'filas'      => $filas,
            'grafico'    => $this->graficoMovimientoReal($filas),
            'mensaje'    => '',
        );
    }

    private function hayPadronPfile($obra)
    {
        try {
            $sql = "SELECT COUNT(*) AS cantidad
                      FROM vfpmedicos.t_padron
                     WHERE OBRASOC = :obra
                       AND PFILE IS NOT NULL
                       AND TRIM(PFILE) <> ''";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':obra' => $obra));
            $fila = $stmt->fetch();
            return (int) $this->valorFila($fila, 'cantidad') > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Cruza cada PFILE con el archivo anterior.
     * OA261030.DBF y OA261106.DBF pueden compartir el período 26/11:
     * alta es el documento que está en el nuevo y no en el anterior,
     * baja es el que estaba y no vino.
     */
    private function movimientoPorArchivo($obra, $anios)
    {
        $mapaAnios = array();
        if (is_array($anios)) {
            foreach ($anios as $anio) {
                $mapaAnios[(int) $anio] = true;
            }
        }

        $archivos = $this->leerArchivosPadron($obra);
        if (count($archivos) === 0) {
            return array(
                'disponible' => false,
                'fuente'     => 'pfile',
                'filas'      => array(),
                'mensaje'    => 'No hay archivos PFILE para ' . $obra . '.',
            );
        }

        $ordenados = array();
        foreach ($archivos as $clave => $personas) {
            $partes = explode('|', $clave, 2);
            $ordenados[] = array(
                'periodo' => $partes[0],
                'pfile'   => isset($partes[1]) ? $partes[1] : '',
                'orden'   => $this->ordenArchivo(isset($partes[1]) ? $partes[1] : '', $partes[0]),
                'personas'=> $personas,
            );
        }
        usort($ordenados, array($this, 'compararArchivo'));
        $ordenados = $this->quedarseConArchivosFechados($ordenados);

        $indices = array();
        $cantidad = count($ordenados);
        for ($i = 0; $i < $cantidad; $i++) {
            if ($this->anioDentro($ordenados[$i]['periodo'], $mapaAnios)) {
                if (count($indices) === 0 && $i > 0) {
                    $indices[] = $i - 1;
                }
                $indices[] = $i;
            }
        }
        if (count($indices) > 13) {
            $indices = array_slice($indices, -13);
        }
        if (count($indices) < 2) {
            $unico = count($ordenados) === 1 ? $ordenados[0]['pfile'] : '';
            $aviso = 'Hay un solo archivo con PFILE'
                . ($unico !== '' ? ' (' . $unico . ')' : '')
                . '. Cuando se cargue el siguiente del mismo período se ven las altas y las bajas.';
            return array(
                'disponible' => false,
                'fuente'     => 'pfile',
                'filas'      => array(),
                'mensaje'    => $aviso,
            );
        }

        $filas = array();
        $stockAnterior = null;
        $vistos = count($indices);
        for ($n = 1; $n < $vistos; $n++) {
            $anterior = $ordenados[$indices[$n - 1]];
            $actual = $ordenados[$indices[$n]];
            $cruce = $this->cruzarMapas($actual['personas'], $anterior['personas'], $actual['periodo']);
            $stock = count($actual['personas']);
            $saldo = (int) $cruce['altas']['total'] - (int) $cruce['bajas']['total'];
            $variacion = null;
            if ($stockAnterior !== null && $stockAnterior > 0) {
                $variacion = round(($saldo / $stockAnterior) * 100, 2);
            }
            $filas[] = array(
                'periodo'        => $actual['periodo'],
                'pfile'          => $actual['pfile'],
                'contra'         => $anterior['pfile'],
                'total'          => $stock,
                'total_anterior' => $stockAnterior === null ? count($anterior['personas']) : $stockAnterior,
                'altas'          => $cruce['altas']['total'],
                'altas_disca'    => $cruce['altas']['disca'],
                'bajas'          => $cruce['bajas']['total'],
                'bajas_disca'    => $cruce['bajas']['disca'],
                'saldo'          => $saldo,
                'variacion_pct'  => $variacion,
                'altas_edad'     => $cruce['altas']['rangos'],
                'bajas_edad'     => $cruce['bajas']['rangos'],
            );
            $stockAnterior = $stock;
        }

        return array(
            'disponible' => true,
            'fuente'     => 'pfile',
            'filas'      => $filas,
            'grafico'    => $this->graficoMovimientoReal($filas),
            'mensaje'    => '',
        );
    }

    private function leerArchivosPadron($obra)
    {
        $sql = "SELECT TRIM(PERIODO) AS periodo,
                       TRIM(PFILE) AS pfile,
                       NRODOC AS dni,
                       FECNACIM AS nacimiento,
                       SEXO AS sexo,
                       CASE WHEN UPPER(TRIM(ENROL1)) LIKE '%DISCAP%' THEN 1 ELSE 0 END AS disca
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PFILE IS NOT NULL
                   AND TRIM(PFILE) <> ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $archivos = array();
        if (!is_array($filas)) {
            return $archivos;
        }
        foreach ($filas as $fila) {
            $periodo = trim((string) $this->valorFila($fila, 'periodo'));
            $pfile = strtoupper(trim((string) $this->valorFila($fila, 'pfile')));
            $dni = (int) $this->valorFila($fila, 'dni');
            if ($this->ordenPeriodo($periodo) <= 0 || $pfile === '' || $dni <= 0) {
                continue;
            }
            $clave = $periodo . '|' . $pfile;
            if (!isset($archivos[$clave])) {
                $archivos[$clave] = array();
            }
            $archivos[$clave][$dni] = array(
                'disca'      => (int) $this->valorFila($fila, 'disca') === 1,
                'sexo'       => $this->sexoClave($this->valorFila($fila, 'sexo')),
                'nacimiento' => $this->valorFila($fila, 'nacimiento'),
                'rango'      => '',
            );
        }
        return $archivos;
    }

    private function cruzarMapas($actual, $anterior, $periodo)
    {
        $altas = $this->bolsaMovimiento();
        $bajas = $this->bolsaMovimiento();
        foreach ($actual as $dni => $persona) {
            if (!isset($anterior[$dni])) {
                $persona['rango'] = $this->rangoPorNacimiento($persona['nacimiento'], $periodo);
                $this->sumarPersona($altas, $persona);
            }
        }
        foreach ($anterior as $dni => $persona) {
            if (!isset($actual[$dni])) {
                $persona['rango'] = $this->rangoPorNacimiento($persona['nacimiento'], $periodo);
                $this->sumarPersona($bajas, $persona);
            }
        }
        return array('altas' => $altas, 'bajas' => $bajas);
    }

    private function ordenArchivo($pfile, $periodo)
    {
        $nombre = strtoupper(trim((string) $pfile));
        if (preg_match('/(\d{2})(\d{2})(\d{2})(?:\.DBF)?$/', $nombre, $partes)) {
            $mes = (int) $partes[2];
            $dia = (int) $partes[3];
            if ($mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31) {
                return ((2000 + (int) $partes[1]) * 10000) + ($mes * 100) + $dia;
            }
        }
        if (preg_match('/(\d{2})(\d{2})(?:\.DBF)?$/', $nombre, $partes)) {
            $mes = (int) $partes[2];
            if ($mes >= 1 && $mes <= 12) {
                return ((2000 + (int) $partes[1]) * 10000) + ($mes * 100);
            }
        }
        return $this->ordenPeriodo($periodo) * 100;
    }

    private function archivoTieneFecha($pfile)
    {
        $nombre = strtoupper(trim((string) $pfile));
        if (preg_match('/(\d{2})(\d{2})(\d{2})(?:\.DBF)?$/', $nombre, $partes)) {
            $mes = (int) $partes[2];
            $dia = (int) $partes[3];
            return $mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31;
        }
        if (preg_match('/(\d{2})(\d{2})(?:\.DBF)?$/', $nombre, $partes)) {
            $mes = (int) $partes[2];
            return $mes >= 1 && $mes <= 12;
        }
        return false;
    }

    private function quedarseConArchivosFechados($archivos)
    {
        $fechados = array();
        foreach ($archivos as $archivo) {
            if ($this->archivoTieneFecha($archivo['pfile'])) {
                $fechados[] = $archivo;
            }
        }
        if (count($fechados) === 0) {
            return $archivos;
        }
        return $fechados;
    }

    private function compararArchivo($a, $b)
    {
        if ($a['orden'] === $b['orden']) {
            return strcmp($a['pfile'], $b['pfile']);
        }
        if ($a['orden'] < $b['orden']) {
            return -1;
        }
        return 1;
    }

    private function nombreCortoArchivo($pfile)
    {
        $nombre = strtoupper(trim((string) $pfile));
        if (substr($nombre, -4) === '.DBF') {
            $nombre = substr($nombre, 0, -4);
        }
        return $nombre;
    }

    private function anioDentro($periodo, $mapaAnios)
    {
        if (count($mapaAnios) === 0) {
            return true;
        }
        $orden = $this->ordenPeriodo($periodo);
        if ($orden <= 0) {
            return false;
        }
        return isset($mapaAnios[(int) floor($orden / 100)]);
    }

    private function leerMovimientoUnicos($obra)
    {
        $sql = "SELECT TRIM(PERIODO) AS periodo,
                       TRIM(FBAJA) AS fbaja,
                       NRODOC AS dni,
                       FECNACIM AS nacimiento,
                       SEXO AS sexo
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $lista = array();
        if (!is_array($filas)) {
            return $lista;
        }
        foreach ($filas as $fila) {
            $periodo = trim((string) $this->valorFila($fila, 'periodo'));
            $fbaja = trim((string) $this->valorFila($fila, 'fbaja'));
            if ($this->ordenPeriodo($periodo) <= 0) {
                $periodo = '';
            }
            if ($this->ordenPeriodo($fbaja) <= 0) {
                $fbaja = '';
            }
            if ($periodo === '' && $fbaja === '') {
                continue;
            }
            $lista[] = array(
                'periodo'    => $periodo,
                'fbaja'      => $fbaja,
                'dni'        => (int) $this->valorFila($fila, 'dni'),
                'nacimiento' => $this->valorFila($fila, 'nacimiento'),
                'sexo'       => $this->sexoClave($this->valorFila($fila, 'sexo')),
                'disca'      => false,
                'rango'      => '',
            );
        }
        return $lista;
    }

    private function documentosConDiscapacidad($obra)
    {
        $sql = "SELECT TRIM(PERIODO) AS periodo, NRODOC AS dni
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND UPPER(TRIM(ENROL1)) LIKE '%DISCAP%'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $porPeriodo = array();
        $periodos = array();
        if (is_array($filas)) {
            foreach ($filas as $fila) {
                $periodo = trim((string) $this->valorFila($fila, 'periodo'));
                $dni = (int) $this->valorFila($fila, 'dni');
                if ($this->ordenPeriodo($periodo) <= 0 || $dni <= 0) {
                    continue;
                }
                if (!isset($porPeriodo[$periodo])) {
                    $porPeriodo[$periodo] = array();
                    $periodos[] = $periodo;
                }
                $porPeriodo[$periodo][$dni] = true;
            }
        }
        usort($periodos, array($this, 'compararPeriodoAsc'));
        return array(
            'porPeriodo' => $porPeriodo,
            'periodos'   => $periodos,
        );
    }

    private function periodoDiscapacidadAnterior($periodos, $periodo)
    {
        $orden = $this->ordenPeriodo($periodo);
        $mejor = '';
        $ordenMejor = -1;
        foreach ($periodos as $candidato) {
            $ordenCandidato = $this->ordenPeriodo($candidato);
            if ($ordenCandidato < $orden && $ordenCandidato > $ordenMejor) {
                $ordenMejor = $ordenCandidato;
                $mejor = $candidato;
            }
        }
        return $mejor;
    }

    private function stockUnicosHasta($personas, $periodo)
    {
        $orden = $this->ordenPeriodo($periodo);
        $total = 0;
        foreach ($personas as $persona) {
            $ordenAlta = $this->ordenPeriodo($persona['periodo']);
            if ($ordenAlta <= 0 || $ordenAlta > $orden) {
                continue;
            }
            $ordenBaja = $this->ordenPeriodo($persona['fbaja']);
            if ($persona['fbaja'] !== '' && $ordenBaja > 0 && $ordenBaja <= $orden) {
                continue;
            }
            $total++;
        }
        return $total;
    }

    private function graficoMovimientoReal($filas)
    {
        $labels = array();
        $totales = array();
        $altas = array();
        $bajas = array();
        $altasDisca = array();
        $bajasDisca = array();
        $variacion = array();

        $archivos = array();
        $contra = array();
        $fuente = 'unicos';
        foreach ($filas as $fila) {
            $archivo = isset($fila['pfile']) ? trim((string) $fila['pfile']) : '';
            if ($archivo !== '') {
                $fuente = 'pfile';
                $labels[] = $fila['periodo'] . ' ' . $this->nombreCortoArchivo($archivo);
            } else {
                $labels[] = $fila['periodo'];
            }
            $archivos[] = $archivo;
            $contra[] = isset($fila['contra']) ? $fila['contra'] : '';
            $totales[] = (int) $fila['total'];
            $altas[] = (int) $fila['altas'];
            $bajas[] = (int) $fila['bajas'];
            $altasDisca[] = (int) $fila['altas_disca'];
            $bajasDisca[] = (int) $fila['bajas_disca'];
            $variacion[] = $fila['variacion_pct'];
        }

        return array(
            'reales'        => true,
            'fuente'        => $fuente,
            'archivos'      => $archivos,
            'contra'        => $contra,
            'labels'        => $labels,
            'totales'       => $totales,
            'altas'         => $altas,
            'bajas'         => $bajas,
            'altas_disca'   => $altasDisca,
            'bajas_disca'   => $bajasDisca,
            'variacion_pct' => $variacion,
        );
    }

    private function periodosParaMovimiento($obra, $anios)
    {
        $mapaAnios = array();
        if (is_array($anios)) {
            foreach ($anios as $anio) {
                $mapaAnios[(int) $anio] = true;
            }
        }

        $lista = $this->listarPeriodosPadron($obra);
        usort($lista, array($this, 'compararPeriodoAsc'));

        $elegidos = array();
        $anterior = '';
        foreach ($lista as $periodo) {
            $orden = $this->ordenPeriodo($periodo);
            $anio = (int) floor($orden / 100);
            if (count($mapaAnios) > 0 && !isset($mapaAnios[$anio])) {
                $anterior = $periodo;
                continue;
            }
            if (count($elegidos) === 0 && $anterior !== '') {
                $elegidos[] = $anterior;
            }
            $elegidos[] = $periodo;
            $anterior = $periodo;
        }

        if (count($elegidos) > 13) {
            $elegidos = array_values(array_slice($elegidos, -13));
        }

        return $elegidos;
    }

    private function leerPersonasPadron($obra, $periodos)
    {
        $params = array(':obra' => $obra);
        $marcas = array();
        $i = 0;
        foreach ($periodos as $periodo) {
            $clave = ':p' . $i;
            $marcas[] = $clave;
            $params[$clave] = $periodo;
            $i++;
        }

        $sql = "SELECT TRIM(PERIODO) AS periodo,
                       NRODOC AS dni,
                       CASE WHEN UPPER(TRIM(ENROL1)) LIKE '%DISCAP%' THEN 1 ELSE 0 END AS disca,
                       FECNACIM AS nacimiento,
                       SEXO AS sexo
                  FROM vfpmedicos.t_padron
                 WHERE OBRASOC = :obra
                   AND PERIODO IN (" . implode(', ', $marcas) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $mapas = array();
        if (!is_array($filas)) {
            return $mapas;
        }

        foreach ($filas as $fila) {
            $periodo = trim((string) $this->valorFila($fila, 'periodo'));
            $dni = (int) $this->valorFila($fila, 'dni');
            if ($periodo === '' || $dni <= 0) {
                continue;
            }
            if (!isset($mapas[$periodo])) {
                $mapas[$periodo] = array();
            }
            $mapas[$periodo][$dni] = array(
                'disca' => (int) $this->valorFila($fila, 'disca') === 1,
                'sexo'  => $this->sexoClave($this->valorFila($fila, 'sexo')),
                'rango' => $this->rangoPorNacimiento($this->valorFila($fila, 'nacimiento'), $periodo),
            );
        }

        return $mapas;
    }

    private function cruzarCortes($actual, $anterior, $mapaActual, $mapaAnterior)
    {
        $altas = $this->bolsaMovimiento();
        $bajas = $this->bolsaMovimiento();

        foreach ($mapaActual as $dni => $persona) {
            if (!isset($mapaAnterior[$dni])) {
                $this->sumarPersona($altas, $persona);
            }
        }
        foreach ($mapaAnterior as $dni => $persona) {
            if (!isset($mapaActual[$dni])) {
                $this->sumarPersona($bajas, $persona);
            }
        }

        $totalAnterior = count($mapaAnterior);
        $saldo = (int) $altas['total'] - (int) $bajas['total'];
        $variacion = null;
        if ($totalAnterior > 0) {
            $variacion = round(($saldo / $totalAnterior) * 100, 2);
        }

        return array(
            'periodo'       => $actual,
            'contra'        => $anterior,
            'total'         => count($mapaActual),
            'total_anterior'=> $totalAnterior,
            'altas'         => $altas['total'],
            'altas_disca'   => $altas['disca'],
            'bajas'         => $bajas['total'],
            'bajas_disca'   => $bajas['disca'],
            'saldo'         => $saldo,
            'variacion_pct' => $variacion,
            'altas_edad'    => $altas['rangos'],
            'bajas_edad'    => $bajas['rangos'],
        );
    }

    private function bolsaMovimiento()
    {
        $rangos = array();
        foreach ($this->rangosEtarios() as $rango) {
            $rangos[] = array(
                'rango'   => $rango,
                'm'       => 0,
                'f'       => 0,
                'o'       => 0,
                'disca_m' => 0,
                'disca_f' => 0,
                'disca_o' => 0,
            );
        }

        return array(
            'total'  => 0,
            'disca'  => 0,
            'rangos' => $rangos,
        );
    }

    private function sumarPersona(&$bolsa, $persona)
    {
        $bolsa['total']++;
        $sexo = isset($persona['sexo']) ? $persona['sexo'] : 'O';
        $rango = isset($persona['rango']) ? $persona['rango'] : '';
        $disca = !empty($persona['disca']);
        if ($disca) {
            $bolsa['disca']++;
        }

        $indice = null;
        foreach ($bolsa['rangos'] as $i => $fila) {
            if ($fila['rango'] === $rango) {
                $indice = $i;
                break;
            }
        }
        if ($indice === null) {
            return;
        }

        if ($sexo === 'M') {
            $bolsa['rangos'][$indice]['m']++;
            if ($disca) {
                $bolsa['rangos'][$indice]['disca_m']++;
            }
        } elseif ($sexo === 'F') {
            $bolsa['rangos'][$indice]['f']++;
            if ($disca) {
                $bolsa['rangos'][$indice]['disca_f']++;
            }
        } else {
            $bolsa['rangos'][$indice]['o']++;
            if ($disca) {
                $bolsa['rangos'][$indice]['disca_o']++;
            }
        }
    }

    private function sexoClave($sexo)
    {
        $texto = strtoupper(trim((string) $sexo));
        if ($texto === 'M' || $texto === 'MASCULINO' || $texto === 'H' || $texto === 'V' || $texto === '1') {
            return 'M';
        }
        if ($texto === 'F' || $texto === 'FEMENINO' || $texto === '2') {
            return 'F';
        }
        return 'O';
    }

    private function rangoPorNacimiento($fechaNac, $periodo)
    {
        $edad = $this->edadAlCierre($fechaNac, $periodo);
        if ($edad === null) {
            return '';
        }
        if ($edad <= 14) {
            return '0-14 (Pediátrico)';
        }
        if ($edad <= 30) {
            return '15-30 (Jóvenes)';
        }
        if ($edad <= 50) {
            return '31-50 (Adultos)';
        }
        if ($edad <= 64) {
            return '51-64 (Adultos Mayores)';
        }
        return '65+ (Pasivos)';
    }

    private function edadAlCierre($fechaNac, $periodo)
    {
        $texto = trim((string) $fechaNac);
        if ($texto === '' || strpos($texto, '0000-00-00') === 0) {
            return null;
        }
        $nac = strtotime(substr($texto, 0, 10));
        $corte = strtotime($this->fechaCorteDesdePeriodo($periodo));
        if ($nac === false || $corte === false || $nac > $corte) {
            return null;
        }
        $edad = (int) date('Y', $corte) - (int) date('Y', $nac);
        $cumple = mktime(0, 0, 0, (int) date('n', $nac), (int) date('j', $nac), (int) date('Y', $corte));
        if ($cumple > $corte) {
            $edad--;
        }
        if ($edad < 0 || $edad > 120) {
            return null;
        }
        return $edad;
    }

    private function filtroCorteActivo($obrasoc, $periodo)
    {
        $sql = ' WHERE PERIODO = :periodo ';
        $params = array(':periodo' => trim((string) $periodo));
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra !== '') {
            $sql .= ' AND OBRASOC = :obra ';
            $params[':obra'] = $obra;
            $archivo = $this->archivoVigente($obra, $params[':periodo']);
            if ($archivo !== '') {
                $sql .= ' AND TRIM(PFILE) = :archivoVigente ';
                $params[':archivoVigente'] = $archivo;
            }
        }
        return array('sql' => $sql, 'params' => $params);
    }

    private function fechaCorteDesdePeriodo($periodo)
    {
        $periodo = trim((string) $periodo);
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $periodo, $partes)) {
            return date('Y-m-d');
        }
        $anio = 2000 + (int) $partes[1];
        $mes = (int) $partes[2];
        if ($mes < 1 || $mes > 12) {
            return date('Y-m-d');
        }
        return date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes)));
    }

    private function rangosEtarios()
    {
        return array(
            '0-14 (Pediátrico)',
            '15-30 (Jóvenes)',
            '31-50 (Adultos)',
            '51-64 (Adultos Mayores)',
            '65+ (Pasivos)',
        );
    }

    private function formatoRatio($ratio)
    {
        $ratio = (float) $ratio;
        if (abs($ratio - round($ratio)) < 0.001) {
            return $this->formatoEntero($ratio);
        }
        return number_format($ratio, 2, ',', '.');
    }

    private function respuestaFamiliarVacia($lineas)
    {
        return array(
            'labels'     => array('Titulares', 'Adherentes'),
            'data'       => array(0, 0),
            'titulares'  => 0,
            'adherentes' => 0,
            'ratio'      => null,
            'lineas'     => $lineas,
        );
    }

    private function respuestaEtariaVacia($lineas)
    {
        $ceros = array(0, 0, 0, 0, 0);
        return array(
            'labels' => $this->rangosEtarios(),
            'series' => array(
                array('sexo' => 'M', 'label' => 'Masculino', 'data' => $ceros),
                array('sexo' => 'F', 'label' => 'Femenino', 'data' => $ceros),
                array('sexo' => 'O', 'label' => 'Otros', 'data' => $ceros),
            ),
            'mayoritario' => array('rango' => '', 'cantidad' => 0),
            'lineas' => $lineas,
        );
    }

    private function respuestaPlanesVacia($lineas)
    {
        return array(
            'labels'    => array(),
            'data'      => array(),
            'principal' => array('plan' => '', 'cantidad' => 0, 'porcentaje' => 0),
            'lineas'    => $lineas,
        );
    }

    private function respuestaLocalidadesVacia($lineas)
    {
        return array(
            'titulo'  => 'Top 10 Localidades',
            'campo'   => 'LOCALIDAD',
            'periodo' => '',
            'labels'  => array(),
            'data'    => array(),
            'lineas'  => $lineas,
        );
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
