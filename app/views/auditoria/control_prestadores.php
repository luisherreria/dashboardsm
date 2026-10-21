<?php
$csrfAuditoria = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if (!isset($obras) || !is_array($obras)) {
    $obras = array();
}
if (!isset($periodos) || !is_array($periodos)) {
    $periodos = array();
}
if (!isset($errorCarga)) {
    $errorCarga = '';
}
$periodoDefecto = '';
if (count($periodos) > 0) {
    $periodoDefecto = $periodos[0];
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        padding: 0.35rem 0.6rem;
        background: #fff;
    }
    .dataTables_wrapper .dt-buttons {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin: 0 0 0.6rem;
    }
    .dataTables_wrapper .dt-button {
        display: inline-flex !important;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.5rem !important;
        background: #fff !important;
        color: #0f172a !important;
        padding: 0.3rem 0.55rem !important;
        font-size: 0.7rem !important;
        font-weight: 600 !important;
        line-height: 1.2 !important;
        cursor: pointer;
    }
    .dataTables_wrapper .dt-button:hover {
        background: #eff6ff !important;
        border-color: #1565c0 !important;
        color: #0d47a1 !important;
    }
    .dt-button .fa-copy { color: #334155; }
    .dt-button .fa-file-excel { color: #15803d; }
    .dt-button .fa-file-pdf { color: #b91c1c; }
    .dt-button .fa-print { color: #0d47a1; }
    table.dataTable tbody td { font-size: 0.78rem; }
    table.dataTable tbody tr { cursor: pointer; }
    table.dataTable tbody tr.fila-activa td { background: #dbeafe !important; }
    #tblHistoria tbody tr.fila-debito td { background: #fee2e2 !important; color: #7f1d1d !important; }
    #tblHistoria tbody tr.fila-refactura td { background: #dbeafe !important; color: #1e3a8a !important; }
    #tblHistoria.dataTable tbody td { font-size: 0.72rem; white-space: nowrap; }
    .auditoria-tabla { overflow-x: auto; }
</style>

<section class="mb-4">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Auditoría de Consumo Prestacional</h1>
            <p class="mt-1 text-sm text-slate-500">Estadística de uso de prestaciones por afiliado o por prestador.</p>
        </div>
    </div>

    <form id="formAuditoria" class="rounded-2xl bg-white p-4 shadow-sm">
        <input type="hidden" id="csrfAuditoria" value="<?php echo h($csrfAuditoria); ?>">
        <div class="mb-3 flex flex-wrap gap-2" id="modosAuditoria">
            <button type="button" data-modo="afiliado" class="modo-auditoria rounded-full bg-[#1565c0] px-4 py-2 text-xs font-semibold text-white">
                <i class="fa-solid fa-user mr-1"></i> Consumo por Afiliado
            </button>
            <button type="button" data-modo="prestador" class="modo-auditoria rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600">
                <i class="fa-solid fa-user-doctor mr-1"></i> Consumo por Prestador
            </button>
        </div>
        <div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,0.7fr)_minmax(0,0.7fr)_auto] md:items-end">
            <div>
                <label for="filtroObra" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Obra social</label>
                <select id="filtroObra" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                    <option value="">Seleccione una obra</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?php echo h($obra['codigo']); ?>"><?php echo h($obra['etiqueta']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtroDesde" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Período desde</label>
                <select id="filtroDesde" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                    <?php foreach ($periodos as $periodo): ?>
                        <option value="<?php echo h($periodo); ?>"<?php echo $periodo === $periodoDefecto ? ' selected' : ''; ?>><?php echo h($periodo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtroHasta" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Período hasta</label>
                <select id="filtroHasta" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                    <?php foreach ($periodos as $periodo): ?>
                        <option value="<?php echo h($periodo); ?>"<?php echo $periodo === $periodoDefecto ? ' selected' : ''; ?>><?php echo h($periodo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#1565c0] px-4 text-sm font-semibold text-white hover:bg-[#0d47a1]">
                <i class="fa-solid fa-magnifying-glass"></i>
                Auditar
            </button>
        </div>
        <p class="mt-2 text-xs text-slate-400">La práctica es el código del nomenclador. El prestador es quien brindó la atención. El rango máximo es de 12 meses, en formato aa/mm.</p>
    </form>
</section>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
    <section class="rounded-2xl bg-white p-4 shadow-sm lg:col-span-5">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 id="tituloMaster" class="text-sm font-bold text-slate-800">Sobrefrecuencia por afiliado y práctica</h2>
                <p id="selMaster" class="mt-1 text-xs text-slate-500">Seleccione una fila para ver dónde se consumió.</p>
            </div>
            <span id="badgeMaster" class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <div class="auditoria-tabla">
            <table id="tblMaster" class="display compact stripe" style="width:100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm lg:col-span-3">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 id="tituloExp1" class="text-sm font-bold text-slate-800">Dónde consumió</h2>
                <p id="selExp1" class="mt-1 text-xs text-slate-500">Aparece al elegir una fila del cuadro principal.</p>
            </div>
            <span id="badgeExp1" class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <div class="auditoria-tabla">
            <table id="tblExplosion1" class="display compact stripe" style="width:100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm lg:col-span-4">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 id="tituloExp2" class="text-sm font-bold text-slate-800">Detalle cronológico</h2>
                <p id="selExp2" class="mt-1 text-xs text-slate-500">Fecha por fecha, después de elegir el segundo cuadro.</p>
            </div>
            <span id="badgeExp2" class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <div class="auditoria-tabla">
            <table id="tblExplosion2" class="display compact stripe" style="width:100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>
</div>

<div class="mt-4 flex flex-wrap gap-2">
    <button type="button" id="btnFicha" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#0d47a1] shadow-sm hover:bg-blue-50">
        <i class="fa-solid fa-address-card"></i>
        Ver Historia / Ficha Afiliado
    </button>
    <button type="button" id="btnMailPrestador" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#0d47a1] shadow-sm hover:bg-blue-50">
        <i class="fa-solid fa-envelope"></i>
        Solicitar Justificación a Prestador
    </button>
    <button type="button" id="btnMailAfiliado" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#0d47a1] shadow-sm hover:bg-blue-50">
        <i class="fa-solid fa-comment-medical"></i>
        Notificación / Consulta a Afiliado
    </button>
</div>

<div id="modalFicha" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-2">
    <div class="flex h-[96vh] w-[98vw] max-w-none flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-3 text-white">
            <div>
                <h2 class="text-sm font-bold">Historia clínica y consumos</h2>
                <p class="text-xs text-white/75" id="fichaPeriodo">Detalle completo del afiliado</p>
            </div>
            <button type="button" id="cerrarFicha" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p id="fichaNombre" class="text-base font-bold text-slate-800">Afiliado</p>
                <span id="fichaTotal" class="rounded-full bg-[#0d47a1] px-3 py-1 text-xs font-bold text-white">Registros totales: 0</span>
            </div>
            <dl id="fichaCuerpo" class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4 lg:grid-cols-7"></dl>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-600">
                <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-red-200"></span> Débito</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-blue-200"></span> Refactura</span>
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-auto px-3 py-3">
            <table id="tblHistoria" class="display compact stripe" style="width:100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script>
(function () {
    var modalidad = 'afiliado';
    var tablaMaster = null;
    var tablaExp1 = null;
    var tablaExp2 = null;
    var masterSel = null;
    var exp1Sel = null;
    var errorCarga = <?php echo json_encode($errorCarga); ?>;

    var idioma = {
        processing: 'Procesando...',
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ filas',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Mostrando 0 a 0 de 0',
        infoFiltered: '(filtrado de _MAX_)',
        loadingRecords: 'Cargando...',
        zeroRecords: 'Sin resultados',
        emptyTable: 'Sin registros',
        paginate: { first: 'Primero', last: 'Último', previous: 'Anterior', next: 'Siguiente' },
        aria: { sortAscending: ': orden ascendente', sortDescending: ': orden descendente' },
        buttons: {
            copy: 'Copiar', excel: 'Excel', pdf: 'PDF', print: 'Imprimir',
            copyTitle: 'Copiado al portapapeles',
            copySuccess: { _: '%d filas copiadas', 1: '1 fila copiada' }
        }
    };

    function fmtEntero(valor) {
        var n = Number(valor);
        if (isNaN(n)) { return '0'; }
        return Math.round(n).toLocaleString('es-AR');
    }

    function fmtImporte(valor) {
        var n = Number(valor);
        if (isNaN(n)) { n = 0; }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(texto) {
        return jQuery('<div>').text(texto == null ? '' : String(texto)).html();
    }

    var tablasVivas = {};
    var exportesListos = false;

    function cargarScript(src) {
        return new Promise(function (ok, mal) {
            var etiqueta = document.createElement('script');
            etiqueta.src = src;
            etiqueta.async = true;
            etiqueta.onload = function () { ok(); };
            etiqueta.onerror = function () { mal(); };
            document.head.appendChild(etiqueta);
        });
    }

    function exportesDisponibles() {
        return !!(window.JSZip && window.pdfMake && window.pdfMake.vfs);
    }

    function prepararExportes() {
        cargarScript('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js').catch(function () {});
        cargarScript('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js').then(function () {
            return cargarScript('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js');
        }).then(function () {
            exportesListos = exportesDisponibles();
            var id;
            for (id in tablasVivas) {
                if (tablasVivas.hasOwnProperty(id)) {
                    completarExportes(tablasVivas[id]);
                }
            }
        }).catch(function () {});
    }

    function botonesDe(titulo, exportar) {
        var botones = [
            { extend: 'copyHtml5', text: '<i class="fa-solid fa-copy"></i> Copiar', title: titulo, exportOptions: exportar },
            { extend: 'print', text: '<i class="fa-solid fa-print"></i> Imprimir', title: titulo, exportOptions: exportar }
        ];
        if (exportesDisponibles()) {
            botones.splice(1, 0,
                { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel', title: titulo, exportOptions: exportar },
                { extend: 'pdfHtml5', text: '<i class="fa-solid fa-file-pdf"></i> PDF', title: titulo, orientation: 'landscape', pageSize: 'A4', exportOptions: exportar }
            );
        }
        return botones;
    }

    function completarExportes(meta) {
        if (!meta || !meta.tabla || !exportesDisponibles()) {
            return;
        }
        if (meta.tabla.buttons().container().find('.buttons-excel').length) {
            return;
        }
        var titulo = meta.titulo;
        var exportar = meta.exportar;
        meta.tabla.button().add(1, { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel', title: titulo, exportOptions: exportar });
        meta.tabla.button().add(2, { extend: 'pdfHtml5', text: '<i class="fa-solid fa-file-pdf"></i> PDF', title: titulo, orientation: 'landscape', pageSize: 'A4', exportOptions: exportar });
    }

    function destruirTabla(id) {
        var nodo = jQuery('#' + id);
        if (jQuery.fn.DataTable.isDataTable(nodo)) {
            nodo.DataTable().clear().destroy();
        }
        delete tablasVivas[id];
        nodo.find('thead tr').empty();
        nodo.find('tbody').empty();
    }

    function pintarModo() {
        var botones = document.querySelectorAll('.modo-auditoria');
        var i;
        for (i = 0; i < botones.length; i++) {
            var activo = botones[i].getAttribute('data-modo') === modalidad;
            botones[i].className = 'modo-auditoria rounded-full px-4 py-2 text-xs font-semibold ' + (activo
                ? 'bg-[#1565c0] text-white'
                : 'bg-slate-100 text-slate-600');
        }
        document.getElementById('tituloMaster').textContent = modalidad === 'prestador'
            ? 'Prescripción por prestador y práctica'
            : 'Sobrefrecuencia por afiliado y práctica';
        document.getElementById('tituloExp1').textContent = modalidad === 'prestador'
            ? 'A quién se le brindó'
            : 'Dónde consumió';
        document.getElementById('selMaster').textContent = modalidad === 'prestador'
            ? 'Seleccione una fila para ver a qué afiliados se les brindó.'
            : 'Seleccione una fila para ver dónde se consumió.';
    }

    function columnasMaster() {
        var persona = modalidad === 'prestador'
            ? { data: 'nombre', title: 'Nombre prestador' }
            : { data: 'nombre', title: 'Nombre afiliado' };
        return [
            { data: 'casos', title: 'Casos', className: 'dt-body-right' },
            { data: 'practica', title: 'Código práctica' },
            { data: 'descripcion', title: 'Descripción práctica' },
            persona,
            { data: 'importe', title: 'Importe total', className: 'dt-body-right', render: function (v) { return fmtImporte(v); } }
        ];
    }

    function columnasExp1() {
        if (modalidad === 'prestador') {
            return [
                { data: 'casos', title: 'Casos', className: 'dt-body-right' },
                { data: 'nombre', title: 'Nombre afiliado' },
                { data: 'afiliado', title: 'Nro afiliado' }
            ];
        }
        return [
            { data: 'casos', title: 'Casos', className: 'dt-body-right' },
            { data: 'nombre', title: 'Nombre prestador' },
            { data: 'prestador', title: 'Código prestador' }
        ];
    }

    function columnasExp2() {
        return [
            { data: 'fecha', title: 'Fecha consumo' },
            { data: 'motivo', title: 'Motivo / diagnóstico' },
            { data: 'importe', title: 'Importe', className: 'dt-body-right', render: function (v) { return fmtImporte(v); } },
            { data: 'orden', title: 'Orden', visible: false }
        ];
    }

    function crearTabla(id, filas, columnas, alClick) {
        var nodo = jQuery('#' + id);
        if (jQuery.fn.DataTable.isDataTable(nodo)) {
            nodo.DataTable().clear().destroy();
        }
        var cabeza = nodo.find('thead tr');
        cabeza.empty();
        var i;
        for (i = 0; i < columnas.length; i++) {
            cabeza.append('<th>' + esc(columnas[i].title) + '</th>');
        }
        var exportar = {
            columns: ':visible',
            format: { body: function (dato) { return jQuery('<div>').html(dato).text(); } }
        };
        var titulo = document.getElementById('tituloMaster').textContent;
        var tabla = nodo.DataTable({
            data: filas || [],
            columns: columnas,
            order: [id === 'tblExplosion2' ? [3, 'asc'] : [0, 'desc']],
            deferRender: true,
            paging: true,
            pageLength: 25,
            lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todos']],
            lengthChange: true,
            autoWidth: false,
            dom: 'lBfrtip',
            buttons: botonesDe(titulo, exportar),
            language: idioma
        });
        tablasVivas[id] = { tabla: tabla, titulo: titulo, exportar: exportar };
        completarExportes(tablasVivas[id]);
        nodo.off('click.audit').on('click.audit', 'tbody tr', function () {
            var data = tabla.row(this).data();
            if (!data) { return; }
            nodo.find('tbody tr').removeClass('fila-activa');
            jQuery(this).addClass('fila-activa');
            alClick(data);
        });
        nodo.off('dblclick.audit').on('dblclick.audit', 'tbody tr', function () {
            var data = tabla.row(this).data();
            if (!data || !data.afiliado) { return; }
            abrirFicha(data.afiliado);
        });
        return tabla;
    }

    function vaciarExp1() {
        exp1Sel = null;
        document.getElementById('badgeExp1').textContent = '0';
        document.getElementById('selExp1').textContent = 'Aparece al elegir una fila del cuadro principal.';
        destruirTabla('tblExplosion1');
        tablaExp1 = null;
    }

    function vaciarExp2() {
        document.getElementById('badgeExp2').textContent = '0';
        document.getElementById('selExp2').textContent = 'Fecha por fecha, después de elegir el segundo cuadro.';
        destruirTabla('tblExplosion2');
        tablaExp2 = null;
    }

    function cuerpoBase() {
        return 'obrasoc=' + encodeURIComponent(document.getElementById('filtroObra').value)
            + '&desde=' + encodeURIComponent(document.getElementById('filtroDesde').value)
            + '&hasta=' + encodeURIComponent(document.getElementById('filtroHasta').value)
            + '&modalidad=' + encodeURIComponent(modalidad)
            + '&csrf_token=' + encodeURIComponent(document.getElementById('csrfAuditoria').value);
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
                try { return JSON.parse(texto); } catch (e) { return null; }
            });
        });
    }

    function avisoError(data) {
        Swal.fire({
            icon: 'error',
            title: 'No se pudo consultar',
            text: (data && data.message) ? data.message : 'No se pudo contactar al servidor.',
            confirmButtonColor: '#1565c0'
        });
    }

    function cargando(texto) {
        Swal.fire({
            title: 'Consultando consumo',
            text: texto,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); }
        });
    }

    function etiquetaMaster(fila) {
        if (modalidad === 'prestador') {
            return (fila.nombre || 'Prestador') + ' · ' + (fila.practica || 'sin práctica');
        }
        return (fila.nombre || 'Afiliado') + ' · ' + (fila.practica || 'sin práctica');
    }

    function cargarMaster() {
        masterSel = null;
        exp1Sel = null;
        cargando('Armando la matriz del rango elegido...');
        pedir('index.php?c=auditoria&a=ajaxGetMaster', cuerpoBase()).then(function (data) {
            Swal.close();
            if (!data || data.status !== 'success') {
                avisoError(data);
                return;
            }
            document.getElementById('badgeMaster').textContent = fmtEntero(data.total_filas || 0);
            document.getElementById('selMaster').textContent = 'Casos: ' + fmtEntero(data.total_casos || 0)
                + ' · Importe: ' + fmtImporte(data.total_importe || 0);
            tablaMaster = crearTabla('tblMaster', data.filas || [], columnasMaster(), alElegirMaster);
            vaciarExp1();
            vaciarExp2();
        }).catch(function () {
            Swal.close();
            avisoError(null);
        });
    }

    function alElegirMaster(fila) {
        masterSel = fila;
        exp1Sel = null;
        document.getElementById('selMaster').textContent = etiquetaMaster(fila)
            + ' · ' + fmtEntero(fila.casos) + ' casos';
        vaciarExp2();
        cargando('Buscando el desglose de la fila...');
        var extra = '&practica=' + encodeURIComponent(fila.practica || '')
            + '&afiliado=' + encodeURIComponent(fila.afiliado || '')
            + '&prestador=' + encodeURIComponent(fila.prestador || '');
        pedir('index.php?c=auditoria&a=ajaxGetExplosion1', cuerpoBase() + extra).then(function (data) {
            Swal.close();
            if (!data || data.status !== 'success') {
                avisoError(data);
                return;
            }
            document.getElementById('badgeExp1').textContent = fmtEntero(data.total_filas || 0);
            document.getElementById('selExp1').textContent = etiquetaMaster(fila);
            tablaExp1 = crearTabla('tblExplosion1', data.filas || [], columnasExp1(), alElegirExp1);
        }).catch(function () {
            Swal.close();
            avisoError(null);
        });
    }

    function alElegirExp1(fila) {
        exp1Sel = fila;
        var afiliado = modalidad === 'prestador' ? (fila.afiliado || '') : ((masterSel && masterSel.afiliado) || '');
        var prestador = modalidad === 'prestador' ? ((masterSel && masterSel.prestador) || '') : (fila.prestador || '');
        var practica = (masterSel && masterSel.practica) || '';
        document.getElementById('selExp1').textContent = (fila.nombre || '') + ' · ' + fmtEntero(fila.casos) + ' casos';
        cargando('Armando el detalle fecha por fecha...');
        var extra = '&practica=' + encodeURIComponent(practica)
            + '&afiliado=' + encodeURIComponent(afiliado)
            + '&prestador=' + encodeURIComponent(prestador);
        pedir('index.php?c=auditoria&a=ajaxGetExplosion2', cuerpoBase() + extra).then(function (data) {
            Swal.close();
            if (!data || data.status !== 'success') {
                avisoError(data);
                return;
            }
            document.getElementById('badgeExp2').textContent = fmtEntero(data.total_filas || 0);
            document.getElementById('selExp2').textContent = (fila.nombre || 'Detalle') + ' · ' + fmtImporte(data.total_importe || 0);
            tablaExp2 = crearTabla('tblExplosion2', data.filas || [], columnasExp2(), function () {});
        }).catch(function () {
            Swal.close();
            avisoError(null);
        });
    }

    function afiliadoActivo() {
        if (modalidad === 'prestador') {
            return exp1Sel;
        }
        return masterSel;
    }

    function prestadorActivo() {
        if (modalidad === 'prestador') {
            return masterSel;
        }
        return exp1Sel;
    }

    function abrirFicha(nroDirecto) {
        var nro = (typeof nroDirecto === 'string' || typeof nroDirecto === 'number') ? String(nroDirecto) : '';
        if (!nro) {
            var fila = afiliadoActivo();
            if (!fila || !fila.afiliado) {
                Swal.fire({
                    icon: 'info',
                    title: 'Falta el afiliado',
                    text: 'Elija primero la fila del afiliado que quiere consultar.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            nro = fila.afiliado;
        }
        if (!document.getElementById('filtroObra').value) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta la obra social',
                text: 'Seleccione una obra para leer la historia.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        cargando('Leyendo la historia clínica y los consumos...');
        pedir('index.php?c=auditoria&a=ajaxGetHistoriaAfiliado', cuerpoBase() + '&nro_afiliado=' + encodeURIComponent(nro)).then(function (data) {
            Swal.close();
            if (!data || data.status !== 'success') {
                avisoError(data);
                return;
            }
            var ficha = data.afiliado || {};
            if ((data.message) && !(data.consumos && data.consumos.length)) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin historia',
                    text: data.message,
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            document.getElementById('fichaNombre').textContent = ficha.nombre || 'Afiliado';
            document.getElementById('fichaPeriodo').textContent = (ficha.afiliado || nro) + (ficha.obrasoc ? ' · ' + ficha.obrasoc : '');
            document.getElementById('fichaTotal').textContent = 'Registros totales: ' + fmtEntero(data.total_registros || 0);
            var campos = [
                ['Afiliado', ficha.afiliado || nro],
                ['Documento', ficha.documento],
                ['Plan', ficha.plan],
                ['Edad', ficha.edad],
                ['Sexo', ficha.sexo],
                ['Teléfono', ficha.telefono],
                ['Localidad', ficha.localidad],
                ['Domicilio', ficha.domicilio]
            ];
            var html = '';
            var i;
            for (i = 0; i < campos.length; i++) {
                html += '<div><dt class="text-[10px] font-bold uppercase text-slate-400">' + esc(campos[i][0]) + '</dt>'
                    + '<dd class="truncate text-slate-800" title="' + esc(campos[i][1] || '') + '">' + esc(campos[i][1] || '—') + '</dd></div>';
            }
            document.getElementById('fichaCuerpo').innerHTML = html;
            var modal = document.getElementById('modalFicha');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            dibujarHistoria(data.consumos || []);
        }).catch(function () {
            Swal.close();
            avisoError(null);
        });
    }

    function dibujarHistoria(filas) {
        var nodo = jQuery('#tblHistoria');
        if (jQuery.fn.DataTable.isDataTable(nodo)) {
            nodo.DataTable().clear().destroy();
        }
        var columnas = [
            { data: 'periodo', title: 'Período' },
            { data: 'fecha', title: 'F. Prestación' },
            { data: null, title: 'Práctica', render: function (data, type) {
                var texto = ((data.practica || '') + ' ' + (data.descripcion || '')).replace(/^\s+|\s+$/g, '');
                if (type && type !== 'display') { return texto; }
                return '<span class="font-semibold">' + esc(data.practica || '') + '</span> ' + esc(data.descripcion || '');
            } },
            { data: 'prestador', title: 'Prestador' },
            { data: 'derivante', title: 'Derivante' },
            { data: 'cantidad', title: 'Cantidad', className: 'dt-body-right' },
            { data: 'importe', title: 'Importe', className: 'dt-body-right', render: function (v) { return fmtImporte(v); } },
            { data: 'facturado', title: 'Facturado', className: 'dt-body-right', render: function (v) { return fmtImporte(v); } },
            { data: 'diagnostico', title: 'Diagnóstico' },
            { data: 'debito', title: 'Débito' },
            { data: 'motivo_debito', title: 'Motivo débito' },
            { data: 'autorizacion', title: 'Nro autorización' },
            { data: 'internacion', title: 'Internación' },
            { data: 'periodo_orden', title: 'Orden período', visible: false },
            { data: 'fecha_orden', title: 'Orden fecha', visible: false }
        ];
        var cabeza = nodo.find('thead tr');
        cabeza.empty();
        var i;
        for (i = 0; i < columnas.length; i++) {
            cabeza.append('<th>' + esc(columnas[i].title) + '</th>');
        }
        var exportar = {
            columns: ':visible',
            orthogonal: 'export',
            format: { body: function (dato) { return jQuery('<div>').html(dato).text(); } }
        };
        var tabla = nodo.DataTable({
            data: filas,
            columns: columnas,
            order: [[13, 'desc'], [14, 'desc']],
            deferRender: true,
            paging: true,
            pageLength: 50,
            lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todos']],
            lengthChange: true,
            autoWidth: false,
            dom: 'lBfrtip',
            buttons: botonesDe('Historia clínica', exportar),
            language: idioma,
            createdRow: function (row, data) {
                if (data.es_debito) {
                    jQuery(row).addClass('fila-debito');
                } else if (data.es_refactura) {
                    jQuery(row).addClass('fila-refactura');
                }
            }
        });
        tablasVivas.tblHistoria = { tabla: tabla, titulo: 'Historia clínica', exportar: exportar };
        completarExportes(tablasVivas.tblHistoria);
        setTimeout(function () { tabla.columns.adjust(); }, 0);
    }

    function cerrarFicha() {
        var modal = document.getElementById('modalFicha');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function obraLegible() {
        var select = document.getElementById('filtroObra');
        var opcion = select.options[select.selectedIndex];
        return opcion ? opcion.text : '';
    }

    function redactar(destino) {
        var afiliado = afiliadoActivo();
        var prestador = prestadorActivo();
        if (destino === 'prestador' && (!prestador || !prestador.prestador)) {
            Swal.fire({
                icon: 'info',
                title: 'Falta el prestador',
                text: 'Elija la fila del prestador antes de redactar el pedido.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        if (destino === 'afiliado' && (!afiliado || !afiliado.afiliado)) {
            Swal.fire({
                icon: 'info',
                title: 'Falta el afiliado',
                text: 'Elija la fila del afiliado antes de redactar el aviso.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        var practica = (masterSel && masterSel.practica) ? masterSel.practica : '';
        var descripcion = (masterSel && masterSel.descripcion) ? masterSel.descripcion : '';
        var rango = document.getElementById('filtroDesde').value + ' a ' + document.getElementById('filtroHasta').value;
        var texto;
        if (destino === 'prestador') {
            texto = 'Estimado/a ' + (prestador.nombre || 'prestador') + ' (código ' + prestador.prestador + '):\n\n'
                + 'En el marco de la auditoría de consumo de ' + obraLegible() + ', período ' + rango + ', '
                + 'registramos ' + fmtEntero(prestador.casos || (masterSel && masterSel.casos) || 0) + ' casos de la práctica '
                + practica + ' ' + descripcion + '.\n\n'
                + 'Solicitamos historia clínica o justificación del consumo atípico, dentro de los próximos 10 días hábiles.\n\n'
                + 'Auditoría Médica — COMEDICA';
        } else {
            texto = 'Estimado/a ' + (afiliado.nombre || 'afiliado') + ' (afiliado ' + afiliado.afiliado + '):\n\n'
                + 'Estamos verificando prestaciones de ' + obraLegible() + ' entre ' + rango + ', práctica '
                + practica + ' ' + descripcion + '.\n\n'
                + 'Le pedimos que confirme si recibió estas atenciones. Si algún consumo no le corresponde, responda este aviso.\n\n'
                + 'Auditoría Médica — COMEDICA';
        }
        Swal.fire({
            title: destino === 'prestador' ? 'Pedido al prestador' : 'Aviso al afiliado',
            html: '<textarea id="textoAuditoria" class="swal2-textarea" style="height:220px">' + esc(texto) + '</textarea>',
            showCancelButton: true,
            confirmButtonText: 'Copiar texto',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#1565c0',
            cancelButtonColor: '#64748b',
            preConfirm: function () {
                var area = document.getElementById('textoAuditoria');
                return area ? area.value : texto;
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) { return; }
            var copiar = resultado.value || texto;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copiar).then(function () {
                    Swal.fire({ icon: 'success', title: 'Texto copiado', text: 'El aviso quedó en el portapapeles. No se envió ningún correo.', confirmButtonColor: '#1565c0' });
                }).catch(function () {
                    Swal.fire({ icon: 'info', title: 'Texto listo', text: 'No se pudo copiar solo. Selecciónelo en el cuadro y cópielo a mano.', confirmButtonColor: '#1565c0' });
                });
                return;
            }
            Swal.fire({ icon: 'success', title: 'Texto listo', text: 'Selecciónelo y cópielo. No se envió ningún correo.', confirmButtonColor: '#1565c0' });
        });
    }

    document.getElementById('modosAuditoria').addEventListener('click', function (e) {
        var boton = e.target.closest ? e.target.closest('button[data-modo]') : null;
        if (!boton) { return; }
        modalidad = boton.getAttribute('data-modo');
        masterSel = null;
        exp1Sel = null;
        pintarModo();
        document.getElementById('badgeMaster').textContent = '0';
        destruirTabla('tblMaster');
        tablaMaster = null;
        vaciarExp1();
        vaciarExp2();
    });

    document.getElementById('formAuditoria').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!document.getElementById('filtroObra').value) {
            Swal.fire({ icon: 'warning', title: 'Falta la obra social', text: 'Seleccione una obra para auditar.', confirmButtonColor: '#1565c0' });
            return;
        }
        cargarMaster();
    });

    document.getElementById('btnFicha').addEventListener('click', function () { abrirFicha(); });
    document.getElementById('btnMailPrestador').addEventListener('click', function () { redactar('prestador'); });
    document.getElementById('btnMailAfiliado').addEventListener('click', function () { redactar('afiliado'); });
    document.getElementById('cerrarFicha').addEventListener('click', cerrarFicha);
    document.getElementById('modalFicha').addEventListener('click', function (e) {
        if (e.target === document.getElementById('modalFicha')) { cerrarFicha(); }
    });

    pintarModo();
    prepararExportes();

    if (errorCarga) {
        Swal.fire({ icon: 'error', title: 'Auditoría no disponible', text: errorCarga, confirmButtonColor: '#1565c0' });
    }
})();
</script>
