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
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <div class="flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del cuadro
                </div>
                <button type="button" class="js-analizar inline-flex items-center gap-1 rounded-lg bg-[#1565c0] px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-[#0d47a1]" data-cuadro="cargadas">
                    <i class="fa-solid fa-brain"></i> Análisis IA
                </button>
            </div>
            <div id="analisisCargadas" class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium leading-relaxed text-slate-800">
                <p class="text-slate-400">El análisis se calcula al consultar.</p>
            </div>
            <div id="iaCargadas" class="mt-2 hidden rounded-lg border border-blue-100 bg-white p-3 text-xs leading-relaxed text-slate-800"></div>
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
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <div class="flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del cuadro
                </div>
                <button type="button" class="js-analizar inline-flex items-center gap-1 rounded-lg bg-[#1565c0] px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-[#0d47a1]" data-cuadro="estados">
                    <i class="fa-solid fa-brain"></i> Análisis IA
                </button>
            </div>
            <div id="analisisEstados" class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium leading-relaxed text-slate-800">
                <p class="text-slate-400">El análisis se calcula al consultar.</p>
            </div>
            <div id="iaEstados" class="mt-2 hidden rounded-lg border border-blue-100 bg-white p-3 text-xs leading-relaxed text-slate-800"></div>
        </div>
    </section>
</div>
<?php elseif ($reporte === 'facturacion'): ?>
<p class="mb-4 text-xs text-slate-500">
    Cada fila es un registro de <span class="font-medium">vfpmedicos.detconsu</span> cargado por data entry.
    La fecha es <span class="font-medium">COFECHACAR</span> y la persona es <span class="font-medium">COUSUARIO</span>.
    <span class="font-medium">NOE</span> no se cuenta: es auditora y sus registros son ajustes.
    La jornada de cálculo es de 8 horas con 60 minutos de descanso: 7 horas netas.
