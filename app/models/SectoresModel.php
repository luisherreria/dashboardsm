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
        foreach ($fuentes as $fuente) {
            $sql = "SELECT DATE(COFECAUTO) AS dia,
                           SUM(CASE WHEN UPPER(TRIM(COESTADO)) = 'AUTORIZADA' THEN 1 ELSE 0 END) AS autorizadas,
                           SUM(CASE WHEN UPPER(TRIM(COESTADO)) = 'RECHAZADA' THEN 1 ELSE 0 END) AS rechazadas
                      FROM " . $fuente['tabla'] . "
                     WHERE COFECAUTO >= :desde AND COFECAUTO < :hasta
                       AND UPPER(TRIM(COESTADO)) IN ('AUTORIZADA', 'RECHAZADA')
                     GROUP BY DATE(COFECAUTO)";
            $filas = $this->consultar($sql, array(
                ':desde' => $desde . ' 00:00:00',
                ':hasta' => $hastaExclusivo . ' 00:00:00',
            ));
            foreach ($filas as $fila) {
                $dia = substr((string) $fila['dia'], 0, 10);
                if (!isset($mapa[$dia])) {
                    $mapa[$dia] = array(
                        'dia'          => $dia,
                        'autorizadas'  => 0,
                        'rechazadas'   => 0,
                    );
                }
                $mapa[$dia]['autorizadas'] += (int) $fila['autorizadas'];
                $mapa[$dia]['rechazadas'] += (int) $fila['rechazadas'];
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
            $parte = $this->leerOrdenes($fuente, 'COFECHA', $dia, '');
            foreach ($parte as $fila) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    public function explosionEstado($dia, $estado)
    {
        $filas = array();
        $fuentes = $this->fuentes();
        foreach ($fuentes as $fuente) {
            $parte = $this->leerOrdenes($fuente, 'COFECAUTO', $dia, $estado);
            foreach ($parte as $fila) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    private function leerOrdenes($fuente, $campoFecha, $dia, $estado)
    {
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
                       :origen AS origen
                  FROM " . $fuente['tabla'] . "
                 WHERE " . $campoFecha . " >= :desde AND " . $campoFecha . " < :hasta";

        $params = array(
            ':origen' => $fuente['etiqueta'],
            ':desde'  => $dia . ' 00:00:00',
            ':hasta'  => date('Y-m-d', strtotime($dia . ' +1 day')) . ' 00:00:00',
        );

        if ($estado !== '') {
            $sql .= ' AND UPPER(TRIM(COESTADO)) = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY CONOMPAC, COOBRASOC';

        return $this->consultar($sql, $params);
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
            ),
            array(
                'clave'    => 'sanatorial',
                'etiqueta' => 'Sanatorial',
                'tabla'    => 'cartilla.sanorden',
            ),
            array(
                'clave'    => 'medicamentos',
                'etiqueta' => 'Medicamentos',
                'tabla'    => 'cartilla.ordenes_medicamentos',
            ),
        );
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
