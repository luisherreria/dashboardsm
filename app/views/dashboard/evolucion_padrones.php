<?php
$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$categ = isset($_SESSION['categ']) ? $_SESSION['categ'] : '';
$usuarioId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
$esAdmin = ($rol === 'admin');

$accesos = array(
    array(
        'id'    => 'padrones',
        'label' => 'Evolución de Padrones',
        'href'  => 'index.php?c=dashboard&a=evolucionPadrones',
        'icon'  => 'fa-solid fa-chart-line',
        'texto' => 'Seguimiento del padrón',
    ),
);

if ($esAdmin) {
    $accesos[] = array(
        'id'    => 'admin',
        'label' => 'Administradores',
        'href'  => 'index.php?c=dashboard&a=administradores',
        'icon'  => 'fa-solid fa-user-shield',
        'texto' => 'Cuentas internas',
    );
}

$accesosJson = json_encode($accesos);
if ($accesosJson === false) {
    $accesosJson = '[]';
}

if (!isset($obras) || !is_array($obras)) {
    $obras = array();
}
if (!isset($anios) || !is_array($anios)) {
    $anios = array();
}
$aniosMarcados = array();
$marcaAnios = 0;
foreach ($anios as $anioItem) {
    if ($marcaAnios >= 4) {
        break;
    }
    $aniosMarcados[(int) $anioItem] = true;
    $marcaAnios++;
}
$csrfPadron = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
$mensajeCarga = isset($errorCarga) ? $errorCarga : '';
$flagsJson = 0;
if (defined('JSON_UNESCAPED_UNICODE')) {
    $flagsJson = JSON_UNESCAPED_UNICODE;
}
?>

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-lg font-bold text-[#0d47a1]">
            <i class="fa-solid fa-chart-line mr-2"></i>Evolución de Padrones
        </h1>
        <p class="text-sm text-slate-500">
            Bienvenido, <strong class="text-slate-700"><?php echo h($nombre); ?></strong>.
            Hoy es <strong class="text-slate-700"><?php echo h(dm_fecha_larga(time())); ?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center gap-1 rounded-full bg-[#1565c0] px-3 py-1.5 text-xs font-semibold text-white">
            <i class="fa-solid fa-id-badge"></i>
            <?php echo h(dm_rol_legible($rol)); ?>
        </span>
        <?php if ($categ !== ''): ?>
        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-semibold text-sky-900">
            <i class="fa-solid fa-layer-group"></i>
            Categoría <?php echo h($categ); ?>
        </span>
        <?php endif; ?>
    </div>
</div>

<section class="mb-4 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1976d2] px-4 py-2.5 text-white">
        <div class="flex items-center gap-2 text-sm font-semibold">
            <i class="fa-solid fa-star"></i>
            Accesos rápidos
            <span id="badge-accesos" class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-[#0d47a1]">0</span>
        </div>
        <button type="button" id="btnPersonalizar"
                class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#0d47a1] hover:bg-blue-50">
            <i class="fa-solid fa-plus mr-1"></i>Personalizar
        </button>
    </div>
    <div class="p-4">
        <div id="grillaAccesos" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"></div>
        <div id="accesosVacios" class="hidden py-8 text-center text-sm text-slate-500">
            <i class="fa-solid fa-star mb-2 block text-2xl text-slate-300"></i>
            No hay accesos rápidos visibles.
            <button type="button" id="btnPersonalizarVacio" class="mt-3 block w-full text-[#1565c0] underline">Elegir módulos</button>
        </div>
    </div>
</section>

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
        gap: 0.4rem;
        margin: 0 0 0.75rem 0.75rem;
    }
    .dataTables_wrapper .dt-button {
        display: inline-flex !important;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.5rem !important;
        background: #fff !important;
        color: #0f172a !important;
        padding: 0.35rem 0.7rem !important;
        font-size: 0.75rem !important;
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
    #tblEvolucion { width: 100% !important; }
    table.dataTable tbody td { font-size: 0.875rem; }
</style>

<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-[#0d47a1]">
        <i class="fa-solid fa-filter"></i>
        Filtros
    </div>
    <form id="formEvolucion" class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)_auto] md:items-end">
        <input type="hidden" id="csrfPadron" value="<?php echo h($csrfPadron); ?>">
        <div>
            <label for="filtroObra" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Obra social</label>
            <select id="filtroObra" class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800">
                <option value="">Todas las obras sociales</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo h($obra['codigo']); ?>"><?php echo h($obra['etiqueta']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filtroAnios" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Años (hasta 8)</label>
            <select id="filtroAnios" multiple size="4" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                <?php foreach ($anios as $anioItem): ?>
                    <option value="<?php echo (int) $anioItem; ?>"<?php echo isset($aniosMarcados[(int) $anioItem]) ? ' selected' : ''; ?>><?php echo (int) $anioItem; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" id="btnFiltrar"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#1565c0] px-4 text-sm font-semibold text-white hover:bg-[#0d47a1]">
            <i class="fa-solid fa-rotate"></i>
            Filtrar / Actualizar
        </button>
    </form>
    <p class="mt-2 text-xs text-slate-400">Mantenga Ctrl para elegir varios años. El total es el corte mensual importado, en formato aa/mm.</p>
</section>

<section class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Padrón total vigente</p>
        <p id="kpiTotal" class="mt-2 text-2xl font-bold text-slate-800">—</p>
    </article>
    <article class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Variación absoluta</p>
        <p id="kpiVar" class="mt-2 text-2xl font-bold text-slate-800">—</p>
        <p id="kpiVarDetalle" class="mt-1 text-xs text-slate-400"></p>
    </article>
    <article class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Variación porcentual</p>
        <p id="kpiPct" class="mt-2 text-2xl font-bold text-slate-800">—</p>
    </article>
    <article class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Período auditado</p>
        <p id="kpiPeriodo" class="mt-2 text-2xl font-bold text-[#0d47a1]">—</p>
    </article>
</section>

<div class="mb-4 flex flex-wrap gap-2" id="solapasPadron" role="tablist">
    <button type="button" id="tab-temporal" data-solapa="temporal" class="inline-flex items-center gap-2 rounded-full bg-[#1565c0] px-4 py-2 text-sm font-semibold text-white">
        <i class="fa-solid fa-chart-line"></i> Evolución temporal
    </button>
    <button type="button" id="tab-demografia" data-solapa="demografia" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600">
        <i class="fa-solid fa-people-group"></i> Demografía
    </button>
    <button type="button" id="tab-planes" data-solapa="planes" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600">
        <i class="fa-solid fa-map-location-dot"></i> Planes y geografía
    </button>
</div>
<p class="mb-4 text-xs text-slate-400">Demografía y planes usan el último corte activo de la obra elegida. El rango de años aplica solo a la evolución temporal.</p>

<div id="solapa-temporal">
<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
        <i class="fa-solid fa-chart-line text-[#1565c0]"></i>
        Comparación interanual mes a mes
    </div>
    <div class="relative h-80">
        <canvas id="graficoInteranual"></canvas>
    </div>
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
            Análisis del Indicador y Diagnóstico
        </div>
        <p class="mb-2 text-xs text-slate-600" id="descTecnica_interanual">Compara el comportamiento mes a mes (Enero a Diciembre) superponiendo las curvas de cada año seleccionado para auditar estacionalidad y desregulaciones periódicas.</p>
        <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_interanual">
            <p>El diagnóstico se calcula al actualizar el filtro.</p>
        </div>
    </div>
</section>

<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <i class="fa-solid fa-chart-area text-[#1565c0]"></i>
            Evolución histórica continua
        </div>
        <div id="marcasHistoricas" class="flex flex-wrap gap-2 text-xs"></div>
    </div>
    <div class="relative h-80">
        <canvas id="graficoHistorico"></canvas>
    </div>
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
            Análisis del Indicador y Diagnóstico
        </div>
        <p class="mb-2 text-xs text-slate-600" id="descTecnica_historico">Evolución cronológica de la cartera de afiliados a lo largo de toda la serie histórica auditada.</p>
        <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_historico">
            <p>El diagnóstico se calcula al actualizar el filtro.</p>
        </div>
    </div>
</section>

<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
        <i class="fa-solid fa-chart-column text-[#1565c0]"></i>
        Movimiento y tasa de variación
    </div>
    <p class="mb-3 text-xs text-slate-400">El volumen (total, altas y bajas) usa el eje izquierdo. La línea naranja es la variación porcentual y usa el eje derecho.</p>
    <div class="relative h-80">
        <canvas id="graficoMovimiento"></canvas>
    </div>
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
            Análisis del Indicador y Diagnóstico
        </div>
        <p class="mb-2 text-xs text-slate-600" id="descTecnica_movimiento">Monitorea la rotación mensual de la cartera, contrastando el volumen de altas y bajas frente a la tasa neta de crecimiento o contracción.</p>
        <div class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_movimiento">
            <p>El diagnóstico se calcula al actualizar el filtro.</p>
        </div>
        <div class="mt-3 flex justify-end">
            <button type="button" id="btnProfundizarIa" class="inline-flex items-center gap-2 rounded-lg bg-[#1565c0] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0d47a1]">
                <i class="fa-solid fa-brain"></i>
                Profundizar con IA
            </button>
        </div>
    </div>
</section>

<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <i class="fa-solid fa-user-group text-[#1565c0]"></i>
            Programas especiales y patologías
        </div>
        <button type="button" id="btnVerEvolucionEspeciales" class="flex items-center space-x-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition-all hover:bg-blue-700">
            <i class="fa-solid fa-chart-line mr-1"></i> Ver Evolución Temporal
        </button>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
            Análisis del Indicador y Diagnóstico
        </div>
        <p class="mb-2 text-xs text-slate-600" id="descTecnica_especiales">Población categorizada en programas especiales y patologías complejas (enrol1: CUD, oncología, crónicos).</p>
        <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_especiales">
            <p>El diagnóstico se calcula al actualizar el filtro.</p>
        </div>
    </div>
</section>

<section class="mb-4 overflow-hidden rounded-2xl bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
        <i class="fa-solid fa-table text-[#1565c0]"></i>
        Detalle por período
    </div>
    <div class="overflow-x-auto">
        <table id="tblEvolucion" class="display compact stripe hover" style="width:100%">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Obra social</th>
                    <th>Total afiliados</th>
                    <th>Variación mes anterior</th>
                    <th>Variación %</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</section>
</div>

<div id="solapa-demografia" class="hidden">
    <p id="leyendaCorteDemo" class="mb-3 text-xs font-semibold text-slate-500">El corte se calcula al actualizar el filtro.</p>
    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-circle-half-stroke text-[#1565c0]"></i>
                Titulares y adherentes
            </div>
            <div class="relative h-72">
                <canvas id="graficoFamiliar"></canvas>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600" id="descTecnica_familiar">Separa titulares y adherentes comparando CODADH como texto. Vacío, 00, 000, 0 o TIT es titular. ADH y el resto de los códigos son adherentes.</p>
                <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_familiar">
                    <p>El diagnóstico se calcula al actualizar el filtro.</p>
                </div>
            </div>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-chart-bar text-[#1565c0]"></i>
                Pirámide por edad y sexo
            </div>
            <div class="relative h-72">
                <canvas id="graficoEtario"></canvas>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600" id="descTecnica_etario">Distribuye la cartera por edad al cierre del período y por sexo. Los rangos son pediátrico, jóvenes, adultos, adultos mayores y pasivos.</p>
                <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_etario">
                    <p>El diagnóstico se calcula al actualizar el filtro.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="solapa-planes" class="hidden">
    <p id="leyendaCortePlanes" class="mb-3 text-xs font-semibold text-slate-500">El corte se calcula al actualizar el filtro.</p>
    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-chart-pie text-[#1565c0]"></i>
                Mix de planes
            </div>
            <div class="relative h-72">
                <canvas id="graficoPlanes"></canvas>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600" id="descTecnica_planes">Participación de cada plan de cobertura dentro del corte activo.</p>
                <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_planes">
                    <p>El diagnóstico se calcula al actualizar el filtro.</p>
                </div>
            </div>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-location-dot text-[#1565c0]"></i>
                <span id="tituloLocalidades">Top 10 Localidades</span>
            </div>
            <div class="relative h-96">
                <canvas id="graficoLocalidades"></canvas>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600" id="descTecnica_localidades">Concentración geográfica de los afiliados según la localidad informada en el padrón.</p>
                <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_localidades">
                    <p>El diagnóstico se calcula al actualizar el filtro.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="modalEvolucionEspeciales" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/45 p-4">
    <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-4 text-white">
            <div>
                <h2 class="text-sm font-bold">Evolución de programas especiales</h2>
                <p class="text-xs text-white/75" id="rangoEvolucionEspeciales">enrol1 por período</p>
            </div>
            <button type="button" id="cerrarEvolucionEspeciales" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-5">
            <div class="relative h-80">
                <canvas id="chartEvolucionEspeciales"></canvas>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600">Compara la marcha de cada programa de alto costo a lo largo de los períodos con marca enrol1 en el padrón histórico.</p>
                <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_evolucionEspeciales">
                    <p>El diagnóstico se calcula al abrir la evolución.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalDetalle" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/45 p-4">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-4 text-white">
            <div>
                <h2 class="text-sm font-bold">Detalle del período</h2>
                <p class="text-xs text-white/75" id="detallePeriodo">—</p>
            </div>
            <button type="button" id="cerrarDetalle" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <dl id="detalleCuerpo" class="space-y-2 p-5 text-sm text-slate-700"></dl>
        <div class="flex justify-end border-t border-slate-100 px-5 py-3">
            <button type="button" id="cerrarDetallePie" class="rounded-lg bg-[#1565c0] px-4 py-2 text-sm font-semibold text-white">Cerrar</button>
        </div>
    </div>
</div>

<div id="modalAccesos" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/45 p-4">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-gradient-to-r from-[#0d47a1] to-[#1565c0] px-5 py-4 text-white">
            <div>
                <h2 class="text-sm font-bold">Personalizar accesos rápidos</h2>
                <p class="text-xs text-white/75">Elija qué módulos ver en esta barra</p>
            </div>
            <button type="button" id="cerrarModalAccesos" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="listaModalAccesos" class="space-y-2 p-5"></div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
            <button type="button" id="cancelarModalAccesos" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
            <button type="button" id="guardarModalAccesos" class="rounded-lg bg-[#1565c0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0d47a1]">Guardar</button>
        </div>
    </div>
</div>

<script>
(function () {
    var accesos = <?php echo $accesosJson; ?>;
    var clave = 'dsm_accesos_<?php echo (int) $usuarioId; ?>';
    var avisoPermiso = <?php echo !empty($avisoPermiso) ? 'true' : 'false'; ?>;
    var grilla = document.getElementById('grillaAccesos');
    var vacio = document.getElementById('accesosVacios');
    var badge = document.getElementById('badge-accesos');
    var modal = document.getElementById('modalAccesos');
    var lista = document.getElementById('listaModalAccesos');

    function leerSeleccion() {
        var todos = [];
        var i;
        for (i = 0; i < accesos.length; i++) {
            todos.push(accesos[i].id);
        }
        try {
            var guardado = window.localStorage.getItem(clave);
            if (!guardado) {
                return todos;
            }
            var ids = JSON.parse(guardado);
            if (!ids || !ids.length && ids.length !== 0) {
                return todos;
            }
            var validos = [];
            for (i = 0; i < ids.length; i++) {
                var j;
                for (j = 0; j < accesos.length; j++) {
                    if (accesos[j].id === ids[i]) {
                        validos.push(ids[i]);
                    }
                }
            }
            return validos;
        } catch (e) {
            return todos;
        }
    }

    function pintar(ids) {
        grilla.innerHTML = '';
        var visibles = 0;
        var i;
        var j;
        for (i = 0; i < accesos.length; i++) {
            var elegido = false;
            for (j = 0; j < ids.length; j++) {
                if (ids[j] === accesos[i].id) {
                    elegido = true;
                }
            }
            if (!elegido) {
                continue;
            }
            visibles++;
            var enlace = document.createElement('a');
            enlace.href = accesos[i].href;
            enlace.className = 'flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-white px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md';
            enlace.innerHTML = '<span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-lg text-[#1565c0]"><i class="' + accesos[i].icon + '"></i></span>'
                + '<span class="text-sm font-semibold text-slate-700">' + accesos[i].label + '</span>'
                + '<span class="mt-1 text-xs text-slate-400">' + accesos[i].texto + '</span>';
            grilla.appendChild(enlace);
        }
        badge.textContent = String(visibles);
        if (visibles === 0) {
            vacio.classList.remove('hidden');
        } else {
            vacio.classList.add('hidden');
        }
    }

    function abrirModal() {
        var ids = leerSeleccion();
        lista.innerHTML = '';
        var i;
        for (i = 0; i < accesos.length; i++) {
            var marcado = false;
            var j;
            for (j = 0; j < ids.length; j++) {
                if (ids[j] === accesos[i].id) {
                    marcado = true;
                }
            }
            var fila = document.createElement('label');
            fila.className = 'flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 hover:bg-slate-50';
            fila.innerHTML = '<input type="checkbox" class="h-4 w-4" value="' + accesos[i].id + '"' + (marcado ? ' checked' : '') + '>'
                + '<span><span class="block text-sm font-semibold text-slate-700">' + accesos[i].label + '</span>'
                + '<span class="block text-xs text-slate-400">' + accesos[i].texto + '</span></span>';
            lista.appendChild(fila);
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('btnPersonalizar').addEventListener('click', abrirModal);
    document.getElementById('btnPersonalizarVacio').addEventListener('click', abrirModal);
    document.getElementById('cerrarModalAccesos').addEventListener('click', cerrarModal);
    document.getElementById('cancelarModalAccesos').addEventListener('click', cerrarModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });

    document.getElementById('guardarModalAccesos').addEventListener('click', function () {
        var checks = lista.querySelectorAll('input[type="checkbox"]');
        var ids = [];
        var i;
        for (i = 0; i < checks.length; i++) {
            if (checks[i].checked) {
                ids.push(checks[i].value);
            }
        }
        window.localStorage.setItem(clave, JSON.stringify(ids));
        pintar(ids);
        cerrarModal();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Accesos actualizados',
            showConfirmButton: false,
            timer: 1800
        });
    });

    pintar(leerSeleccion());

    if (avisoPermiso) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin permiso',
            text: 'Esa sección está reservada a administradores.',
            confirmButtonColor: '#1565c0'
        });
    }
})();
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var errorCarga = <?php echo json_encode($mensajeCarga, $flagsJson); ?>;
    var chartInteranual = null;
    var chartHistorico = null;
    var chartMovimiento = null;
    var chartFamiliar = null;
    var chartEtario = null;
    var chartPlanes = null;
    var chartLocalidades = null;
    var chartEspeciales = null;
    var tabla = null;
    var contextoDiagnosticoIa = '';
    var datosDemografia = null;
    var solapaActiva = 'temporal';
    var paleta = ['#1565c0', '#ef6c00', '#2e7d32', '#6a1b9a', '#c62828', '#00838f', '#5d4037', '#1565c0'];

    function fmtEntero(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }
        return Number(valor).toLocaleString('es-AR');
    }

    function fmtFirmado(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }
        var numero = Number(valor);
        var signo = numero > 0 ? '+' : '';
        return signo + numero.toLocaleString('es-AR');
    }

    function fmtPct(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }
        var numero = Number(valor);
        var signo = numero > 0 ? '+' : '';
        return signo + numero.toLocaleString('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %';
    }

    function claseVariacion(valor) {
        var numero = Number(valor);
        if (numero > 0) {
            return 'text-emerald-700';
        }
        if (numero < 0) {
            return 'text-red-700';
        }
        return 'text-slate-600';
    }

    function pintarKpis(kpis) {
        var total = document.getElementById('kpiTotal');
        var variacion = document.getElementById('kpiVar');
        var detalle = document.getElementById('kpiVarDetalle');
        var pct = document.getElementById('kpiPct');
        var periodo = document.getElementById('kpiPeriodo');
        total.textContent = fmtEntero(kpis.total);
        variacion.textContent = fmtFirmado(kpis.variacion);
        variacion.className = 'mt-2 text-2xl font-bold ' + claseVariacion(kpis.variacion);
        pct.textContent = fmtPct(kpis.variacion_pct);
        pct.className = 'mt-2 text-2xl font-bold ' + claseVariacion(kpis.variacion_pct);
        periodo.textContent = kpis.periodo ? kpis.periodo : '—';
        if (kpis.periodo_anterior) {
            detalle.textContent = 'Contra ' + kpis.periodo_anterior;
        } else {
            detalle.textContent = '';
        }
    }

    function pintarMarcas(historico) {
        var caja = document.getElementById('marcasHistoricas');
        caja.innerHTML = '';
        if (!historico || !historico.min || !historico.max) {
            return;
        }
        caja.innerHTML = '<span class="rounded-full bg-red-50 px-2 py-1 font-semibold text-red-700">Mínimo ' + fmtEntero(historico.min.total) + ' · ' + historico.min.periodo + '</span>'
            + '<span class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">Máximo ' + fmtEntero(historico.max.total) + ' · ' + historico.max.periodo + '</span>';
    }

    function coloresPuntos(totales, minimo, maximo) {
        var colores = [];
        var i;
        for (i = 0; i < totales.length; i++) {
            if (minimo && totales[i] === minimo) {
                colores.push('#c62828');
            } else if (maximo && totales[i] === maximo) {
                colores.push('#2e7d32');
            } else {
                colores.push('#1565c0');
            }
        }
        return colores;
    }

    function dibujarInteranual(bloque) {
        if (chartInteranual) {
            chartInteranual.destroy();
        }
        var datasets = [];
        var i;
        for (i = 0; i < bloque.series.length; i++) {
            datasets.push({
                label: String(bloque.series[i].anio),
                data: bloque.series[i].data,
                borderColor: paleta[i % paleta.length],
                backgroundColor: paleta[i % paleta.length],
                tension: 0.25,
                spanGaps: false,
                pointRadius: 3
            });
        }
        chartInteranual = new Chart(document.getElementById('graficoInteranual'), {
            type: 'line',
            data: { labels: bloque.labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: false, ticks: { callback: function (v) { return fmtEntero(v); } } } }
            }
        });
    }

    function dibujarHistorico(bloque) {
        if (chartHistorico) {
            chartHistorico.destroy();
        }
        var minTotal = bloque.min ? bloque.min.total : null;
        var maxTotal = bloque.max ? bloque.max.total : null;
        chartHistorico = new Chart(document.getElementById('graficoHistorico'), {
            type: 'line',
            data: {
                labels: bloque.labels,
                datasets: [{
                    label: 'Total afiliados',
                    data: bloque.totales,
                    borderColor: '#1565c0',
                    backgroundColor: 'rgba(21,101,192,0.12)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 4,
                    pointBackgroundColor: coloresPuntos(bloque.totales, minTotal, maxTotal)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { ticks: { callback: function (v) { return fmtEntero(v); } } } }
            }
        });
        pintarMarcas(bloque);
    }

    function dibujarMovimiento(bloque) {
        if (chartMovimiento) {
            chartMovimiento.destroy();
        }
        chartMovimiento = new Chart(document.getElementById('graficoMovimiento'), {
            data: {
                labels: bloque.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Total',
                        data: bloque.totales,
                        backgroundColor: 'rgba(21,101,192,0.35)',
                        borderColor: '#1565c0',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 3
                    },
                    {
                        type: 'bar',
                        label: 'Altas',
                        data: bloque.altas,
                        backgroundColor: 'rgba(46,125,50,0.85)',
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Bajas',
                        data: bloque.bajas,
                        backgroundColor: 'rgba(198,40,40,0.8)',
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Variación %',
                        data: bloque.variacion_pct,
                        borderColor: '#ef6c00',
                        backgroundColor: '#ef6c00',
                        tension: 0.25,
                        spanGaps: false,
                        pointRadius: 3,
                        yAxisID: 'y1',
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 8 } },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var valor = ctx.parsed.y;
                                if (ctx.dataset.yAxisID === 'y1') {
                                    return ctx.dataset.label + ': ' + fmtPct(valor);
                                }
                                return ctx.dataset.label + ': ' + fmtEntero(valor);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        grace: '10%',
                        title: { display: true, text: 'Afiliados' },
                        ticks: { callback: function (v) { return fmtEntero(v); } }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grace: '10%',
                        title: { display: true, text: 'Variación %' },
                        grid: { drawOnChartArea: false },
                        ticks: { callback: function (v) { return v + '%'; } }
                    }
                }
            }
        });
    }

    function dibujarTabla(filas) {
        if (tabla) {
            tabla.clear().destroy();
            tabla = null;
        }
        var exportar = {
            columns: [0, 1, 2, 3, 4, 5],
            format: {
                body: function (dato) {
                    return jQuery('<div>').html(dato).text();
                }
            }
        };
        tabla = jQuery('#tblEvolucion').DataTable({
            data: filas,
            columns: [
                { data: 'periodo' },
                { data: 'obra' },
                { data: 'total', className: 'dt-body-right', render: function (v) { return fmtEntero(v); } },
                { data: 'variacion', className: 'dt-body-right', render: function (v) {
                    return '<span class="' + claseVariacion(v) + ' font-semibold">' + fmtFirmado(v) + '</span>';
                } },
                { data: 'variacion_pct', className: 'dt-body-right', render: function (v) {
                    return '<span class="' + claseVariacion(v) + ' font-semibold">' + fmtPct(v) + '</span>';
                } },
                { data: 'estado', render: function (v) {
                    if (v === 'Vigente') {
                        return '<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Vigente</span>';
                    }
                    return '<span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">Cerrado</span>';
                } },
                { data: 'orden', visible: false }
            ],
            order: [[6, 'desc']],
            paging: true,
            pageLength: 25,
            lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todos']],
            lengthChange: true,
            autoWidth: false,
            dom: 'lBfrtip',
            buttons: [
                { extend: 'copyHtml5', text: '<i class="fa-solid fa-copy"></i> Copiar', title: 'Evolución de padrones', exportOptions: exportar },
                { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel', title: 'Evolucion de padrones', exportOptions: exportar },
                { extend: 'pdfHtml5', text: '<i class="fa-solid fa-file-pdf"></i> PDF', title: 'Evolucion de padrones', orientation: 'landscape', pageSize: 'A4', exportOptions: exportar },
                { extend: 'print', text: '<i class="fa-solid fa-print"></i> Imprimir', title: 'Evolución de padrones', exportOptions: exportar }
            ],
            language: {
                processing: 'Procesando...',
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ filas',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                infoFiltered: '(filtrado de _MAX_ registros)',
                loadingRecords: 'Cargando...',
                zeroRecords: 'No hay períodos para este filtro',
                emptyTable: 'No hay períodos para este filtro',
                paginate: { first: 'Primero', last: 'Último', previous: 'Anterior', next: 'Siguiente' },
                aria: {
                    sortAscending: ': orden ascendente',
                    sortDescending: ': orden descendente'
                },
                buttons: {
                    copy: 'Copiar',
                    excel: 'Excel',
                    pdf: 'PDF',
                    print: 'Imprimir',
                    copyTitle: 'Copiado al portapapeles',
                    copySuccess: {
                        _: '%d filas copiadas',
                        1: '1 fila copiada'
                    }
                }
            }
        });
    }

    function etiquetaTendencia(inicial, final) {
        if (inicial > 0) {
            var ratio = final / inicial;
            if (ratio >= 1.02) {
                return 'Creciente';
            }
            if (ratio <= 0.98) {
                return 'En declive';
            }
            return 'Estable';
        }
        if (final > inicial) {
            return 'Creciente';
        }
        if (final < inicial) {
            return 'En declive';
        }
        return 'Estable';
    }

    function diagnosticarMovimiento(bloque, filas) {
        var caja = document.getElementById('insightDinamico_movimiento');
        contextoDiagnosticoIa = '';
        if (!caja) {
            return;
        }
        var labels = (bloque && bloque.labels) ? bloque.labels : [];
        var totales = (bloque && bloque.totales) ? bloque.totales : [];
        var altas = (bloque && bloque.altas) ? bloque.altas : [];
        var bajas = (bloque && bloque.bajas) ? bloque.bajas : [];
        var porcentajes = (bloque && bloque.variacion_pct) ? bloque.variacion_pct : [];
        if (!labels.length) {
            caja.innerHTML = '<p>Sin datos para diagnosticar este indicador.</p>';
            return;
        }

        var serie = [];
        var i;
        for (i = 0; i < labels.length; i++) {
            var variacion = null;
            if (i > 0) {
                if (Number(altas[i]) > 0) {
                    variacion = Number(altas[i]);
                } else if (Number(bajas[i]) > 0) {
                    variacion = -Number(bajas[i]);
                } else {
                    variacion = 0;
                }
            }
            serie.push({
                periodo: labels[i],
                total: Number(totales[i]) || 0,
                variacion: variacion,
                variacion_pct: (porcentajes[i] === null || porcentajes[i] === undefined) ? null : Number(porcentajes[i])
            });
        }

        var cierre = serie[serie.length - 1];
        var inicio = serie[0];
        var picoAltas = null;
        var picoBajas = null;
        for (i = 0; i < serie.length; i++) {
            var punto = serie[i];
            if (punto.variacion === null) {
                continue;
            }
            if (punto.variacion > 0 && (picoAltas === null || punto.variacion > picoAltas.variacion)) {
                picoAltas = punto;
            }
            if (punto.variacion < 0 && (picoBajas === null || punto.variacion < picoBajas.variacion)) {
                picoBajas = punto;
            }
        }

        var alcance = 'la cartera auditada';
        if (filas && filas.length) {
            var mismaObra = true;
            for (i = 1; i < filas.length; i++) {
                if (filas[i].obrasoc !== filas[0].obrasoc) {
                    mismaObra = false;
                    break;
                }
            }
            alcance = mismaObra ? filas[0].obra : 'todas las obras sociales';
        }
        var estado;
        if (cierre.variacion === null) {
            estado = 'En ' + cierre.periodo + ' no hay mes anterior dentro de la serie para medir el saldo neto.';
        } else if (cierre.variacion < 0) {
            estado = 'En ' + cierre.periodo + ' se registró una contracción neta de ' + fmtFirmado(cierre.variacion) + ' afiliados (' + fmtPct(cierre.variacion_pct) + ').';
        } else if (cierre.variacion > 0) {
            estado = 'En ' + cierre.periodo + ' se registró un crecimiento neto de ' + fmtFirmado(cierre.variacion) + ' afiliados (' + fmtPct(cierre.variacion_pct) + ').';
        } else {
            estado = 'En ' + cierre.periodo + ' el padrón se mantuvo sin variación neta (' + fmtFirmado(0) + ' afiliados, ' + fmtPct(0) + ').';
        }

        var textoAltas = picoAltas
            ? 'Mayor pico de altas: ' + picoAltas.periodo + ', con ' + fmtFirmado(picoAltas.variacion) + ' afiliados (' + fmtPct(picoAltas.variacion_pct) + ').'
            : 'No se registraron altas netas en el rango visualizado.';
        var textoBajas = picoBajas
            ? 'Mayor pico de bajas: ' + picoBajas.periodo + ', con ' + fmtFirmado(picoBajas.variacion) + ' afiliados (' + fmtPct(picoBajas.variacion_pct) + ').'
            : 'No se registraron bajas netas en el rango visualizado.';
        var tendencia = etiquetaTendencia(inicio.total, cierre.total);
        var textoTendencia = 'Tendencia general de ' + alcance + ': ' + tendencia + ' (de ' + fmtEntero(inicio.total) + ' en ' + inicio.periodo + ' a ' + fmtEntero(cierre.total) + ' en ' + cierre.periodo + ').';

        var claseEstado = 'bg-slate-200 text-slate-700';
        if (cierre.variacion > 0) {
            claseEstado = 'bg-emerald-100 text-emerald-800';
        } else if (cierre.variacion < 0) {
            claseEstado = 'bg-red-100 text-red-800';
        }

        caja.innerHTML = ''
            + '<p>' + escaparHtml(estado) + '</p>'
            + '<div class="flex flex-wrap gap-2">'
            + '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ' + claseEstado + '">' + escaparHtml(cierre.periodo + ' · ' + (cierre.variacion === null ? 'sin saldo' : fmtFirmado(cierre.variacion))) + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">' + escaparHtml(picoAltas ? ('Altas ' + picoAltas.periodo) : 'Sin altas netas') + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-800">' + escaparHtml(picoBajas ? ('Bajas ' + picoBajas.periodo) : 'Sin bajas netas') + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700">' + escaparHtml(tendencia) + '</span>'
            + '</div>'
            + '<p>' + escaparHtml(textoAltas) + '</p>'
            + '<p>' + escaparHtml(textoBajas) + '</p>'
            + '<p>' + escaparHtml(textoTendencia) + '</p>';

        contextoDiagnosticoIa = [
            'Indicador: movimiento y tasa de variación.',
            'Alcance: ' + alcance + '.',
            estado,
            textoAltas,
            textoBajas,
            textoTendencia
        ].join(' ');
    }

    function escaparHtml(texto) {
        return jQuery('<div>').text(texto === null || texto === undefined ? '' : String(texto)).html();
    }

    function pintarCaja(id, lineas) {
        var caja = document.getElementById(id);
        if (!caja) {
            return;
        }
        if (!lineas || !lineas.length) {
            caja.innerHTML = '<p>Sin datos para diagnosticar este indicador.</p>';
            return;
        }
        var html = '';
        var i;
        for (i = 0; i < lineas.length; i++) {
            html += '<p>' + escaparHtml(lineas[i]) + '</p>';
        }
        caja.innerHTML = html;
    }

    function pintarInsights(insights) {
        var vacio = { lineas: [] };
        var datos = insights || {};
        pintarCaja('insightDinamico_interanual', (datos.interanual || vacio).lineas);
        pintarCaja('insightDinamico_historico', (datos.historico || vacio).lineas);
        pintarCaja('insightDinamico_especiales', (datos.especiales || vacio).lineas);
    }

    function abrirDetalle(fila) {
        document.getElementById('detallePeriodo').textContent = fila.periodo + ' · ' + fila.obra;
        document.getElementById('detalleCuerpo').innerHTML = ''
            + '<div class="flex justify-between gap-4"><dt class="text-slate-500">Obra social</dt><dd class="text-right font-semibold">' + escaparHtml(fila.obra) + '</dd></div>'
            + '<div class="flex justify-between gap-4"><dt class="text-slate-500">Total afiliados</dt><dd class="font-semibold">' + fmtEntero(fila.total) + '</dd></div>'
            + '<div class="flex justify-between gap-4"><dt class="text-slate-500">Variación mes anterior</dt><dd class="font-semibold ' + claseVariacion(fila.variacion) + '">' + fmtFirmado(fila.variacion) + '</dd></div>'
            + '<div class="flex justify-between gap-4"><dt class="text-slate-500">Variación %</dt><dd class="font-semibold ' + claseVariacion(fila.variacion_pct) + '">' + fmtPct(fila.variacion_pct) + '</dd></div>'
            + '<div class="flex justify-between gap-4"><dt class="text-slate-500">Estado</dt><dd class="font-semibold">' + escaparHtml(fila.estado) + '</dd></div>';
        var modal = document.getElementById('modalDetalle');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarDetalle() {
        var modal = document.getElementById('modalDetalle');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function destruirChart(grafico) {
        if (grafico) {
            grafico.destroy();
        }
        return null;
    }

    function textoCorte(corte) {
        if (!corte || !corte.disponible) {
            return 'Sin corte activo para la obra elegida.';
        }
        var obra = corte.obra ? corte.obra : 'todas las obras del corte activo';
        return 'Corte ' + corte.periodo + ' · ' + obra + ' · ' + fmtEntero(corte.total) + ' afiliados';
    }

    function dibujarFamiliar(bloque) {
        chartFamiliar = destruirChart(chartFamiliar);
        chartFamiliar = new Chart(document.getElementById('graficoFamiliar'), {
            type: 'doughnut',
            data: {
                labels: bloque.labels || [],
                datasets: [{
                    data: bloque.data || [],
                    backgroundColor: ['#1565c0', '#ef6c00'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function dibujarEtario(bloque) {
        chartEtario = destruirChart(chartEtario);
        var colores = { M: '#1565c0', F: '#c2185b', O: '#7b1fa2' };
        var datasets = [];
        var series = bloque.series || [];
        var i;
        for (i = 0; i < series.length; i++) {
            var suma = 0;
            var j;
            var valores = series[i].data || [];
            for (j = 0; j < valores.length; j++) {
                suma += Number(valores[j]) || 0;
            }
            if (series[i].sexo === 'O' && suma === 0) {
                continue;
            }
            datasets.push({
                label: series[i].label,
                data: valores,
                backgroundColor: colores[series[i].sexo] || '#64748b'
            });
        }
        chartEtario = new Chart(document.getElementById('graficoEtario'), {
            type: 'bar',
            data: { labels: bloque.labels || [], datasets: datasets },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { beginAtZero: true, grace: '10%', ticks: { callback: function (v) { return fmtEntero(v); } } } }
            }
        });
    }

    function dibujarPlanes(bloque) {
        chartPlanes = destruirChart(chartPlanes);
        var colores = [];
        var i;
        var datos = bloque.data || [];
        for (i = 0; i < datos.length; i++) {
            colores.push(paleta[i % paleta.length]);
        }
        chartPlanes = new Chart(document.getElementById('graficoPlanes'), {
            type: 'doughnut',
            data: {
                labels: bloque.labels || [],
                datasets: [{ data: datos, backgroundColor: colores, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function dibujarLocalidades(bloque) {
        chartLocalidades = destruirChart(chartLocalidades);
        var titulo = document.getElementById('tituloLocalidades');
        if (titulo) {
            titulo.textContent = bloque.titulo || 'Top 10 localidades';
        }
        chartLocalidades = new Chart(document.getElementById('graficoLocalidades'), {
            type: 'bar',
            data: {
                labels: bloque.labels || [],
                datasets: [{
                    label: 'Afiliados',
                    data: bloque.data || [],
                    backgroundColor: '#1565c0'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, grace: '10%', ticks: { callback: function (v) { return fmtEntero(v); } } } }
            }
        });
    }

    function dibujarEtapa2(data) {
        if (!data) {
            return;
        }
        var leyenda = textoCorte(data.corte || {});
        var nodoDemo = document.getElementById('leyendaCorteDemo');
        var nodoPlanes = document.getElementById('leyendaCortePlanes');
        if (nodoDemo) {
            nodoDemo.textContent = leyenda;
        }
        if (nodoPlanes) {
            nodoPlanes.textContent = leyenda;
        }
        dibujarFamiliar(data.familiar || { labels: [], data: [] });
        dibujarEtario(data.etario || { labels: [], series: [] });
        dibujarPlanes(data.planes || { labels: [], data: [] });
        dibujarLocalidades(data.localidades || { labels: [], data: [], titulo: 'Top 10 Localidades' });
        pintarCaja('insightDinamico_familiar', (data.familiar || {}).lineas);
        pintarCaja('insightDinamico_etario', (data.etario || {}).lineas);
        pintarCaja('insightDinamico_planes', (data.planes || {}).lineas);
        pintarCaja('insightDinamico_localidades', (data.localidades || {}).lineas);
    }

    function claseSolapa(activa) {
        if (activa) {
            return 'inline-flex items-center gap-2 rounded-full bg-[#1565c0] px-4 py-2 text-sm font-semibold text-white';
        }
        return 'inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600';
    }

    function mostrarSolapa(nombre) {
        solapaActiva = nombre;
        var ids = ['temporal', 'demografia', 'planes'];
        var i;
        for (i = 0; i < ids.length; i++) {
            var panel = document.getElementById('solapa-' + ids[i]);
            var boton = document.getElementById('tab-' + ids[i]);
            if (!panel || !boton) {
                continue;
            }
            if (ids[i] === nombre) {
                panel.classList.remove('hidden');
                boton.className = claseSolapa(true);
            } else {
                panel.classList.add('hidden');
                boton.className = claseSolapa(false);
            }
        }
        if (nombre === 'temporal') {
            if (chartInteranual) { chartInteranual.resize(); }
            if (chartHistorico) { chartHistorico.resize(); }
            if (chartMovimiento) { chartMovimiento.resize(); }
            if (tabla) { tabla.columns.adjust(); }
            return;
        }
        dibujarEtapa2(datosDemografia);
    }

    function pedirJson(url, cuerpo) {
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

    function aniosElegidos() {
        var select = document.getElementById('filtroAnios');
        var elegidos = [];
        var i;
        for (i = 0; i < select.options.length; i++) {
            if (select.options[i].selected) {
                elegidos.push(select.options[i].value);
            }
        }
        return elegidos;
    }

    function cargar(mostrarAviso) {
        var anios = aniosElegidos();
        if (anios.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta el rango',
                text: 'Seleccione al menos un año.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        if (anios.length > 8) {
            Swal.fire({
                icon: 'warning',
                title: 'Demasiados años',
                text: 'Seleccione hasta 8 años.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }

        if (mostrarAviso) {
            Swal.fire({
                title: 'Actualizando padrón',
                text: 'Calculando cortes, demografía y planes...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });
        }

        var cuerpoBase = 'obrasoc=' + encodeURIComponent(document.getElementById('filtroObra').value)
            + '&csrf_token=' + encodeURIComponent(document.getElementById('csrfPadron').value);
        var cuerpo = cuerpoBase;
        var i;
        for (i = 0; i < anios.length; i++) {
            cuerpo += '&anios[]=' + encodeURIComponent(anios[i]);
        }

        Promise.all([
            pedirJson('index.php?c=dashboard&a=ajaxGetEvolucionData', cuerpo),
            pedirJson('index.php?c=dashboard&a=ajaxGetDemografiaYPlanes', cuerpoBase)
        ]).then(function (resultados) {
            var data = resultados[0];
            datosDemografia = resultados[1];
            if (mostrarAviso) {
                Swal.close();
            }
            if (!data || data.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo actualizar',
                    text: (data && data.message) ? data.message : 'No se pudo contactar al servidor.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            pintarKpis(data.kpis || {});
            dibujarInteranual(data.interanual || { labels: [], series: [] });
            dibujarHistorico(data.historico || { labels: [], totales: [], min: null, max: null });
            dibujarMovimiento(data.movimiento || { labels: [], totales: [], altas: [], bajas: [], variacion_pct: [] });
            dibujarTabla(data.tabla || []);
            diagnosticarMovimiento(data.movimiento || { labels: [], totales: [], altas: [], bajas: [], variacion_pct: [] }, data.tabla || []);
            pintarInsights(data.insights || {});
            if (solapaActiva !== 'temporal') {
                dibujarEtapa2(datosDemografia);
            }
            if (data.message) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin datos',
                    text: data.message,
                    confirmButtonColor: '#1565c0'
                });
            }
        }).catch(function () {
            if (mostrarAviso) {
                Swal.close();
            }
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo contactar al servidor.',
                confirmButtonColor: '#1565c0'
            });
        });
    }

    document.getElementById('solapasPadron').addEventListener('click', function (e) {
        var boton = e.target.closest ? e.target.closest('button[data-solapa]') : null;
        if (!boton) {
            return;
        }
        mostrarSolapa(boton.getAttribute('data-solapa'));
    });

    document.getElementById('formEvolucion').addEventListener('submit', function (e) {
        e.preventDefault();
        cargar(true);
    });

    jQuery('#tblEvolucion').on('dblclick', 'tbody tr', function () {
        if (!tabla) {
            return;
        }
        var fila = tabla.row(this).data();
        if (!fila || !fila.periodo) {
            return;
        }
        abrirDetalle(fila);
    });

    function abrirEvolucionEspeciales() {
        var modal = document.getElementById('modalEvolucionEspeciales');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarEvolucionEspeciales() {
        var modal = document.getElementById('modalEvolucionEspeciales');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function dibujarEvolucionEspeciales(data) {
        var rango = document.getElementById('rangoEvolucionEspeciales');
        var etiquetas = data.labels || [];
        if (rango) {
            if (etiquetas.length) {
                rango.textContent = etiquetas[0] + ' a ' + etiquetas[etiquetas.length - 1];
            } else {
                rango.textContent = 'Sin períodos con enrol1';
            }
        }
        chartEspeciales = destruirChart(chartEspeciales);
        var lista = data.datasets || [];
        var datasets = [];
        var i;
        for (i = 0; i < lista.length; i++) {
            datasets.push({
                label: lista[i].label,
                data: lista[i].data,
                borderColor: paleta[i % paleta.length],
                backgroundColor: paleta[i % paleta.length],
                tension: 0.25,
                pointRadius: 3,
                fill: false
            });
        }
        chartEspeciales = new Chart(document.getElementById('chartEvolucionEspeciales'), {
            type: 'line',
            data: { labels: etiquetas, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grace: '10%',
                        ticks: { callback: function (v) { return fmtEntero(v); } }
                    }
                }
            }
        });
        pintarCaja('insightDinamico_evolucionEspeciales', data.lineas || []);
    }

    document.getElementById('btnVerEvolucionEspeciales').addEventListener('click', function () {
        Swal.fire({
            title: 'Evolución de programas',
            text: 'Leyendo las marcas enrol1 del padrón histórico...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); }
        });
        var cuerpo = 'obrasoc=' + encodeURIComponent(document.getElementById('filtroObra').value)
            + '&csrf_token=' + encodeURIComponent(document.getElementById('csrfPadron').value)
            + '&limite=12';
        pedirJson('index.php?c=dashboard&a=ajaxGetEvolucionEspeciales', cuerpo).then(function (data) {
            Swal.close();
            if (!data || data.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo calcular',
                    text: (data && data.message) ? data.message : 'No se pudo contactar al servidor.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            abrirEvolucionEspeciales();
            dibujarEvolucionEspeciales(data);
            if (data.message) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin serie histórica',
                    text: data.message,
                    confirmButtonColor: '#1565c0'
                });
            }
        }).catch(function () {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo contactar al servidor.',
                confirmButtonColor: '#1565c0'
            });
        });
    });

    document.getElementById('cerrarEvolucionEspeciales').addEventListener('click', cerrarEvolucionEspeciales);
    document.getElementById('modalEvolucionEspeciales').addEventListener('click', function (e) {
        if (e.target === document.getElementById('modalEvolucionEspeciales')) {
            cerrarEvolucionEspeciales();
        }
    });

    document.getElementById('btnProfundizarIa').addEventListener('click', function () {
        if (!contextoDiagnosticoIa) {
            Swal.fire({
                icon: 'info',
                title: 'Sin diagnóstico',
                text: 'Actualice el filtro para armar el contexto antes de consultar a la IA.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        Swal.fire({
            icon: 'info',
            title: 'Disparador preparado',
            text: 'El contexto del indicador quedó armado para la futura consulta a la API de IA. Todavía no se envía ninguna petición.',
            confirmButtonColor: '#1565c0'
        });
    });

    document.getElementById('cerrarDetalle').addEventListener('click', cerrarDetalle);
    document.getElementById('cerrarDetallePie').addEventListener('click', cerrarDetalle);
    document.getElementById('modalDetalle').addEventListener('click', function (e) {
        if (e.target === document.getElementById('modalDetalle')) {
            cerrarDetalle();
        }
    });

    if (errorCarga) {
        Swal.fire({
            icon: 'error',
            title: 'Padrón no disponible',
            text: errorCarga,
            confirmButtonColor: '#1565c0'
        });
    } else {
        cargar(false);
    }
})();
</script>
