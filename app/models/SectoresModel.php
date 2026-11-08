<?php
/**
 * Control de sectores. Todo se lee de la base cartilla.
 *
 * Ambulatorio → cartilla.ordenes
 * Sanatorial → cartilla.sanorden
 * Medicamentos → cartilla.ordenes_medicamentos
 *
 * Cargadas: una fila por COFECHA.
 * Autorizadas o rechazadas: COFECAUTO y COESTADO AUTORIZADA / RECHAZADA.
 *
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../../config/database.php';

class SectoresModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    public function rangoSugerido()
    {
        $maximo = '';
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            $sql = 'SELECT MAX(COFECHA) AS maximo FROM ' . $fuente['tabla'];
            $stmt = $this->db->query($sql);
            $fila = $stmt->fetch();
            $valor = ($fila && isset($fila['maximo'])) ? substr((string) $fila['maximo'], 0, 10) : '';
            if ($valor !== '' && $valor > $maximo) {
                $maximo = $valor;
            }
        }

        if ($maximo === '') {
            $maximo = date('Y-m-d');
        }

        $desde = date('Y-m-d', strtotime($maximo . ' -13 days'));

        return array(
            'desde' => $desde,
            'hasta' => $maximo,
        );
    }

    public function resumenCargadas($desde, $hastaExclusivo)
    {
        $mapa = array();
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            $sql = 'SELECT DATE(COFECHA) AS dia, COUNT(*) AS total
                      FROM ' . $fuente['tabla'] . '
                     WHERE COFECHA >= :desde AND COFECHA < :hasta
                     GROUP BY DATE(COFECHA)';
            $filas = $this->consultar($sql, array(
                ':desde' => $desde . ' 00:00:00',
                ':hasta' => $hastaExclusivo . ' 00:00:00',
            ));
            foreach ($filas as $fila) {
                $dia = substr((string) $fila['dia'], 0, 10);
                if (!isset($mapa[$dia])) {
                    $mapa[$dia] = $this->filaCargadaVacia($dia);
                }
                $cantidad = (int) $fila['total'];
                $mapa[$dia][$fuente['clave']] = $cantidad;
                $mapa[$dia]['total'] += $cantidad;
            }
        }

        ksort($mapa);
        return array_values($mapa);
    }

    public function resumenEstados($desde, $hastaExclusivo)
    {
        $mapa = array();
        $fuentes = $this->fuentes();
        $rango = array(
            ':desde' => $desde . ' 00:00:00',
            ':hasta' => $hastaExclusivo . ' 00:00:00',
        );
        foreach ($fuentes as $fuente) {
            if ($fuente['clave'] === 'medicamentos') {
                continue;
            }
            $sql = "SELECT DATE(COFECAUTO) AS dia,
                           COUNT(*) AS autorizadas,
                           SUM(CASE WHEN CONROAUTO IS NULL OR TRIM(CONROAUTO) = '' THEN 1 ELSE 0 END) AS sin_numero
                      FROM " . $fuente['tabla'] . "
                     WHERE COFECAUTO >= :desde AND COFECAUTO < :hasta
                       AND UPPER(TRIM(COESTADO)) = 'AUTORIZADA'
                     GROUP BY DATE(COFECAUTO)";
            $filas = $this->consultar($sql, $rango);
            foreach ($filas as $fila) {
                $dia = substr((string) $fila['dia'], 0, 10);
                if (!isset($mapa[$dia])) {
                    $mapa[$dia] = $this->filaEstadoVacia($dia);
                }
                $cantidad = (int) $fila['autorizadas'];
                $mapa[$dia][$fuente['clave']] = $cantidad;
                $mapa[$dia]['total'] += $cantidad;
                $mapa[$dia]['sin_numero'] += (int) $fila['sin_numero'];
            }

            $sqlRechazo = "SELECT DATE(COFECHA) AS dia, COUNT(*) AS rechazadas
                             FROM " . $fuente['tabla'] . "
                            WHERE COFECHA >= :desde AND COFECHA < :hasta
                              AND UPPER(TRIM(COESTADO)) = 'RECHAZADA'
                            GROUP BY DATE(COFECHA)";
            $rechazos = $this->consultar($sqlRechazo, $rango);
            foreach ($rechazos as $fila) {
                $dia = substr((string) $fila['dia'], 0, 10);
                if (!isset($mapa[$dia])) {
                    $mapa[$dia] = $this->filaEstadoVacia($dia);
                }
                $cantidad = (int) $fila['rechazadas'];
                $mapa[$dia]['rechazadas'] += $cantidad;
                $mapa[$dia]['total'] += $cantidad;
            }

            if ($fuente['detalle'] === '') {
                continue;
            }
            $sqlPracticas = "SELECT DATE(o.COFECAUTO) AS dia, COUNT(*) AS practicas
                               FROM " . $fuente['tabla'] . " o
                               INNER JOIN " . $fuente['detalle'] . " d
                                 ON d.CONUMERO = LPAD(TRIM(o.CONROAUTO), 10, '0')
                              WHERE o.COFECAUTO >= :desde AND o.COFECAUTO < :hasta
                                AND UPPER(TRIM(o.COESTADO)) = 'AUTORIZADA'
                                AND TRIM(o.CONROAUTO) <> ''
                              GROUP BY DATE(o.COFECAUTO)";
            $practicas = $this->consultar($sqlPracticas, $rango);
            foreach ($practicas as $fila) {
                $dia = substr((string) $fila['dia'], 0, 10);
                if (!isset($mapa[$dia])) {
                    $mapa[$dia] = $this->filaEstadoVacia($dia);
                }
                $mapa[$dia]['practicas'] += (int) $fila['practicas'];
            }
        }

        ksort($mapa);
        return array_values($mapa);
    }

    public function explosionCargadas($dia, $origen)
    {
        $filas = array();
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            if ($origen !== '' && $origen !== $fuente['clave']) {
                continue;
            }
            $parte = $this->leerOrdenes($fuente, 'COFECHA', $dia, '', false);
            foreach ($parte as $fila) {
                $fila['origen_clave'] = $fuente['clave'];
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    public function explosionEstado($dia, $estado, $origen)
    {
        $filas = array();
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            if ($fuente['clave'] === 'medicamentos') {
                continue;
            }
            if ($origen !== '' && $origen !== $fuente['clave']) {
                continue;
            }
            $partes = array();
            if ($estado === 'RECHAZADA') {
                $partes[] = $this->leerOrdenes($fuente, 'COFECHA', $dia, 'RECHAZADA', true);
            } elseif ($estado === 'AMBAS') {
                $partes[] = $this->leerOrdenes($fuente, 'COFECAUTO', $dia, 'AUTORIZADA', true);
                $partes[] = $this->leerOrdenes($fuente, 'COFECHA', $dia, 'RECHAZADA', true);
            } else {
                $partes[] = $this->leerOrdenes($fuente, 'COFECAUTO', $dia, $estado, true);
            }
            foreach ($partes as $parte) {
                foreach ($parte as $fila) {
                    $fila['origen_clave'] = $fuente['clave'];
                    $filas[] = $fila;
                }
            }
        }

        return $filas;
    }

    public function practicasDeOrden($origen, $conroauto)
    {
        $detalle = '';
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            if ($fuente['clave'] === $origen) {
                $detalle = $fuente['detalle'];
            }
        }
        if ($detalle === '') {
            return array();
        }

        $nro = str_pad(trim($conroauto), 10, '0', STR_PAD_LEFT);
        $sql = "SELECT TRIM(COCODPREST) AS cocodprest,
                       TRIM(CONOMBPRES) AS conombpres
                  FROM " . $detalle . "
                 WHERE TRIM(CONUMERO) = :nro
                 ORDER BY COCODPREST, CONOMBPRES";

        return $this->consultar($sql, array(':nro' => $nro));
    }

    private function leerOrdenes($fuente, $campoFecha, $dia, $estado, $conPracticas)
    {
        $practicas = '';
        if ($conPracticas && $fuente['detalle'] !== '') {
            $practicas = ", TRIM(CONROAUTO) AS conroauto,
                       (SELECT COUNT(*)
                          FROM " . $fuente['detalle'] . " d
                         WHERE TRIM(d.CONUMERO) = LPAD(TRIM(CONROAUTO), 10, '0')) AS practicas";
        } elseif ($conPracticas) {
            $practicas = ", TRIM(CONROAUTO) AS conroauto, 0 AS practicas";
        }

        $sql = "SELECT DATE_FORMAT(" . $campoFecha . ", '%d/%m/%Y') AS fecha_grupo,
                       DATE_FORMAT(COFECHA, '%d/%m/%Y') AS cofecha,
                       DATE_FORMAT(COFECAUTO, '%d/%m/%Y') AS cofecauto,
                       TRIM(COOBRASOC) AS coobrasoc,
                       TRIM(COPRESTADO) AS coprestado,
                       TRIM(CONOMPREST) AS conomprest,
                       TRIM(COAFILIADO) AS coafiliado,
                       TRIM(CONOMPAC) AS conompac,
                       TRIM(CAST(CONUMERO AS CHAR)) AS numero,
                       TRIM(COESTADO) AS coestado,
                       TRIM(CODIRPDF) AS archivo,
                       :origen AS origen"
                       . $practicas . "
                  FROM " . $fuente['tabla'] . "
                 WHERE " . $campoFecha . " >= :desde AND " . $campoFecha . " < :hasta";

        $params = array(
            ':origen' => $fuente['etiqueta'],
            ':desde'  => $dia . ' 00:00:00',
            ':hasta'  => date('Y-m-d', strtotime($dia . ' +1 day')) . ' 00:00:00',
        );

        if ($estado === 'AMBAS') {
            $sql .= " AND UPPER(TRIM(COESTADO)) IN ('AUTORIZADA', 'RECHAZADA')";
        } elseif ($estado !== '') {
            $sql .= ' AND UPPER(TRIM(COESTADO)) = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY CONOMPAC, COOBRASOC';

        return $this->consultar($sql, $params);
    }

    private function filaEstadoVacia($dia)
    {
        return array(
            'dia'          => $dia,
            'ambulatorio'  => 0,
            'sanatorial'   => 0,
            'medicamentos' => 0,
            'rechazadas'   => 0,
            'practicas'    => 0,
            'sin_numero'   => 0,
            'total'        => 0,
        );
    }

    private function filaCargadaVacia($dia)
    {
        return array(
            'dia'          => $dia,
            'ambulatorio'  => 0,
            'sanatorial'   => 0,
            'medicamentos' => 0,
            'total'        => 0,
        );
    }

    private function fuentes()
    {
        return array(
            array(
                'clave'    => 'ambulatorio',
                'etiqueta' => 'Ambulatorio',
                'tabla'    => 'cartilla.ordenes',
                'detalle'  => 'cartilla.detauto',
            ),
            array(
                'clave'    => 'sanatorial',
                'etiqueta' => 'Sanatorial',
                'tabla'    => 'cartilla.sanorden',
                'detalle'  => 'cartilla.sanautod',
            ),
            array(
                'clave'    => 'medicamentos',
                'etiqueta' => 'Medicamentos',
                'tabla'    => 'cartilla.ordenes_medicamentos',
                'detalle'  => '',
            ),
        );
    }

    public function rangoFacturacion()
    {
        $sql = 'SELECT MAX(COFECHACAR) AS maximo FROM vfpmedicos.detconsu WHERE ' . $this->condicionDataEntry();
        $stmt = $this->db->query($sql);
        $fila = $stmt->fetch();
        $maximo = ($fila && !empty($fila['maximo'])) ? substr((string) $fila['maximo'], 0, 10) : '';
        if ($maximo === '') {
            $maximo = date('Y-m-d');
        }

        return array(
            'desde' => date('Y-m-d', strtotime($maximo . ' -13 days')),
            'hasta' => $maximo,
        );
    }

    public function resumenFacturacion($desde, $hastaExclusivo)
    {
        $sql = "SELECT DATE(COFECHACAR) AS dia,
                       TRIM(COUSUARIO) AS usuario,
                       COUNT(*) AS registros
                  FROM vfpmedicos.detconsu
                 WHERE COFECHACAR >= :desde AND COFECHACAR < :hasta
                   AND " . $this->condicionDataEntry() . "
                 GROUP BY DATE(COFECHACAR), TRIM(COUSUARIO)
                 ORDER BY dia, usuario";
        $filas = $this->consultar($sql, array(
            ':desde' => $desde . ' 00:00:00',
            ':hasta' => $hastaExclusivo . ' 00:00:00',
        ));

        $porUsuario = array();
        $porDia = array();
        $columnas = array();
        foreach ($filas as $fila) {
            $dia = substr((string) $fila['dia'], 0, 10);
            $usuario = (string) $fila['usuario'];
            $cantidad = (int) $fila['registros'];
            if (!isset($porUsuario[$usuario])) {
                $porUsuario[$usuario] = array('usuario' => $usuario, 'registros' => 0, 'dias' => 0);
            }
            $porUsuario[$usuario]['registros'] += $cantidad;
            $porUsuario[$usuario]['dias'] += 1;
            if (!isset($porDia[$dia])) {
                $porDia[$dia] = array('dia' => $dia, 'total' => 0, 'cargas' => array());
            }
            $porDia[$dia]['cargas'][$usuario] = $cantidad;
            $porDia[$dia]['total'] += $cantidad;
            $columnas[$usuario] = true;
        }

        $usuarios = array();
        foreach ($porUsuario as $usuario) {
            $ritmo = $this->ritmoJornada($usuario['registros'], $usuario['dias']);
            $usuarios[] = array(
                'usuario'   => $usuario['usuario'],
                'registros' => $usuario['registros'],
                'dias'      => $usuario['dias'],
                'promedio'  => $ritmo['promedio'],
                'por_hora'  => $ritmo['por_hora'],
                'minutos'   => $ritmo['minutos'],
            );
        }
        usort($usuarios, array($this, 'compararRegistros'));

        $nombres = array_keys($columnas);
        sort($nombres);
        ksort($porDia);

        return array(
            'jornada' => array(
                'horas'        => 8,
                'descanso_min' => 60,
                'neta_horas'   => 7,
                'neta_min'     => 420,
            ),
            'usuarios' => $usuarios,
            'columnas' => $nombres,
            'dias'     => array_values($porDia),
        );
    }

    public function detalleFacturacion($desde, $hastaExclusivo, $usuario, $dia)
    {
        if ($dia !== '') {
            $desde = $dia;
            $hastaExclusivo = date('Y-m-d', strtotime($dia . ' +1 day'));
        }

        $where = ' WHERE COFECHACAR >= :desde AND COFECHACAR < :hasta
                     AND ' . $this->condicionDataEntry();
        $params = array(
            ':desde' => $desde . ' 00:00:00',
            ':hasta' => $hastaExclusivo . ' 00:00:00',
        );
        if ($usuario !== '') {
            $where .= ' AND TRIM(COUSUARIO) = :usuario';
            $params[':usuario'] = $usuario;
        }

        $conteo = $this->consultar('SELECT COUNT(*) AS n FROM vfpmedicos.detconsu' . $where, $params);
        $total = ($conteo && isset($conteo[0]['n'])) ? (int) $conteo[0]['n'] : 0;

        $sql = "SELECT DATE_FORMAT(COFECHACAR, '%d/%m/%Y') AS fecha,
                       TRIM(COHORACAR) AS hora,
                       TRIM(COUSUARIO) AS usuario,
                       TRIM(COOBRASOC) AS obra,
                       TRIM(COPRESTADO) AS prestador,
                       TRIM(CONOMPREST) AS nombre_prestador,
                       TRIM(COAFILIADO) AS afiliado,
                       TRIM(CONOMPAC) AS paciente,
                       TRIM(COCODPREST) AS codigo,
                       TRIM(CONOMBPRES) AS practica,
                       COIMPORTE AS importe,
                       TRIM(COPERIODO) AS periodo
                  FROM vfpmedicos.detconsu"
             . $where
             . ' ORDER BY COFECHACAR, COHORACAR, CONOMPAC
                 LIMIT 2000';
        $filas = $this->consultar($sql, $params);
        foreach ($filas as $i => $fila) {
            $filas[$i]['importe'] = number_format((float) $fila['importe'], 2, ',', '.');
        }

        return array(
            'filas'   => $filas,
            'total'   => $total,
            'recorte' => $total > count($filas),
        );
    }

    private function condicionDataEntry()
    {
        return "TRIM(COUSUARIO) <> '' AND UPPER(TRIM(COUSUARIO)) <> 'NOE'";
    }

    private function ritmoJornada($registros, $dias)
    {
        $promedio = $dias > 0 ? $registros / $dias : 0;
        $porHora = $promedio / 7;
        $minutos = $registros > 0 ? ($dias * 420) / $registros : 0;

        return array(
            'promedio' => round($promedio, 1),
            'por_hora' => round($porHora, 1),
            'minutos'  => round($minutos, 1),
        );
    }

    private function compararRegistros($a, $b)
    {
        if ($a['registros'] === $b['registros']) {
            return strcmp($a['usuario'], $b['usuario']);
        }
        return ($a['registros'] > $b['registros']) ? -1 : 1;
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
}
