<?php
if (!isset($obras) || !is_array($obras)) {
    $obras = array();
}
if (!isset($categorias) || !is_array($categorias)) {
    $categorias = array();
}
$csrfMapa = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
$mensajeCarga = isset($errorCarga) ? $errorCarga : '';
$flagsJson = 0;
if (defined('JSON_UNESCAPED_UNICODE')) {
    $flagsJson = JSON_UNESCAPED_UNICODE;
}
$categoriasJson = json_encode($categorias, $flagsJson);
if ($categoriasJson === false) {
    $categoriasJson = '[]';
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
    #mapaCobertura { height: calc(100vh - 210px); min-height: 520px; border-radius: 0 0 1rem 1rem; }
    .mapa-tri, .mapa-grupo { background: transparent; border: 0; }
    .leaflet-tooltip.mapa-tip {
        background: #fff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .16);
        padding: 8px 10px;
        font-size: 12px;
        line-height: 1.35;
    }
    .leaflet-tooltip.mapa-tip .mapa-tip-titulo { font-weight: 700; margin-bottom: 2px; }
    .leaflet-tooltip.mapa-tip .mapa-tip-cat { font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
</style>

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-lg font-bold text-[#0d47a1]">
            <i class="fa-solid fa-map-location-dot mr-2"></i>Mapa de cobertura
        </h1>
        <p class="text-sm text-slate-500">Afiliados activos de unicos (FBAJA vacía) y prestadores de la obra elegida.</p>
    </div>
    <p id="estadoMapa" class="text-xs font-medium text-slate-500">Seleccione una obra social para cargar el mapa.</p>
</div>

<?php if ($mensajeCarga !== ''): ?>
<p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?php echo h($mensajeCarga); ?></p>
<?php endif; ?>

<div class="grid items-start gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <label for="filtroObra" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Obra social</label>
            <select id="filtroObra" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800">
                <option value="">Seleccione una obra</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo h($obra['codigo']); ?>"><?php echo h($obra['etiqueta']); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                <input id="verAfiliados" type="checkbox" class="h-4 w-4 accent-[#1565c0]" checked>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full border border-[#145a22] bg-[#2e7d32]"></span>
                    Ver afiliados
                </span>
            </label>
            <p id="resumenAfiliados" class="mt-2 text-xs text-slate-500">Sin obra seleccionada. Los activos son los de unicos con FBAJA vacía.</p>
            <button type="button" id="linkSinUbicar" class="mt-1 hidden text-left text-xs font-semibold text-[#1565c0] underline"></button>
            <div id="listaDisca" class="mt-2 space-y-1"></div>
            <button type="button" id="btnReintentarAfiliados" class="mt-3 hidden rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-[#1565c0] hover:bg-blue-50">Reintentar afiliados</button>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prestadores</p>
                <span class="inline-flex items-center gap-1 text-[11px] text-slate-500">
                    <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><polygon points="12,2 22,22 2,22" fill="#00bcd4"></polygon></svg>
                    triángulo
                </span>
            </div>
            <label class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-800">
                <input id="verPrestadores" type="checkbox" class="h-4 w-4 accent-[#1565c0]" checked>
                Ver sucursales
            </label>
            <div class="mb-2 flex gap-2">
                <button type="button" id="btnCatsTodas" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">Todas</button>
                <button type="button" id="btnCatsNinguna" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">Ninguna</button>
            </div>
            <div id="listaCategorias" class="max-h-80 space-y-1 overflow-y-auto pr-1">
                <p class="px-1 text-xs text-slate-400">Se listan al elegir la obra social.</p>
                <?php foreach ($categorias as $cat): ?>
                <label class="flex items-center justify-between gap-2 rounded-lg px-1 py-1 text-sm text-slate-700 hover:bg-slate-50">
                    <span class="flex min-w-0 items-center gap-2">
                        <input type="checkbox" class="js-cat h-4 w-4" data-categ="<?php echo h($cat['codigo']); ?>" checked>
                        <span class="inline-block h-3 w-3 shrink-0" style="background:<?php echo h($cat['color']); ?>;clip-path:polygon(50% 0,100% 100%,0 100%)"></span>
                        <span class="truncate"><?php echo h($cat['codigo']); ?></span>
                    </span>
                    <span class="shrink-0 text-xs text-slate-400"><?php echo (int) $cat['total']; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <p id="resumenPrestadores" class="mt-2 text-xs text-slate-500">Se cargan al elegir la obra social.</p>
            <button type="button" id="btnReintentarPrestadores" class="mt-3 hidden rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-[#1565c0] hover:bg-blue-50">Reintentar prestadores</button>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <label for="buscarMapa" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Buscar</label>
            <div class="flex gap-2">
                <input id="buscarMapa" type="search" placeholder="Nombre, NroA o prestador" class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                <button type="button" id="btnBuscar" class="rounded-lg bg-[#1565c0] px-3 text-sm font-semibold text-white hover:bg-[#0d47a1]">Ir</button>
            </div>
            <p class="mt-3 text-[11px] leading-relaxed text-slate-400">El círculo verde es un afiliado sin condición. Los otros colores del círculo son las condiciones de la lista. El grupo verde con número también son afiliados. El grupo azul con número son prestadores, y suelto cada uno es un triángulo.</p>
        </section>
    </aside>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div id="mapaCobertura"></div>
    </section>
</div>

<div id="modalSinUbicar" class="fixed inset-0 z-[2000] hidden items-center justify-center bg-slate-900/50 p-4">
    <section class="flex max-h-[88vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Afiliados sin ubicar</h2>
                <p id="tituloSinUbicar" class="text-xs text-slate-500">Corregí la localidad o la zona y guardá. El mapa usa esa corrección para ubicar y para buscar.</p>
            </div>
            <button type="button" id="cerrarSinUbicar" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Cerrar</button>
        </header>
        <div class="border-b border-slate-100 px-4 py-2">
            <input id="buscarSinUbicar" type="search" placeholder="Buscar afiliado, nombre, dirección, teléfono, localidad o zona" class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
        </div>
        <div class="overflow-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="sticky top-0 bg-slate-50 text-[11px] uppercase text-slate-500">
                    <tr>
                        <th class="px-2 py-2">Afiliado</th>
                        <th class="px-2 py-2">Nombre</th>
                        <th class="px-2 py-2">Dirección</th>
                        <th class="px-2 py-2">Teléfono</th>
                        <th class="px-2 py-2">Localidad</th>
                        <th class="px-2 py-2">Zona</th>
                        <th class="px-2 py-2">Obra social</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody id="cuerpoSinUbicar"></tbody>
            </table>
        </div>
    </section>
</div>

<input type="hidden" id="csrfMapa" value="<?php echo h($csrfMapa); ?>">

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
    var categorias = <?php echo $categoriasJson; ?>;
    var colores = {};
    var i;
    for (i = 0; i < categorias.length; i++) {
        colores[categorias[i].codigo] = categorias[i].color;
    }

    var mapa = L.map('mapaCobertura', { scrollWheelZoom: true }).setView([-34.61, -58.45], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    function iconoGrupo(cluster, prestador) {
        var n = cluster.getChildCount();
        var fondo = prestador
            ? (n < 10 ? '#81d4fa' : (n < 100 ? '#0277bd' : '#01579b'))
            : (n < 10 ? '#a5d6a7' : (n < 100 ? '#2e7d32' : '#1b5e20'));
        var tinta = n < 10 ? (prestador ? '#01579b' : '#1b5e20') : '#ffffff';
        var sombra = prestador ? 'rgba(1,87,155,.28)' : 'rgba(27,94,32,.28)';
        var medida = n < 100 ? 34 : 42;
        return L.divIcon({
            html: '<div style="background:' + fondo + ';color:' + tinta + ';width:' + medida + 'px;height:' + medida + 'px;line-height:' + medida + 'px;border-radius:50%;text-align:center;font-weight:700;font-size:12px;box-shadow:0 0 0 4px ' + sombra + '">' + n + '</div>',
            className: 'mapa-grupo',
            iconSize: [medida, medida],
            iconAnchor: [medida / 2, medida / 2]
        });
    }

    function iconoGrupoAfiliados(cluster) {
        return iconoGrupo(cluster, false);
    }

    function iconoGrupoPrestadores(cluster) {
        return iconoGrupo(cluster, true);
    }

    var grupoAfiliados = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 48,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        iconCreateFunction: iconoGrupoAfiliados
    });
    var gruposPrestadores = {};
    var marcasAfiliados = [];
    var marcasPrestadores = [];
    var pedidoAfiliados = 0;
    var pedidoPrestadores = 0;
    var afiliadosCargados = false;

    function esc(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function dato(texto) {
        var limpio = String(texto == null ? '' : texto).replace(/^\s+|\s+$/g, '');
        return limpio === '' ? '—' : esc(limpio);
    }

    function linea(etiqueta, valor) {
        return '<div><span style="color:#64748b">' + etiqueta + ':</span> ' + dato(valor) + '</div>';
    }

    function tooltipAfiliado(p) {
        var disca = p[9] ? '<div class="mapa-tip-cat" style="color:' + esc(p[10] || '#7b1fa2') + '">' + dato(p[9]) + '</div>' : '';
        return disca
            + '<div class="mapa-tip-titulo">' + dato(p[2]) + '</div>'
            + linea('NroA', p[3])
            + linea('Afiliado', p[4])
            + linea('Teléfono', p[5])
            + linea('Dirección', p[6])
            + linea('Localidad', p[7])
            + linea('Provincia', p[8]);
    }

    function tooltipPrestador(p) {
        var extra = p[8] ? '<div style="margin-top:4px;color:#b45309">Ubicación aproximada por localidad</div>' : '';
        return '<div class="mapa-tip-cat" style="color:' + esc(colores[p[2]] || '#1565c0') + '">' + dato(p[2]) + '</div>'
            + '<div class="mapa-tip-titulo">' + dato(p[3]) + '</div>'
            + linea('Dirección', p[4])
            + linea('Teléfono', p[5])
            + linea('Localidad', p[6])
            + linea('Zona', p[7])
            + extra;
    }

    function extraZoom() {
        var extra = mapa.getZoom() - 13;
        if (extra < 0) {
            extra = 0;
        }
        if (extra > 4) {
            extra = 4;
        }
        return extra;
    }

    function iconoTriangulo(color, lado) {
        lado = lado || (18 + extraZoom() * 4);
        return L.divIcon({
            className: 'mapa-tri',
            html: '<svg width="' + lado + '" height="' + lado + '" viewBox="0 0 24 24"><polygon points="12,1.5 22.5,22 1.5,22" fill="' + color + '" stroke="#ffffff" stroke-width="1.6"></polygon></svg>',
            iconSize: [lado, lado],
            iconAnchor: [lado / 2, lado],
            tooltipAnchor: [0, -lado]
        });
    }

    function ajustarTamanoMarcas() {
        var extra = extraZoom();
        if (ajustarTamanoMarcas.ultimo === extra) {
            return;
        }
        ajustarTamanoMarcas.ultimo = extra;
        var i;
        for (i = 0; i < marcasAfiliados.length; i++) {
            var circulo = marcasAfiliados[i];
            circulo.setRadius((circulo._radioBase || 6) + extra * 2);
        }
        var lado = 18 + extra * 4;
        for (i = 0; i < marcasPrestadores.length; i++) {
            var tri = marcasPrestadores[i];
            tri.setIcon(iconoTriangulo(tri._colorTri || '#546e7a', lado));
        }
    }

    function ponerTooltip(marca, html) {
        marca.bindTooltip(html, { className: 'mapa-tip', direction: 'top', opacity: 1, sticky: true });
    }

    function textoBusqueda(partes) {
        return String(partes.join(' ')).toUpperCase();
    }

    function entero(n) {
        n = parseInt(n, 10) || 0;
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function estado(texto) {
        var el = document.getElementById('estadoMapa');
        if (el) {
            el.textContent = texto;
        }
    }

    function pedir(url, cuerpo) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo,
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.text().then(function (texto) {
                try {
                    return JSON.parse(texto);
                } catch (e) {
                    return null;
                }
            });
        });
    }

    function cuerpoObra() {
        var obra = document.getElementById('filtroObra').value;
        return 'obrasoc=' + encodeURIComponent(obra)
            + '&csrf_token=' + encodeURIComponent(document.getElementById('csrfMapa').value);
    }

    function cuerpoToken() {
        return 'csrf_token=' + encodeURIComponent(document.getElementById('csrfMapa').value);
    }

    function categoriaVisible(codigo) {
        var master = document.getElementById('verPrestadores');
        if (master && !master.checked) {
            return false;
        }
        var casillas = document.getElementsByClassName('js-cat');
        var k;
        for (k = 0; k < casillas.length; k++) {
            if (casillas[k].getAttribute('data-categ') === codigo) {
                return casillas[k].checked;
            }
        }
        return true;
    }

    function aplicarCapasPrestadores() {
        var codigo;
        for (codigo in gruposPrestadores) {
            if (!gruposPrestadores.hasOwnProperty(codigo)) {
                continue;
            }
            if (categoriaVisible(codigo)) {
                if (!mapa.hasLayer(gruposPrestadores[codigo])) {
                    mapa.addLayer(gruposPrestadores[codigo]);
                }
            } else if (mapa.hasLayer(gruposPrestadores[codigo])) {
                mapa.removeLayer(gruposPrestadores[codigo]);
            }
        }
    }

    function aplicarCapaAfiliados() {
        var ver = document.getElementById('verAfiliados').checked;
        if (ver && afiliadosCargados) {
            if (!mapa.hasLayer(grupoAfiliados)) {
                mapa.addLayer(grupoAfiliados);
            }
        } else if (mapa.hasLayer(grupoAfiliados)) {
            mapa.removeLayer(grupoAfiliados);
        }
    }

    function dibujarAfiliados(puntos) {
        grupoAfiliados.clearLayers();
        marcasAfiliados = [];
        var k;
        for (k = 0; k < puntos.length; k++) {
            var p = puntos[k];
            var colorDisca = p[10] || '';
            var radioBase = colorDisca ? 7 : 6;
            var marca = L.circleMarker([p[0], p[1]], {
                radius: radioBase + extraZoom() * 2,
                color: colorDisca ? '#ffffff' : '#145a22',
                weight: colorDisca ? 2 : 1,
                fillColor: colorDisca || '#2e7d32',
                fillOpacity: 0.95
            });
            marca._radioBase = radioBase;
            ponerTooltip(marca, tooltipAfiliado(p));
            marca._busca = textoBusqueda([p[2], p[3], p[4], p[5], p[6], p[7], p[8], p[9]]);
            marcasAfiliados.push(marca);
        }
        grupoAfiliados.addLayers(marcasAfiliados);
        afiliadosCargados = true;
        aplicarCapaAfiliados();
        if (puntos.length > 0) {
            var bounds = L.latLngBounds([puntos[0][0], puntos[0][1]], [puntos[0][0], puntos[0][1]]);
            for (k = 1; k < puntos.length; k++) {
                bounds.extend([puntos[k][0], puntos[k][1]]);
            }
            mapa.fitBounds(bounds.pad(0.12));
        }
    }

    function dibujarPrestadores(puntos) {
        var codigo;
        for (codigo in gruposPrestadores) {
            if (gruposPrestadores.hasOwnProperty(codigo)) {
                mapa.removeLayer(gruposPrestadores[codigo]);
            }
        }
        gruposPrestadores = {};
        marcasPrestadores = [];
        var porCodigo = {};
        var k;
        for (k = 0; k < puntos.length; k++) {
            var p = puntos[k];
            codigo = p[2] || 'S/C';
            if (!gruposPrestadores[codigo]) {
                gruposPrestadores[codigo] = L.markerClusterGroup({
                    chunkedLoading: true,
                    maxClusterRadius: 42,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false,
                    iconCreateFunction: iconoGrupoPrestadores
                });
                porCodigo[codigo] = [];
            }
            var colorTri = colores[codigo] || '#546e7a';
            var marca = L.marker([p[0], p[1]], {
                icon: iconoTriangulo(colorTri)
            });
            marca._colorTri = colorTri;
            ponerTooltip(marca, tooltipPrestador(p));
            marca._busca = textoBusqueda([p[2], p[3], p[4], p[6]]);
            marca._categ = codigo;
            marcasPrestadores.push(marca);
            porCodigo[codigo].push(marca);
        }
        for (codigo in porCodigo) {
            if (porCodigo.hasOwnProperty(codigo)) {
                gruposPrestadores[codigo].addLayers(porCodigo[codigo]);
            }
        }
        aplicarCapasPrestadores();
    }

    function cargarAfiliados() {
        var obra = document.getElementById('filtroObra').value;
        var resumen = document.getElementById('resumenAfiliados');
        var reintento = document.getElementById('btnReintentarAfiliados');
        pedidoAfiliados++;
        var actual = pedidoAfiliados;
        if (obra === '') {
            grupoAfiliados.clearLayers();
            marcasAfiliados = [];
            afiliadosCargados = false;
            aplicarCapaAfiliados();
            resumen.textContent = 'Sin obra seleccionada. Los activos son los de unicos con FBAJA vacía.';
            pintarDisca([]);
            var linkVacio = document.getElementById('linkSinUbicar');
            if (linkVacio) {
                linkVacio.classList.add('hidden');
            }
            reintento.classList.add('hidden');
            estado('Seleccione una obra social para cargar el mapa.');
            return;
        }
        reintento.classList.add('hidden');
        resumen.textContent = 'Ubicando localidades…';
        estado('Ubicando localidades de la obra…');

        function paso() {
            pedir('index.php?c=dashboard&a=ajaxGetMapaPreparar', cuerpoObra()).then(function (data) {
                if (actual !== pedidoAfiliados) {
                    return;
                }
                if (!data || data.status !== 'ok') {
                    resumen.textContent = (data && data.message) ? data.message : 'No se pudo preparar el mapa.';
                    reintento.classList.remove('hidden');
                    return;
                }
                resumen.textContent = 'Localidades ubicadas: ' + entero(data.ubicadas) + ' de ' + entero(data.localidades) + '.';
                if (data.pendientes > 0 && data.georef && data.avance > 0) {
                    estado('Ubicando localidades ' + entero(data.ubicadas) + ' / ' + entero(data.localidades) + '…');
                    paso();
                    return;
                }
                if (data.pendientes > 0 && !data.georef) {
                    resumen.textContent = data.message || 'Falta ubicar localidades.';
                    reintento.classList.remove('hidden');
                }
                estado('Leyendo afiliados activos de unicos…');
                pedir('index.php?c=dashboard&a=ajaxGetMapaAfiliados', cuerpoObra()).then(function (puntos) {
                    if (actual !== pedidoAfiliados) {
                        return;
                    }
                    if (!puntos || puntos.status !== 'ok') {
                        resumen.textContent = (puntos && puntos.message) ? puntos.message : 'No se pudo leer el padrón.';
                        return;
                    }
                    dibujarAfiliados(puntos.puntos || []);
                    pintarDisca(puntos.discapacidad || []);
                    var sinUbicar = Number(puntos.sin_ubicar) || 0;
                    resumen.textContent = 'Activos de unicos: ' + entero(puntos.total)
                        + ' · ' + entero(puntos.ubicados) + ' en el mapa'
                        + (puntos.disca_total ? (' · ' + entero(puntos.disca_total) + ' con condición') : '');
                    var linkSin = document.getElementById('linkSinUbicar');
                    if (linkSin) {
                        if (sinUbicar > 0) {
                            linkSin.textContent = entero(sinUbicar) + ' sin ubicar';
                            linkSin.classList.remove('hidden');
                        } else {
                            linkSin.textContent = '';
                            linkSin.classList.add('hidden');
                        }
                    }
                    estado(puntos.message || 'Afiliados activos listos.');
                }).catch(function () {
                    if (actual === pedidoAfiliados) {
                        resumen.textContent = 'No se pudo leer el padrón.';
                    }
                });
            }).catch(function () {
                if (actual === pedidoAfiliados) {
                    resumen.textContent = 'No se pudo consultar el mapa.';
                    reintento.classList.remove('hidden');
                }
            });
        }
        paso();
    }

    function cargarPrestadores() {
        var obra = document.getElementById('filtroObra').value;
        pedidoPrestadores++;
        var actual = pedidoPrestadores;
        var resumen = document.getElementById('resumenPrestadores');
        var reintento = document.getElementById('btnReintentarPrestadores');
        if (obra === '') {
            resumen.textContent = 'Se cargan al elegir la obra social.';
            reintento.classList.add('hidden');
            return;
        }
        reintento.classList.add('hidden');

        function paso() {
            resumen.textContent = 'Ubicando sucursales de la obra…';
            pedir('index.php?c=dashboard&a=ajaxGetMapaPrestadores', cuerpoObra()).then(function (data) {
                if (actual !== pedidoPrestadores) {
                    return;
                }
                if (!data || data.status !== 'ok') {
                    resumen.textContent = (data && data.message) ? data.message : 'No se pudieron leer las sucursales.';
                    reintento.classList.remove('hidden');
                    return;
                }
                pintarCategorias(data.categorias || []);
                dibujarPrestadores(data.puntos || []);
                resumen.textContent = entero(data.ubicados) + ' de ' + entero(data.total) + ' sucursales en el mapa'
                    + (data.aproximados ? (' · ' + entero(data.aproximados) + ' por localidad') : '')
                    + (data.pendientes ? (' · faltan ' + entero(data.pendientes)) : '');
                if (data.pendientes > 0 && data.georef && data.avance > 0) {
                    estado('Ubicando sucursales: ' + entero(data.ubicados) + ' de ' + entero(data.total) + '…');
                    paso();
                    return;
                }
                if (data.pendientes > 0) {
                    reintento.classList.remove('hidden');
                    estado(data.message || 'Quedaron sucursales sin ubicar.');
                    return;
                }
                estado('Sucursales listas. Pase el mouse sobre un triángulo para ver la categoría.');
            }).catch(function () {
                if (actual === pedidoPrestadores) {
                    resumen.textContent = 'No se pudieron ubicar las sucursales.';
                    reintento.classList.remove('hidden');
                }
            });
        }
        paso();
    }

    function marcarCategorias(valor) {
        var casillas = document.getElementsByClassName('js-cat');
        var k;
        for (k = 0; k < casillas.length; k++) {
            casillas[k].checked = valor;
        }
        aplicarCapasPrestadores();
    }

    function buscar() {
        var q = String(document.getElementById('buscarMapa').value || '').toUpperCase().replace(/^\s+|\s+$/g, '');
        if (q.length < 2) {
            estado('Escriba al menos 2 letras para buscar.');
            return;
        }
        var k;
        for (k = 0; k < marcasAfiliados.length; k++) {
            if (marcasAfiliados[k]._busca.indexOf(q) !== -1) {
                if (!mapa.hasLayer(grupoAfiliados)) {
                    document.getElementById('verAfiliados').checked = true;
                    mapa.addLayer(grupoAfiliados);
                }
                grupoAfiliados.zoomToShowLayer(marcasAfiliados[k], function () {
                    marcasAfiliados[k].openTooltip();
                });
                return;
            }
        }
        for (k = 0; k < marcasPrestadores.length; k++) {
            if (marcasPrestadores[k]._busca.indexOf(q) !== -1) {
                var capa = null;
                var codigo;
                for (codigo in gruposPrestadores) {
                    if (gruposPrestadores.hasOwnProperty(codigo) && gruposPrestadores[codigo].hasLayer(marcasPrestadores[k])) {
                        capa = gruposPrestadores[codigo];
                        if (!categoriaVisible(codigo)) {
                            var casillas = document.getElementsByClassName('js-cat');
                            var n;
                            for (n = 0; n < casillas.length; n++) {
                                if (casillas[n].getAttribute('data-categ') === codigo) {
                                    casillas[n].checked = true;
                                }
                            }
                            document.getElementById('verPrestadores').checked = true;
                            aplicarCapasPrestadores();
                        }
                    }
                }
                if (capa) {
                    capa.zoomToShowLayer(marcasPrestadores[k], function () {
                        marcasPrestadores[k].openTooltip();
                    });
                }
                return;
            }
        }
        estado('No hay un punto visible con ese texto.');
    }

    function pintarCategorias(lista) {
        var caja = document.getElementById('listaCategorias');
        colores = {};
        if (!caja) {
            return;
        }
        if (!lista || !lista.length) {
            caja.innerHTML = '<p class="px-1 text-xs text-slate-400">Esta obra no tiene prestadores activos.</p>';
            return;
        }
        var html = '';
        var i;
        for (i = 0; i < lista.length; i++) {
            colores[lista[i].codigo] = lista[i].color;
            html += '<label class="flex items-center justify-between gap-2 rounded-lg px-1 py-1 text-sm text-slate-700 hover:bg-slate-50">'
                + '<span class="flex min-w-0 items-center gap-2">'
                + '<input type="checkbox" class="js-cat h-4 w-4" data-categ="' + esc(lista[i].codigo) + '" checked>'
                + '<span class="inline-block h-3 w-3 shrink-0" style="background:' + esc(lista[i].color || '#1565c0') + ';clip-path:polygon(50% 0,100% 100%,0 100%)"></span>'
                + '<span class="truncate">' + dato(lista[i].codigo) + '</span></span>'
                + '<span class="shrink-0 text-xs text-slate-400">' + entero(lista[i].total) + '</span></label>';
        }
        caja.innerHTML = html;
        var casillas = document.getElementsByClassName('js-cat');
        var k;
        for (k = 0; k < casillas.length; k++) {
            casillas[k].addEventListener('change', aplicarCapasPrestadores);
        }
    }

    function pintarDisca(lista) {
        var caja = document.getElementById('listaDisca');
        if (!caja) {
            return;
        }
        if (!lista || !lista.length) {
            caja.innerHTML = '';
            return;
        }
        var html = '<p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Condiciones</p>';
        var i;
        for (i = 0; i < lista.length; i++) {
            html += '<div class="flex items-center justify-between gap-2 text-xs text-slate-700">'
                + '<span class="flex min-w-0 items-center gap-2"><span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background:'
                + esc(lista[i].color || '#7b1fa2') + '"></span><span class="truncate">' + dato(lista[i].etiqueta) + '</span></span>'
                + '<span class="shrink-0 text-slate-400">' + entero(lista[i].total) + '</span></div>';
        }
        caja.innerHTML = html;
    }

    function campoGrilla(valor) {
        return '<input class="h-8 w-full min-w-[8rem] rounded border border-slate-200 px-2" value="' + esc(valor || '') + '">';
    }

    function abrirSinUbicar() {
        var obra = document.getElementById('filtroObra').value;
        if (!obra) {
            return;
        }
        var modal = document.getElementById('modalSinUbicar');
        var cuerpo = document.getElementById('cuerpoSinUbicar');
        cuerpo.innerHTML = '<tr><td class="px-3 py-4 text-slate-500" colspan="8">Cargando afiliados sin ubicar…</td></tr>';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        pedir('index.php?c=dashboard&a=ajaxGetMapaSinUbicar', cuerpoObra()).then(function (data) {
            if (!data || data.status !== 'ok') {
                cuerpo.innerHTML = '<tr><td class="px-3 py-4 text-red-700" colspan="8">' + dato((data && data.message) ? data.message : 'No se pudo cargar.') + '</td></tr>';
                return;
            }
            document.getElementById('tituloSinUbicar').textContent = entero(data.total) + ' afiliados. Corregí la localidad o la zona y guardá. El mapa usa esa corrección para ubicar y para buscar.';
            pintarGrillaSinUbicar(data.filas || []);
        }).catch(function () {
            cuerpo.innerHTML = '<tr><td class="px-3 py-4 text-red-700" colspan="8">No se pudo cargar el listado.</td></tr>';
        });
    }

    function pintarGrillaSinUbicar(filas) {
        var cuerpo = document.getElementById('cuerpoSinUbicar');
        if (!filas.length) {
            cuerpo.innerHTML = '<tr><td class="px-3 py-4 text-slate-500" colspan="8">No quedan afiliados sin ubicar.</td></tr>';
            return;
        }
        var html = '';
        var i;
        for (i = 0; i < filas.length; i++) {
            var f = filas[i];
            html += '<tr class="border-t border-slate-100" data-busca="' + esc(textoBusqueda([f.afiliado, f.nombre, f.direccion, f.telefono, f.localidad, f.zona, f.obrasoc])) + '">'
                + '<td class="px-2 py-1 font-semibold" data-afiliado="' + esc(f.afiliado) + '">' + dato(f.afiliado) + '</td>'
                + '<td class="px-2 py-1">' + campoGrilla(f.nombre) + '</td>'
                + '<td class="px-2 py-1">' + campoGrilla(f.direccion) + '</td>'
                + '<td class="px-2 py-1">' + campoGrilla(f.telefono) + '</td>'
                + '<td class="px-2 py-1">' + campoGrilla(f.localidad) + '</td>'
                + '<td class="px-2 py-1">' + campoGrilla(f.zona) + '</td>'
                + '<td class="px-2 py-1">' + dato(f.obrasoc) + '</td>'
                + '<td class="px-2 py-1"><button type="button" class="js-guardar-correccion rounded bg-[#1565c0] px-2 py-1 text-[11px] font-semibold text-white">Guardar</button></td>'
                + '</tr>';
        }
        cuerpo.innerHTML = html;
    }

    function filtrarSinUbicar() {
        var q = String(document.getElementById('buscarSinUbicar').value || '').toUpperCase().replace(/^\s+|\s+$/g, '');
        var filas = document.getElementById('cuerpoSinUbicar').getElementsByTagName('tr');
        var i;
        for (i = 0; i < filas.length; i++) {
            var texto = filas[i].getAttribute('data-busca') || '';
            if (!texto) {
                var inputs = filas[i].getElementsByTagName('input');
                var k;
                for (k = 0; k < inputs.length; k++) {
                    texto += ' ' + String(inputs[k].value || '').toUpperCase();
                }
            }
            filas[i].style.display = (q === '' || texto.indexOf(q) !== -1) ? '' : 'none';
        }
    }

    function guardarFilaCorreccion(boton) {
        var fila = boton.parentNode.parentNode;
        var inputs = fila.getElementsByTagName('input');
        var afiliado = fila.getElementsByTagName('td')[0].getAttribute('data-afiliado') || '';
        var cuerpo = cuerpoObra()
            + '&afiliado=' + encodeURIComponent(afiliado)
            + '&nombre=' + encodeURIComponent(inputs[0].value)
            + '&direccion=' + encodeURIComponent(inputs[1].value)
            + '&telefono=' + encodeURIComponent(inputs[2].value)
            + '&localidad=' + encodeURIComponent(inputs[3].value)
            + '&zona=' + encodeURIComponent(inputs[4].value);
        boton.disabled = true;
        pedir('index.php?c=dashboard&a=ajaxGuardarMapaCorreccion', cuerpo).then(function (data) {
            boton.disabled = false;
            if (!data || data.status !== 'ok') {
                estado((data && data.message) ? data.message : 'No se pudo guardar.');
                return;
            }
            estado(data.message || 'Guardado.');
            if (Number(data.ubicado) === 1) {
                fila.parentNode.removeChild(fila);
                cargarAfiliados();
            }
        }).catch(function () {
            boton.disabled = false;
            estado('No se pudo guardar la corrección.');
        });
    }

    function cerrarSinUbicar() {
        var modal = document.getElementById('modalSinUbicar');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('linkSinUbicar').addEventListener('click', abrirSinUbicar);
    document.getElementById('cerrarSinUbicar').addEventListener('click', cerrarSinUbicar);
    document.getElementById('modalSinUbicar').addEventListener('click', function (e) {
        if (e.target === document.getElementById('modalSinUbicar')) {
            cerrarSinUbicar();
        }
    });
    document.getElementById('buscarSinUbicar').addEventListener('input', filtrarSinUbicar);
    document.getElementById('cuerpoSinUbicar').addEventListener('click', function (e) {
        var boton = e.target;
        if (!boton || !boton.className || String(boton.className).indexOf('js-guardar-correccion') === -1) {
            return;
        }
        guardarFilaCorreccion(boton);
    });

    document.getElementById('filtroObra').addEventListener('change', function () {
        cargarAfiliados();
        cargarPrestadores();
    });
    document.getElementById('verAfiliados').addEventListener('change', aplicarCapaAfiliados);
    document.getElementById('verPrestadores').addEventListener('change', aplicarCapasPrestadores);
    document.getElementById('btnCatsTodas').addEventListener('click', function () { marcarCategorias(true); });
    document.getElementById('btnCatsNinguna').addEventListener('click', function () { marcarCategorias(false); });
    document.getElementById('btnReintentarAfiliados').addEventListener('click', cargarAfiliados);
    document.getElementById('btnReintentarPrestadores').addEventListener('click', cargarPrestadores);
    document.getElementById('btnBuscar').addEventListener('click', buscar);
    mapa.on('zoomend', ajustarTamanoMarcas);
    document.getElementById('buscarMapa').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscar();
        }
    });
    var casillas = document.getElementsByClassName('js-cat');
    for (i = 0; i < casillas.length; i++) {
        casillas[i].addEventListener('change', aplicarCapasPrestadores);
    }

    setTimeout(function () { mapa.invalidateSize(); }, 200);
})();
</script>
