<?php
/**
 * Auditoría de consumo prestacional sobre vfpmedicos.detconsu.
 *
 * En el archivo, la práctica del nomenclador es COCODPREST / CONOMBPRES
 * (por ejemplo 420131 CONSULTA ORL). El prestador que la brindó es
 * COPRESTADO / CONOMPREST. COPERIODO viene en aa/mm.
 *
 * Los códigos de práctica de exactamente 3 dígitos (002, 004, 005, …)
 * son imputaciones administrativas (IVA, redondeo, percepción, ajustes)
 * y quedan fuera de las estadísticas y de las grillas de consumo.
 *
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../../config/database.php';

class AuditoriaModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    public function getPeriodosDisponibles()
    {
        $sql = "SELECT COPERIODO AS periodo
                  FROM vfpmedicos.detconsu
                 GROUP BY COPERIODO";
        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        $lista = array();
        $vistos = array();

        if (!is_array($filas)) {
            return $lista;
        }

        foreach ($filas as $fila) {
            $periodo = trim((string) $this->valor($fila, 'periodo'));
            if (!$this->periodoValido($periodo) || isset($vistos[$periodo])) {
                continue;
            }
            $vistos[$periodo] = true;
            $lista[] = $periodo;
        }

        usort($lista, array($this, 'compararPeriodoDesc'));
        return $lista;
    }

    public function getConsumoPorAfiliado($obrasoc, $periodoDesde, $periodoHasta)
    {
        $filtro = $this->filtroBase($obrasoc, $periodoDesde, $periodoHasta);
        $sql = "SELECT TRIM(COAFILIADO) AS afiliado,
                       MAX(TRIM(CONOMPAC)) AS nombre,
                       TRIM(COCODPREST) AS practica,
                       MAX(TRIM(CONOMBPRES)) AS descripcion,
                       COUNT(*) AS casos,
                       SUM(COIMPORTE) AS importe
                  FROM vfpmedicos.detconsu"
             . $filtro['sql']
             . " GROUP BY COAFILIADO, COCODPREST
                 ORDER BY casos DESC, importe DESC";

        return $this->empaquetar($this->consultar($sql, $filtro['params']), $filtro, 'afiliado');
    }

    public function getPrestadoresDeAfiliadoPractica($obrasoc, $afiliado, $practica, $periodoDesde, $periodoHasta)
    {
        $filtro = $this->filtroBase($obrasoc, $periodoDesde, $periodoHasta);
        $filtro['sql'] .= ' AND COAFILIADO = :afiliado AND COCODPREST = :practica ';
        $filtro['params'][':afiliado'] = $this->limpiar($afiliado, 20);
        $filtro['params'][':practica'] = $this->limpiar($practica, 12);

        $sql = "SELECT TRIM(COPRESTADO) AS prestador,
                       MAX(TRIM(CONOMPREST)) AS nombre,
                       COUNT(*) AS casos,
                       SUM(COIMPORTE) AS importe
                  FROM vfpmedicos.detconsu"
             . $filtro['sql']
             . " GROUP BY COPRESTADO
                 ORDER BY casos DESC, importe DESC";

        return $this->empaquetar($this->consultar($sql, $filtro['params']), $filtro, 'prestador');
    }

    public function getDetalleCronologicoAfiliado($obrasoc, $afiliado, $practica, $prestador, $periodoDesde, $periodoHasta)
    {
        $filtro = $this->filtroBase($obrasoc, $periodoDesde, $periodoHasta);
        $filtro['sql'] .= ' AND COAFILIADO = :afiliado AND COCODPREST = :practica AND COPRESTADO = :prestador ';
        $filtro['params'][':afiliado'] = $this->limpiar($afiliado, 20);
        $filtro['params'][':practica'] = $this->limpiar($practica, 12);
        $filtro['params'][':prestador'] = $this->limpiar($prestador, 16);

        $sql = "SELECT COFECCONS AS fecha,
                       TRIM(CONOMMOT) AS motivo,
                       TRIM(CONOMDIAG) AS diagnostico,
                       TRIM(COPERIODO) AS periodo,
                       COIMPORTE AS importe
                  FROM vfpmedicos.detconsu"
             . $filtro['sql']
             . " ORDER BY COFECCONS ASC, id ASC";

        return $this->empaquetar($this->consultar($sql, $filtro['params']), $filtro, 'detalle');
    }

    public function getConsumoPorPrestador($obrasoc, $periodoDesde, $periodoHasta)
    {
        $filtro = $this->filtroBase($obrasoc, $periodoDesde, $periodoHasta);
        $sql = "SELECT TRIM(COPRESTADO) AS prestador,
                       MAX(TRIM(CONOMPREST)) AS nombre,
                       TRIM(COCODPREST) AS practica,
                       MAX(TRIM(CONOMBPRES)) AS descripcion,
                       COUNT(*) AS casos,
                       SUM(COIMPORTE) AS importe
                  FROM vfpmedicos.detconsu"
             . $filtro['sql']
             . " GROUP BY COPRESTADO, COCODPREST
                 ORDER BY casos DESC, importe DESC";

        return $this->empaquetar($this->consultar($sql, $filtro['params']), $filtro, 'prestador');
    }

    public function getAfiliadosDePrestadorPractica($obrasoc, $prestador, $practica, $periodoDesde, $periodoHasta)
    {
        $filtro = $this->filtroBase($obrasoc, $periodoDesde, $periodoHasta);
        $filtro['sql'] .= ' AND COPRESTADO = :prestador AND COCODPREST = :practica ';
        $filtro['params'][':prestador'] = $this->limpiar($prestador, 16);
        $filtro['params'][':practica'] = $this->limpiar($practica, 12);

        $sql = "SELECT TRIM(COAFILIADO) AS afiliado,
                       MAX(TRIM(CONOMPAC)) AS nombre,
                       COUNT(*) AS casos,
                       SUM(COIMPORTE) AS importe
                  FROM vfpmedicos.detconsu"
             . $filtro['sql']
             . " GROUP BY COAFILIADO
                 ORDER BY casos DESC, importe DESC";

        return $this->empaquetar($this->consultar($sql, $filtro['params']), $filtro, 'afiliado');
    }

    public function getDetalleCronologicoPrestador($obrasoc, $prestador, $practica, $afiliado, $periodoDesde, $periodoHasta)
    {
        return $this->getDetalleCronologicoAfiliado($obrasoc, $afiliado, $practica, $prestador, $periodoDesde, $periodoHasta);
    }

    public function getFichaAfiliado($obrasoc, $afiliado)
    {
        $afiliado = $this->limpiar($afiliado, 20);
        $obra = strtoupper(trim((string) $obrasoc));
        if ($afiliado === '') {
            return array(
                'encontrada' => false,
                'message'    => 'Seleccione un afiliado para ver la ficha.',
            );
        }

        $sql = "SELECT TRIM(NOMBRE) AS nombre,
                       TRIM(NROAFILIAD) AS nro,
                       AFILIADO AS numero,
                       TRIM(OBRASOC) AS obrasoc,
                       TRIM(PLAN) AS plan,
                       TRIM(SEXO) AS sexo,
                       FECNACIM AS nacimiento,
                       TRIM(TIPODOC) AS tipodoc,
                       NRODOC AS nrodoc,
                       TRIM(DOMICILIO) AS domicilio,
                       TRIM(D_PUERTA) AS puerta,
                       TRIM(D_PISO) AS piso,
                       TRIM(D_DEPTO) AS depto,
                       TRIM(LOCALIDAD) AS localidad,
                       TRIM(TELEFONO) AS telefono,
                       TRIM(EMAIL) AS email,
                       TRIM(ESTADO) AS estado,
                       TRIM(ENROL1) AS enrol1,
                       TRIM(PERIODO) AS periodo,
                       TRIM(CODADH) AS codadh
                  FROM vfpmedicos.padron_activos
                 WHERE TRIM(NROAFILIAD) = :nro";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':nro' => $afiliado));
        $filas = $stmt->fetchAll();

        if (!is_array($filas) || count($filas) === 0) {
            if (preg_match('/^\d{1,15}$/', $afiliado)) {
                $sql = "SELECT TRIM(NOMBRE) AS nombre,
                               TRIM(NROAFILIAD) AS nro,
                               AFILIADO AS numero,
                               TRIM(OBRASOC) AS obrasoc,
                               TRIM(PLAN) AS plan,
                               TRIM(SEXO) AS sexo,
                               FECNACIM AS nacimiento,
                               TRIM(TIPODOC) AS tipodoc,
                               NRODOC AS nrodoc,
                               TRIM(DOMICILIO) AS domicilio,
                               TRIM(D_PUERTA) AS puerta,
                               TRIM(D_PISO) AS piso,
                               TRIM(D_DEPTO) AS depto,
                               TRIM(LOCALIDAD) AS localidad,
                               TRIM(TELEFONO) AS telefono,
                               TRIM(EMAIL) AS email,
                               TRIM(ESTADO) AS estado,
                               TRIM(ENROL1) AS enrol1,
                               TRIM(PERIODO) AS periodo,
                               TRIM(CODADH) AS codadh
                          FROM vfpmedicos.padron_activos
                         WHERE AFILIADO = :num";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(':num' => $afiliado));
                $filas = $stmt->fetchAll();
            }
        }

        $elegida = $this->elegirFicha($filas, $obra);
        if ($elegida === null) {
            return array(
                'encontrada' => false,
                'message'    => 'El afiliado ' . $afiliado . ' no está en el padrón activo.',
                'afiliado'   => $afiliado,
            );
        }

        $nro = trim((string) $this->valor($elegida, 'nro'));
        if ($nro === '') {
            $nro = trim((string) $this->valor($elegida, 'numero'));
        }

        $domicilio = trim((string) $this->valor($elegida, 'domicilio'));
        $puerta = trim((string) $this->valor($elegida, 'puerta'));
        if ($puerta !== '') {
            $domicilio = trim($domicilio . ' ' . $puerta);
        }
        $piso = trim((string) $this->valor($elegida, 'piso'));
        $depto = trim((string) $this->valor($elegida, 'depto'));
        if ($piso !== '') {
            $domicilio .= ' piso ' . $piso;
        }
        if ($depto !== '') {
            $domicilio .= ' depto ' . $depto;
        }

        $documento = trim((string) $this->valor($elegida, 'tipodoc'));
        $nrodoc = trim((string) $this->valor($elegida, 'nrodoc'));
        if ($nrodoc !== '' && $nrodoc !== '0') {
            $documento = trim($documento . ' ' . $nrodoc);
        }

        return array(
            'encontrada' => true,
            'message'    => '',
            'nombre'     => trim((string) $this->valor($elegida, 'nombre')),
            'afiliado'   => $nro,
            'obrasoc'    => trim((string) $this->valor($elegida, 'obrasoc')),
            'plan'       => trim((string) $this->valor($elegida, 'plan')),
            'sexo'       => $this->etiquetaSexo($this->valor($elegida, 'sexo')),
            'nacimiento' => $this->fechaCorta($this->valor($elegida, 'nacimiento')),
            'documento'  => $documento,
            'domicilio'  => trim($domicilio),
            'localidad'  => trim((string) $this->valor($elegida, 'localidad')),
            'telefono'   => trim((string) $this->valor($elegida, 'telefono')),
            'email'      => trim((string) $this->valor($elegida, 'email')),
            'estado'     => trim((string) $this->valor($elegida, 'estado')),
            'enrol1'     => trim((string) $this->valor($elegida, 'enrol1')),
            'periodo'    => trim((string) $this->valor($elegida, 'periodo')),
            'vinculo'    => $this->etiquetaVinculo($this->valor($elegida, 'codadh')),
        );
    }

    public function getHistoriaClinicaAfiliado($obrasoc, $nroAfiliado)
    {
        $nro = $this->limpiar($nroAfiliado, 20);
        $obra = strtoupper(trim((string) $obrasoc));
        if ($nro === '' || $obra === '') {
            return array(
                'afiliado'        => array('encontrada' => false, 'afiliado' => $nro),
                'consumos'        => array(),
                'total_registros' => 0,
                'message'         => 'Seleccione la obra social y el afiliado.',
            );
        }

        $ficha = $this->getFichaAfiliado($obra, $nro);
        if (empty($ficha['encontrada'])) {
            $ficha = $this->cabeceraDesdeConsumo($obra, $nro);
        }
        $ficha['edad'] = $this->edadTexto(isset($ficha['nacimiento']) ? $ficha['nacimiento'] : '');
        $consumos = $this->leerConsumosAfiliado($obra, $nro);

        $mensaje = '';
        if (count($consumos) === 0 && empty($ficha['encontrada'])) {
            $mensaje = 'No hay consumos ni ficha para el afiliado ' . $nro . '.';
        }

        return array(
            'afiliado'        => $ficha,
            'consumos'        => $consumos,
            'total_registros' => count($consumos),
            'message'         => $mensaje,
        );
    }

    private function leerConsumosAfiliado($obra, $nro)
    {
        $sql = "SELECT id,
                       TRIM(COPERIODO) AS periodo,
                       COFECCONS AS fecha,
                       TRIM(COCODPREST) AS practica,
                       TRIM(CONOMBPRES) AS descripcion,
                       TRIM(COPRESTADO) AS prestador_codigo,
                       TRIM(CONOMPREST) AS prestador,
                       TRIM(CONOMDERIV) AS derivante,
                       COCANTPRES AS cantidad,
                       COIMPORTE AS importe,
                       COFACTURAD AS facturado,
                       TRIM(CODIAGNO) AS coddiag,
                       TRIM(CONOMDIAG) AS diagnostico,
                       TRIM(CONOMMOT) AS motivo,
                       TRIM(CODEBITO) AS debito,
                       CODEBITADO AS importe_debito,
                       TRIM(CONOMDEBI) AS motivo_debito,
                       TRIM(COLEYEDEB) AS leyenda_debito,
                       TRIM(COAUTORIZ) AS autorizacion,
                       TRIM(COPLAN) AS plan,
                       TRIM(COUSUARIO) AS usuario,
                       COFECHACAR AS fecha_carga,
                       FECINIINT AS inicio,
                       FECOUTINT AS fin,
                       CODIASINT AS dias,
                       TRIM(REFACNRO) AS refactura
                  FROM vfpmedicos.detconsu
                 WHERE COOBRASOC = :obra
                   AND COAFILIADO = :nro"
             . $this->sqlExcluirImputaciones()
             . " ORDER BY COPERIODO DESC, COFECCONS DESC, id DESC";
        $filas = $this->consultar($sql, array(
            ':obra' => $obra,
            ':nro'  => $nro,
        ));

        $salida = array();
        foreach ($filas as $fila) {
            $periodo = trim((string) $this->valor($fila, 'periodo'));
            $debito = trim((string) $this->valor($fila, 'debito'));
            $motivoDebito = trim((string) $this->valor($fila, 'motivo_debito'));
            if ($motivoDebito === '') {
                $motivoDebito = trim((string) $this->valor($fila, 'leyenda_debito'));
            }
            $importeDebito = round((float) $this->valor($fila, 'importe_debito'), 2);
            $refactura = trim((string) $this->valor($fila, 'refactura'));
            $diagnostico = trim((string) $this->valor($fila, 'diagnostico'));
            if ($diagnostico === '') {
                $diagnostico = trim((string) $this->valor($fila, 'motivo'));
            }
            if ($diagnostico === '') {
                $diagnostico = trim((string) $this->valor($fila, 'coddiag'));
            }
            $prestador = trim((string) $this->valor($fila, 'prestador'));
            $codigoPrestador = trim((string) $this->valor($fila, 'prestador_codigo'));
            if ($prestador === '') {
                $prestador = $codigoPrestador;
            }
            $dias = (int) $this->valor($fila, 'dias');
            $inicio = $this->fechaCorta($this->valor($fila, 'inicio'));
            $fin = $this->fechaCorta($this->valor($fila, 'fin'));
            $marca = strtotime(trim((string) $this->valor($fila, 'fecha')));
            $salida[] = array(
                'periodo'        => $periodo,
                'fecha'          => $this->fechaCorta($this->valor($fila, 'fecha')),
                'practica'       => trim((string) $this->valor($fila, 'practica')),
                'descripcion'    => trim((string) $this->valor($fila, 'descripcion')),
                'prestador'      => $prestador,
                'derivante'      => trim((string) $this->valor($fila, 'derivante')),
                'cantidad'       => (int) $this->valor($fila, 'cantidad'),
                'importe'        => round((float) $this->valor($fila, 'importe'), 2),
                'facturado'      => round((float) $this->valor($fila, 'facturado'), 2),
                'diagnostico'    => $diagnostico,
                'debito'         => $debito,
                'motivo_debito'  => $motivoDebito,
                'autorizacion'   => trim((string) $this->valor($fila, 'autorizacion')),
                'internacion'    => $this->textoInternacion($dias, $inicio, $fin),
                'plan'           => trim((string) $this->valor($fila, 'plan')),
                'usuario'        => trim((string) $this->valor($fila, 'usuario')),
                'fecha_carga'    => $this->fechaCorta($this->valor($fila, 'fecha_carga')),
                'es_debito'      => $this->esDebito($debito, $importeDebito, $motivoDebito),
                'es_refactura'   => $this->tieneMarca($refactura),
                'periodo_orden'  => $this->ordenPeriodo($periodo),
                'fecha_orden'    => ($marca === false) ? 0 : (int) date('Ymd', $marca),
            );
        }

        return $salida;
    }

    private function cabeceraDesdeConsumo($obra, $nro)
    {
        $sql = "SELECT TRIM(CONOMPAC) AS nombre,
                       TRIM(COSEXO) AS sexo,
                       COFECNAC AS nacimiento,
                       TRIM(COPLAN) AS plan,
                       TRIM(DNIAFIL) AS documento
                  FROM vfpmedicos.detconsu
                 WHERE COOBRASOC = :obra
                   AND COAFILIADO = :nro
                 ORDER BY COPERIODO DESC, id DESC
                 LIMIT 1";
        $filas = $this->consultar($sql, array(
            ':obra' => $obra,
            ':nro'  => $nro,
        ));
        if (count($filas) === 0) {
            return array(
                'encontrada' => false,
                'afiliado'   => $nro,
                'nombre'     => '',
                'obrasoc'    => $obra,
                'plan'       => '',
                'sexo'       => '',
                'nacimiento' => '',
                'documento'  => '',
                'domicilio'  => '',
                'localidad'  => '',
                'telefono'   => '',
            );
        }

        $fila = $filas[0];
        return array(
            'encontrada' => true,
            'message'    => '',
            'nombre'     => trim((string) $this->valor($fila, 'nombre')),
            'afiliado'   => $nro,
            'obrasoc'    => $obra,
            'plan'       => trim((string) $this->valor($fila, 'plan')),
            'sexo'       => $this->etiquetaSexo($this->valor($fila, 'sexo')),
            'nacimiento' => $this->fechaCorta($this->valor($fila, 'nacimiento')),
            'documento'  => trim((string) $this->valor($fila, 'documento')),
            'domicilio'  => '',
            'localidad'  => '',
            'telefono'   => '',
            'vinculo'    => '',
        );
    }

    private function textoInternacion($dias, $inicio, $fin)
    {
        $partes = array();
        if ($dias > 0) {
            $partes[] = $dias . ($dias === 1 ? ' día' : ' días');
        }
        if ($inicio !== '') {
            $rango = $inicio;
            if ($fin !== '' && $fin !== $inicio) {
                $rango .= ' – ' . $fin;
            }
            $partes[] = $rango;
        }
        if (count($partes) === 0) {
            return '';
        }
        return implode(' · ', $partes);
    }

    private function esDebito($codigo, $importe, $motivo)
    {
        if (trim((string) $motivo) !== '') {
            return true;
        }
        if ((float) $importe > 0) {
            return true;
        }
        return $this->tieneMarca($codigo);
    }

    private function tieneMarca($texto)
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return false;
        }
        if (preg_match('/^0+$/', $texto)) {
            return false;
        }
        return true;
    }

    private function edadTexto($fecha)
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }
        $dt = DateTime::createFromFormat('d/m/Y', $fecha);
        if (!$dt) {
            $marca = strtotime($fecha);
            if ($marca === false) {
                return '';
            }
            $dt = new DateTime(date('Y-m-d', $marca));
        }
        $hoy = new DateTime('today');
        if ($dt > $hoy) {
            return '';
        }
        $anios = $dt->diff($hoy)->y;
        return $anios . ($anios === 1 ? ' año' : ' años');
    }

    private function consultar($sql, $params)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            return array();
        }
        return $filas;
    }

    private function empaquetar($filas, $filtro, $tipo)
    {
        $salida = array();
        $casos = 0;
        $importe = 0;

        foreach ($filas as $fila) {
            if ($tipo === 'detalle') {
                $motivo = trim((string) $this->valor($fila, 'motivo'));
                $diagnostico = trim((string) $this->valor($fila, 'diagnostico'));
                $texto = $motivo;
                if ($diagnostico !== '' && $diagnostico !== $motivo) {
                    if ($texto === '') {
                        $texto = $diagnostico;
                    } else {
                        $texto .= ' — ' . $diagnostico;
                    }
                }
                if ($texto === '') {
                    $texto = 'Sin motivo informado';
                }
                $monto = round((float) $this->valor($fila, 'importe'), 2);
                $marca = strtotime(trim((string) $this->valor($fila, 'fecha')));
                $salida[] = array(
                    'fecha'   => $this->fechaCorta($this->valor($fila, 'fecha')),
                    'motivo'  => $texto,
                    'importe' => $monto,
                    'periodo' => trim((string) $this->valor($fila, 'periodo')),
                    'orden'   => ($marca === false) ? 0 : (int) date('Ymd', $marca),
                );
                $casos++;
                $importe += $monto;
                continue;
            }

            $cantidad = (int) $this->valor($fila, 'casos');
            $monto = round((float) $this->valor($fila, 'importe'), 2);
            $item = array(
                'casos'   => $cantidad,
                'importe' => $monto,
            );

            if ($tipo === 'afiliado' || isset($fila['afiliado']) || isset($fila['AFILIADO'])) {
                if ($this->valor($fila, 'afiliado') !== null) {
                    $item['afiliado'] = trim((string) $this->valor($fila, 'afiliado'));
                    $item['nombre'] = trim((string) $this->valor($fila, 'nombre'));
                }
            }
            if ($this->valor($fila, 'prestador') !== null && $tipo !== 'detalle') {
                $item['prestador'] = trim((string) $this->valor($fila, 'prestador'));
                if (!isset($item['nombre']) || $tipo === 'prestador') {
                    $item['nombre'] = trim((string) $this->valor($fila, 'nombre'));
                }
            }
            if ($this->valor($fila, 'practica') !== null) {
                $item['practica'] = trim((string) $this->valor($fila, 'practica'));
                $item['descripcion'] = trim((string) $this->valor($fila, 'descripcion'));
            }
            if ($tipo === 'prestador' && $this->valor($fila, 'prestador') !== null && $this->valor($fila, 'practica') === null) {
                $item['nombre'] = trim((string) $this->valor($fila, 'nombre'));
            }

            $salida[] = $item;
            $casos += $cantidad;
            $importe += $monto;
        }

        return array(
            'filas'         => $salida,
            'total_filas'   => count($salida),
            'total_casos'   => $casos,
            'total_importe' => round($importe, 2),
            'desde'         => $filtro['desde'],
            'hasta'         => $filtro['hasta'],
        );
    }

    private function sqlExcluirImputaciones()
    {
        return " AND COCODPREST IS NOT NULL "
             . " AND TRIM(COCODPREST) != '' "
             . " AND TRIM(COCODPREST) NOT REGEXP '^[0-9]{3}$' ";
    }

    private function filtroBase($obrasoc, $periodoDesde, $periodoHasta)
    {
        $desde = trim((string) $periodoDesde);
        $hasta = trim((string) $periodoHasta);
        if ($this->ordenPeriodo($desde) > $this->ordenPeriodo($hasta)) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }

        $sql = ' WHERE COPERIODO >= :desde AND COPERIODO <= :hasta ';
        $params = array(
            ':desde' => $desde,
            ':hasta' => $hasta,
        );
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra !== '') {
            $sql .= ' AND COOBRASOC = :obra ';
            $params[':obra'] = $obra;
        }
        $sql .= $this->sqlExcluirImputaciones();

        return array(
            'sql'    => $sql,
            'params' => $params,
            'desde'  => $desde,
            'hasta'  => $hasta,
        );
    }

    private function elegirFicha($filas, $obra)
    {
        if (!is_array($filas) || count($filas) === 0) {
            return null;
        }

        $mejor = null;
        $ordenMejor = -1;
        foreach ($filas as $fila) {
            $obraFila = strtoupper(trim((string) $this->valor($fila, 'obrasoc')));
            if ($obra !== '' && $obraFila !== $obra) {
                continue;
            }
            $orden = $this->ordenPeriodo($this->valor($fila, 'periodo'));
            if ($mejor === null || $orden > $ordenMejor) {
                $mejor = $fila;
                $ordenMejor = $orden;
            }
        }

        if ($mejor === null) {
            foreach ($filas as $fila) {
                $orden = $this->ordenPeriodo($this->valor($fila, 'periodo'));
                if ($mejor === null || $orden > $ordenMejor) {
                    $mejor = $fila;
                    $ordenMejor = $orden;
                }
            }
        }

        return $mejor;
    }

    private function etiquetaSexo($sexo)
    {
        $sexo = strtoupper(trim((string) $sexo));
        if ($sexo === '1' || $sexo === 'M' || $sexo === 'H' || $sexo === 'V') {
            return 'Masculino';
        }
        if ($sexo === '2' || $sexo === 'F') {
            return 'Femenino';
        }
        if ($sexo === '') {
            return '';
        }
        return 'Otros';
    }

    private function etiquetaVinculo($codigo)
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === 'ADH') {
            return 'Adherente';
        }
        if ($codigo === '' || $codigo === '0' || $codigo === '00' || $codigo === '000' || $codigo === 'TIT') {
            return 'Titular';
        }
        return 'Adherente';
    }

    private function fechaCorta($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return '';
        }
        $marca = strtotime($valor);
        if ($marca === false || (int) date('Y', $marca) < 1901) {
            return '';
        }
        return date('d/m/Y', $marca);
    }

    private function periodoValido($periodo)
    {
        return preg_match('/^\d{2}\/\d{2}$/', trim((string) $periodo)) === 1;
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

    private function compararPeriodoDesc($a, $b)
    {
        $ordenA = $this->ordenPeriodo($a);
        $ordenB = $this->ordenPeriodo($b);
        if ($ordenA === $ordenB) {
            return 0;
        }
        if ($ordenA < $ordenB) {
            return 1;
        }
        return -1;
    }

    private function limpiar($valor, $maximo)
    {
        $valor = trim((string) $valor);
        if (strlen($valor) > $maximo) {
            $valor = substr($valor, 0, $maximo);
        }
        return $valor;
    }

    private function valor($fila, $clave)
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
}
