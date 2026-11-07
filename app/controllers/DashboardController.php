<?php
/**
 * Panel posterior al login: evolución de padrones, accesos y administradores.
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../models/PadronModel.php';

class DashboardController extends Controller
{
    public function index()
    {
        $this->evolucionPadrones();
    }

    public function evolucionPadrones()
    {
        $this->requerirLogin();

        $avisoPermiso = isset($_GET['aviso']) && $_GET['aviso'] === 'permiso';
        $obras = array();
        $anios = array();
        $errorCarga = '';

        try {
            $modelo = new PadronModel();
            $obras = $modelo->getObrasSociales();
            $anios = $modelo->getAniosDisponibles();
        } catch (Exception $e) {
            error_log('DashboardController::evolucionPadrones — ' . $e->getMessage());
            $errorCarga = 'No se pudo leer el padrón.';
        }

        $this->render('dashboard/evolucion_padrones', array(
            'pageTitle'    => 'COMEDICA — Evolución de Padrones',
            'menuActivo'   => 'padrones',
            'avisoPermiso' => $avisoPermiso,
            'obras'        => $obras,
            'anios'        => $anios,
            'errorCarga'   => $errorCarga,
            'breadcrumb'   => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Evolución de Padrones'),
            ),
        ));
    }

    public function ajaxGetEvolucionData()
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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        $anios = array();
        if (isset($_POST['anios']) && is_array($_POST['anios'])) {
            $anios = $_POST['anios'];
        } elseif (isset($_POST['anios']) && $_POST['anios'] !== '') {
            $anios = explode(',', (string) $_POST['anios']);
        }

        if (count($anios) > 8) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione hasta 8 años para comparar.',
            ));
        }

        try {
            $modelo = new PadronModel();
            $periodoActivo = $modelo->getUltimoPeriodoReal($obrasoc);
            $respuesta = $modelo->construirRespuesta($obrasoc, $anios);
            if (is_array($respuesta)) {
                $respuesta['periodo_activo'] = $periodoActivo;
            }
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetEvolucionData — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la evolución del padrón.',
            ));
        }

        $this->json($respuesta);
    }

    public function ajaxGetDemografiaYPlanes()
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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        try {
            $modelo = new PadronModel();
            $periodoReal = $modelo->getUltimoPeriodoReal($obrasoc);
            $respuesta = $modelo->getDemografiaYPlanes($obrasoc, $periodoReal);
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetDemografiaYPlanes — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la demografía del padrón.',
            ));
        }

        $this->json($respuesta);
    }

    public function ajaxGetEvolucionEspeciales()
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

        $obrasoc = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        if ($obrasoc !== '' && !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'La obra social no es válida.',
            ));
        }

        $limite = 12;
        if (isset($_POST['limite'])) {
            $limite = (int) $_POST['limite'];
        }

        try {
            $modelo = new PadronModel();
            $datos = $modelo->getEvolucionProgramasEspeciales($obrasoc, $limite);
        } catch (Exception $e) {
            error_log('DashboardController::ajaxGetEvolucionEspeciales — ' . $e->getMessage());
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudo calcular la evolución de los programas especiales.',
            ));
        }

        if (!is_array($datos)) {
            $datos = array();
        }
        $datos['status'] = 'success';
        if (!isset($datos['message'])) {
            $datos['message'] = '';
        }
        $this->json($datos);
    }

    public function ajaxChatAi()
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

        $accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';
        if ($accion === 'reiniciar') {
            unset($_SESSION['ai_chat_history']);
            unset($_SESSION['ai_padron_contexto']);
            $this->json(array(
                'status'  => 'success',
                'message' => 'Conversación reiniciada.',
                'html'    => '',
            ));
        }

        $obrasoc = isset($_POST['obrasoc']) ? strtoupper(trim((string) $_POST['obrasoc'])) : '';
        if ($obrasoc === '' || !preg_match('/^[A-Za-z0-9 .\/\-]{1,12}$/', $obrasoc)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social antes de consultar al auditor.',
            ));
        }

        if (!isset($_SESSION['ai_chat_history']) || !is_array($_SESSION['ai_chat_history'])) {
            $_SESSION['ai_chat_history'] = array();
        }
        $obraContexto = '';
        if (isset($_SESSION['ai_padron_contexto']['obrasoc'])) {
            $obraContexto = (string) $_SESSION['ai_padron_contexto']['obrasoc'];
        }
        if ($obraContexto !== '' && $obraContexto !== $obrasoc) {
            $_SESSION['ai_chat_history'] = array();
            unset($_SESSION['ai_padron_contexto']);
        }

        require_once __DIR__ . '/../services/AiService.php';
        $servicio = new AiService();

        if ($accion === 'iniciar') {
            $_SESSION['ai_chat_history'] = array();
            try {
                $_SESSION['ai_padron_contexto'] = $this->armarContextoAuditoria($obrasoc);
            } catch (Exception $e) {
                error_log('DashboardController::ajaxChatAi iniciar — ' . $e->getMessage());
                $this->json(array(
                    'status'  => 'error',
                    'message' => 'No se pudo armar el contexto del padrón.',
                ));
            }
            $_SESSION['ai_chat_history'][] = array(
                'role' => 'user',
                'text' => 'Emití el diagnóstico inicial del corte.',
            );
        } elseif ($accion === 'mensaje') {
            $pregunta = isset($_POST['mensaje']) ? trim((string) $_POST['mensaje']) : '';
            if ($pregunta === '' && (!isset($_FILES['archivo_adjunto']) || (int) $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_NO_FILE)) {
                $this->json(array(
                    'status'  => 'error',
                    'message' => 'Escriba una consulta o adjunte un documento.',
                ));
            }
            if ($pregunta === '') {
                $pregunta = 'Analizá el documento adjunto en el contexto del padrón.';
            }
            if (!isset($_SESSION['ai_padron_contexto']) || !is_array($_SESSION['ai_padron_contexto'])) {
                try {
                    $_SESSION['ai_padron_contexto'] = $this->armarContextoAuditoria($obrasoc);
                } catch (Exception $e) {
                    error_log('DashboardController::ajaxChatAi mensaje — ' . $e->getMessage());
                    $this->json(array(
                        'status'  => 'error',
                        'message' => 'No se pudo armar el contexto del padrón.',
                    ));
                }
            }
            $_SESSION['ai_chat_history'][] = array(
                'role' => 'user',
                'text' => $pregunta,
            );
        } else {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Acción de chat no válida.',
            ));
        }

        if (count($_SESSION['ai_chat_history']) > 16) {
            $_SESSION['ai_chat_history'] = array_slice($_SESSION['ai_chat_history'], -16);
        }

        $adjunto = $this->leerAdjuntoChat();
        if (isset($adjunto['error'])) {
            array_pop($_SESSION['ai_chat_history']);
            $this->json(array(
                'status'  => 'error',
                'message' => $adjunto['error'],
            ));
        }

        $resultado = $servicio->enviarConsulta(
            $_SESSION['ai_chat_history'],
            $_SESSION['ai_padron_contexto'],
            $adjunto
        );
        if (empty($resultado['ok'])) {
            array_pop($_SESSION['ai_chat_history']);
            $this->json(array(
                'status'  => 'error',
                'message' => isset($resultado['texto']) ? $resultado['texto'] : 'Gemini no respondió.',
            ));
        }

        $_SESSION['ai_chat_history'][] = array(
            'role' => 'model',
            'text' => $resultado['texto'],
        );

        $contexto = $_SESSION['ai_padron_contexto'];
        $this->json(array(
            'status'  => 'success',
            'message' => '',
            'html'    => $resultado['texto'],
            'obra'    => isset($contexto['obrasoc']) ? $contexto['obrasoc'] : $obrasoc,
            'periodo' => isset($contexto['periodo']) ? $contexto['periodo'] : '',
        ));
    }

    private function armarContextoAuditoria($obrasoc)
    {
        $modelo = new PadronModel();
        $periodo = $modelo->getUltimoPeriodoReal($obrasoc);
        $evolucion = $modelo->construirRespuesta($obrasoc, array());
        $demografia = $modelo->getDemografiaYPlanes($obrasoc, $periodo);
        $kpis = isset($evolucion['kpis']) && is_array($evolucion['kpis']) ? $evolucion['kpis'] : array();
        $especiales = array();
        if (isset($evolucion['insights']['especiales']) && is_array($evolucion['insights']['especiales'])) {
            $especiales = $evolucion['insights']['especiales'];
        }

        return array(
            'obrasoc'    => $obrasoc,
            'periodo'    => $periodo,
            'kpis'       => $kpis,
            'especiales' => $especiales,
            'familiar'   => isset($demografia['familiar']) ? $demografia['familiar'] : array(),
            'etario'     => isset($demografia['etario']) ? $demografia['etario'] : array(),
            'planes'     => isset($demografia['planes']) ? $demografia['planes'] : array(),
            'localidades'=> isset($demografia['localidades']) ? $demografia['localidades'] : array(),
        );
    }

    private function leerAdjuntoChat()
    {
        if (!isset($_FILES['archivo_adjunto']) || !is_array($_FILES['archivo_adjunto'])) {
            return null;
        }
        $archivo = $_FILES['archivo_adjunto'];
        $error = isset($archivo['error']) ? (int) $archivo['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            return array('error' => 'No se pudo recibir el archivo.');
        }
        $tamanio = isset($archivo['size']) ? (int) $archivo['size'] : 0;
        if ($tamanio <= 0 || $tamanio > 8388608) {
            return array('error' => 'El archivo debe pesar menos de 8 MB.');
        }
        $nombre = isset($archivo['name']) ? (string) $archivo['name'] : '';
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $permitidas = array('pdf', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif');
        if (!in_array($extension, $permitidas, true)) {
            return array('error' => 'Adjunte un PDF, CSV, TXT o una imagen.');
        }
        $tmp = isset($archivo['tmp_name']) ? (string) $archivo['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return array('error' => 'El archivo adjunto no es válido.');
        }

        return array(
            'nombre'    => $nombre,
            'extension' => $extension,
            'ruta'      => $tmp,
        );
    }

    public function ajaxEnviarInformeAi()
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

        $destinatarios = isset($_POST['destinatarios']) ? trim((string) $_POST['destinatarios']) : '';
        $conCopia = isset($_POST['con_copia']) ? trim((string) $_POST['con_copia']) : '';
        $asunto = isset($_POST['asunto']) ? trim((string) $_POST['asunto']) : '';
        $nota = isset($_POST['nota']) ? trim((string) $_POST['nota']) : '';
        $htmlCrudo = isset($_POST['mensaje_html']) ? (string) $_POST['mensaje_html'] : '';
        $obra = isset($_POST['obrasoc']) ? trim((string) $_POST['obrasoc']) : '';
        $fecha = isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '';
        if (function_exists('mb_substr')) {
            $asunto = mb_substr($asunto, 0, 255, 'UTF-8');
            $nota = mb_substr($nota, 0, 4000, 'UTF-8');
            $obra = mb_substr($obra, 0, 20, 'UTF-8');
        } else {
            if (strlen($asunto) > 255) {
                $asunto = substr($asunto, 0, 255);
            }
            if (strlen($nota) > 4000) {
                $nota = substr($nota, 0, 4000);
            }
            if (strlen($obra) > 20) {
                $obra = substr($obra, 0, 20);
            }
        }
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            $fecha = date('d/m/Y');
        }
        if (strlen($htmlCrudo) > 200000) {
            $htmlCrudo = substr($htmlCrudo, 0, 200000);
        }

        $lista = $this->partirCorreosInforme($destinatarios, true);
        if (isset($lista['error'])) {
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $lista['error']);
            $this->json(array(
                'status'  => 'error',
                'message' => $lista['error'],
            ));
        }
        $copias = $this->partirCorreosInforme($conCopia, false);
        if (isset($copias['error'])) {
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $copias['error']);
            $this->json(array(
                'status'  => 'error',
                'message' => $copias['error'],
            ));
        }
        if ($asunto === '') {
            $detalle = 'El asunto es obligatorio.';
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $detalle);
            $this->json(array(
                'status'  => 'error',
                'message' => $detalle,
            ));
        }

        $informe = $this->htmlInformeCorreo($htmlCrudo);
        if ($informe === '' && $nota === '') {
            $detalle = 'El informe está vacío.';
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $detalle);
            $this->json(array(
                'status'  => 'error',
                'message' => $detalle,
            ));
        }
        $cuerpo = $this->plantillaInformeCorreo($asunto, $obra, $fecha, $nota, $informe);
        $alt = trim($nota . "\n\n" . $this->textoPlanoInforme($informe));

        $adjunto = $this->leerAdjuntoCorreo();
        if (isset($adjunto['error'])) {
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $adjunto['error']);
            $this->json(array(
                'status'  => 'error',
                'message' => $adjunto['error'],
            ));
        }

        $resultado = $this->enviarInformeSmtp($lista, $copias, $asunto, $cuerpo, $alt, $adjunto);
        $estado = !empty($resultado['ok']) ? 'ENVIADO' : 'ERROR';
        $detalle = isset($resultado['texto']) ? $resultado['texto'] : '';
        $registro = implode(', ', $lista);
        if (count($copias) > 0) {
            $registro .= ' | CC: ' . implode(', ', $copias);
        }
        $this->registrarEnvioInforme($registro, $asunto, $estado, $detalle);

        if (empty($resultado['ok'])) {
            $this->json(array(
                'status'  => 'error',
                'message' => $detalle !== '' ? $detalle : 'No se pudo enviar el informe.',
            ));
        }

        $this->json(array(
            'status'  => 'success',
            'message' => 'El informe se envió. Copia oculta a no_responder@comedica.com.ar.',
        ));
    }

    private function partirCorreosInforme($texto, $obligatorio)
    {
        $partes = preg_split('/[;,]+/', (string) $texto);
        $lista = array();
        $vistos = array();
        if (!is_array($partes)) {
            if ($obligatorio) {
                return array('error' => 'Escribí al menos un destinatario.');
            }
            return array();
        }
        foreach ($partes as $parte) {
            $correo = trim((string) $parte);
            if ($correo === '') {
                continue;
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return array('error' => 'El correo "' . $correo . '" no es válido.');
            }
            $clave = strtolower($correo);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $lista[] = $correo;
            if (count($lista) > 10) {
                return array('error' => 'Podés incluir 10 correos como máximo en cada campo.');
            }
        }
        if ($obligatorio && count($lista) === 0) {
            return array('error' => 'Escribí al menos un destinatario.');
        }
        return $lista;
    }

    private function leerAdjuntoCorreo()
    {
        if (!isset($_FILES['archivo_correo']) || !is_array($_FILES['archivo_correo'])) {
            return null;
        }
        $archivo = $_FILES['archivo_correo'];
        $error = isset($archivo['error']) ? (int) $archivo['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            return array('error' => 'No se pudo recibir el adjunto.');
        }
        $tamanio = isset($archivo['size']) ? (int) $archivo['size'] : 0;
        if ($tamanio <= 0 || $tamanio > 8388608) {
            return array('error' => 'El adjunto debe pesar menos de 8 MB.');
        }
        $nombre = isset($archivo['name']) ? basename((string) $archivo['name']) : '';
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $permitidas = array('pdf', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'doc', 'docx', 'xls', 'xlsx');
        if (!in_array($extension, $permitidas, true)) {
            return array('error' => 'El adjunto debe ser PDF, CSV, TXT, imagen o planilla.');
        }
        $tmp = isset($archivo['tmp_name']) ? (string) $archivo['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return array('error' => 'El adjunto no es válido.');
        }
        return array(
            'nombre' => $nombre,
            'ruta'   => $tmp,
        );
    }

    private function enviarInformeSmtp($lista, $copias, $asunto, $cuerpoHtml, $alt, $adjunto)
    {
        $bcc = 'no_responder@comedica.com.ar';
        try {
            $db = Database::getConnection();
            $st = $db->prepare("SELECT host, puerto, usuario, password, remitente_email, remitente_nombre FROM t_config_smtp WHERE metodo_envio = 'SMTPD' LIMIT 1");
            $st->execute();
            $cfg = $st->fetch();
        } catch (Exception $e) {
            error_log('DashboardController::enviarInformeSmtp config — ' . $e->getMessage());
            return array('ok' => false, 'texto' => 'No se pudo leer la configuración de correo.');
        }
        if (!is_array($cfg) || empty($cfg['host']) || empty($cfg['usuario']) || empty($cfg['password']) || empty($cfg['remitente_email'])) {
            return array('ok' => false, 'texto' => 'No hay un servidor SMTPD configurado.');
        }

        $raiz = dirname(dirname(__DIR__));
        require_once $raiz . '/lib/PHPMailer/src/Exception.php';
        require_once $raiz . '/lib/PHPMailer/src/PHPMailer.php';
        require_once $raiz . '/lib/PHPMailer/src/SMTP.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $cfg['host'];
            $mail->Port = (int) $cfg['puerto'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $cfg['usuario'];
            $mail->Password = (string) $cfg['password'];
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Timeout = 30;
            $mail->SMTPSecure = ((int) $cfg['puerto'] === 465) ? 'ssl' : 'tls';
            $mail->setFrom((string) $cfg['remitente_email'], (string) $cfg['remitente_nombre']);
            $usados = array();
            foreach ($lista as $correo) {
                $mail->addAddress($correo);
                $usados[strtolower($correo)] = true;
            }
            if (is_array($copias)) {
                foreach ($copias as $correo) {
                    $clave = strtolower($correo);
                    if (isset($usados[$clave])) {
                        continue;
                    }
                    $mail->addCC($correo);
                    $usados[$clave] = true;
                }
            }
            if (!isset($usados[$bcc])) {
                $mail->addBCC($bcc);
            }
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpoHtml;
            $mail->AltBody = $alt;
            if (is_array($adjunto) && !empty($adjunto['ruta'])) {
                $mail->addAttachment($adjunto['ruta'], $adjunto['nombre']);
            }
            $mail->send();
        } catch (Exception $e) {
            $detalle = $this->limpiarErrorSmtp($e->getMessage(), (string) $cfg['password']);
            error_log('DashboardController::enviarInformeSmtp — ' . $detalle);
            return array('ok' => false, 'texto' => $detalle !== '' ? $detalle : 'El servidor SMTP rechazó el envío.');
        }

        return array('ok' => true, 'texto' => 'Aceptado por el servidor SMTP.');
    }

    private function htmlInformeCorreo($html)
    {
        $html = (string) $html;
        $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html);
        $html = preg_replace('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', '', $html);
        $html = strip_tags($html, '<b><strong><ul><ol><li><p><table><thead><tbody><tr><td><th><br><em><h3><h4>');
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $erroresPrevios = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="raiz-informe">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($erroresPrevios);

        $xpath = new DOMXPath($doc);
        $nodos = $xpath->query('//*[@id="raiz-informe"]');
        $raiz = ($nodos && $nodos->length > 0) ? $nodos->item(0) : null;
        if (!$raiz) {
            return '';
        }

        $estilos = array(
            'p'      => 'margin:0 0 12px;font-family:Arial,sans-serif;font-size:14px;line-height:1.55;color:#1e293b;',
            'ul'     => 'margin:0 0 12px 20px;padding:0;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#1e293b;',
            'ol'     => 'margin:0 0 12px 20px;padding:0;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#1e293b;',
            'li'     => 'margin:0 0 6px;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#1e293b;',
            'h3'     => 'margin:18px 0 8px;font-family:Arial,sans-serif;font-size:16px;line-height:1.3;color:#1e3a8a;border-bottom:2px solid #dbeafe;padding-bottom:4px;',
            'h4'     => 'margin:14px 0 6px;font-family:Arial,sans-serif;font-size:14px;line-height:1.3;color:#1d4ed8;',
            'b'      => 'font-weight:bold;color:#0f172a;',
            'strong' => 'font-weight:bold;color:#0f172a;',
            'em'     => 'font-style:italic;color:#334155;',
            'table'  => 'width:100%;border-collapse:collapse;margin:12px 0 16px;font-family:Arial,sans-serif;font-size:13px;',
            'th'     => 'background:#1d4ed8;color:#ffffff;text-align:left;padding:8px 10px;border:1px solid #1e40af;font-weight:bold;font-family:Arial,sans-serif;font-size:13px;',
            'td'     => 'padding:8px 10px;border:1px solid #cbd5e1;color:#1e293b;vertical-align:top;font-family:Arial,sans-serif;font-size:13px;',
        );
        foreach ($raiz->childNodes as $hijo) {
            $this->aplicarEstilosInforme($hijo, $estilos, 0);
        }

        $filas = $raiz->getElementsByTagName('tr');
        $datos = 0;
        foreach ($filas as $fila) {
            if ($fila->getElementsByTagName('th')->length > 0) {
                continue;
            }
            if ($datos % 2 === 1) {
                foreach ($fila->childNodes as $celda) {
                    if ($celda->nodeType === XML_ELEMENT_NODE && strtolower($celda->nodeName) === 'td') {
                        $celda->setAttribute('style', $celda->getAttribute('style') . 'background:#f8fafc;');
                    }
                }
            }
            $datos++;
        }

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }
        return $salida;
    }

    private function aplicarEstilosInforme($nodo, $estilos, $profundidad)
    {
        if ($profundidad > 40 || $nodo->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        $hijos = array();
        foreach ($nodo->childNodes as $hijo) {
            $hijos[] = $hijo;
        }
        foreach ($hijos as $hijo) {
            $this->aplicarEstilosInforme($hijo, $estilos, $profundidad + 1);
        }
        if ($nodo->hasAttributes()) {
            $quitar = array();
            foreach ($nodo->attributes as $attr) {
                $quitar[] = $attr->nodeName;
            }
            foreach ($quitar as $nombre) {
                $nodo->removeAttribute($nombre);
            }
        }
        $tag = strtolower($nodo->nodeName);
        if (isset($estilos[$tag])) {
            $nodo->setAttribute('style', $estilos[$tag]);
        }
    }

    private function plantillaInformeCorreo($asunto, $obra, $fecha, $nota, $informe)
    {
        $asuntoHtml = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
        $obraHtml = htmlspecialchars($obra !== '' ? $obra : 'Obra social', ENT_QUOTES, 'UTF-8');
        $fechaHtml = htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8');
        $notaHtml = '';
        if ($nota !== '') {
            $notaHtml = '<p style="margin:0 0 16px;padding:12px 14px;background:#eff6ff;border-left:4px solid #1d4ed8;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#1e3a8a;">'
                . nl2br(htmlspecialchars($nota, ENT_QUOTES, 'UTF-8'))
                . '</p>';
        }

        return '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><title>'
            . $asuntoHtml
            . '</title></head><body style="margin:0;padding:0;background:#f1f5f9;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;width:100%;">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:640px;background:#ffffff;border:1px solid #e2e8f0;">'
            . '<tr><td style="background:#1d4ed8;padding:22px 28px;">'
            . '<div style="font-family:Arial,sans-serif;font-size:11px;letter-spacing:1px;color:#bfdbfe;">COMEDICA</div>'
            . '<div style="font-family:Arial,sans-serif;font-size:20px;font-weight:bold;color:#ffffff;margin-top:4px;">Auditor Médico Pro</div>'
            . '<div style="font-family:Arial,sans-serif;font-size:13px;color:#dbeafe;margin-top:6px;">' . $obraHtml . ' · ' . $fechaHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:20px 28px 0;">'
            . '<div style="font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:1.35;color:#0f172a;">' . $asuntoHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px 28px;font-family:Arial,sans-serif;font-size:14px;line-height:1.55;color:#1e293b;">'
            . $notaHtml . $informe
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 28px;font-family:Arial,sans-serif;font-size:11px;line-height:1.4;color:#64748b;">'
            . 'Informe generado por el Auditor Médico Pro. Este mensaje se envía desde una casilla que no recibe respuestas.'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private function textoPlanoInforme($html)
    {
        $texto = str_replace(
            array('<br>', '<br/>', '<br />', '</p>', '</li>', '</h3>', '</h4>', '</tr>', '</div>'),
            array("\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n"),
            (string) $html
        );
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES, 'UTF-8');
        $texto = preg_replace("/[ \t]+\n/", "\n", $texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
        return trim($texto);
    }

    private function limpiarErrorSmtp($texto, $password)
    {
        $texto = (string) $texto;
        if ($password !== '') {
            $texto = str_replace($password, '***', $texto);
        }
        if (strlen($texto) > 2000) {
            $texto = substr($texto, 0, 2000);
        }
        return $texto;
    }

    private function registrarEnvioInforme($destinatarios, $asunto, $estado, $detalle)
    {
        try {
            $db = Database::getConnection();
            $sql = "INSERT INTO t_log_emails (modulo_origen, codigo_plantilla, destinatarios, asunto, fecha, estado, mensaje_servidor)
                    VALUES ('Auditoría IA Padrones', 'INFORME_AUDITORIA', :destinatarios, :asunto, NOW(), :estado, :detalle)";
            $st = $db->prepare($sql);
            $st->execute(array(
                ':destinatarios' => (string) $destinatarios,
                ':asunto'        => (string) $asunto,
                ':estado'        => (string) $estado,
                ':detalle'       => (string) $detalle,
            ));
        } catch (Exception $e) {
            error_log('DashboardController::registrarEnvioInforme — ' . $e->getMessage());
        }
    }

    public function ajaxObtenerReglasIa()
    {
        $this->exigirAjaxPadron();
        $ruta = $this->rutaReglasIa();
        if (!is_file($ruta)) {
            $plantilla = $this->plantillaReglasIa();
            if (file_put_contents($ruta, $plantilla, LOCK_EX) === false) {
                $this->json(array(
                    'status'  => 'error',
                    'message' => 'No se pudo crear el archivo de reglas.',
                ));
            }
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudieron leer las reglas.',
            ));
        }
        $this->json(array(
            'status'    => 'ok',
            'contenido' => $contenido,
        ));
    }

    public function ajaxGuardarReglasIa()
    {
        $this->exigirAjaxPadron();
        if (isset($_POST['contenido'])) {
            $contenido = (string) $_POST['contenido'];
        } else {
            $contenido = $this->leerTxtReglasSubido();
            if (is_array($contenido)) {
                $this->json(array(
                    'status'  => 'error',
                    'message' => $contenido['error'],
                ));
            }
        }
        if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") {
            $contenido = substr($contenido, 3);
        }
        if (strlen($contenido) > 100000) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'Las reglas superan el tamaño permitido.',
            ));
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $contenido)) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'El archivo tiene que ser texto plano.',
            ));
        }
        $contenido = str_replace(array("\r\n", "\r"), "\n", $contenido);
        if (file_put_contents($this->rutaReglasIa(), $contenido, LOCK_EX) === false) {
            $this->json(array(
                'status'  => 'error',
                'message' => 'No se pudieron guardar las reglas.',
            ));
        }
        $this->json(array(
            'status'  => 'ok',
            'mensaje' => 'Reglas actualizadas correctamente.',
        ));
    }

    private function exigirAjaxPadron()
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

    private function rutaReglasIa()
    {
        return dirname(dirname(__DIR__)) . '/config/reglas_ia.txt';
    }

    private function plantillaReglasIa()
    {
        return "Directivas del Auditor Médico Pro\n"
            . "- Respondé en español, de forma ejecutiva y sin saludos.\n"
            . "- Priorizá desvíos de padrón, envejecimiento y CUD (discapacidad).\n"
            . "- No inventes cifras: usá solo el contexto del corte y los documentos adjuntos.\n"
            . "- Si el padrón es inicial, no calcules variación contra un corte inexistente.\n"
            . "- Señalá el peso del CUD sobre el padrón total y el riesgo financiero.\n"
            . "- Cuando armes una tabla, incluí totales y el porcentaje sobre el padrón.\n";
    }

    private function leerTxtReglasSubido()
    {
        if (!isset($_FILES['archivo_reglas']) || !is_array($_FILES['archivo_reglas'])) {
            return array('error' => 'No llegó el texto de las reglas.');
        }
        $archivo = $_FILES['archivo_reglas'];
        $error = isset($archivo['error']) ? (int) $archivo['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return array('error' => 'No se pudo recibir el archivo de reglas.');
        }
        $nombre = isset($archivo['name']) ? (string) $archivo['name'] : '';
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($extension !== 'txt') {
            return array('error' => 'Solo se aceptan archivos .txt.');
        }
        $tamanio = isset($archivo['size']) ? (int) $archivo['size'] : 0;
        if ($tamanio <= 0 || $tamanio > 100000) {
            return array('error' => 'El archivo de reglas supera el tamaño permitido.');
        }
        $tmp = isset($archivo['tmp_name']) ? (string) $archivo['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return array('error' => 'El archivo de reglas no es válido.');
        }
        $texto = file_get_contents($tmp);
        if ($texto === false) {
            return array('error' => 'No se pudo leer el archivo de reglas.');
        }
        return $texto;
    }

    public function administradores()
    {
        $this->requerirLogin();

        if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
            $this->redirigir('index.php?c=dashboard&a=evolucionPadrones&aviso=permiso');
        }

        $listado = array();
        $errorListado = '';

        try {
            $modelo = new UsuarioModel();
            $listado = $modelo->listarAdministradores();
        } catch (Exception $e) {
            error_log('DashboardController::administradores — ' . $e->getMessage());
            $errorListado = 'No se pudo leer el listado de administradores.';
        }

        $this->render('dashboard/administradores', array(
            'pageTitle'    => 'COMEDICA — Administradores',
            'menuActivo'   => 'admin',
            'listado'      => $listado,
            'errorListado' => $errorListado,
            'breadcrumb'   => array(
                array('label' => 'Inicio', 'href' => 'index.php?c=dashboard&a=evolucionPadrones'),
                array('label' => 'Administradores'),
            ),
        ));
    }
}
