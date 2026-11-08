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
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="cargadas">
                    <i class="fa-solid fa-file-excel text-emerald-700"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="cargadas">
                    <i class="fa-solid fa-file-pdf text-red-700"></i> PDF
                </button>
                <span id="badgeCargadas" class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
            </div>
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
                <p class="mt-1 text-xs text-slate-500">AMB y SAN son órdenes autorizadas por fecha de autorización. RECH cuenta órdenes con fecha de carga y estado rechazada. TOTPRAC suma las prácticas de cartilla.detauto y cartilla.sanautod.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="estados">
                    <i class="fa-solid fa-file-excel text-emerald-700"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="estados">
                    <i class="fa-solid fa-file-pdf text-red-700"></i> PDF
                </button>
                <span id="badgeEstados" class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="whitespace-nowrap px-3 py-2 font-semibold" title="AMB y SAN usan la fecha de autorización. RECH usa la fecha de la orden.">Fecha</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold" title="Ambulatorio">AMB</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold" title="Sanatorial">SAN</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold" title="Rechazadas por fecha de la orden">RECH</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold" title="Total de prácticas autorizadas">TOTPRAC</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold" title="Autorizadas más rechazadas">TOTAL</th>
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
            <div class="flex items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="modal">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="modal">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" id="cerrarModalOrdenes" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
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
                        <th id="colPracticas" class="hidden px-3 py-2 text-right font-semibold">Prácticas</th>
                    </tr>
                </thead>
                <tbody id="cuerpoModal" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalPracticas" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="flex max-h-[80vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-3 text-white">
            <div>
                <h2 id="practicasTitulo" class="text-sm font-bold">Prácticas autorizadas</h2>
                <p id="practicasSubtitulo" class="text-xs text-white/75"></p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="practicas">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="practicas">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" id="cerrarModalPracticas" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 bg-slate-900 text-[11px] uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Código</th>
                        <th class="px-3 py-2 font-semibold">Práctica</th>
                    </tr>
                </thead>
                <tbody id="cuerpoPracticas" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script>
(function () {
    var reporte = document.getElementById('reporteSectores').value;
    var errorCarga = <?php echo json_encode($errorCarga); ?>;
    var modal = document.getElementById('modalOrdenes');
    var datosCargadas = [];
    var datosEstados = [];
    var datosModal = { titulo: '', subtitulo: '', filas: [], conPracticas: false };
    var datosPracticas = { titulo: '', subtitulo: '', filas: [] };
    var modalPracticas = document.getElementById('modalPracticas');

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
        datosCargadas = filas || [];
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
        datosEstados = filas || [];
        var cuerpo = document.getElementById('cuerpoEstados');
        var badge = document.getElementById('badgeEstados');
        var i;
        var html = '';
        if (!filas || !filas.length) {
            filaVacia('cuerpoEstados', 6, 'No hay autorizadas ni rechazadas en ese rango.');
            badge.textContent = '0';
            return;
        }
        for (i = 0; i < filas.length; i++) {
            var fila = filas[i];
            html += '<tr class="hover:bg-slate-50">'
                + '<td class="px-3 py-2 font-medium text-slate-800">' + fechaCorta(fila.dia) + '</td>'
                + celdaNumero(fila.dia, 'estado', 'ambulatorio', 'AUTORIZADA', fila.ambulatorio, 'text-emerald-700')
                + celdaNumero(fila.dia, 'estado', 'sanatorial', 'AUTORIZADA', fila.sanatorial, 'text-emerald-700')
                + celdaNumero(fila.dia, 'estado', '', 'RECHAZADA', fila.rechazadas, 'text-red-700')
                + '<td class="px-3 py-2 text-right font-semibold text-[#0d47a1]">' + entero(fila.practicas) + '</td>'
                + celdaNumero(fila.dia, 'estado', '', 'AMBAS', fila.total, 'text-slate-900')
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

    function abrirModal(titulo, subtitulo, filas, conPracticas) {
        datosModal = { titulo: titulo, subtitulo: subtitulo, filas: filas || [], conPracticas: !!conPracticas };
        var cuerpo = document.getElementById('cuerpoModal');
        var columna = document.getElementById('colPracticas');
        var i;
        var html = '';
        document.getElementById('modalTitulo').textContent = titulo;
        document.getElementById('modalSubtitulo').textContent = subtitulo;
        if (conPracticas) {
            columna.classList.remove('hidden');
        } else {
            columna.classList.add('hidden');
        }
        if (!filas || !filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="' + (conPracticas ? '10' : '9') + '" class="px-3 py-8 text-center text-sm text-slate-400">No hay órdenes para mostrar.</td></tr>';
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
                    + (conPracticas ? celdaPracticas(fila) : '')
                    + '</tr>';
            }
            cuerpo.innerHTML = html;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function celdaPracticas(fila) {
        var n = Number(fila.practicas) || 0;
        var nro = plano(fila.conroauto);
        var clave = plano(fila.origen_clave);
        if (n === 0 || nro === '') {
            return '<td class="px-3 py-2 text-right text-slate-300">0</td>';
        }
        return '<td class="px-3 py-2 text-right">'
            + '<button type="button" class="js-practicas font-semibold underline decoration-dotted text-[#0d47a1]" '
            + 'data-origen="' + xml(clave) + '" data-nro="' + xml(nro) + '" data-paciente="' + xml(plano(fila.conompac)) + '">'
            + entero(n) + '</button></td>';
    }

    function abrirPracticas(titulo, subtitulo, filas) {
        datosPracticas = { titulo: titulo, subtitulo: subtitulo, filas: filas || [] };
        var cuerpo = document.getElementById('cuerpoPracticas');
        var i;
        var html = '';
        document.getElementById('practicasTitulo').textContent = titulo;
        document.getElementById('practicasSubtitulo').textContent = subtitulo;
        if (!filas || !filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="2" class="px-3 py-8 text-center text-sm text-slate-400">Esta orden no tiene prácticas cargadas.</td></tr>';
        } else {
            for (i = 0; i < filas.length; i++) {
                html += '<tr class="hover:bg-slate-50">'
                    + '<td class="px-3 py-2 whitespace-nowrap font-medium">' + textoCelda(filas[i].cocodprest) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(filas[i].conombpres) + '</td>'
                    + '</tr>';
            }
            cuerpo.innerHTML = html;
        }
        modalPracticas.classList.remove('hidden');
        modalPracticas.classList.add('flex');
    }

    function cerrarPracticas() {
        modalPracticas.classList.add('hidden');
        modalPracticas.classList.remove('flex');
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

    function rangoTexto() {
        return fechaCorta(document.getElementById('filtroDesde').value) + ' al ' + fechaCorta(document.getElementById('filtroHasta').value);
    }

    function plano(valor) {
        if (valor === null || valor === undefined || String(valor) === '') {
            return '';
        }
        return String(valor);
    }

    function cuadroExportable(cual) {
        if (cual === 'cargadas') {
            var filas = [];
            var i;
            for (i = 0; i < datosCargadas.length; i++) {
                filas.push([
                    fechaCorta(datosCargadas[i].dia),
                    entero(datosCargadas[i].ambulatorio),
                    entero(datosCargadas[i].sanatorial),
                    entero(datosCargadas[i].medicamentos),
                    entero(datosCargadas[i].total)
                ]);
            }
            return {
                titulo: 'Órdenes cargadas por día',
                nombre: 'ordenes-cargadas',
                columnas: ['Fecha', 'Ambulatorio', 'Sanatorial', 'Medicamentos', 'Total'],
                filas: filas
            };
        }
        if (cual === 'estados') {
            var estados = [];
            var j;
            for (j = 0; j < datosEstados.length; j++) {
                estados.push([
                    fechaCorta(datosEstados[j].dia),
                    entero(datosEstados[j].ambulatorio),
                    entero(datosEstados[j].sanatorial),
                    entero(datosEstados[j].rechazadas),
                    entero(datosEstados[j].practicas),
                    entero(datosEstados[j].total)
                ]);
            }
            return {
                titulo: 'Autorizadas y rechazadas por día',
                nombre: 'ordenes-autorizadas',
                columnas: ['Fecha', 'AMB', 'SAN', 'RECH', 'TOTPRAC', 'TOTAL'],
                filas: estados
            };
        }
        if (cual === 'practicas') {
            var practicas = [];
            var p;
            for (p = 0; p < datosPracticas.filas.length; p++) {
                practicas.push([
                    plano(datosPracticas.filas[p].cocodprest),
                    plano(datosPracticas.filas[p].conombpres)
                ]);
            }
            return {
                titulo: datosPracticas.titulo || 'Prácticas autorizadas',
                nombre: 'practicas-autorizadas',
                columnas: ['Código', 'Práctica'],
                filas: practicas
            };
        }
        var detalle = [];
        var k;
        for (k = 0; k < datosModal.filas.length; k++) {
            var fila = datosModal.filas[k];
            var linea = [
                plano(fila.cofecha),
                plano(fila.coobrasoc),
                plano(fila.coprestado),
                plano(fila.conomprest),
                plano(fila.coafiliado),
                plano(fila.conompac),
                plano(fila.origen),
                plano(fila.coestado),
                plano(fila.cofecauto)
            ];
            if (datosModal.conPracticas) {
                linea.push(entero(fila.practicas));
            }
            detalle.push(linea);
        }
        var columnasDetalle = ['Fecha', 'Obra social', 'Prestador', 'Nombre prestador', 'Afiliado', 'Paciente', 'Origen', 'Estado', 'Autorización'];
        if (datosModal.conPracticas) {
            columnasDetalle.push('Prácticas');
        }
        return {
            titulo: datosModal.titulo || 'Órdenes',
            nombre: 'ordenes-detalle',
            columnas: columnasDetalle,
            filas: detalle
        };
    }

    function xml(valor) {
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function exportarExcel(cual) {
        var cuadro = cuadroExportable(cual);
        if (!cuadro.filas.length) {
            aviso('No hay filas para exportar.');
            return;
        }
        var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table><tr>';
        var i;
        var j;
        for (i = 0; i < cuadro.columnas.length; i++) {
            html += '<th>' + xml(cuadro.columnas[i]) + '</th>';
        }
        html += '</tr>';
        for (i = 0; i < cuadro.filas.length; i++) {
            html += '<tr>';
            for (j = 0; j < cuadro.filas[i].length; j++) {
                html += '<td>' + xml(cuadro.filas[i][j]) + '</td>';
            }
            html += '</tr>';
        }
        html += '</table></body></html>';
        var blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' });
        var enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(blob);
        enlace.download = cuadro.nombre + '.xls';
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
    }

    function abrirPdf(cual) {
        var cuadro = cuadroExportable(cual);
        if (!cuadro.filas.length) {
            aviso('No hay filas para imprimir.');
            return;
        }
        if (!window.pdfMake || !window.pdfMake.createPdf) {
            aviso('El generador de PDF todavía no está listo. Recargue la página.');
            return;
        }
        var cuerpo = [];
        var encabezado = [];
        var i;
        var j;
        for (i = 0; i < cuadro.columnas.length; i++) {
            encabezado.push({ text: cuadro.columnas[i], bold: true, color: '#ffffff', fillColor: '#0d47a1' });
        }
        cuerpo.push(encabezado);
        for (i = 0; i < cuadro.filas.length; i++) {
            var linea = [];
            for (j = 0; j < cuadro.filas[i].length; j++) {
                linea.push(String(cuadro.filas[i][j]));
            }
            cuerpo.push(linea);
        }
        var anchos = [];
        for (i = 0; i < cuadro.columnas.length; i++) {
            anchos.push('*');
        }
        pdfMake.createPdf({
            pageSize: 'A4',
            pageOrientation: cuadro.columnas.length > 5 ? 'landscape' : 'portrait',
            pageMargins: [24, 28, 24, 28],
            content: [
                { text: cuadro.titulo, fontSize: 14, bold: true, color: '#0d47a1', margin: [0, 0, 0, 4] },
                { text: 'COMEDICA · ' + rangoTexto(), fontSize: 9, color: '#64748b', margin: [0, 0, 0, 10] },
                { table: { headerRows: 1, widths: anchos, body: cuerpo }, fontSize: 8 }
            ]
        }).open();
    }

    document.addEventListener('click', function (e) {
        var nodo = e.target;
        var excel = false;
        var pdf = false;
        while (nodo && nodo !== document) {
            if (nodo.classList && nodo.classList.contains('js-excel')) {
                excel = true;
                break;
            }
            if (nodo.classList && nodo.classList.contains('js-pdf')) {
                pdf = true;
                break;
            }
            nodo = nodo.parentNode;
        }
        if (!nodo || nodo === document) {
            return;
        }
        var cual = nodo.getAttribute('data-cual');
        if (excel) {
            exportarExcel(cual);
        }
        if (pdf) {
            abrirPdf(cual);
        }
    });

    document.getElementById('formSectores').addEventListener('submit', function (e) {
        e.preventDefault();
        consultar();
    });

    if (reporte === 'ordenes') {
        document.getElementById('cuerpoCargadas').addEventListener('click', abrirDesdeBoton);
        document.getElementById('cuerpoEstados').addEventListener('click', abrirDesdeBoton);
        document.getElementById('cerrarModalOrdenes').addEventListener('click', cerrarModal);
        document.getElementById('cerrarModalPracticas').addEventListener('click', cerrarPracticas);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });
        modalPracticas.addEventListener('click', function (e) {
            if (e.target === modalPracticas) {
                cerrarPracticas();
            }
        });
        document.getElementById('cuerpoModal').addEventListener('click', abrirPracticasDesdeBoton);
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
                if (origen) {
                    titulo = origen.charAt(0).toUpperCase() + origen.slice(1) + ' autorizadas del ' + fechaCorta(dia);
                }
                subtitulo = (data.total || 0) + ' órdenes. La cantidad de prácticas abre el detalle de cada una.';
            }
            if (modo === 'estado' && estado === 'RECHAZADA') {
                titulo = 'Rechazadas del ' + fechaCorta(dia);
            }
            if (modo === 'estado' && estado === 'AMBAS') {
                titulo = 'Autorizadas y rechazadas del ' + fechaCorta(dia);
                subtitulo = (data.total || 0) + ' órdenes. La cantidad de prácticas abre el detalle de cada una.';
            }
            abrirModal(titulo, subtitulo, data.filas || [], modo === 'estado');
        }).catch(function () {
            aviso('No se pudo abrir el detalle.');
        });
    }

    function abrirPracticasDesdeBoton(e) {
        var nodo = e.target;
        while (nodo && nodo !== document) {
            if (nodo.classList && nodo.classList.contains('js-practicas')) {
                break;
            }
            nodo = nodo.parentNode;
        }
        if (!nodo || nodo === document) {
            return;
        }
        var origen = nodo.getAttribute('data-origen') || '';
        var nro = nodo.getAttribute('data-nro') || '';
        var paciente = nodo.getAttribute('data-paciente') || '';
        post('index.php?c=sectores&a=ajaxExplosionPracticas', {
            origen: origen,
            conroauto: nro
        }).then(function (data) {
            if (!data || data.status !== 'success') {
                aviso((data && data.message) ? data.message : 'No se pudieron leer las prácticas.');
                return;
            }
            abrirPracticas(
                'Prácticas de la autorización ' + nro,
                paciente + ' · ' + (data.total || 0) + ((data.total || 0) === 1 ? ' práctica' : ' prácticas'),
                data.filas || []
            );
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
