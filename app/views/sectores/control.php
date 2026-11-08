<?php
$csrfSectores = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if (!isset($reporte)) {
    $reporte = 'ordenes';
}
if (!isset($titulo)) {
    $titulo = 'Control de sectores';
}
if (!isset($texto)) {
    $texto = '';
}
if (!isset($desde)) {
    $desde = date('Y-m-d');
}
if (!isset($hasta)) {
    $hasta = date('Y-m-d');
}
if (!isset($errorCarga)) {
    $errorCarga = '';
}

$sectores = array(
    array(
        'id'    => 'ordenes',
        'href'  => 'index.php?c=sectores&a=ordenes',
        'icon'  => 'fa-solid fa-file-medical',
        'label' => '1. Órdenes y autorizaciones',
    ),
    array(
        'id'    => 'facturacion',
        'href'  => 'index.php?c=sectores&a=facturacion',
        'icon'  => 'fa-solid fa-file-invoice-dollar',
        'label' => '2. Carga de facturación',
    ),
    array(
        'id'    => 'auditoria_facturas',
        'href'  => 'index.php?c=sectores&a=auditoriaFacturas',
        'icon'  => 'fa-solid fa-file-circle-check',
        'label' => '3. Auditoría de facturas',
    ),
);
?>

<section class="mb-4">
    <div class="mb-3">
        <h1 class="text-xl font-bold text-slate-800"><?php echo h($titulo); ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?php echo h($texto); ?></p>
    </div>

    <nav class="mb-4 flex flex-wrap gap-2">
        <?php foreach ($sectores as $sector): ?>
            <?php $activo = ($reporte === $sector['id']); ?>
            <a href="<?php echo h($sector['href']); ?>"
               class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold <?php echo $activo ? 'bg-[#1565c0] text-white' : 'bg-white text-slate-600 shadow-sm hover:bg-blue-50'; ?>">
                <i class="<?php echo h($sector['icon']); ?>"></i>
                <?php echo h($sector['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form id="formSectores" class="rounded-2xl bg-white p-4 shadow-sm">
        <input type="hidden" id="csrfSectores" value="<?php echo h($csrfSectores); ?>">
        <input type="hidden" id="reporteSectores" value="<?php echo h($reporte); ?>">
        <div class="grid gap-3 md:grid-cols-[minmax(0,0.7fr)_minmax(0,0.7fr)_auto] md:items-end">
            <div>
                <label for="filtroDesde" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Desde</label>
                <input type="date" id="filtroDesde" value="<?php echo h($desde); ?>" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
            </div>
            <div>
                <label for="filtroHasta" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Hasta</label>
                <input type="date" id="filtroHasta" value="<?php echo h($hasta); ?>" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#1565c0] px-4 text-sm font-semibold text-white hover:bg-[#0d47a1]">
                <i class="fa-solid fa-magnifying-glass"></i>
                Consultar
            </button>
        </div>
        <p class="mt-2 text-xs text-slate-400">El rango puede ser un día o varios meses, con un máximo de 12 meses.</p>
    </form>
</section>

<?php if ($reporte === 'ordenes'): ?>
<p class="mb-4 text-xs text-slate-500">
    Ambulatorio sale de <span class="font-medium">cartilla.ordenes</span>,
    sanatorial de <span class="font-medium">cartilla.sanorden</span>
    y medicamentos de <span class="font-medium">cartilla.ordenes_medicamentos</span>.
    El archivo de origen del circuito es Z:\general\medicos\ORDENES.
</p>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Órdenes cargadas por día</h2>
                <p class="mt-1 text-xs text-slate-500">Cada número es la cantidad de filas de ese día, según COFECHA. Un clic abre esas órdenes.</p>
            </div>
            <span id="badgeCargadas" class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Fecha</th>
                        <th class="px-3 py-2 text-right font-semibold">Ambulatorio</th>
                        <th class="px-3 py-2 text-right font-semibold">Sanatorial</th>
                        <th class="px-3 py-2 text-right font-semibold">Medicamentos</th>
                        <th class="px-3 py-2 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody id="cuerpoCargadas" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Autorizadas y rechazadas por día</h2>
                <p class="mt-1 text-xs text-slate-500">Mismas tablas, mirando COFECAUTO y COESTADO autorizada o rechazada. Un clic abre ese grupo.</p>
            </div>
            <span id="badgeEstados" class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Fecha autorización</th>
                        <th class="px-3 py-2 text-right font-semibold">Autorizadas</th>
                        <th class="px-3 py-2 text-right font-semibold">Rechazadas</th>
                    </tr>
                </thead>
                <tbody id="cuerpoEstados" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </section>
</div>
<?php else: ?>
<section id="panelPendiente" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
    <i class="fa-solid fa-hourglass-half mb-3 text-2xl text-slate-300"></i>
    <p class="text-sm font-semibold text-slate-700">El rango quedó elegido.</p>
    <p id="textoPendiente" class="mt-1 text-sm text-slate-500"></p>
    <p class="mt-3 text-xs text-slate-400">Este sector usa el mismo criterio de fechas. El conteo se arma en el paso siguiente.</p>
</section>
<?php endif; ?>

<div id="modalOrdenes" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-2">
    <div class="flex h-[92vh] w-[98vw] max-w-none flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-3 text-white">
            <div>
                <h2 id="modalTitulo" class="text-sm font-bold">Órdenes</h2>
                <p id="modalSubtitulo" class="text-xs text-white/75"></p>
            </div>
            <button type="button" id="cerrarModalOrdenes" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="sticky top-0 bg-slate-900 text-[11px] uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Fecha</th>
                        <th class="px-3 py-2 font-semibold">Obra social</th>
                        <th class="px-3 py-2 font-semibold">Prestador</th>
                        <th class="px-3 py-2 font-semibold">Nombre prestador</th>
                        <th class="px-3 py-2 font-semibold">Afiliado</th>
                        <th class="px-3 py-2 font-semibold">Paciente</th>
                        <th class="px-3 py-2 font-semibold">Origen</th>
                        <th class="px-3 py-2 font-semibold">Estado</th>
                        <th class="px-3 py-2 font-semibold">Autorización</th>
                    </tr>
                </thead>
                <tbody id="cuerpoModal" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var reporte = document.getElementById('reporteSectores').value;
    var errorCarga = <?php echo json_encode($errorCarga); ?>;
    var modal = document.getElementById('modalOrdenes');

    function aviso(texto) {
        Swal.fire({
            icon: 'warning',
            title: 'Control de sectores',
            text: texto,
            confirmButtonColor: '#1565c0'
        });
    }

    function fechaCorta(iso) {
        if (!iso || iso.length < 10) {
            return '';
        }
        return iso.substr(8, 2) + '/' + iso.substr(5, 2) + '/' + iso.substr(0, 4);
    }

    function entero(valor) {
        var n = Number(valor) || 0;
        return String(n);
    }

    function celdaNumero(dia, modo, origen, estado, cantidad, tono) {
        var n = Number(cantidad) || 0;
        if (n === 0) {
            return '<td class="px-3 py-2 text-right text-slate-300">0</td>';
        }
        return '<td class="px-3 py-2 text-right">'
            + '<button type="button" class="js-abrir font-semibold underline decoration-dotted ' + tono + '" '
            + 'data-dia="' + dia + '" data-modo="' + modo + '" data-origen="' + origen + '" data-estado="' + estado + '">'
            + entero(n) + '</button></td>';
    }

    function filaVacia(id, columnas, texto) {
        var cuerpo = document.getElementById(id);
        cuerpo.innerHTML = '<tr><td colspan="' + columnas + '" class="px-3 py-8 text-center text-sm text-slate-400">' + texto + '</td></tr>';
    }

    function pintarCargadas(filas) {
        var cuerpo = document.getElementById('cuerpoCargadas');
        var badge = document.getElementById('badgeCargadas');
        var total = 0;
        var i;
        var html = '';
        if (!filas || !filas.length) {
            filaVacia('cuerpoCargadas', 5, 'No hay órdenes cargadas en ese rango.');
            badge.textContent = '0';
            return;
        }
        for (i = 0; i < filas.length; i++) {
            var fila = filas[i];
            total += Number(fila.total) || 0;
            html += '<tr class="hover:bg-slate-50">'
                + '<td class="px-3 py-2 font-medium text-slate-800">' + fechaCorta(fila.dia) + '</td>'
                + celdaNumero(fila.dia, 'cargadas', 'ambulatorio', '', fila.ambulatorio, 'text-[#1565c0]')
                + celdaNumero(fila.dia, 'cargadas', 'sanatorial', '', fila.sanatorial, 'text-[#1565c0]')
                + celdaNumero(fila.dia, 'cargadas', 'medicamentos', '', fila.medicamentos, 'text-[#1565c0]')
                + celdaNumero(fila.dia, 'cargadas', '', '', fila.total, 'text-slate-900')
                + '</tr>';
        }
        cuerpo.innerHTML = html;
        badge.textContent = String(filas.length) + ' días';
    }

    function pintarEstados(filas) {
        var cuerpo = document.getElementById('cuerpoEstados');
        var badge = document.getElementById('badgeEstados');
        var i;
        var html = '';
        if (!filas || !filas.length) {
            filaVacia('cuerpoEstados', 3, 'No hay autorizadas ni rechazadas en ese rango.');
            badge.textContent = '0';
            return;
        }
        for (i = 0; i < filas.length; i++) {
            var fila = filas[i];
            html += '<tr class="hover:bg-slate-50">'
                + '<td class="px-3 py-2 font-medium text-slate-800">' + fechaCorta(fila.dia) + '</td>'
                + celdaNumero(fila.dia, 'estado', '', 'AUTORIZADA', fila.autorizadas, 'text-emerald-700')
                + celdaNumero(fila.dia, 'estado', '', 'RECHAZADA', fila.rechazadas, 'text-red-700')
                + '</tr>';
        }
        cuerpo.innerHTML = html;
        badge.textContent = String(filas.length) + ' días';
    }

    function textoCelda(valor) {
        var texto = valor === null || valor === undefined ? '' : String(valor);
        texto = texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return texto === '' ? '—' : texto;
    }

    function abrirModal(titulo, subtitulo, filas) {
        var cuerpo = document.getElementById('cuerpoModal');
        var i;
        var html = '';
        document.getElementById('modalTitulo').textContent = titulo;
        document.getElementById('modalSubtitulo').textContent = subtitulo;
        if (!filas || !filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="9" class="px-3 py-8 text-center text-sm text-slate-400">No hay órdenes para mostrar.</td></tr>';
        } else {
            for (i = 0; i < filas.length; i++) {
                var fila = filas[i];
                html += '<tr class="hover:bg-slate-50">'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.cofecha) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.coobrasoc) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.coprestado) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(fila.conomprest) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.coafiliado) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(fila.conompac) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.origen) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.coestado) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.cofecauto) + '</td>'
                    + '</tr>';
            }
            cuerpo.innerHTML = html;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function post(url, extra) {
        var cuerpo = new FormData();
        cuerpo.append('csrf_token', document.getElementById('csrfSectores').value);
        cuerpo.append('desde', document.getElementById('filtroDesde').value);
        cuerpo.append('hasta', document.getElementById('filtroHasta').value);
        var clave;
        for (clave in extra) {
            if (extra.hasOwnProperty(clave)) {
                cuerpo.append(clave, extra[clave]);
            }
        }
        return fetch(url, { method: 'POST', body: cuerpo, credentials: 'same-origin' }).then(function (respuesta) {
            return respuesta.json();
        });
    }

    function consultar() {
        if (reporte !== 'ordenes') {
            var panel = document.getElementById('panelPendiente');
            var desde = document.getElementById('filtroDesde').value;
            var hasta = document.getElementById('filtroHasta').value;
            if (!desde || !hasta) {
                aviso('Indique el rango de fechas.');
                return;
            }
            document.getElementById('textoPendiente').textContent = 'Desde ' + fechaCorta(desde) + ' hasta ' + fechaCorta(hasta) + '.';
            panel.classList.remove('hidden');
            return;
        }

        post('index.php?c=sectores&a=ajaxResumenOrdenes', {}).then(function (data) {
            if (!data || data.status !== 'success') {
                aviso((data && data.message) ? data.message : 'No se pudo consultar el rango.');
                return;
            }
            pintarCargadas(data.cargadas || []);
            pintarEstados(data.estados || []);
        }).catch(function () {
            aviso('No se pudo consultar el rango.');
        });
    }

    document.getElementById('formSectores').addEventListener('submit', function (e) {
        e.preventDefault();
        consultar();
    });

    if (reporte === 'ordenes') {
        document.getElementById('cuerpoCargadas').addEventListener('click', abrirDesdeBoton);
        document.getElementById('cuerpoEstados').addEventListener('click', abrirDesdeBoton);
        document.getElementById('cerrarModalOrdenes').addEventListener('click', cerrarModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });
    }

    function abrirDesdeBoton(e) {
        var nodo = e.target;
        while (nodo && nodo !== document) {
            if (nodo.classList && nodo.classList.contains('js-abrir')) {
                break;
            }
            nodo = nodo.parentNode;
        }
        if (!nodo || nodo === document) {
            return;
        }
        var dia = nodo.getAttribute('data-dia');
        var modo = nodo.getAttribute('data-modo');
        var origen = nodo.getAttribute('data-origen') || '';
        var estado = nodo.getAttribute('data-estado') || '';
        post('index.php?c=sectores&a=ajaxExplosionOrdenes', {
            dia: dia,
            modo: modo,
            origen: origen,
            estado: estado
        }).then(function (data) {
            if (!data || data.status !== 'success') {
                aviso((data && data.message) ? data.message : 'No se pudo abrir el detalle.');
                return;
            }
            var titulo = 'Órdenes del ' + fechaCorta(dia);
            var subtitulo = (data.total || 0) + ' filas';
            if (modo === 'cargadas' && origen) {
                titulo = origen.charAt(0).toUpperCase() + origen.slice(1) + ' del ' + fechaCorta(dia);
            }
            if (modo === 'estado' && estado === 'AUTORIZADA') {
                titulo = 'Autorizadas del ' + fechaCorta(dia);
                subtitulo = (data.total || 0) + ' filas. Cada orden autorizada tiene un detalle más, que vemos en el paso siguiente.';
            }
            if (modo === 'estado' && estado === 'RECHAZADA') {
                titulo = 'Rechazadas del ' + fechaCorta(dia);
            }
            abrirModal(titulo, subtitulo, data.filas || []);
        }).catch(function () {
            aviso('No se pudo abrir el detalle.');
        });
    }

    if (errorCarga) {
        aviso(errorCarga);
    } else if (reporte === 'ordenes') {
        consultar();
    }
})();
</script>