</p>
<div class="grid grid-cols-1 gap-4">
    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Registros por data entry</h2>
                <p class="mt-1 text-xs text-slate-500">Prom/día es el promedio de los días en que esa persona cargó. Reg/hora y Min/reg salen de las 7 horas netas. Un clic en la cantidad abre esos registros.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="factura-usuarios">
                    <i class="fa-solid fa-file-excel text-emerald-700"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="factura-usuarios">
                    <i class="fa-solid fa-file-pdf text-red-700"></i> PDF
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Usuario</th>
                        <th class="px-3 py-2 text-right font-semibold">Registros</th>
                        <th class="px-3 py-2 text-right font-semibold">Días</th>
                        <th class="px-3 py-2 text-right font-semibold" title="Registros divididos por los días con carga">Prom/día</th>
                        <th class="px-3 py-2 text-right font-semibold" title="Promedio diario dividido 7 horas netas">Reg/hora</th>
                        <th class="px-3 py-2 text-right font-semibold" title="420 minutos netos divididos el promedio diario">Min/reg</th>
                    </tr>
                </thead>
                <tbody id="cuerpoFacturaUsuarios" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Carga por día</h2>
                <p class="mt-1 text-xs text-slate-500">Un clic abre los registros de esa persona en ese día.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="factura-dias">
                    <i class="fa-solid fa-file-excel text-emerald-700"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-cual="factura-dias">
                    <i class="fa-solid fa-file-pdf text-red-700"></i> PDF
                </button>
                <span id="badgeFacturaDias" class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-[#0d47a1]">0</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead id="cabezaFacturaDias" class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100"></thead>
                <tbody id="cuerpoFacturaDias" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <div class="flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del cuadro
                </div>
                <button type="button" class="js-analizar inline-flex items-center gap-1 rounded-lg bg-[#1565c0] px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-[#0d47a1]" data-cuadro="facturacion">
                    <i class="fa-solid fa-brain"></i> Análisis IA
                </button>
            </div>
            <div id="analisisFacturacion" class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium leading-relaxed text-slate-800">
                <p class="text-slate-400">El análisis se calcula al consultar.</p>
            </div>
            <div id="iaFacturacion" class="mt-2 hidden rounded-lg border border-blue-100 bg-white p-3 text-xs leading-relaxed text-slate-800"></div>
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

<div id="modalFactura" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-2">
    <div class="flex h-[92vh] w-[98vw] max-w-none flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-3 text-white">
            <div>
                <h2 id="facturaTitulo" class="text-sm font-bold">Registros cargados</h2>
                <p id="facturaSubtitulo" class="text-xs text-white/75"></p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="js-excel inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="factura-detalle">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="js-pdf inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/15 px-2.5 py-1 text-[11px] font-semibold hover:bg-white/25" data-cual="factura-detalle">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" id="cerrarModalFactura" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="sticky top-0 bg-slate-900 text-[11px] uppercase tracking-wide text-slate-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Fecha carga</th>
                        <th class="px-3 py-2 font-semibold">Hora</th>
                        <th class="px-3 py-2 font-semibold">Usuario</th>
                        <th class="px-3 py-2 font-semibold">Obra social</th>
                        <th class="px-3 py-2 font-semibold">Prestador</th>
                        <th class="px-3 py-2 font-semibold">Paciente</th>
                        <th class="px-3 py-2 font-semibold">Práctica</th>
                        <th class="px-3 py-2 text-right font-semibold">Importe</th>
                        <th class="px-3 py-2 font-semibold">Período</th>
                    </tr>
                </thead>
                <tbody id="cuerpoFacturaDetalle" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

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
    var datosFactura = { usuarios: [], columnas: [], dias: [], jornada: { neta_horas: 7, neta_min: 420 } };
    var datosFacturaDetalle = { titulo: '', subtitulo: '', filas: [] };
    var modalPracticas = document.getElementById('modalPracticas');
    var analisisTicket = { cargadas: 0, estados: 0 };

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
        if (reporte === 'facturacion') {
            consultarFacturacion();
            return;
        }
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
            pintarAnalisis('cargadas', datosCargadas);
            pintarAnalisis('estados', datosEstados);
            pedirAnalisis('cargadas');
            pedirAnalisis('estados');
        }).catch(function () {
            aviso('No se pudo consultar el rango.');
        });
    }

    function rangoTexto() {
        return fechaCorta(document.getElementById('filtroDesde').value) + ' al ' + fechaCorta(document.getElementById('filtroHasta').value);
    }

    function sumarCampo(filas, campo) {
        var total = 0;
        var i;
        for (i = 0; i < filas.length; i++) {
            total += Number(filas[i][campo]) || 0;
        }
        return total;
    }

    function extremo(filas, valorDe) {
        var mejor = null;
        var peor = null;
        var i;
        var n;
        for (i = 0; i < filas.length; i++) {
            n = valorDe(filas[i]);
            if (mejor === null || n > mejor.valor) {
                mejor = { fila: filas[i], valor: n };
            }
            if (peor === null || n < peor.valor) {
                peor = { fila: filas[i], valor: n };
            }
        }
        return { alto: mejor, bajo: peor };
    }

    function nombreDia(iso) {
        var partes = String(iso || '').split('-');
        if (partes.length < 3) {
            return '';
        }
        var fecha = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
        return ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][fecha.getDay()];
    }

    function fraseDia(fila) {
        return 'el ' + nombreDia(fila.dia) + ' ' + fechaCorta(fila.dia);
    }

    function filasConMaximo(filas, valorDe) {
        var maximo = null;
        var elegidas = [];
        var i;
        var n;
        for (i = 0; i < filas.length; i++) {
            n = valorDe(filas[i]);
            if (maximo === null || n > maximo) {
                maximo = n;
                elegidas = [filas[i]];
            } else if (n === maximo) {
                elegidas.push(filas[i]);
            }
        }
        return elegidas;
    }

    function fraseVariosDias(filas) {
        var textos = [];
        var i;
        for (i = 0; i < filas.length; i++) {
            textos.push(fraseDia(filas[i]));
        }
        if (textos.length <= 1) {
            return textos[0] || '';
        }
        return textos.slice(0, textos.length - 1).join(', ') + ' y ' + textos[textos.length - 1];
    }

    function textoAnalisis(cuadro, filas) {
        if (!filas || !filas.length) {
            return '<p>No hay datos en ese rango.</p>';
        }
        var rango = rangoTexto();
        if (cuadro === 'cargadas') {
            var marcas = extremo(filas, function (fila) { return Number(fila.total) || 0; });
            var meds = extremo(filas, function (fila) { return Number(fila.medicamentos) || 0; });
            var alto = marcas.alto.fila;
            var html = '<p>Del ' + rango + ' se cargaron <b>' + entero(sumarCampo(filas, 'total')) + '</b> órdenes: '
                + entero(sumarCampo(filas, 'ambulatorio')) + ' ambulatorias, '
                + entero(sumarCampo(filas, 'sanatorial')) + ' sanatoriales y '
                + entero(sumarCampo(filas, 'medicamentos')) + ' de medicamentos.</p>'
                + '<p>El día más alto fue ' + fraseDia(alto) + ', con <b>' + entero(alto.total) + '</b>: '
                + entero(alto.ambulatorio) + ' ambulatorias, ' + entero(alto.sanatorial) + ' sanatoriales y '
                + entero(alto.medicamentos) + ' medicamentos.</p>';
            if (filas.length > 1 && marcas.bajo.fila.dia !== alto.dia) {
                html += '<p>El día más bajo fue ' + fraseDia(marcas.bajo.fila) + ', con <b>' + entero(marcas.bajo.valor) + '</b>.</p>';
            }
            if (meds.alto.valor > 0 && meds.alto.fila.dia !== alto.dia) {
                html += '<p>El máximo de medicamentos fue ' + fraseDia(meds.alto.fila) + ', con <b>' + entero(meds.alto.valor) + '</b>.</p>';
            }
            return html;
        }
        var diasAutorizados = filasConMaximo(filas, function (fila) {
            return (Number(fila.ambulatorio) || 0) + (Number(fila.sanatorial) || 0);
        });
        var practicas = extremo(filas, function (fila) { return Number(fila.practicas) || 0; });
        var sinNumero = sumarCampo(filas, 'sin_numero');
        var texto = '<p>Del ' + rango + ' se autorizaron <b>' + entero(sumarCampo(filas, 'ambulatorio') + sumarCampo(filas, 'sanatorial')) + '</b> órdenes: '
            + entero(sumarCampo(filas, 'ambulatorio')) + ' ambulatorias y '
            + entero(sumarCampo(filas, 'sanatorial')) + ' sanatoriales. Las prácticas suman <b>' + entero(sumarCampo(filas, 'practicas')) + '</b>. '
            + 'Las rechazadas, por fecha de la orden, suman <b>' + entero(sumarCampo(filas, 'rechazadas')) + '</b>. '
            + 'El total del cuadro es <b>' + entero(sumarCampo(filas, 'total')) + '</b>.</p>';
        if (diasAutorizados.length && ((Number(diasAutorizados[0].ambulatorio) || 0) + (Number(diasAutorizados[0].sanatorial) || 0)) > 0) {
            var cantidadAut = (Number(diasAutorizados[0].ambulatorio) || 0) + (Number(diasAutorizados[0].sanatorial) || 0);
            texto += '<p>' + (diasAutorizados.length > 1 ? 'Los días con más órdenes autorizadas fueron ' : 'El día con más órdenes autorizadas fue ')
                + fraseVariosDias(diasAutorizados) + ', con <b>' + entero(cantidadAut) + '</b>';
            if (diasAutorizados.length === 1) {
                texto += ' (' + entero(diasAutorizados[0].ambulatorio) + ' ambulatorias y ' + entero(diasAutorizados[0].sanatorial) + ' sanatoriales)';
            }
            texto += '.</p>';
        }
        if (practicas.alto.valor > 0) {
            var mismoDia = diasAutorizados.length === 1 && practicas.alto.fila.dia === diasAutorizados[0].dia;
            if (!mismoDia) {
                texto += '<p>El día con más prácticas fue ' + fraseDia(practicas.alto.fila) + ', con <b>' + entero(practicas.alto.valor) + '</b>.</p>';
            }
        }
        if (sinNumero > 0) {
            texto += '<p><b>' + entero(sinNumero) + '</b> autorizadas no tienen número y están contadas en ambulatorio o sanatorial.</p>';
        }
        return texto;
    }

    function pintarAnalisis(cuadro, filas) {
        var caja = document.getElementById(idAnalisis(cuadro));
        if (!caja) {
            return;
        }
        caja.innerHTML = cuadro === 'facturacion' ? textoFacturacion() : textoAnalisis(cuadro, filas || []);
    }

    function idAnalisis(cuadro) {
        if (cuadro === 'cargadas') {
            return 'analisisCargadas';
        }
        if (cuadro === 'estados') {
            return 'analisisEstados';
        }
        return 'analisisFacturacion';
    }

    function idIa(cuadro) {
        if (cuadro === 'cargadas') {
            return 'iaCargadas';
        }
        if (cuadro === 'estados') {
            return 'iaEstados';
        }
        return 'iaFacturacion';
    }

    function decimal(valor) {
        var numero = Math.round((Number(valor) || 0) * 10) / 10;
        return String(numero).replace('.', ',');
    }

    function consultarFacturacion() {
        post('index.php?c=sectores&a=ajaxResumenFacturacion', {}).then(function (data) {
            if (!data || data.status !== 'success') {
                aviso((data && data.message) ? data.message : 'No se pudo consultar la carga.');
                return;
            }
            datosFactura = data;
            pintarFacturacion();
            pintarAnalisis('facturacion');
            pedirAnalisis('facturacion');
        }).catch(function () {
            aviso('No se pudo consultar la carga.');
        });
    }

    function celdaFactura(dia, usuario, cantidad) {
        var n = Number(cantidad) || 0;
        if (n === 0) {
            return '<td class="px-3 py-2 text-right text-slate-300">0</td>';
        }
        return '<td class="px-3 py-2 text-right">'
            + '<button type="button" class="js-factura font-semibold underline decoration-dotted text-[#1565c0]" data-dia="'
            + xml(dia) + '" data-usuario="' + xml(usuario) + '">' + entero(n) + '</button></td>';
    }

    function pintarFacturacion() {
        var usuarios = datosFactura.usuarios || [];
        var columnas = datosFactura.columnas || [];
        var dias = datosFactura.dias || [];
        var cuerpo = document.getElementById('cuerpoFacturaUsuarios');
        var html = '';
        var i;
        var j;
        if (!usuarios.length) {
            cuerpo.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-400">No hay registros cargados en ese rango.</td></tr>';
        } else {
            for (i = 0; i < usuarios.length; i++) {
                var persona = usuarios[i];
                html += '<tr class="hover:bg-slate-50">'
                    + '<td class="px-3 py-2 font-medium text-slate-800">' + textoCelda(persona.usuario) + '</td>'
                    + celdaFactura('', persona.usuario, persona.registros)
                    + '<td class="px-3 py-2 text-right">' + entero(persona.dias) + '</td>'
                    + '<td class="px-3 py-2 text-right">' + decimal(persona.promedio) + '</td>'
                    + '<td class="px-3 py-2 text-right">' + decimal(persona.por_hora) + '</td>'
                    + '<td class="px-3 py-2 text-right">' + decimal(persona.minutos) + '</td>'
                    + '</tr>';
            }
            cuerpo.innerHTML = html;
        }

        var cabeza = '<tr><th class="px-3 py-2 font-semibold">Fecha</th>';
        for (i = 0; i < columnas.length; i++) {
            cabeza += '<th class="px-3 py-2 text-right font-semibold">' + xml(columnas[i]) + '</th>';
        }
        cabeza += '<th class="px-3 py-2 text-right font-semibold">Total</th></tr>';
        document.getElementById('cabezaFacturaDias').innerHTML = cabeza;

        html = '';
        for (i = 0; i < dias.length; i++) {
            var fila = dias[i];
            html += '<tr class="hover:bg-slate-50"><td class="px-3 py-2 font-medium text-slate-800">' + fechaCorta(fila.dia) + '</td>';
            for (j = 0; j < columnas.length; j++) {
                html += celdaFactura(fila.dia, columnas[j], fila.cargas ? fila.cargas[columnas[j]] : 0);
            }
            html += celdaFactura(fila.dia, '', fila.total) + '</tr>';
        }
        if (!dias.length) {
            html = '<tr><td class="px-3 py-8 text-center text-sm text-slate-400">No hay días con carga en ese rango.</td></tr>';
        }
        document.getElementById('cuerpoFacturaDias').innerHTML = html;
        document.getElementById('badgeFacturaDias').textContent = String(dias.length) + ' días';
    }

    function textoFacturacion() {
        var usuarios = datosFactura.usuarios || [];
        if (!usuarios.length) {
            return '<p>No hay registros de data entry en ese rango.</p>';
        }
        var total = 0;
        var diasPersona = 0;
        var mas = usuarios[0];
        var rapido = usuarios[0];
        var i;
        for (i = 0; i < usuarios.length; i++) {
            total += Number(usuarios[i].registros) || 0;
            diasPersona += Number(usuarios[i].dias) || 0;
            if ((Number(usuarios[i].por_hora) || 0) > (Number(rapido.por_hora) || 0)) {
                rapido = usuarios[i];
            }
        }
        var porHora = diasPersona > 0 ? (total / diasPersona) / 7 : 0;
        var minutos = total > 0 ? (diasPersona * 420) / total : 0;
        var html = '<p>Del ' + rangoTexto() + ' se cargaron <b>' + entero(total) + '</b> registros. '
            + 'La jornada de cálculo es de <b>8 horas</b> con <b>60 minutos</b> de descanso: quedan <b>7 horas netas</b>, 420 minutos, por cada día en que la persona cargó. NOE no entra: es auditora y esos registros son ajustes.</p>'
            + '<p>El equipo promedia <b>' + decimal(porHora) + '</b> registros por hora neta y <b>' + decimal(minutos) + '</b> minutos por registro.</p>'
            + '<p>Quien más cargó fue <b>' + xml(mas.usuario) + '</b>, con ' + entero(mas.registros) + ' registros en ' + entero(mas.dias)
            + ' días: ' + decimal(mas.promedio) + ' por día, ' + decimal(mas.por_hora) + ' por hora y ' + decimal(mas.minutos) + ' minutos por registro.</p>';
        if (rapido.usuario !== mas.usuario) {
            html += '<p>El ritmo más alto es el de <b>' + xml(rapido.usuario) + '</b>: ' + decimal(rapido.por_hora) + ' registros por hora neta.</p>';
        }
        return html;
    }

    function abrirDetalleFactura(dia, usuario) {
        post('index.php?c=sectores&a=ajaxDetalleFacturacion', {
            dia: dia || '',
            usuario: usuario || ''
        }).then(function (data) {
            if (!data || data.status !== 'success') {
                aviso((data && data.message) ? data.message : 'No se pudo abrir el detalle.');
                return;
            }
            var titulo = 'Registros cargados';
            if (usuario && dia) {
                titulo = usuario + ' del ' + fechaCorta(dia);
            } else if (usuario) {
                titulo = usuario + ' del ' + rangoTexto();
            } else if (dia) {
                titulo = 'Carga del ' + fechaCorta(dia);
            }
            var subtitulo = (data.total || 0) + ' registros';
            if (data.recorte) {
                subtitulo += '. Se muestran los primeros ' + (data.filas || []).length + '.';
            }
            pintarDetalleFactura(titulo, subtitulo, data.filas || []);
        }).catch(function () {
            aviso('No se pudo abrir el detalle.');
        });
    }

    function pintarDetalleFactura(titulo, subtitulo, filas) {
        datosFacturaDetalle = { titulo: titulo, subtitulo: subtitulo, filas: filas || [] };
        var cuerpo = document.getElementById('cuerpoFacturaDetalle');
        var html = '';
        var i;
        document.getElementById('facturaTitulo').textContent = titulo;
        document.getElementById('facturaSubtitulo').textContent = subtitulo;
        if (!filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="9" class="px-3 py-8 text-center text-sm text-slate-400">No hay registros para mostrar.</td></tr>';
        } else {
            for (i = 0; i < filas.length; i++) {
                var fila = filas[i];
                html += '<tr class="hover:bg-slate-50">'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.fecha) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.hora) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.usuario) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.obra) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(fila.nombre_prestador) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(fila.paciente) + '</td>'
                    + '<td class="px-3 py-2">' + textoCelda(fila.codigo) + ' ' + textoCelda(fila.practica) + '</td>'
                    + '<td class="px-3 py-2 text-right whitespace-nowrap">' + textoCelda(fila.importe) + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + textoCelda(fila.periodo) + '</td>'
                    + '</tr>';
            }
            cuerpo.innerHTML = html;
        }
        var modalFactura = document.getElementById('modalFactura');
        modalFactura.classList.remove('hidden');
        modalFactura.classList.add('flex');
    }

    function pedirAnalisis(cuadro) {
        var caja = document.getElementById(idIa(cuadro));
        if (!caja) {
            return;
        }
        var ticket = ++analisisTicket[cuadro];
        caja.classList.remove('hidden');
        caja.innerHTML = '<p class="text-slate-400">La IA está leyendo este cuadro.</p>';
        post('index.php?c=sectores&a=ajaxAnalisisCuadro', { cuadro: cuadro }).then(function (data) {
            if (ticket !== analisisTicket[cuadro]) {
                return;
            }
            if (!data || data.status !== 'success') {
                caja.innerHTML = '<p class="text-red-700">' + textoCelda((data && data.message) ? data.message : 'La IA no respondió.') + '</p>';
                return;
            }
            caja.innerHTML = data.html || '';
        }).catch(function () {
            if (ticket !== analisisTicket[cuadro]) {
                return;
            }
            caja.innerHTML = '<p class="text-red-700">No se pudo consultar el análisis IA.</p>';
        });
    }

    function plano(valor) {
        if (valor === null || valor === undefined || String(valor) === '') {
            return '';
        }
        return String(valor);
    }

    function cuadroExportable(cual) {
        if (cual === 'factura-usuarios') {
            var personas = [];
            var u;
            var lista = datosFactura.usuarios || [];
            for (u = 0; u < lista.length; u++) {
                personas.push([
                    plano(lista[u].usuario),
                    entero(lista[u].registros),
                    entero(lista[u].dias),
                    decimal(lista[u].promedio),
                    decimal(lista[u].por_hora),
                    decimal(lista[u].minutos)
                ]);
            }
            return {
                titulo: 'Registros por data entry',
                nombre: 'facturacion-usuarios',
                columnas: ['Usuario', 'Registros', 'Días', 'Prom/día', 'Reg/hora', 'Min/reg'],
                filas: personas
            };
        }
        if (cual === 'factura-dias') {
            var diasExp = [];
            var columnasDias = ['Fecha'].concat(datosFactura.columnas || []).concat(['Total']);
            var d;
            var c;
            var diasLista = datosFactura.dias || [];
            for (d = 0; d < diasLista.length; d++) {
                var lineaDia = [fechaCorta(diasLista[d].dia)];
                for (c = 0; c < (datosFactura.columnas || []).length; c++) {
                    var clave = datosFactura.columnas[c];
                    lineaDia.push(entero(diasLista[d].cargas && diasLista[d].cargas[clave] ? diasLista[d].cargas[clave] : 0));
                }
                lineaDia.push(entero(diasLista[d].total));
                diasExp.push(lineaDia);
            }
            return {
                titulo: 'Carga de facturación por día',
                nombre: 'facturacion-dias',
                columnas: columnasDias,
                filas: diasExp
            };
        }
        if (cual === 'factura-detalle') {
            var detalleFactura = [];
            var f;
            for (f = 0; f < datosFacturaDetalle.filas.length; f++) {
                var item = datosFacturaDetalle.filas[f];
                detalleFactura.push([
                    plano(item.fecha),
                    plano(item.hora),
                    plano(item.usuario),
                    plano(item.obra),
                    plano(item.nombre_prestador),
                    plano(item.paciente),
                    plano(item.codigo) + ' ' + plano(item.practica),
                    plano(item.importe),
                    plano(item.periodo)
                ]);
            }
            return {
                titulo: datosFacturaDetalle.titulo || 'Registros cargados',
                nombre: 'facturacion-detalle',
                columnas: ['Fecha carga', 'Hora', 'Usuario', 'Obra social', 'Prestador', 'Paciente', 'Práctica', 'Importe', 'Período'],
                filas: detalleFactura
            };
        }
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
        document.addEventListener('click', function (e) {
            var nodo = e.target;
            while (nodo && nodo !== document) {
                if (nodo.classList && nodo.classList.contains('js-analizar')) {
                    pedirAnalisis(nodo.getAttribute('data-cuadro'));
                    return;
                }
                nodo = nodo.parentNode;
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

    if (reporte === 'facturacion') {
        document.getElementById('cuerpoFacturaUsuarios').addEventListener('click', abrirFacturaDesdeBoton);
        document.getElementById('cuerpoFacturaDias').addEventListener('click', abrirFacturaDesdeBoton);
        document.getElementById('cerrarModalFactura').addEventListener('click', cerrarFactura);
        document.getElementById('modalFactura').addEventListener('click', function (e) {
            if (e.target.id === 'modalFactura') {
                cerrarFactura();
            }
        });
    }

    function abrirFacturaDesdeBoton(e) {
        var nodo = e.target;
        while (nodo && nodo !== document) {
            if (nodo.classList && nodo.classList.contains('js-factura')) {
                break;
            }
            nodo = nodo.parentNode;
        }
        if (!nodo || nodo === document) {
            return;
        }
        abrirDetalleFactura(nodo.getAttribute('data-dia') || '', nodo.getAttribute('data-usuario') || '');
    }

    function cerrarFactura() {
        var modalFactura = document.getElementById('modalFactura');
        modalFactura.classList.add('hidden');
        modalFactura.classList.remove('flex');
    }

    if (errorCarga) {
        aviso(errorCarga);
    } else if (reporte === 'ordenes' || reporte === 'facturacion') {
        consultar();
    }
})();
</script>
