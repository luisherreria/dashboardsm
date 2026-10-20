<?php
/**
 * Autenticación contra vfpmedicos.users y vfpmedicos.usuarios_prestadores.
 *
 * users                  → rol admin (usuario o email + contraseña).
 * usuarios_prestadores   → rol prestador (email_login). La contraseña se
 *                          toma de la columna password/clave si existe; si no,
 *                          de vfpmedicos.users cuando el mismo login está ahí.
 * cartilla.ebamp         → categ del prestador.
 * a_permisos             → flags según el tipo de correo que coincida
 *                          (mail_auto, mailcontra, mail_deb, mail_pago).
 * a_permisos_categorias  → ver_pagos de la categoría. El flag efectivo
 *                          exige permiso de correo y de categoría.
 *
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../../config/database.php';

class UsuarioModel
{
    private $db;
    private $columnaClavePrestador = null;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    /**
     * @param string $login
     * @param string $password
     * @return array  ok, motivo (ok|invalidas|bloqueado), usuario
     */
    public function autenticar($login, $password)
    {
        $login = strtolower(trim((string) $login));
        $password = (string) $password;

        if ($login === '' || $password === '') {
            return $this->rechazo('invalidas');
        }

        $admin = $this->buscarAdmin($login);
        if ($admin) {
            if ($this->cuentaAdminBloqueada($admin)) {
                return $this->rechazo('bloqueado');
            }
            $hash = isset($admin['PASSWORD']) ? (string) $admin['PASSWORD'] : '';
            if (!$this->verificarPassword($password, $hash)) {
                return $this->rechazo('invalidas');
            }
            return array(
                'ok'      => true,
                'motivo'  => 'ok',
                'usuario' => $this->armarAdmin($admin),
            );
        }

        $prestador = $this->buscarPrestador($login);
        if (!$prestador) {
            return $this->rechazo('invalidas');
        }

        if (!$this->prestadorActivo($prestador)) {
            return $this->rechazo('bloqueado');
        }

        $hashPrestador = $this->hashPrestador($prestador);
        if ($hashPrestador === '' || !$this->verificarPassword($password, $hashPrestador)) {
            return $this->rechazo('invalidas');
        }

        return array(
            'ok'      => true,
            'motivo'  => 'ok',
            'usuario' => $this->armarPrestador($prestador, $login),
        );
    }

    public function listarAdministradores()
    {
        $sql = "SELECT USER_ID, USERNAME, FIRST_NAME, LAST_NAME, EMAIL, id_rol, ACTIVATED
                  FROM vfpmedicos.users
                 ORDER BY FIRST_NAME, LAST_NAME, USERNAME";
        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            return array();
        }
        return $filas;
    }

    private function rechazo($motivo)
    {
        return array(
            'ok'      => false,
            'motivo'  => $motivo,
            'usuario' => null,
        );
    }

    private function buscarAdmin($login)
    {
        $porUsuario = $this->buscarAdminPorCampo('USERNAME', $login);
        if ($porUsuario) {
            return $porUsuario;
        }

        return $this->buscarAdminPorCampo('EMAIL', $login);
    }

    private function buscarAdminPorCampo($campo, $login)
    {
        if ($campo !== 'USERNAME' && $campo !== 'EMAIL') {
            return null;
        }

        $sql = "SELECT USER_ID, USERNAME, PASSWORD, EMAIL, FIRST_NAME, LAST_NAME, id_rol, ACTIVATED
                  FROM vfpmedicos.users
                 WHERE LOWER(TRIM(" . $campo . ")) = :login
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':login' => $login));
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return $fila;
    }

    private function buscarPrestador($login)
    {
        $sql = "SELECT id, codigo_prestador, email_login, nombre, apellido, estado
                  FROM vfpmedicos.usuarios_prestadores
                 WHERE LOWER(TRIM(email_login)) = :login
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':login' => $login));
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return $fila;
    }

    private function cuentaAdminBloqueada($admin)
    {
        if (!isset($admin['ACTIVATED']) || $admin['ACTIVATED'] === null || $admin['ACTIVATED'] === '') {
            return false;
        }
        return (int) $admin['ACTIVATED'] === 0;
    }

    private function prestadorActivo($prestador)
    {
        if (!isset($prestador['estado']) || $prestador['estado'] === null || $prestador['estado'] === '') {
            return true;
        }
        return (int) $prestador['estado'] === 1;
    }

    /**
     * Hash del prestador. La tabla actual no guarda contraseña: se usa la
     * columna si más adelante existe, y si no, la de users con el mismo login.
     */
    private function hashPrestador($prestador)
    {
        $columna = $this->columnaClavePrestador();
        if ($columna !== '' && isset($prestador['id'])) {
            $sql = "SELECT `" . $columna . "` AS clave
                      FROM vfpmedicos.usuarios_prestadores
                     WHERE id = :id
                     LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':id' => (int) $prestador['id']));
            $fila = $stmt->fetch();
            if ($fila && isset($fila['clave']) && trim((string) $fila['clave']) !== '') {
                return (string) $fila['clave'];
            }
        }

        $login = isset($prestador['email_login']) ? strtolower(trim($prestador['email_login'])) : '';
        if ($login === '') {
            return '';
        }

        $admin = $this->buscarAdmin($login);
        if ($admin && isset($admin['PASSWORD'])) {
            return (string) $admin['PASSWORD'];
        }

        return '';
    }

    private function columnaClavePrestador()
    {
        if ($this->columnaClavePrestador !== null) {
            return $this->columnaClavePrestador;
        }

        $this->columnaClavePrestador = '';
        $permitidas = array('password' => true, 'clave' => true, 'pass' => true);

        $sql = "SELECT COLUMN_NAME
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = 'vfpmedicos'
                   AND TABLE_NAME = 'usuarios_prestadores'
                   AND COLUMN_NAME IN ('password', 'clave', 'pass')
                 LIMIT 1";
        $stmt = $this->db->query($sql);
        $fila = $stmt->fetch();
        if ($fila && isset($fila['COLUMN_NAME'])) {
            $nombre = strtolower($fila['COLUMN_NAME']);
            if (isset($permitidas[$nombre])) {
                $this->columnaClavePrestador = $nombre;
            }
        }

        return $this->columnaClavePrestador;
    }

    private function armarAdmin($user)
    {
        $nombre = trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME']);
        if ($nombre === '') {
            $nombre = (string) $user['USERNAME'];
        }

        $email = isset($user['EMAIL']) ? trim((string) $user['EMAIL']) : '';
        if ($email === '') {
            $email = (string) $user['USERNAME'];
        }

        $permisos = $this->permisosVacios();
        foreach ($permisos as $clave => $valor) {
            $permisos[$clave] = 1;
        }

        return array(
            'usuario_id'        => (int) $user['USER_ID'],
            'nombre'            => $nombre,
            'email'             => $email,
            'rol'               => 'admin',
            'categ'             => '',
            'codigo_prestador'  => '',
            'tipo_correo'       => '',
            'permisos'          => $permisos,
            'ver_pagos'         => 1,
        );
    }

    private function armarPrestador($prestador, $login)
    {
        $codigo = isset($prestador['codigo_prestador']) ? trim((string) $prestador['codigo_prestador']) : '';
        $ebamp = $this->buscarEbamp($codigo);

        $categ = '';
        $tipos = array();
        if ($ebamp) {
            $categ = isset($ebamp['categ']) ? trim((string) $ebamp['categ']) : '';
            $tipos = $this->tiposCorreoCoincidentes($ebamp, $login);
        }

        $permisos = $this->permisosVacios();
        $nombresTipos = array();

        foreach ($tipos as $tipo) {
            $fila = $this->permisosPorTipo($tipo);
            if (!$fila) {
                continue;
            }
            $nombresTipos[] = $tipo;
            foreach ($permisos as $clave => $valor) {
                if (isset($fila[$clave]) && (int) $fila[$clave] === 1) {
                    $permisos[$clave] = 1;
                }
            }
        }

        $verPagosCategoria = 0;
        if ($categ !== '') {
            $categoria = $this->permisoCategoria($categ);
            if ($categoria && isset($categoria['ver_pagos']) && (int) $categoria['ver_pagos'] === 1) {
                $verPagosCategoria = 1;
            }
        }

        if ((int) $permisos['ver_pagos'] === 1 && $verPagosCategoria === 1) {
            $permisos['ver_pagos'] = 1;
        } else {
            $permisos['ver_pagos'] = 0;
        }

        $nombre = trim($prestador['nombre'] . ' ' . $prestador['apellido']);
        if ($nombre === '') {
            $nombre = $login;
        }

        $email = isset($prestador['email_login']) ? trim((string) $prestador['email_login']) : $login;

        return array(
            'usuario_id'        => (int) $prestador['id'],
            'nombre'            => $nombre,
            'email'             => $email,
            'rol'               => 'prestador',
            'categ'             => $categ,
            'codigo_prestador'  => $codigo,
            'tipo_correo'       => implode(',', $nombresTipos),
            'permisos'          => $permisos,
            'ver_pagos'         => (int) $permisos['ver_pagos'],
        );
    }

    private function buscarEbamp($codigo)
    {
        if ($codigo === '') {
            return null;
        }

        $sql = "SELECT TRIM(categ) AS categ,
                       TRIM(codigo) AS codigo,
                       mail_auto,
                       mailcontra,
                       mail_deb,
                       mail_pago
                  FROM cartilla.ebamp
                 WHERE TRIM(codigo) = :codigo
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':codigo' => $codigo));
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return $fila;
    }

    private function tiposCorreoCoincidentes($ebamp, $login)
    {
        $columnas = array('mail_auto', 'mailcontra', 'mail_deb', 'mail_pago');
        $loginNorm = strtolower(trim((string) $login));
        $tipos = array();

        foreach ($columnas as $columna) {
            $campo = isset($ebamp[$columna]) ? $ebamp[$columna] : '';
            if ($this->emailEnListado($campo, $loginNorm)) {
                $tipos[] = $columna;
            }
        }

        return $tipos;
    }

    private function emailEnListado($campo, $email)
    {
        if ($email === '' || strpos($email, '@') === false) {
            return false;
        }

        $partes = preg_split('/[;,\s]+/', strtolower((string) $campo));
        if (!is_array($partes)) {
            return false;
        }

        foreach ($partes as $parte) {
            if (trim($parte) === $email) {
                return true;
            }
        }

        return false;
    }

    private function permisosPorTipo($tipo)
    {
        $sql = "SELECT tipo_correo,
                       ver_autorizaciones,
                       ver_obras_sociales,
                       ver_coseguros,
                       ver_normativas,
                       ver_contratos,
                       ver_pagos,
                       ver_empadronamiento
                  FROM vfpmedicos.a_permisos
                 WHERE tipo_correo = :tipo
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':tipo' => $tipo));
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return $fila;
    }

    private function permisoCategoria($categ)
    {
        $sql = "SELECT categoria, descripcion, ver_pagos
                  FROM vfpmedicos.a_permisos_categorias
                 WHERE UPPER(TRIM(categoria)) = UPPER(:categ)
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':categ' => $categ));
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return $fila;
    }

    private function permisosVacios()
    {
        return array(
            'ver_autorizaciones'   => 0,
            'ver_obras_sociales'   => 0,
            'ver_coseguros'        => 0,
            'ver_normativas'       => 0,
            'ver_contratos'        => 0,
            'ver_pagos'            => 0,
            'ver_empadronamiento'  => 0,
        );
    }

    /**
     * Acepta password_hash ($2y$ / $argon), MySQL PASSWORD(), SHA1, MD5
     * y texto plano legacy (el esquema actual de users guarda la clave así).
     */
    private function verificarPassword($plano, $almacenado)
    {
        $almacenado = (string) $almacenado;
        $plano = (string) $plano;

        if ($almacenado === '' || $plano === '') {
            return false;
        }

        if (isset($almacenado[0]) && $almacenado[0] === '$') {
            return password_verify($plano, $almacenado);
        }

        if (strlen($almacenado) === 41 && $almacenado[0] === '*') {
            $mysql = '*' . strtoupper(sha1(sha1($plano, true)));
            return hash_equals(strtoupper($almacenado), $mysql);
        }

        if (strlen($almacenado) === 40 && ctype_xdigit($almacenado)) {
            return hash_equals(strtolower($almacenado), sha1($plano));
        }

        if (strlen($almacenado) === 32 && ctype_xdigit($almacenado)) {
            return hash_equals(strtolower($almacenado), md5($plano));
        }

        if (strlen($almacenado) !== strlen($plano)) {
            return false;
        }

        return hash_equals($almacenado, $plano);
    }
}
