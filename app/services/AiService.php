<?php
/**
 * Consultas a Gemini para el auditor médico del padrón.
 * Compatible con PHP 5.6. Sin Composer: solo cURL.
 */

class AiService
{
    public function enviarConsulta($historialMensajes, $datosContexto = array(), $archivoAdjunto = null)
    {
        if (!defined('GEMINI_API_KEY')) {
            require_once dirname(dirname(__DIR__)) . '/config/config.php';
        }
        if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
            return array(
                'ok'    => false,
                'texto' => 'La clave de Gemini no está configurada.',
            );
        }

        if (!is_array($datosContexto)) {
            $datosContexto = array();
        }
        if (!is_array($historialMensajes)) {
            $historialMensajes = array();
        }

        $opciones = 0;
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $opciones = JSON_UNESCAPED_UNICODE;
        }
        $contextoJson = json_encode($datosContexto, $opciones);
        if ($contextoJson === false) {
            $contextoJson = '{}';
        }

        $sistema = 'Eres el Auditor Médico Senior y Gerente Financiero de la Obra Social. Analiza estos datos consolidados del corte actual: '
            . $contextoJson
            . '. Detecta desvíos poblacionales, riesgos por envejecimiento, impacto de discapacidad (CUD) y analiza cualquier documento externo adjunto. Sé ejecutivo, directo, sin saludos y responde en HTML simple (<b>, <ul>, <li>, <p>, <table>).';
        $reglas = $this->leerReglasNegocio();
        if ($reglas !== '') {
            $sistema .= ' Respetá estas directivas de negocio en todas las obras sociales: ' . $reglas;
        }

        $contents = array();
        $empezo = false;
        foreach ($historialMensajes as $mensaje) {
            if (!is_array($mensaje)) {
                continue;
            }
            $texto = isset($mensaje['text']) ? trim((string) $mensaje['text']) : '';
            if ($texto === '') {
                continue;
            }
            $rol = (isset($mensaje['role']) && $mensaje['role'] === 'model') ? 'model' : 'user';
            if (!$empezo && $rol !== 'user') {
                continue;
            }
            $empezo = true;
            $contents[] = array(
                'role'  => $rol,
                'parts' => array(array('text' => $texto)),
            );
        }

        if (count($contents) === 0) {
            $contents[] = array(
                'role'  => 'user',
                'parts' => array(array('text' => 'Emití el diagnóstico inicial del corte.')),
            );
        }

        $ultimo = count($contents) - 1;
        if ($contents[$ultimo]['role'] !== 'user') {
            $contents[] = array(
                'role'  => 'user',
                'parts' => array(array('text' => 'Continuá el análisis.')),
            );
            $ultimo = count($contents) - 1;
        }

        $extra = $this->partesAdjunto($archivoAdjunto);
        if (isset($extra['error'])) {
            return array('ok' => false, 'texto' => $extra['error']);
        }
        if (isset($extra['parts']) && is_array($extra['parts'])) {
            foreach ($extra['parts'] as $parte) {
                $contents[$ultimo]['parts'][] = $parte;
            }
        }

        $cuerpo = array(
            'systemInstruction' => array(
                'parts' => array(array('text' => $sistema)),
            ),
            'contents' => $contents,
        );
        $json = json_encode($cuerpo, $opciones);
        if ($json === false) {
            return array('ok' => false, 'texto' => 'No se pudo armar la consulta.');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $ch = curl_init($url);
        if ($ch === false) {
            return array('ok' => false, 'texto' => 'No se pudo iniciar la conexión con Gemini.');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $respuesta = curl_exec($ch);
        $errorRed = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false || $errorRed !== '') {
            return array('ok' => false, 'texto' => 'Gemini no respondió a tiempo.');
        }

        $data = json_decode($respuesta, true);
        if (!is_array($data)) {
            return array('ok' => false, 'texto' => 'La respuesta de Gemini no se pudo leer.');
        }
        if ($codigo >= 400 || isset($data['error'])) {
            $mensaje = 'Gemini rechazó la consulta.';
            if (isset($data['error']['message'])) {
                $mensaje = (string) $data['error']['message'];
            }
            return array('ok' => false, 'texto' => $mensaje);
        }

        $texto = '';
        if (isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $parte) {
                if (isset($parte['text'])) {
                    $texto .= (string) $parte['text'];
                }
            }
        }
        $texto = trim($texto);
        if ($texto === '') {
            return array('ok' => false, 'texto' => 'Gemini no devolvió un diagnóstico.');
        }

        return array(
            'ok'    => true,
            'texto' => $this->htmlSeguro($texto),
        );
    }

    private function partesAdjunto($archivoAdjunto)
    {
        if (!is_array($archivoAdjunto) || empty($archivoAdjunto['ruta'])) {
            return array('parts' => array());
        }

        $ruta = (string) $archivoAdjunto['ruta'];
        $extension = isset($archivoAdjunto['extension']) ? strtolower((string) $archivoAdjunto['extension']) : '';
        $nombre = isset($archivoAdjunto['nombre']) ? (string) $archivoAdjunto['nombre'] : 'adjunto';
        if (!is_file($ruta)) {
            return array('error' => 'No se encontró el archivo adjunto.');
        }

        $texto = array('csv', 'txt');
        if (in_array($extension, $texto, true)) {
            $contenido = file_get_contents($ruta);
            if ($contenido === false) {
                return array('error' => 'No se pudo leer el archivo de texto.');
            }
            if (strlen($contenido) > 120000) {
                $contenido = substr($contenido, 0, 120000);
            }
            return array(
                'parts' => array(array(
                    'text' => "Documento adjunto (" . $nombre . "):\n" . $contenido,
                )),
            );
        }

        $mime = 'application/octet-stream';
        if ($extension === 'pdf') {
            $mime = 'application/pdf';
        } elseif ($extension === 'png') {
            $mime = 'image/png';
        } elseif ($extension === 'jpg' || $extension === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($extension === 'webp') {
            $mime = 'image/webp';
        } elseif ($extension === 'gif') {
            $mime = 'image/gif';
        } else {
            return array('error' => 'El adjunto debe ser PDF, CSV, TXT o una imagen.');
        }

        $binario = file_get_contents($ruta);
        if ($binario === false) {
            return array('error' => 'No se pudo leer el documento adjunto.');
        }

        return array(
            'parts' => array(array(
                'inline_data' => array(
                    'mime_type' => $mime,
                    'data'      => base64_encode($binario),
                ),
            )),
        );
    }

    private function htmlSeguro($html)
    {
        $html = (string) $html;
        $html = preg_replace('/```html/i', '', $html);
        $html = preg_replace('/```/', '', $html);
        $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html);
        return strip_tags($html, '<b><strong><ul><ol><li><p><table><thead><tbody><tr><td><th><br><em><h3><h4>');
    }

    private function leerReglasNegocio()
    {
        $ruta = dirname(dirname(__DIR__)) . '/config/reglas_ia.txt';
        if (!is_file($ruta)) {
            return '';
        }
        $texto = file_get_contents($ruta);
        if ($texto === false) {
            return '';
        }
        if (substr($texto, 0, 3) === "\xEF\xBB\xBF") {
            $texto = substr($texto, 3);
        }
        $texto = trim($texto);
        if (strlen($texto) > 12000) {
            $texto = substr($texto, 0, 12000);
        }
        return $texto;
    }
}
