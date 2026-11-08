<?php
/**
 * Mapa de afiliados y sucursales.
 *
 * Afiliados: último corte vigente de vfpmedicos.t_padron.
 * El padrón no tiene coordenadas: cada persona se ubica en su localidad
 * (Georef) y se separa un poco para poder señalarla.
 * Sucursales: vfpmedicos.sucprest (SUCPRES no está en cartilla).
 * Categoría: cartilla.ebamp, cruce SUPRESTADO = CODIGO.
 * Obras: cartilla.obrasoc, vía PadronModel.
 *
 * Compatible con PHP 5.6.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/PadronModel.php';

class MapaModel
{
    private $db;
    private $cache = null;
    private $presupuesto = 10;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    public function listarCategorias()
    {
        $sql = "SELECT TRIM(e.CATEG) AS categ, COUNT(*) AS total
                  FROM vfpmedicos.sucprest s
                  INNER JOIN cartilla.ebamp e ON s.SUPRESTADO = e.CODIGO
                 WHERE e.CATEG IS NOT NULL
                   AND TRIM(e.CATEG) <> ''
                 GROUP BY TRIM(e.CATEG)
                 ORDER BY total DESC, categ ASC";
        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll();
        $paleta = array(
            '#00bcd4', '#ec407a', '#455a64', '#ff4081', '#8d6e63',
            '#78909c', '#006064', '#f06292', '#37474f', '#b0bec5',
            '#880e4f', '#ce93d8', '#ffab91', '#546e7a', '#f48fb1', '#4db6ac',
        );
        $lista = array();
        $i = 0;
        if (!is_array($filas)) {
            return $lista;
        }
        foreach ($filas as $fila) {
            $codigo = trim((string) $fila['categ']);
            if ($codigo === '') {
                continue;
            }
            $lista[] = array(
                'codigo' => $codigo,
                'total'  => (int) $fila['total'],
                'color'  => $paleta[$i % count($paleta)],
            );
            $i++;
        }
        return $lista;
    }

    /**
     * Georreferencia las localidades que faltan del corte, en tandas cortas.
     */
    public function prepararAfiliados($obrasoc)
    {
        @set_time_limit(90);
        $obra = strtoupper(trim((string) $obrasoc));
        $nombres = $this->localidadesActivas($obra);
        if (count($nombres) === 0) {
            return array(
                'status'  => 'error',
                'message' => 'No hay afiliados activos de esta obra en unicos.',
            );
        }
        $inicio = microtime(true);
        $this->precargarProvincias($obra);
        $cache = $this->cache();
        $faltan = $this->lugaresParaGeoref($obra, $cache);

        $georef = true;
        $avance = 0;
        $claves = array_keys($faltan);
        $paso = 50;
        $n = count($claves);
        for ($i = 0; $i < $n; $i += $paso) {
            if (!$this->hayTiempo($inicio)) {
                break;
            }
            $trozo = array_slice($claves, $i, $paso);
            $items = array();
            foreach ($trozo as $clave) {
                $item = array(
                    'nombre' => $faltan[$clave]['nombre'],
                    'max'    => 1,
                    'campos' => 'nombre,centroide',
                );
                if ($faltan[$clave]['provincia'] !== '') {
                    $item['provincia'] = $faltan[$clave]['provincia'];
                }
                $items[] = $item;
            }
            $datos = $this->postJson('https://apis.datos.gob.ar/georef/api/localidades', array(
                'localidades' => $items,
            ));
            if ($datos === null || !isset($datos['resultados']) || count($datos['resultados']) !== count($trozo)) {
                $georef = false;
                break;
            }
            $nuevos = array();
            foreach ($trozo as $idx => $clave) {
                $nuevos[$clave] = $this->leerCentroide(
                    isset($datos['resultados'][$idx]['localidades'][0]['centroide'])
                        ? $datos['resultados'][$idx]['localidades'][0]['centroide']
                        : null
                );
            }
            $this->persistir($nuevos);
            $avance += count($nuevos);
        }

        $cache = $this->cache();
        $siguen = $this->lugaresParaGeoref($obra, $cache);
        $pendientes = count($siguen);
        $ubicadas = count($nombres);

        $mensaje = '';
        if (!$georef && $pendientes > 0) {
            $mensaje = 'El georreferenciador no respondió. Lo ya ubicado quedó guardado: puede reintentar.';
        }

        return array(
            'status'       => 'ok',
            'periodo'      => 'unicos',
            'archivo'      => '',
            'localidades'  => count($nombres),
            'ubicadas'     => $ubicadas,
            'pendientes'   => $pendientes,
            'avance'       => $avance,
            'georef'       => $georef,
            'message'      => $mensaje,
        );
    }

    public function puntosAfiliados($obrasoc)
    {
        @set_time_limit(120);
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social.',
            );
        }

        $this->precargarProvincias($obra);
        $sql = "SELECT TRIM(NOMBRE) AS nombre,
                       TRIM(NROAFILIAD) AS nroa,
                       NRODOC AS dni,
                       AFILIADO AS afiliado,
                       TRIM(CODADH) AS codadh,
                       TRIM(TELEFONO) AS telefono,
                       TRIM(DOMICILIO) AS domicilio,
                       TRIM(D_PUERTA) AS puerta,
                       TRIM(LOCALIDAD) AS localidad,
                       TRIM(PROVINCIA) AS provincia
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra
                   AND (FBAJA IS NULL OR TRIM(FBAJA) = '')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            $filas = array();
        }
        if (count($filas) === 0) {
            return array(
                'status'  => 'error',
                'message' => 'No hay afiliados activos de esta obra en unicos.',
            );
        }

        $enrolPorDni = $this->discapacidadVigente($obra);
        $padron = $this->lugaresPadron($obra);
        $correcciones = $this->correccionesObra($obra);
        $cache = $this->cache();
        $totales = array();
        $puestos = array();
        foreach ($filas as $indice => $fila) {
            $puesto = $this->resolverPuesto($fila, $padron, $cache, $correcciones);
            $puestos[$indice] = $puesto;
            if ($puesto === null) {
                continue;
            }
            if (!isset($totales[$puesto['clave']])) {
                $totales[$puesto['clave']] = 0;
            }
            $totales[$puesto['clave']]++;
        }

        $conteoDisca = array();
        foreach ($filas as $fila) {
            $dniMarca = (int) $fila['dni'];
            $marca = isset($enrolPorDni[$dniMarca]) ? $enrolPorDni[$dniMarca] : '';
            $variante = $this->varianteDiscapacidad($marca);
            if ($variante === '') {
                continue;
            }
            if (!isset($conteoDisca[$variante])) {
                $conteoDisca[$variante] = 0;
            }
            $conteoDisca[$variante]++;
        }
        $coloresDisca = $this->coloresDiscapacidad($conteoDisca);

        $secuencia = array();
        $puntos = array();
        $sinUbicar = 0;
        $discaUbicados = 0;
        foreach ($filas as $indice => $fila) {
            $puesto = isset($puestos[$indice]) ? $puestos[$indice] : null;
            if ($puesto === null) {
                $sinUbicar++;
                continue;
            }
            $clave = $puesto['clave'];
            if (!isset($secuencia[$clave])) {
                $secuencia[$clave] = 0;
            }
            $pos = $this->espiral($puesto['lat'], $puesto['lng'], $secuencia[$clave], $totales[$clave]);
            $secuencia[$clave]++;

            $nroa = trim((string) $fila['nroa']);
            $lugar = $this->lugarFila($fila['localidad'], $fila['provincia']);
            $provincia = trim((string) $fila['provincia']);
            $nombrePunto = trim((string) $fila['nombre']);
            $telefonoPunto = trim((string) $fila['telefono']);
            $direccionPunto = $this->direccionAfiliado($fila['domicilio'], $fila['puerta']);
            $claveCorr = $this->claveAfiliado($fila['afiliado']);
            if ($claveCorr !== '' && isset($correcciones[$claveCorr])) {
                $corr = $correcciones[$claveCorr];
                if ($corr['nombre'] !== '') {
                    $nombrePunto = $corr['nombre'];
                }
                if ($corr['telefono'] !== '') {
                    $telefonoPunto = $corr['telefono'];
                }
                if ($corr['direccion'] !== '') {
                    $direccionPunto = $corr['direccion'];
                }
                if ($corr['localidad'] !== '') {
                    $lugar = $corr['localidad'];
                }
                if ($corr['zona'] !== '') {
                    $provincia = $corr['zona'];
                }
            }
            $dni = (int) $fila['dni'];
            $marca = isset($enrolPorDni[$dni]) ? $enrolPorDni[$dni] : '';
            $variante = $this->varianteDiscapacidad($marca);
            if ($variante !== '') {
                $discaUbicados++;
            }
            $puntos[] = array(
                $pos[0],
                $pos[1],
                $nombrePunto,
                $nroa,
                $this->numeroAfiliado($fila['afiliado']),
                $telefonoPunto,
                $direccionPunto,
                $lugar,
                $provincia,
                $variante,
                $variante !== '' && isset($coloresDisca[$variante]) ? $coloresDisca[$variante] : '',
            );
        }

        $discaTotal = 0;
        $discapacidad = array();
        foreach ($coloresDisca as $etiqueta => $color) {
            $cantidad = isset($conteoDisca[$etiqueta]) ? (int) $conteoDisca[$etiqueta] : 0;
            $discaTotal += $cantidad;
            $discapacidad[] = array(
                'etiqueta' => $etiqueta,
                'color'    => $color,
                'total'    => $cantidad,
            );
        }

        $mensaje = 'Afiliados activos de unicos (FBAJA vacía): ' . count($filas) . '. Se ubican por localidad.';

        return array(
            'status'          => 'ok',
            'periodo'         => 'unicos',
            'archivo'         => '',
            'total'           => count($filas),
            'ubicados'        => count($puntos),
            'sin_ubicar'      => $sinUbicar,
            'disca_total'     => $discaTotal,
            'disca_ubicados'  => $discaUbicados,
            'discapacidad'    => $discapacidad,
            'puntos'          => $puntos,
            'message'         => $mensaje,
        );
    }

    public function puntosPrestadores($obrasoc)
    {
        @set_time_limit(90);
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social.',
            );
        }

        $vigente = "(fecha IS NULL
                     OR fecha = '0000-00-00'
                     OR fecha = '0000-00-00 00:00:00'
                     OR TRIM(CAST(fecha AS CHAR)) = ''
                     OR fecha > CURDATE())";
        $sql = "SELECT s.id AS id,
                       TRIM(s.SUNOMBRE) AS nombre,
                       TRIM(s.SUDIR) AS direccion,
                       TRIM(s.SUDIRMAP) AS dirmap,
                       TRIM(s.SULOCALIDA) AS localidad,
                       TRIM(s.SUZONA) AS zona,
                       TRIM(s.SUTELEF) AS telefono,
                       TRIM(e.CATEG) AS categ,
                       TRIM(e.NOMBRE) AS nombre_base
                  FROM vfpmedicos.sucprest s
                  INNER JOIN cartilla.ebamp e ON s.SUPRESTADO = e.CODIGO
                  INNER JOIN cartilla.obramed om
                          ON TRIM(om.OBRASOC) = :obra
                         AND (TRIM(om.MEDICO) = TRIM(e.MATRICULA) OR TRIM(om.MEDICO) = TRIM(e.CODIGO))
                 WHERE e.CATEG IS NOT NULL
                   AND TRIM(e.CATEG) <> ''
                   AND " . str_replace('fecha', 'om.FECHABAJA', $vigente) . "
                   AND " . str_replace('fecha', 'e.FECHABAJA', $vigente);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            $filas = array();
        }

        $vistos = array();
        $unicas = array();
        foreach ($filas as $fila) {
            $id = (int) $fila['id'];
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $unicas[] = $fila;
        }

        $this->precargarProvincias($obra);
        $georef = true;
        $inicio = microtime(true);
        $avance = $this->geocodificarPrestadores($unicas, $inicio, $georef);
        $cache = $this->cache();
        $crudos = array();
        $pendientes = 0;
        $aproximados = 0;

        foreach ($unicas as $fila) {
            $puesto = $this->resolverPrestador($fila, $cache);
            if (!$puesto['resuelto']) {
                $pendientes++;
                continue;
            }
            if ($puesto['lat'] === null) {
                continue;
            }
            if ((int) $puesto['aprox'] === 1) {
                $aproximados++;
            }
            $nombre = trim((string) $fila['nombre']);
            if ($nombre === '') {
                $nombre = trim((string) $fila['nombre_base']);
            }
            $direccion = trim((string) $fila['direccion']);
            if ($direccion === '') {
                $direccion = trim((string) $fila['dirmap']);
            }
            $clavePunto = round((float) $puesto['lat'], 4) . '|' . round((float) $puesto['lng'], 4);
            if (!isset($crudos[$clavePunto])) {
                $crudos[$clavePunto] = array();
            }
            $crudos[$clavePunto][] = array(
                'lat' => (float) $puesto['lat'],
                'lng' => (float) $puesto['lng'],
                'categ' => trim((string) $fila['categ']),
                'nombre' => $nombre,
                'direccion' => $direccion,
                'telefono' => trim((string) $fila['telefono']),
                'localidad' => trim((string) $fila['localidad']),
                'zona' => trim((string) $fila['zona']),
                'aprox' => (int) $puesto['aprox'],
            );
        }

        $puntos = array();
        foreach ($crudos as $grupo) {
            $totalGrupo = count($grupo);
            foreach ($grupo as $indice => $fila) {
                $pos = $this->espiral($fila['lat'], $fila['lng'], $indice, $totalGrupo);
                $puntos[] = array(
                    $pos[0],
                    $pos[1],
                    $fila['categ'],
                    $fila['nombre'],
                    $fila['direccion'],
                    $fila['telefono'],
                    $fila['localidad'],
                    $fila['zona'],
                    $fila['aprox'],
                );
            }
        }

        $mensaje = '';
        if (!$georef && $pendientes > 0) {
            $mensaje = 'El georreferenciador no respondió. Lo ya ubicado quedó guardado: puede reintentar.';
        }

        $conteoCat = array();
        foreach ($unicas as $fila) {
            $categ = trim((string) $fila['categ']);
            if ($categ === '') {
                $categ = 'S/C';
            }
            if (!isset($conteoCat[$categ])) {
                $conteoCat[$categ] = 0;
            }
            $conteoCat[$categ]++;
        }
        arsort($conteoCat);
        $paleta = array(
            '#00bcd4', '#ec407a', '#455a64', '#ff4081', '#8d6e63',
            '#78909c', '#006064', '#f06292', '#37474f', '#b0bec5',
            '#880e4f', '#ce93d8', '#ffab91', '#546e7a', '#f48fb1', '#4db6ac',
        );
        $conteoZona = array();
        foreach ($unicas as $fila) {
            $zonaNombre = trim((string) $fila['zona']);
            if ($zonaNombre === '') {
                $zonaNombre = 'Sin zona';
            }
            if (!isset($conteoZona[$zonaNombre])) {
                $conteoZona[$zonaNombre] = 0;
            }
            $conteoZona[$zonaNombre]++;
        }
        arsort($conteoZona);
        $provincias = array();
        foreach ($conteoZona as $zonaNombre => $cantidad) {
            $provincias[] = array(
                'zona'  => $zonaNombre,
                'total' => (int) $cantidad,
            );
        }

        $categorias = array();
        $iCat = 0;
        foreach ($conteoCat as $codigo => $cantidad) {
            $categorias[] = array(
                'codigo' => $codigo,
                'total'  => (int) $cantidad,
                'color'  => $paleta[$iCat % count($paleta)],
            );
            $iCat++;
        }

        return array(
            'status'      => 'ok',
            'total'       => count($unicas),
            'ubicados'    => count($puntos),
            'aproximados' => $aproximados,
            'pendientes'  => $pendientes,
            'avance'      => $avance,
            'georef'      => $georef,
            'message'     => $mensaje,
            'categorias'  => $categorias,
            'provincias'  => $provincias,
            'puntos'      => $puntos,
        );
    }

    private function geocodificarPrestadores($filas, $inicio, &$georef)
    {
        $avance = 0;
        $cache = $this->cache();
        $calles = array();
        $simples = array();
        $lugares = array();

        foreach ($filas as $fila) {
            $consulta = $this->consultaDireccion($fila);
            if ($consulta !== null) {
                if (!isset($cache[$consulta['clave']])) {
                    $calles[$consulta['clave']] = $consulta;
                    continue;
                }
                $precisoOk = (int) $cache[$consulta['clave']]['ok'] === 1;
                $precisoDentro = $precisoOk && $this->puntoEnZona($cache[$consulta['clave']], $fila);
                if ($precisoDentro) {
                    continue;
                }
                $precisoFuera = $precisoOk && !$precisoDentro;
                if (!$precisoFuera && $consulta['tiene_simple'] && !isset($cache[$consulta['clave_simple']])) {
                    $simples[$consulta['clave_simple']] = $consulta;
                    continue;
                }
                $simpleOk = !$precisoFuera
                    && $consulta['tiene_simple']
                    && isset($cache[$consulta['clave_simple']])
                    && (int) $cache[$consulta['clave_simple']]['ok'] === 1
                    && $this->puntoEnZona($cache[$consulta['clave_simple']], $fila);
                if ($simpleOk) {
                    continue;
                }
            }
            $lugar = $this->aliasLocalidadPrestador(
                isset($fila['localidad']) ? $fila['localidad'] : '',
                isset($fila['zona']) ? $fila['zona'] : ''
            );
            if ($lugar === '' || $this->localidadAmbigua($lugar, isset($fila['zona']) ? $fila['zona'] : '', isset($fila['dirmap']) ? $fila['dirmap'] : '')) {
                continue;
            }
            $prov = $this->provinciaDesdeZona(isset($fila['zona']) ? $fila['zona'] : '', isset($fila['dirmap']) ? $fila['dirmap'] : '');
            $claveLugar = 'L|' . $this->normalizar($lugar);
            if ($this->normalizar($prov) !== '') {
                $claveLugar .= '|' . $this->normalizar($prov);
            }
            if (!isset($cache[$claveLugar])) {
                $lugares[$claveLugar] = array(
                    'nombre'    => $lugar,
                    'provincia' => $prov,
                );
            }
        }

        $avance += $this->enviarCalles($calles, false, $inicio, $georef);
        if ($georef && $this->hayTiempo($inicio)) {
            $cache = $this->cache();
            $simples = array();
            foreach ($filas as $fila) {
                $consulta = $this->consultaDireccion($fila);
                if ($consulta === null || !$consulta['tiene_simple']) {
                    continue;
                }
                if (!isset($cache[$consulta['clave']]) || (int) $cache[$consulta['clave']]['ok'] === 1) {
                    continue;
                }
                if (!isset($cache[$consulta['clave_simple']])) {
                    $simples[$consulta['clave_simple']] = $consulta;
                }
            }
            $avance += $this->enviarCalles($simples, true, $inicio, $georef);
        }
        if ($georef && $this->hayTiempo($inicio) && count($lugares) > 0) {
            $avance += $this->enviarLocalidades($lugares, $inicio, $georef);
        }
        return $avance;
    }

    private function enviarCalles($mapa, $simple, $inicio, &$georef)
    {
        if (!$georef || count($mapa) === 0) {
            return 0;
        }
        $avance = 0;
        $claves = array_keys($mapa);
        $n = count($claves);
        $paso = 40;
        for ($i = 0; $i < $n; $i += $paso) {
            if (!$this->hayTiempo($inicio)) {
                break;
            }
            $trozo = array_slice($claves, $i, $paso);
            $items = array();
            foreach ($trozo as $clave) {
                $consulta = $mapa[$clave];
                $item = array(
                    'direccion' => $consulta['calle'],
                    'max'       => 1,
                );
                if ($consulta['prov'] !== '') {
                    $item['provincia'] = $consulta['prov'];
                }
                if (!$simple && $consulta['loc'] !== '') {
                    $item['localidad'] = $consulta['loc'];
                }
                $items[] = $item;
            }
            $datos = $this->postJson('https://apis.datos.gob.ar/georef/api/direcciones', array(
                'direcciones' => $items,
            ));
            if ($datos === null || !isset($datos['resultados']) || count($datos['resultados']) !== count($trozo)) {
                $georef = false;
                break;
            }
            $nuevos = array();
            foreach ($trozo as $idx => $clave) {
                $centro = null;
                if (isset($datos['resultados'][$idx]['direcciones'][0]['ubicacion'])) {
                    $centro = $datos['resultados'][$idx]['direcciones'][0]['ubicacion'];
                }
                $nuevos[$clave] = $this->leerCentroide($centro);
            }
            $this->persistir($nuevos);
            $avance += count($nuevos);
        }
        return $avance;
    }

    private function enviarLocalidades($mapa, $inicio, &$georef)
    {
        $avance = 0;
        $claves = array_keys($mapa);
        $n = count($claves);
        $paso = 50;
        for ($i = 0; $i < $n; $i += $paso) {
            if (!$this->hayTiempo($inicio)) {
                break;
            }
            $trozo = array_slice($claves, $i, $paso);
            $items = array();
            foreach ($trozo as $clave) {
                $dato = $mapa[$clave];
                $nombre = is_array($dato) ? $dato['nombre'] : $dato;
                $provincia = is_array($dato) && isset($dato['provincia']) ? $dato['provincia'] : '';
                $item = array(
                    'nombre' => $nombre,
                    'max'    => 1,
                    'campos' => 'nombre,centroide,provincia.nombre',
                );
                if (trim((string) $provincia) !== '' && $provincia !== 'caba') {
                    $item['provincia'] = $provincia;
                }
                $items[] = $item;
            }
            $datos = $this->postJson('https://apis.datos.gob.ar/georef/api/localidades', array(
                'localidades' => $items,
            ));
            if ($datos === null || !isset($datos['resultados']) || count($datos['resultados']) !== count($trozo)) {
                $georef = false;
                break;
            }
            $nuevos = array();
            foreach ($trozo as $idx => $clave) {
                $loc = isset($datos['resultados'][$idx]['localidades'][0])
                    ? $datos['resultados'][$idx]['localidades'][0]
                    : null;
                $centro = is_array($loc) && isset($loc['centroide']) ? $loc['centroide'] : null;
                $leido = $this->leerCentroide($centro);
                $dato = $mapa[$clave];
                $provincia = is_array($dato) && isset($dato['provincia']) ? $dato['provincia'] : '';
                $provRes = is_array($loc) && isset($loc['provincia']['nombre']) ? $loc['provincia']['nombre'] : '';
                if ($provincia !== '' && $provincia !== 'caba' && !$this->mismaProvincia($provincia, $provRes)) {
                    $leido = array('lat' => null, 'lng' => null, 'ok' => 0);
                }
                $nuevos[$clave] = $leido;
            }
            $this->persistir($nuevos);
            $avance += count($nuevos);
        }
        return $avance;
    }

    private function resolverPrestador($fila, $cache)
    {
        $vacio = array(
            'resuelto' => true,
            'lat'      => null,
            'lng'      => null,
            'aprox'    => 0,
        );
        $consulta = $this->consultaDireccion($fila);
        if ($consulta !== null) {
            if (isset($cache[$consulta['clave']]) && (int) $cache[$consulta['clave']]['ok'] === 1 && $this->puntoEnZona($cache[$consulta['clave']], $fila)) {
                return array(
                    'resuelto' => true,
                    'lat'      => round((float) $cache[$consulta['clave']]['lat'], 6),
                    'lng'      => round((float) $cache[$consulta['clave']]['lng'], 6),
                    'aprox'    => 0,
                );
            }
            if ($consulta['tiene_simple'] && isset($cache[$consulta['clave_simple']]) && (int) $cache[$consulta['clave_simple']]['ok'] === 1 && $this->puntoEnZona($cache[$consulta['clave_simple']], $fila)) {
                return array(
                    'resuelto' => true,
                    'lat'      => round((float) $cache[$consulta['clave_simple']]['lat'], 6),
                    'lng'      => round((float) $cache[$consulta['clave_simple']]['lng'], 6),
                    'aprox'    => 0,
                );
            }
            if (!isset($cache[$consulta['clave']])) {
                return array('resuelto' => false, 'lat' => null, 'lng' => null, 'aprox' => 0);
            }
            if ($consulta['tiene_simple'] && !isset($cache[$consulta['clave_simple']])) {
                return array('resuelto' => false, 'lat' => null, 'lng' => null, 'aprox' => 0);
            }
        }

        $lugar = $this->aliasLocalidadPrestador(
            isset($fila['localidad']) ? $fila['localidad'] : '',
            isset($fila['zona']) ? $fila['zona'] : ''
        );
        $lugarUtil = $lugar !== '' && !$this->localidadAmbigua($lugar, isset($fila['zona']) ? $fila['zona'] : '', isset($fila['dirmap']) ? $fila['dirmap'] : '');
        if ($lugarUtil) {
            $prov = $this->provinciaDesdeZona(isset($fila['zona']) ? $fila['zona'] : '', isset($fila['dirmap']) ? $fila['dirmap'] : '');
            $clave = 'L|' . $this->normalizar($lugar);
            if ($this->normalizar($prov) !== '' && $prov !== 'caba') {
                $clave .= '|' . $this->normalizar($prov);
            }
            if (!isset($cache[$clave])) {
                return array('resuelto' => false, 'lat' => null, 'lng' => null, 'aprox' => 0);
            }
            if ((int) $cache[$clave]['ok'] === 1 && $this->puntoEnZona($cache[$clave], $fila)) {
                return array(
                    'resuelto' => true,
                    'lat'      => round((float) $cache[$clave]['lat'], 6),
                    'lng'      => round((float) $cache[$clave]['lng'], 6),
                    'aprox'    => 1,
                );
            }
        }
        $porProvincia = $this->puestoProvincia($fila, $cache);
        if ($porProvincia !== null) {
            return $porProvincia;
        }
        return $vacio;
    }

    private function puestoProvincia($fila, $cache)
    {
        $zona = isset($fila['zona']) ? $fila['zona'] : '';
        $z = $this->normalizar($zona);
        if ($z === '' || $z === 'ZONA' || strpos($z, 'GBA') === 0) {
            return null;
        }
        if ($z === 'CAPITAL FEDERAL' || $z === 'CABA') {
            $fijo = $this->puntoCapital('Capital Federal');
            if ($fijo === null) {
                return null;
            }
            return array(
                'resuelto' => true,
                'lat'      => $fijo['lat'],
                'lng'      => $fijo['lng'],
                'aprox'    => 1,
            );
        }
        $prov = $this->provinciaDesdeZona($zona, isset($fila['dirmap']) ? $fila['dirmap'] : '');
        if ($prov === '' || $prov === 'caba') {
            return null;
        }
        $nombre = $this->nombreProvincia($prov);
        $clave = 'L|' . $this->normalizar($nombre) . '|' . $this->normalizar($nombre);
        if (!isset($cache[$clave]) || (int) $cache[$clave]['ok'] !== 1) {
            return null;
        }
        return array(
            'resuelto' => true,
            'lat'      => round((float) $cache[$clave]['lat'], 6),
            'lng'      => round((float) $cache[$clave]['lng'], 6),
            'aprox'    => 1,
        );
    }

    private function aliasLocalidadPrestador($localidad, $zona)
    {
        $n = $this->normalizar($localidad);
        $z = $this->normalizar($zona);
        if ($n === 'EL DORADO' && $z === 'MISIONES') {
            return 'Eldorado';
        }
        if ($n === 'ALEM' && $z === 'MISIONES') {
            return 'Leandro N. Alem';
        }
        if ($n === 'CORDOBA CAPITAL' || $n === 'CORDOBA - CAPITAL') {
            return 'Cordoba';
        }
        if (strpos($n, 'SAN SALVADOR DE JUJU') === 0) {
            return 'San Salvador de Jujuy';
        }
        if (strpos($n, 'SAN MIGUEL DE TUCU') === 0) {
            return 'San Miguel de Tucuman';
        }
        return trim((string) $localidad);
    }

    private function puntoEnZona($punto, $fila)
    {
        if (!is_array($punto) || !isset($punto['lat']) || !isset($punto['lng']) || $punto['lat'] === null) {
            return false;
        }
        return $this->dentroDeZona((float) $punto['lat'], (float) $punto['lng'], isset($fila['zona']) ? $fila['zona'] : '');
    }

    private function dentroDeZona($lat, $lng, $zona)
    {
        $caja = $this->cajaProvincia($zona);
        if ($caja === null) {
            return true;
        }
        return $lat >= $caja[0] && $lat <= $caja[1] && $lng >= $caja[2] && $lng <= $caja[3];
    }

    private function cajaProvincia($zona)
    {
        $z = $this->normalizar($zona);
        if ($z === 'CAPITAL FEDERAL' || $z === 'CABA' || strpos($z, 'GBA') === 0 || $z === 'BUENOS AIRES') {
            return array(-41.2, -33.2, -63.5, -56.6);
        }
        $cajas = array(
            'MISIONES'             => array(-28.2, -25.5, -56.1, -53.6),
            'SALTA'                => array(-26.4, -22.0, -68.6, -62.2),
            'TUCUMAN'              => array(-28.0, -26.0, -66.2, -64.4),
            'CORDOBA'              => array(-35.0, -29.5, -65.8, -61.8),
            'SANTA FE'             => array(-34.4, -28.0, -63.0, -59.6),
            'CORRIENTES'           => array(-30.8, -27.2, -59.7, -55.6),
            'JUJUY'                => array(-24.6, -21.8, -67.3, -64.2),
            'LA RIOJA'             => array(-32.0, -28.0, -69.6, -65.4),
            'MENDOZA'              => array(-37.6, -32.0, -70.6, -66.4),
            'SAN LUIS'             => array(-36.0, -31.9, -67.3, -64.9),
            'ENTRE RIOS'           => array(-34.1, -30.0, -60.9, -57.8),
            'CHACO'                => array(-28.0, -24.1, -63.5, -58.4),
            'FORMOSA'              => array(-26.9, -22.4, -62.4, -57.6),
            'CATAMARCA'            => array(-30.1, -25.2, -69.1, -64.8),
            'NEUQUEN'              => array(-41.1, -36.1, -71.9, -67.9),
            'RIO NEGRO'            => array(-42.0, -37.5, -71.9, -62.8),
            'CHUBUT'               => array(-46.0, -42.0, -72.2, -63.6),
            'SANTA CRUZ'           => array(-52.4, -45.9, -73.6, -65.6),
            'LA PAMPA'             => array(-39.3, -35.0, -68.3, -63.4),
            'SANTIAGO DEL ESTERO'  => array(-30.5, -25.6, -65.2, -61.7),
            'TIERRA DEL FUEGO'     => array(-55.1, -52.6, -68.6, -63.8),
        );
        return isset($cajas[$z]) ? $cajas[$z] : null;
    }

    private function consultaDireccion($fila)
    {
        $calle = $this->limpiarCalle(isset($fila['direccion']) ? $fila['direccion'] : '');
        if ($calle === '') {
            $calle = $this->limpiarCalle($this->primeraParte(isset($fila['dirmap']) ? $fila['dirmap'] : ''));
        }
        if ($calle === '') {
            return null;
        }
        $loc = trim((string) (isset($fila['localidad']) ? $fila['localidad'] : ''));
        $zona = isset($fila['zona']) ? $fila['zona'] : '';
        $dirmap = isset($fila['dirmap']) ? $fila['dirmap'] : '';
        $prov = $this->provinciaDesdeZona($zona, $dirmap);
        if ($prov === '' && $this->esBarrioCaba($this->normalizar($loc))) {
            $prov = 'caba';
        }
        if ($prov === 'caba') {
            $loc = '';
        }
        $normCalle = $this->normalizar($calle);
        $normLoc = $this->normalizar($loc);
        $normProv = $this->normalizar($prov);
        return array(
            'calle'        => $calle,
            'loc'          => $loc,
            'prov'         => $prov,
            'clave'        => 'D|' . sha1($normCalle . '|' . $normLoc . '|' . $normProv),
            'clave_simple' => 'D|' . sha1($normCalle . '||' . $normProv),
            'tiene_simple' => ($normLoc !== ''),
        );
    }

    private function corte($obra)
    {
        $padron = new PadronModel();
        $corte = $padron->getCorteVigente($obra);
        if (!is_array($corte)) {
            return array('periodo' => '', 'archivo' => '');
        }
        return $corte;
    }

    private function filtroCorte($obra, $corte)
    {
        $sql = ' WHERE OBRASOC = :obra AND PERIODO = :periodo ';
        $params = array(
            ':obra'    => $obra,
            ':periodo' => $corte['periodo'],
        );
        if (isset($corte['archivo']) && trim((string) $corte['archivo']) !== '') {
            $sql .= ' AND UPPER(TRIM(PFILE)) = :archivo ';
            $params[':archivo'] = strtoupper(trim((string) $corte['archivo']));
        }
        return array('sql' => $sql, 'params' => $params);
    }

    private function varianteDiscapacidad($marca)
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

    private function coloresDiscapacidad($conteo)
    {
        $fijos = array(
            'Discapacidad' => '#7b1fa2',
            'Diabetes'     => '#e65100',
            'HIV'          => '#d50000',
            'Oncología'    => '#1565c0',
            'Alto valor'   => '#4e342e',
            'PMI'          => '#f9a825',
        );
        $orden = array(
            'Discapacidad' => 1,
            'Diabetes'     => 2,
            'HIV'          => 3,
            'Oncología'    => 4,
            'Alto valor'   => 5,
            'PMI'          => 6,
        );
        $etiquetas = array_keys($conteo);
        usort($etiquetas, function ($a, $b) use ($orden) {
            $ordenA = isset($orden[$a]) ? $orden[$a] : 50;
            $ordenB = isset($orden[$b]) ? $orden[$b] : 50;
            if ($ordenA === $ordenB) {
                return strcmp($a, $b);
            }
            return ($ordenA < $ordenB) ? -1 : 1;
        });
        $paleta = array('#00bcd4', '#ec407a', '#455a64', '#ff4081', '#8d6e63', '#37474f');
        $colores = array();
        $i = 0;
        foreach ($etiquetas as $etiqueta) {
            if (isset($fijos[$etiqueta])) {
                $colores[$etiqueta] = $fijos[$etiqueta];
                continue;
            }
            $colores[$etiqueta] = $paleta[$i % count($paleta)];
            $i++;
        }
        return $colores;
    }

    private function lugaresPadron($obra)
    {
        $mapa = array();
        $corte = $this->corte($obra);
        if ($corte['periodo'] === '') {
            return $mapa;
        }
        try {
            $filtro = $this->filtroCorte($obra, $corte);
            $sql = "SELECT AFILIADO AS afiliado,
                           TRIM(LOCALIDAD) AS loc,
                           TRIM(PROVINCIA) AS prov
                      FROM vfpmedicos.t_padron"
                 . $filtro['sql'];
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtro['params']);
            $filas = $stmt->fetchAll();
        } catch (Exception $e) {
            return $mapa;
        }
        if (!is_array($filas)) {
            return $mapa;
        }
        foreach ($filas as $fila) {
            $clave = $this->claveAfiliado($fila['afiliado']);
            if ($clave === '' || isset($mapa[$clave])) {
                continue;
            }
            $mapa[$clave] = array(
                'loc'  => trim((string) $fila['loc']),
                'prov' => trim((string) $fila['prov']),
            );
        }
        return $mapa;
    }

    private function claveAfiliado($valor)
    {
        $texto = trim((string) $valor);
        if ($texto !== '' && is_numeric($texto)) {
            return sprintf('%.0f', (float) $texto);
        }
        return $texto;
    }

    private function resolverPuesto($fila, $padron, $cache, $correcciones = array())
    {
        $clave = $this->claveAfiliado(isset($fila['afiliado']) ? $fila['afiliado'] : '');
        $candidatos = array();
        if ($clave !== '' && isset($correcciones[$clave])) {
            $corr = $correcciones[$clave];
            if ($corr['lat'] !== null && $corr['lng'] !== null) {
                return array(
                    'lat'   => (float) $corr['lat'],
                    'lng'   => (float) $corr['lng'],
                    'clave' => 'C|' . $clave,
                );
            }
            $zona = $corr['zona'] !== '' ? $corr['zona'] : (isset($fila['provincia']) ? $fila['provincia'] : '');
            $candidatos = $this->candidatosLugar($corr['localidad'], $zona);
        }
        $localidad = isset($fila['localidad']) ? $fila['localidad'] : '';
        $provincia = isset($fila['provincia']) ? $fila['provincia'] : '';
        $candidatos = array_merge($candidatos, $this->candidatosLugar($localidad, $provincia));
        if ($clave !== '' && isset($padron[$clave])) {
            $candidatos = array_merge(
                $candidatos,
                $this->candidatosLugar($padron[$clave]['loc'], $padron[$clave]['prov'])
            );
        }
        return $this->ubicarCandidatos($candidatos, $cache);
    }

    private function candidatosLugar($localidad, $provincia)
    {
        $lista = array();
        $this->sumarCandidato($lista, $localidad, $provincia);
        $limpio = $this->limpiarLocalidad($localidad);
        $this->sumarCandidato($lista, $limpio, $provincia);
        if (strpos($limpio, ',') !== false) {
            $partes = explode(',', $limpio);
            $this->sumarCandidato($lista, trim($partes[count($partes) - 1]), $provincia);
        }
        $alias = $this->aliasLocalidad($limpio, $provincia);
        if ($alias !== '') {
            $this->sumarCandidato($lista, $alias, $provincia);
        }
        $provinciaNombre = $this->nombreProvincia($provincia);
        if ($this->normalizar($provinciaNombre) !== '') {
            $this->sumarCandidato($lista, $provinciaNombre, $provinciaNombre);
        }
        return $lista;
    }

    private function nombreProvincia($provincia)
    {
        $n = $this->normalizar($provincia);
        if ($n === 'SANTIAGO DEL ES' || $n === 'SANTIAGO DEL ESTERO') {
            return 'Santiago del Estero';
        }
        if ($n === 'TIERRA DEL FUEG' || $n === 'TIERRA DEL FUEGO' || strpos($n, 'TIERRA DEL FUEGO') === 0) {
            return 'Tierra del Fuego';
        }
        if ($n === 'CABA' || $n === 'CAPITAL FEDERAL' || strpos($n, 'CIUDAD AUTONOMA') !== false) {
            return 'Capital Federal';
        }
        return trim((string) $provincia);
    }

    private function claveProvincia($nombre)
    {
        $n = $this->normalizar($nombre);
        if ($n === 'CABA' || $n === 'CAPITAL FEDERAL' || strpos($n, 'CIUDAD AUTONOMA') !== false) {
            return 'CAPITAL FEDERAL';
        }
        if ($n === 'TIERRA DEL FUEG' || strpos($n, 'TIERRA DEL FUEGO') === 0) {
            return 'TIERRA DEL FUEGO';
        }
        if (strpos($n, 'SANTIAGO DEL ES') === 0) {
            return 'SANTIAGO DEL ESTERO';
        }
        return $n;
    }

    private function mismaProvincia($pedida, $obtenida)
    {
        $a = $this->claveProvincia($pedida);
        $b = $this->claveProvincia($obtenida);
        return $a !== '' && $a === $b;
    }

    /**
     * Si la localidad no se puede ubicar, el candidato final es la provincia
     * con el mismo nombre (Córdoba / Córdoba). Esta carga deja esa coordenada lista.
     */
    private function precargarProvincias($obra)
    {
        $obra = strtoupper(trim((string) $obra));
        if ($obra === '') {
            return;
        }
        $sql = "SELECT DISTINCT TRIM(PROVINCIA) AS provincia
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra
                   AND (FBAJA IS NULL OR TRIM(FBAJA) = '')
                   AND PROVINCIA IS NOT NULL
                   AND TRIM(PROVINCIA) <> ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            return;
        }
        $cache = $this->cache();
        $faltan = array();
        foreach ($filas as $fila) {
            $nombre = $this->nombreProvincia($fila['provincia']);
            $norm = $this->normalizar($nombre);
            if ($norm === '' || $norm === 'CAPITAL FEDERAL') {
                continue;
            }
            $clave = 'L|' . $norm . '|' . $norm;
            if (isset($cache[$clave]) && (int) $cache[$clave]['ok'] === 1) {
                continue;
            }
            $faltan[$clave] = $nombre;
        }
        if (count($faltan) === 0) {
            return;
        }
        $claves = array_keys($faltan);
        $items = array();
        foreach ($claves as $clave) {
            $items[] = array(
                'nombre'    => $faltan[$clave],
                'provincia' => $faltan[$clave],
                'max'       => 1,
                'campos'    => 'nombre,centroide,provincia.nombre',
            );
        }
        $datos = $this->postJson('https://apis.datos.gob.ar/georef/api/localidades', array(
            'localidades' => $items,
        ));
        $nuevos = array();
        $siguen = array();
        foreach ($claves as $idx => $clave) {
            $centro = null;
            $provRes = '';
            if (is_array($datos) && isset($datos['resultados'][$idx]['localidades'][0])) {
                $loc = $datos['resultados'][$idx]['localidades'][0];
                $centro = isset($loc['centroide']) ? $loc['centroide'] : null;
                $provRes = isset($loc['provincia']['nombre']) ? $loc['provincia']['nombre'] : '';
            }
            $leido = $this->leerCentroide($centro);
            if ((int) $leido['ok'] === 1 && $this->mismaProvincia($faltan[$clave], $provRes)) {
                $nuevos[$clave] = $leido;
                continue;
            }
            $siguen[$clave] = $faltan[$clave];
        }
        if (count($siguen) > 0) {
            $clavesProv = array_keys($siguen);
            $itemsProv = array();
            foreach ($clavesProv as $clave) {
                $itemsProv[] = array(
                    'nombre' => $siguen[$clave],
                    'max'    => 1,
                    'campos' => 'nombre,centroide',
                );
            }
            $datosProv = $this->postJson('https://apis.datos.gob.ar/georef/api/provincias', array(
                'provincias' => $itemsProv,
            ));
            foreach ($clavesProv as $idx => $clave) {
                $centro = null;
                if (is_array($datosProv) && isset($datosProv['resultados'][$idx]['provincias'][0]['centroide'])) {
                    $centro = $datosProv['resultados'][$idx]['provincias'][0]['centroide'];
                }
                $leido = $this->leerCentroide($centro);
                if ((int) $leido['ok'] !== 1 && $this->claveProvincia($siguen[$clave]) === 'TIERRA DEL FUEGO') {
                    $leido = array('lat' => -54.807404, 'lng' => -68.303757, 'ok' => 1);
                }
                $nuevos[$clave] = $leido;
            }
        }
        $this->persistir($nuevos);
    }

    private function sumarCandidato(&$lista, $nombre, $provincia)
    {
        $nombre = trim((string) $nombre);
        $provincia = trim((string) $provincia);
        if ($nombre === '') {
            return;
        }
        $clave = $this->normalizar($nombre) . '|' . $this->normalizar($provincia);
        if (isset($lista[$clave])) {
            return;
        }
        $lista[$clave] = array(
            'nombre'    => $nombre,
            'provincia' => $provincia,
        );
    }

    private function limpiarLocalidad($texto)
    {
        $t = strtoupper(trim((string) $texto));
        $t = preg_replace('/^B\?\s*/', 'BARRIO ', $t);
        $t = str_replace(
            array('C?RDOBA', 'CA?ADA', 'CHA?AR', 'MU?IZ', 'MALAGUE?O', 'PE?A', '4?', 'NAGUES', 'SAN FCO.', 'SAN FCO '),
            array('CORDOBA', 'CANADA', 'CHANAR', 'MUNIZ', 'MALAGUENO', 'PENA', 'CUARTO', 'NOGUES', 'SAN FRANCISCO ', 'SAN FRANCISCO '),
            $t
        );
        $t = str_replace(array('?', '.', '-', '/', '(', ')'), ' ', $t);
        $t = preg_replace('/\bDEPTO\b/', ' ', (string) $t);
        $t = preg_replace('/\bVA\b/', 'VILLA', (string) $t);
        $t = preg_replace('/\bVTE\b/', 'VICENTE', (string) $t);
        $t = preg_replace('/\bGDOR\b/', 'GOBERNADOR', (string) $t);
        $t = preg_replace('/\bING\b/', 'INGENIERO', (string) $t);
        $t = preg_replace('/\bBO\b/', 'BARRIO', (string) $t);
        $t = preg_replace('/^B\s+/', 'BARRIO ', (string) $t);
        $t = preg_replace('/\s+/', ' ', (string) $t);
        return trim((string) $t);
    }

    private function aliasLocalidad($limpio, $provincia)
    {
        $n = $this->normalizar($limpio);
        if ($n === 'VILLA NUEVA DE GUAYMALLEN' || strpos($n, 'GUAYMALLEN') !== false) {
            return 'GUAYMALLEN';
        }
        if ($n === 'MENDOZA CAPITAL') {
            return 'MENDOZA';
        }
        if ($n === 'RIO 4' || $n === 'RIO CUARTO') {
            return 'RIO CUARTO';
        }
        if ($n === 'LA PLATA BA') {
            return 'LA PLATA';
        }
        if ($n === 'SAM MIGUEL') {
            return 'SAN MIGUEL';
        }
        if ($n === 'SAN FCO SOLANO' || $n === 'SAN FRANCISCO SOLANO') {
            return 'SAN FRANCISCO SOLANO';
        }
        if ($n === 'DEPTO CAPITAL' || $n === 'CAPITAL') {
            return trim((string) $provincia);
        }
        return '';
    }

    private function ubicarCandidatos($candidatos, $cache)
    {
        $primero = true;
        foreach ($candidatos as $candidato) {
            $puesto = $this->ubicarUnCandidato($candidato, $cache, $primero);
            $primero = false;
            if ($puesto !== null) {
                return $puesto;
            }
        }
        return null;
    }

    private function ubicarUnCandidato($candidato, $cache, $esOriginal)
    {
        $nombre = $this->normalizar($candidato['nombre']);
        $provincia = $this->normalizar($candidato['provincia']);
        if ($nombre === '') {
            return null;
        }
        if ($nombre === 'CAPITAL FEDERAL' || $nombre === 'CABA' || strpos($nombre, 'CAPITAL FEDERAL ') === 0) {
            $fijo = $this->puntoCapital($candidato['provincia']);
            if ($fijo !== null) {
                return array(
                    'lat'   => $fijo['lat'],
                    'lng'   => $fijo['lng'],
                    'clave' => 'CAPITAL|' . $provincia,
                );
            }
        }
        $conProvincia = 'L|' . $nombre . '|' . $provincia;
        if ($provincia !== '' && isset($cache[$conProvincia]) && (int) $cache[$conProvincia]['ok'] === 1) {
            return array(
                'lat'   => (float) $cache[$conProvincia]['lat'],
                'lng'   => (float) $cache[$conProvincia]['lng'],
                'clave' => $conProvincia,
            );
        }
        $simple = 'L|' . $nombre;
        $usarSimple = $esOriginal || $this->aliasSeguro($nombre);
        $soloBuenosAires = array(
            'LANUS' => true,
            'GERLI' => true,
            'MERLO' => true,
            'VICENTE LOPEZ' => true,
            'SAN MIGUEL' => true,
            'ITUZAINGO' => true,
            'CLAYPOLE' => true,
            'MUNIZ' => true,
            'SAN FRANCISCO SOLANO' => true,
        );
        if (isset($soloBuenosAires[$nombre]) && $provincia !== '' && $provincia !== 'BUENOS AIRES' && $provincia !== 'CAPITAL FEDERAL') {
            $usarSimple = false;
        }
        if ($usarSimple && isset($cache[$simple]) && (int) $cache[$simple]['ok'] === 1) {
            return array(
                'lat'   => (float) $cache[$simple]['lat'],
                'lng'   => (float) $cache[$simple]['lng'],
                'clave' => $simple,
            );
        }
        return null;
    }

    private function puntoCapital($provincia)
    {
        $p = $this->normalizar($provincia);
        if ($p === 'CAPITAL FEDERAL' || $p === 'CABA' || $p === 'CIUDAD AUTONOMA DE BUENOS AIRES' || $p === 'BUENOS AIRES' || $p === '') {
            return array('lat' => -34.603722, 'lng' => -58.381592);
        }
        return null;
    }

    private function aliasSeguro($nombre)
    {
        $seguros = array(
            'GUAYMALLEN' => true,
            'MENDOZA' => true,
            'RIO CUARTO' => true,
            'CORDOBA' => true,
            'LA PLATA' => true,
            'SAN MIGUEL' => true,
            'SAN FRANCISCO SOLANO' => true,
            'PARANA' => true,
            'ITUZAINGO' => true,
            'CLAYPOLE' => true,
            'MUNIZ' => true,
            'VILLA CARLOS PAZ' => true,
            'VICENTE LOPEZ' => true,
            'LANUS' => true,
            'GERLI' => true,
            'MERLO' => true,
        );
        return isset($seguros[$nombre]);
    }

    public function listarSinUbicar($obrasoc)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        if ($obra === '') {
            return array(
                'status'  => 'error',
                'message' => 'Seleccione una obra social.',
            );
        }
        $this->asegurarTablaCorreccion();
        $this->precargarProvincias($obra);
        $sql = "SELECT TRIM(NOMBRE) AS nombre,
                       AFILIADO AS afiliado,
                       TRIM(TELEFONO) AS telefono,
                       TRIM(DOMICILIO) AS domicilio,
                       TRIM(D_PUERTA) AS puerta,
                       TRIM(LOCALIDAD) AS localidad,
                       TRIM(ZONA) AS zona,
                       TRIM(PROVINCIA) AS provincia,
                       TRIM(OBRASOC) AS obrasoc
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra
                   AND (FBAJA IS NULL OR TRIM(FBAJA) = '')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        if (!is_array($filas)) {
            $filas = array();
        }
        $padron = $this->lugaresPadron($obra);
        $correcciones = $this->correccionesObra($obra);
        $cache = $this->cache();
        $lista = array();
        foreach ($filas as $fila) {
            $fila['afiliado'] = $fila['afiliado'];
            if ($this->resolverPuesto($fila, $padron, $cache, $correcciones) !== null) {
                continue;
            }
            $clave = $this->claveAfiliado($fila['afiliado']);
            $corr = ($clave !== '' && isset($correcciones[$clave])) ? $correcciones[$clave] : null;
            $zona = trim((string) $fila['zona']);
            if ($zona === '') {
                $zona = trim((string) $fila['provincia']);
            }
            $lista[] = array(
                'afiliado'   => $clave,
                'nombre'     => $corr && $corr['nombre'] !== '' ? $corr['nombre'] : trim((string) $fila['nombre']),
                'direccion'  => $corr && $corr['direccion'] !== '' ? $corr['direccion'] : $this->direccionAfiliado($fila['domicilio'], $fila['puerta']),
                'telefono'   => $corr && $corr['telefono'] !== '' ? $corr['telefono'] : trim((string) $fila['telefono']),
                'localidad'  => $corr && $corr['localidad'] !== '' ? $corr['localidad'] : trim((string) $fila['localidad']),
                'zona'       => $corr && $corr['zona'] !== '' ? $corr['zona'] : $zona,
                'obrasoc'    => $obra,
                'corregido'  => $corr ? 1 : 0,
            );
        }
        return array(
            'status'  => 'ok',
            'total'   => count($lista),
            'filas'   => $lista,
            'message' => '',
        );
    }

    public function guardarCorreccion($obrasoc, $datos)
    {
        $obra = strtoupper(trim((string) $obrasoc));
        $afiliado = $this->claveAfiliado(isset($datos['afiliado']) ? $datos['afiliado'] : '');
        if ($obra === '' || $afiliado === '') {
            return array(
                'status'  => 'error',
                'message' => 'Falta la obra social o el afiliado.',
            );
        }
        $this->asegurarTablaCorreccion();
        $nombre = $this->recortar(isset($datos['nombre']) ? $datos['nombre'] : '', 80);
        $direccion = $this->recortar(isset($datos['direccion']) ? $datos['direccion'] : '', 120);
        $telefono = $this->recortar(isset($datos['telefono']) ? $datos['telefono'] : '', 40);
        $localidad = $this->recortar(isset($datos['localidad']) ? $datos['localidad'] : '', 80);
        $zona = $this->recortar(isset($datos['zona']) ? $datos['zona'] : '', 40);

        $lat = null;
        $lng = null;
        $puesto = $this->geocodificarTexto($localidad, $zona);
        if ($puesto !== null) {
            $lat = $puesto['lat'];
            $lng = $puesto['lng'];
        }

        $sql = "INSERT INTO vfpmedicos.mapa_correccion
                    (obrasoc, afiliado, nombre, direccion, telefono, localidad, zona, lat, lng, actualizado)
                VALUES
                    (:obra, :afiliado, :nombre, :direccion, :telefono, :localidad, :zona, :lat, :lng, NOW())
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    direccion = VALUES(direccion),
                    telefono = VALUES(telefono),
                    localidad = VALUES(localidad),
                    zona = VALUES(zona),
                    lat = VALUES(lat),
                    lng = VALUES(lng),
                    actualizado = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            ':obra'      => $obra,
            ':afiliado'  => $afiliado,
            ':nombre'    => $nombre,
            ':direccion' => $direccion,
            ':telefono'  => $telefono,
            ':localidad' => $localidad,
            ':zona'      => $zona,
            ':lat'       => $lat,
            ':lng'       => $lng,
        ));

        return array(
            'status'   => 'ok',
            'ubicado'  => $puesto !== null ? 1 : 0,
            'afiliado' => $afiliado,
            'message'  => $puesto !== null
                ? 'Guardado. El afiliado ya se puede ubicar y buscar en el mapa.'
                : 'Guardado. Esa localidad todavía no tiene coordenadas.',
        );
    }

    private function geocodificarTexto($localidad, $zona)
    {
        $localidad = trim((string) $localidad);
        if ($localidad === '') {
            return null;
        }
        $cache = $this->cache();
        $puesto = $this->ubicarCandidatos($this->candidatosLugar($localidad, $zona), $cache);
        if ($puesto !== null) {
            return $puesto;
        }
        $item = array(
            'nombre' => $localidad,
            'max'    => 1,
            'campos' => 'nombre,centroide',
        );
        if (trim((string) $zona) !== '') {
            $item['provincia'] = $zona;
        }
        $datos = $this->postJson('https://apis.datos.gob.ar/georef/api/localidades', array(
            'localidades' => array($item),
        ));
        $centro = null;
        if (is_array($datos) && isset($datos['resultados'][0]['localidades'][0]['centroide'])) {
            $centro = $datos['resultados'][0]['localidades'][0]['centroide'];
        }
        $leido = $this->leerCentroide($centro);
        $clave = 'L|' . $this->normalizar($localidad) . '|' . $this->normalizar($zona);
        $this->persistir(array($clave => $leido));
        if ((int) $leido['ok'] !== 1) {
            return null;
        }
        return array(
            'lat'   => (float) $leido['lat'],
            'lng'   => (float) $leido['lng'],
            'clave' => $clave,
        );
    }

    private function correccionesObra($obra)
    {
        $mapa = array();
        try {
            $this->asegurarTablaCorreccion();
            $sql = "SELECT afiliado, nombre, direccion, telefono, localidad, zona, lat, lng
                      FROM vfpmedicos.mapa_correccion
                     WHERE obrasoc = :obra";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':obra' => $obra));
            $filas = $stmt->fetchAll();
        } catch (Exception $e) {
            return $mapa;
        }
        if (!is_array($filas)) {
            return $mapa;
        }
        foreach ($filas as $fila) {
            $clave = $this->claveAfiliado($fila['afiliado']);
            if ($clave === '') {
                continue;
            }
            $lat = $fila['lat'];
            $lng = $fila['lng'];
            $mapa[$clave] = array(
                'nombre'    => trim((string) $fila['nombre']),
                'direccion' => trim((string) $fila['direccion']),
                'telefono'  => trim((string) $fila['telefono']),
                'localidad' => trim((string) $fila['localidad']),
                'zona'      => trim((string) $fila['zona']),
                'lat'       => ($lat === null || $lat === '') ? null : (float) $lat,
                'lng'       => ($lng === null || $lng === '') ? null : (float) $lng,
            );
        }
        return $mapa;
    }

    private function asegurarTablaCorreccion()
    {
        $sql = "CREATE TABLE IF NOT EXISTS vfpmedicos.mapa_correccion (
                    id INT NOT NULL AUTO_INCREMENT,
                    obrasoc VARCHAR(12) NOT NULL,
                    afiliado VARCHAR(20) NOT NULL,
                    nombre VARCHAR(80) NOT NULL DEFAULT '',
                    direccion VARCHAR(120) NOT NULL DEFAULT '',
                    telefono VARCHAR(40) NOT NULL DEFAULT '',
                    localidad VARCHAR(80) NOT NULL DEFAULT '',
                    zona VARCHAR(40) NOT NULL DEFAULT '',
                    lat DECIMAL(10,6) NULL,
                    lng DECIMAL(10,6) NULL,
                    actualizado DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uk_mapa_correccion (obrasoc, afiliado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        $this->db->exec($sql);
    }

    private function recortar($texto, $largo)
    {
        $texto = trim((string) $texto);
        if (strlen($texto) > $largo) {
            $texto = substr($texto, 0, $largo);
        }
        return $texto;
    }

    private function lugaresParaGeoref($obra, $cache)
    {
        $sql = "SELECT AFILIADO AS afiliado, TRIM(LOCALIDAD) AS loc, TRIM(PROVINCIA) AS prov
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra
                   AND (FBAJA IS NULL OR TRIM(FBAJA) = '')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $padron = $this->lugaresPadron($obra);
        $correcciones = $this->correccionesObra($obra);
        $faltan = array();
        if (!is_array($filas)) {
            return $faltan;
        }
        foreach ($filas as $fila) {
            $fila['localidad'] = $fila['loc'];
            $fila['provincia'] = $fila['prov'];
            if ($this->resolverPuesto($fila, $padron, $cache, $correcciones) !== null) {
                continue;
            }
            $candidatos = $this->candidatosLugar($fila['loc'], $fila['prov']);
            $claveAfi = $this->claveAfiliado($fila['afiliado']);
            if ($claveAfi !== '' && isset($padron[$claveAfi])) {
                $candidatos = array_merge(
                    $candidatos,
                    $this->candidatosLugar($padron[$claveAfi]['loc'], $padron[$claveAfi]['prov'])
                );
            }
            foreach ($candidatos as $candidato) {
                $nombre = $this->normalizar($candidato['nombre']);
                $provincia = $this->normalizar($candidato['provincia']);
                if ($nombre === '' || $nombre === 'CAPITAL FEDERAL' || $nombre === 'CABA') {
                    continue;
                }
                $clave = 'L|' . $nombre . '|' . $provincia;
                if (isset($cache[$clave]) || isset($faltan[$clave])) {
                    continue;
                }
                $faltan[$clave] = array(
                    'nombre'    => $candidato['nombre'],
                    'provincia' => $candidato['provincia'],
                );
            }
        }
        return $faltan;
    }

    private function discapacidadVigente($obra)
    {
        $mapa = array();
        $corte = $this->corte($obra);
        if ($corte['periodo'] === '') {
            return $mapa;
        }
        try {
            $filtro = $this->filtroCorte($obra, $corte);
            $sql = "SELECT NRODOC AS dni, TRIM(ENROL1) AS enrol
                      FROM vfpmedicos.t_padron"
                 . $filtro['sql']
                 . " AND TRIM(ENROL1) <> ''
                    AND UPPER(TRIM(ENROL1)) NOT IN ('COMUN', 'COMUNES', '-')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtro['params']);
            $filas = $stmt->fetchAll();
        } catch (Exception $e) {
            return $mapa;
        }
        if (!is_array($filas)) {
            return $mapa;
        }
        foreach ($filas as $fila) {
            $dni = (int) $fila['dni'];
            $texto = trim((string) $fila['enrol']);
            if ($dni <= 0 || $texto === '' || isset($mapa[$dni])) {
                continue;
            }
            $mapa[$dni] = $texto;
        }
        return $mapa;
    }

    private function localidadesActivas($obra)
    {
        $sql = "SELECT TRIM(LOCALIDAD) AS localidad, TRIM(PROVINCIA) AS provincia
                  FROM vfpmedicos.unicos
                 WHERE OBRASOC = :obra
                   AND (FBAJA IS NULL OR TRIM(FBAJA) = '')
                 GROUP BY TRIM(LOCALIDAD), TRIM(PROVINCIA)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':obra' => $obra));
        $filas = $stmt->fetchAll();
        $nombres = array();
        $vistos = array();
        if (!is_array($filas)) {
            return array();
        }
        foreach ($filas as $fila) {
            $lugar = $this->lugarFila($fila['localidad'], $fila['provincia']);
            $clave = $this->normalizar($lugar);
            if ($clave === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $nombres[] = $lugar;
        }
        return $nombres;
    }

    private function lugarFila($localidad, $provincia)
    {
        $localidad = trim((string) $localidad);
        if ($localidad !== '') {
            return $localidad;
        }
        return trim((string) $provincia);
    }

    private function direccionAfiliado($domicilio, $puerta)
    {
        $domicilio = trim((string) $domicilio);
        $puerta = trim((string) $puerta);
        if ($puerta !== '' && $puerta !== '0' && $puerta !== '00' && $puerta !== '00000') {
            if ($domicilio === '') {
                return $puerta;
            }
            return trim($domicilio . ' ' . $puerta);
        }
        return $domicilio;
    }

    private function numeroAfiliado($valor)
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        if (strpos($texto, '.') !== false) {
            $texto = rtrim(rtrim($texto, '0'), '.');
        }
        return $texto;
    }

    private function primeraParte($texto)
    {
        $partes = explode(',', (string) $texto);
        return trim($partes[0]);
    }

    private function limpiarCalle($calle)
    {
        $calle = trim((string) preg_replace('/\s+/', ' ', (string) $calle));
        $calle = preg_replace('/\b(PISO|DTO|DEPTO|DPTO|CASA|TEL|TELEFONO)\b.*$/i', '', $calle);
        $calle = trim((string) $calle, " ,.-");
        if ($calle === '' || strlen($calle) < 4) {
            return '';
        }
        return $calle;
    }

    private function provinciaDesdeZona($zona, $dirmap)
    {
        $z = $this->normalizar($zona);
        if ($z === 'CAPITAL FEDERAL' || $z === 'CABA' || strpos($z, 'CAPITAL') !== false) {
            return 'caba';
        }
        if (strpos($z, 'GBA') === 0 || $z === 'BUENOS AIRES') {
            return 'Buenos Aires';
        }
        $provincias = array(
            'CORDOBA'              => 'Córdoba',
            'ENTRE RIOS'           => 'Entre Ríos',
            'CORRIENTES'           => 'Corrientes',
            'LA RIOJA'             => 'La Rioja',
            'CHACO'                => 'Chaco',
            'LA PAMPA'             => 'La Pampa',
            'SAN LUIS'             => 'San Luis',
            'SAN JUAN'             => 'San Juan',
            'MENDOZA'              => 'Mendoza',
            'SANTA FE'             => 'Santa Fe',
            'TUCUMAN'              => 'Tucumán',
            'SALTA'                => 'Salta',
            'MISIONES'             => 'Misiones',
            'NEUQUEN'              => 'Neuquén',
            'CHUBUT'               => 'Chubut',
            'RIO NEGRO'            => 'Río Negro',
            'FORMOSA'              => 'Formosa',
            'JUJUY'                => 'Jujuy',
            'CATAMARCA'            => 'Catamarca',
            'SANTIAGO DEL ESTERO'  => 'Santiago del Estero',
            'TIERRA DEL FUEGO'     => 'Tierra del Fuego',
            'SANTA CRUZ'           => 'Santa Cruz',
        );
        if (isset($provincias[$z])) {
            return $provincias[$z];
        }

        $texto = $this->normalizar($dirmap);
        if (strpos($texto, 'CAPITAL FEDERAL') !== false) {
            return 'caba';
        }
        foreach ($provincias as $nombre => $valor) {
            if (strpos($texto, $nombre) !== false) {
                return $valor;
            }
        }
        if (strpos($texto, 'BUENOS AIRES') !== false) {
            return 'Buenos Aires';
        }
        return '';
    }

    private function localidadAmbigua($lugar, $zona, $dirmap)
    {
        if ($this->provinciaDesdeZona($zona, $dirmap) === 'caba') {
            return true;
        }
        return $this->esBarrioCaba($this->normalizar($lugar));
    }

    private function esBarrioCaba($normalizado)
    {
        if ($normalizado === '') {
            return false;
        }
        $barrios = array(
            'CENTRO' => true, 'CABALLITO' => true, 'PALERMO' => true, 'BELGRANO' => true,
            'FLORES' => true, 'ALMAGRO' => true, 'RECOLETA' => true, 'RETIRO' => true,
            'SAN NICOLAS' => true, 'MONTSERRAT' => true, 'BALVANERA' => true, 'BOEDO' => true,
            'LINIERS' => true, 'MATADEROS' => true, 'VILLA DEVOTO' => true, 'COLEGIALES' => true,
            'NUNEZ' => true, 'SAAVEDRA' => true, 'VILLA CRESPO' => true, 'BARRACAS' => true,
            'BOCA' => true, 'CONSTITUCION' => true, 'CHACARITA' => true, 'VILLA URQUIZA' => true,
            'PARQUE PATRICIOS' => true, 'VILLA LURO' => true, 'VERSALLES' => true,
            'MONTE CASTRO' => true, 'VILLA DEL PARQUE' => true, 'AGRONOMIA' => true,
            'PARQUE CHACABUCO' => true, 'NUEVA POMPEYA' => true, 'PUERTO MADERO' => true,
        );
        return isset($barrios[$normalizado]);
    }

    private function espiral($lat, $lng, $indice, $total)
    {
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($total <= 1) {
            return array(round($lat, 6), round($lng, 6));
        }
        $radio = 0.0022 * sqrt($total / 40);
        if ($radio < 0.0008) {
            $radio = 0.0008;
        }
        if ($radio > 0.018) {
            $radio = 0.018;
        }
        $ang = $indice * 2.399963229728653;
        $r = $radio * sqrt(($indice + 0.5) / $total);
        $lat2 = $lat + ($r * cos($ang));
        $coseno = cos(deg2rad($lat));
        if ($coseno < 0.2) {
            $coseno = 0.2;
        }
        $lng2 = $lng + (($r * sin($ang)) / $coseno);
        return array(round($lat2, 6), round($lng2, 6));
    }

    private function leerCentroide($centro)
    {
        if (!is_array($centro)) {
            return array('lat' => null, 'lng' => null, 'ok' => 0);
        }
        $lat = null;
        $lng = null;
        if (isset($centro['lat'])) {
            $lat = (float) $centro['lat'];
        }
        if (isset($centro['lon'])) {
            $lng = (float) $centro['lon'];
        } elseif (isset($centro['lng'])) {
            $lng = (float) $centro['lng'];
        }
        if ($lat === null || $lng === null || !$this->enArgentina($lat, $lng)) {
            return array('lat' => null, 'lng' => null, 'ok' => 0);
        }
        return array(
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
            'ok'  => 1,
        );
    }

    private function enArgentina($lat, $lng)
    {
        return $lat <= -20 && $lat >= -56 && $lng <= -50 && $lng >= -76;
    }

    private function hayTiempo($inicio)
    {
        return (microtime(true) - $inicio) < $this->presupuesto;
    }

    private function normalizar($texto)
    {
        $texto = strtoupper(trim((string) $texto));
        $texto = strtr($texto, array(
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        ));
        $texto = preg_replace('/[^A-Z0-9 ]/', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', (string) $texto);
        return trim((string) $texto);
    }

    private function rutaCache()
    {
        return dirname(dirname(__DIR__)) . '/storage/geo_cache.json';
    }

    private function cache()
    {
        if (is_array($this->cache)) {
            return $this->cache;
        }
        $ruta = $this->rutaCache();
        if (!is_file($ruta)) {
            $this->cache = array();
            return $this->cache;
        }
        $texto = file_get_contents($ruta);
        $datos = json_decode((string) $texto, true);
        $this->cache = is_array($datos) ? $datos : array();
        return $this->cache;
    }

    private function persistir($nuevos)
    {
        if (!is_array($nuevos) || count($nuevos) === 0) {
            return;
        }
        $ruta = $this->rutaCache();
        $dir = dirname($ruta);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen($ruta, 'c+');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        $texto = stream_get_contents($fp);
        $actual = json_decode((string) $texto, true);
        if (!is_array($actual)) {
            $actual = array();
        }
        foreach ($nuevos as $clave => $valor) {
            $actual[$clave] = $valor;
        }
        ftruncate($fp, 0);
        rewind($fp);
        $json = json_encode($actual);
        if ($json === false) {
            $json = '{}';
        }
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $this->cache = $actual;
    }

    private function postJson($url, $payload)
    {
        $cuerpo = json_encode($payload);
        if ($cuerpo === false) {
            return null;
        }
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) {
            return null;
        }
        $datos = json_decode($resp, true);
        return is_array($datos) ? $datos : null;
    }
}
