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
        $asunto = isset($_POST['asunto']) ? trim((string) $_POST['asunto']) : '';
        $mensaje = isset($_POST['mensaje']) ? trim((string) $_POST['mensaje']) : '';
        if (function_exists('mb_substr')) {
            $asunto = mb_substr($asunto, 0, 255, 'UTF-8');
        } elseif (strlen($asunto) > 255) {
            $asunto = substr($asunto, 0, 255);
        }
        if (strlen($mensaje) > 100000) {
            $mensaje = substr($mensaje, 0, 100000);
        }

        $lista = $this->partirCorreosInforme($destinatarios);
        if (isset($lista['error'])) {
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $lista['error']);
            $this->json(array(
                'status'  => 'error',
                'message' => $lista['error'],
            ));
        }
        if ($asunto === '' || $mensaje === '') {
            $detalle = 'El asunto y el mensaje son obligatorios.';
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $detalle);
            $this->json(array(
                'status'  => 'error',
                'message' => $detalle,
            ));
        }

        $adjunto = $this->leerAdjuntoCorreo();
        if (isset($adjunto['error'])) {
            $this->registrarEnvioInforme($destinatarios, $asunto, 'ERROR', $adjunto['error']);
            $this->json(array(
                'status'  => 'error',
                'message' => $adjunto['error'],
            ));
        }

        $resultado = $this->enviarInformeSmtp($lista, $asunto, $mensaje, $adjunto);
        $estado = !empty($resultado['ok']) ? 'ENVIADO' : 'ERROR';
        $detalle = isset($resultado['texto']) ? $resultado['texto'] : '';
        $this->registrarEnvioInforme(implode(', ', $lista), $asunto, $estado, $detalle);

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

    private function partirCorreosInforme($texto)
    {
        $partes = preg_split('/[;,]+/', (string) $texto);
        $lista = array();
        $vistos = array();
        if (!is_array($partes)) {
            return array('error' => 'Escribí al menos un destinatario.');
        }
        foreach ($partes as $parte) {
            $correo = trim((string) $parte);
            if ($correo === '') {
                continue;
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return array('error' => 'El destinatario "' . $correo . '" no es un correo válido.');
            }
            $clave = strtolower($correo);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $lista[] = $correo;
            if (count($lista) > 10) {
                return array('error' => 'Podés enviar el informe a 10 destinatarios como máximo.');
            }
        }
        if (count($lista) === 0) {
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

    private function enviarInformeSmtp($lista, $asunto, $mensaje, $adjunto)
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
            $mail->Timeout = 30;
            $mail->SMTPSecure = ((int) $cfg['puerto'] === 465) ? 'ssl' : 'tls';
            $mail->setFrom((string) $cfg['remitente_email'], (string) $cfg['remitente_nombre']);
            foreach ($lista as $correo) {
                $mail->addAddress($correo);
            }
            $yaCopia = false;
            foreach ($lista as $correo) {
                if (strtolower($correo) === $bcc) {
                    $yaCopia = true;
                }
            }
            if (!$yaCopia) {
                $mail->addBCC($bcc);
            }
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $cuerpo = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#1e293b;">'
                . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'))
                . '</div>';
            $mail->Body = $cuerpo;
            $mail->AltBody = $mensaje;
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
