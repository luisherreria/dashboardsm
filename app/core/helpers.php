<?php
/**
 * Utilidades de vista. PHP 5.6.
 */

function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function dm_iniciales($nombre)
{
    $nombre = trim((string) $nombre);
    if ($nombre === '') {
        return 'U';
    }

    $partes = preg_split('/\s+/', $nombre);
    $iniciales = '';

    if (is_array($partes)) {
        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $iniciales .= mb_substr($parte, 0, 1, 'UTF-8');
            } else {
                $iniciales .= substr($parte, 0, 1);
            }
            if (function_exists('mb_strlen')) {
                $largo = mb_strlen($iniciales, 'UTF-8');
            } else {
                $largo = strlen($iniciales);
            }
            if ($largo >= 2) {
                break;
            }
        }
    }

    if ($iniciales === '') {
        return 'U';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($iniciales, 'UTF-8');
    }

    return strtoupper($iniciales);
}

function dm_fecha_larga($timestamp)
{
    $dias = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $meses = array(
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    );

    $diaSemana = (int) date('w', $timestamp);
    $mes = (int) date('n', $timestamp) - 1;

    return $dias[$diaSemana] . ', ' . date('j', $timestamp) . ' de ' . $meses[$mes] . ' de ' . date('Y', $timestamp);
}

function dm_rol_legible($rol)
{
    if ($rol === 'admin') {
        return 'Administrador';
    }
    if ($rol === 'prestador') {
        return 'Prestador';
    }
    return 'Usuario';
}
