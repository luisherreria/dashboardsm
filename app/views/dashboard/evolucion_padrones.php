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
    array(
        'id'    => 'auditoria',
        'label' => 'Auditoría Consumo',
        'href'  => 'index.php?c=auditoria&a=controlPrestadores',
        'icon'  => 'fa-solid fa-user-doctor',
        'texto' => 'Uso de prestaciones',
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
        <p id="kpiPctDetalle" class="mt-1 text-xs text-slate-400"></p>
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
    <p id="leyendaMovimiento" class="mb-3 text-xs text-slate-400">El volumen (total, altas y bajas) usa el eje izquierdo. La línea naranja es la variación porcentual y usa el eje derecho.</p>
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
    <div class="mb-1 flex items-center gap-2 text-sm font-semibold text-slate-800">
        <i class="fa-solid fa-right-left text-[#1565c0]"></i>
        Altas y bajas del padrón
    </div>
    <p class="mb-3 text-xs text-slate-500">Los números salen de unicos. Altas: PERIODO de ese mes. Bajas: FBAJA de ese mismo mes. La edad sale de la fecha de nacimiento de unicos.</p>
    <div id="tablaMovimientoReal" class="overflow-x-auto text-xs text-slate-700">
        <p class="text-slate-500">El cruce se calcula al actualizar el filtro.</p>
    </div>
    <div id="detalleEdadMovimiento" class="mt-4"></div>
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
            <div class="relative h-96">
                <canvas id="graficoEtario"></canvas>
            </div>
            <div id="tablaDiscapacidadEtaria" class="mt-3 overflow-x-auto text-xs text-slate-700"></div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                    Análisis del Indicador y Diagnóstico
                </div>
                <p class="mb-2 text-xs text-slate-600" id="descTecnica_etario">Distribuye la cartera por edad al cierre del período y por sexo. Las barras de discapacidad son un subconjunto del mismo rango: enrol1 que contiene DISCAP. Población general no se cuenta ahí.</p>
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
    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
            <i class="fa-solid fa-wheelchair text-[#00695c]"></i>
            Condiciones por zona
        </div>
        <p class="mb-3 text-xs text-slate-500">Discapacidad, diabetes, HIV, oncología, alto valor y el resto de las condiciones que traiga el padrón, por provincia. Se usa el archivo más nuevo del período. Población general no entra.</p>
        <div class="relative h-[28rem]">
            <canvas id="graficoZonas"></canvas>
        </div>
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="mb-1 flex items-center text-xs font-bold uppercase text-slate-500">
                <i class="fa-solid fa-wand-magic-sparkles mr-2 text-blue-600"></i>
                Análisis del Indicador y Diagnóstico
            </div>
            <p class="mb-2 text-xs text-slate-600" id="descTecnica_zonas">La zona es la provincia del padrón vigente. Cada color es una condición de enrol1. Población general queda afuera.</p>
            <div class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800" id="insightDinamico_zonas">
                <p>El diagnóstico se calcula al actualizar el filtro.</p>
            </div>
        </div>
    </section>
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

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: none; }
}
.animate-fadeIn { animation: fadeIn .22s ease-out; }
#aiMailInforme table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
#aiMailInforme th, #aiMailInforme td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
#aiMailInforme th { background: #1d4ed8; color: #fff; }
#aiMailInforme h3 { margin: 12px 0 6px; font-weight: 700; color: #1e3a8a; }
#aiMailInforme h4 { margin: 10px 0 4px; font-weight: 700; color: #1d4ed8; }
#aiMailInforme ul, #aiMailInforme ol { margin: 0 0 10px 18px; }
</style>
<div id="aiModalAuditor" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/60 p-3 backdrop-blur-sm transition-opacity duration-300 md:p-6" aria-hidden="true">
    <div class="animate-fadeIn relative flex h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl xl:max-w-5xl">
        <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-blue-700 to-indigo-800 px-6 py-4 text-white shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-xl backdrop-blur-md">
                    <i class="fa-solid fa-brain"></i>
                </div>
                <div>
                    <h3 class="flex items-center gap-2 text-lg font-bold leading-tight">
                        Auditor Médico Pro <span class="rounded-full border border-blue-300/30 bg-blue-500/40 px-2 py-0.5 font-mono text-xs">Gemini 3.5 Flash</span>
                    </h3>
                    <p id="aiModalSubtitulo" class="text-xs font-medium text-blue-100">Seleccioná una obra y un período</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btnAiReiniciarChat" title="Reiniciar auditoría" class="rounded-lg p-2 text-white/80 transition-colors hover:bg-white/15 hover:text-white">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button type="button" id="btnAiExportarPdf" title="Descargar / Imprimir PDF" class="rounded-lg p-2 text-red-200 transition-colors hover:bg-white/15 hover:text-white">
                    <i class="fa-solid fa-file-pdf text-base"></i>
                </button>
                <button type="button" id="btnAiAbrirMail" title="Enviar informe por Email" class="rounded-lg p-2 text-blue-200 transition-colors hover:bg-white/15 hover:text-white">
                    <i class="fa-solid fa-envelope text-base"></i>
                </button>
                <button type="button" id="btnAiConfigReglas" title="Configurar Reglas de Auditoría" class="rounded-lg p-2 text-amber-200 transition-colors hover:bg-white/15 hover:text-white">
                    <i class="fa-solid fa-sliders text-base"></i>
                </button>
                <button type="button" id="btnAiCerrarModal" title="Cerrar ventana" class="rounded-lg p-2 text-lg text-white/80 transition-colors hover:bg-white/15 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div id="aiChatHistorial" class="flex-1 space-y-4 overflow-y-auto bg-slate-50/70 p-6 text-sm leading-relaxed text-slate-800 md:text-base"></div>
        <div id="loaderAuditorIa" class="hidden flex-shrink-0 border-t border-slate-100 bg-white px-6 py-2 text-xs font-medium text-slate-500">El auditor PRO está analizando...</div>
        <div class="flex-shrink-0 border-t border-slate-200 bg-white p-4">
            <div id="aiArchivoPreview" class="mb-2 hidden items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs text-blue-800">
                <span class="flex min-w-0 items-center gap-2 truncate">
                    <i class="fa-solid fa-paperclip text-blue-600"></i>
                    <strong id="aiArchivoNombre" class="truncate font-semibold"></strong>
                </span>
                <button type="button" id="btnAiRemoverArchivo" class="ml-2 text-slate-400 hover:text-red-500">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="aiChatForm" class="flex items-center gap-2">
                <input type="file" id="aiInputFile" class="hidden" accept=".pdf,.csv,.txt,image/*">
                <label for="aiInputFile" title="Adjuntar documento o imagen" class="flex cursor-pointer items-center justify-center rounded-xl p-2.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-blue-600">
                    <i class="fa-solid fa-paperclip text-lg"></i>
                </label>
                <input type="text" id="aiInputTexto" placeholder="Pregunta algo sobre el informe o pide proyecciones..." class="flex-1 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm transition-all focus:border-transparent focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" autocomplete="off">
                <button type="submit" id="btnAiEnviar" class="flex items-center gap-2 rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-800 active:bg-blue-900">
                    <span>Enviar</span>
                    <i class="fa-solid fa-arrow-up text-xs"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<div id="aiModalCorreo" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-3 backdrop-blur-sm md:p-6" aria-hidden="true">
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-blue-700 to-indigo-800 px-5 py-4 text-white">
            <div>
                <h3 class="text-base font-bold">Enviar informe</h3>
                <p class="text-xs text-blue-100">Copia oculta a no_responder@comedica.com.ar</p>
            </div>
            <button type="button" id="btnAiCerrarCorreo" title="Cerrar" class="rounded-lg p-2 text-white/80 hover:bg-white/15 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="aiMailForm" class="flex-1 space-y-3 overflow-y-auto p-5">
            <label class="block text-xs font-semibold text-slate-600" for="aiMailPara">Para</label>
            <input type="text" id="aiMailPara" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm focus:border-transparent focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="destinatario@correo.com, otro@correo.com" autocomplete="off">
            <label class="block text-xs font-semibold text-slate-600" for="aiMailCc">Con copia (CC)</label>
            <input type="text" id="aiMailCc" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm focus:border-transparent focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="opcional@correo.com, otro@correo.com" autocomplete="off">
            <label class="block text-xs font-semibold text-slate-600" for="aiMailAsunto">Asunto</label>
            <input type="text" id="aiMailAsunto" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm focus:border-transparent focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" maxlength="255">
            <p class="text-xs font-semibold text-slate-600">Informe</p>
            <div id="aiMailInforme" class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800"></div>
            <label class="block text-xs font-semibold text-slate-600" for="aiMailNota">Nota para el destinatario</label>
            <textarea id="aiMailNota" rows="3" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm leading-relaxed focus:border-transparent focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Opcional. Se muestra arriba del informe."></textarea>
            <div id="aiMailPreview" class="hidden items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs text-blue-800">
                <span class="flex min-w-0 items-center gap-2 truncate">
                    <i class="fa-solid fa-paperclip text-blue-600"></i>
                    <strong id="aiMailNombre" class="truncate font-semibold"></strong>
                </span>
                <button type="button" id="btnAiMailQuitar" class="ml-2 text-slate-400 hover:text-red-500">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="flex items-center justify-between gap-3 pt-1">
                <div>
                    <input type="file" id="aiMailFile" class="hidden" accept=".pdf,.csv,.txt,.doc,.docx,.xls,.xlsx,image/*">
                    <label for="aiMailFile" title="Adjuntar documento" class="inline-flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-blue-700">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Adjuntar</span>
                    </label>
                </div>
                <button type="submit" id="btnAiEnviarCorreo" class="inline-flex items-center gap-2 rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Enviar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-reglas-ia" class="fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" aria-hidden="true">
    <div class="animate-fadeIn flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex flex-shrink-0 items-center justify-between bg-slate-800 px-6 py-4 text-white">
            <h3 class="flex items-center gap-2 text-base font-bold">
                <i class="fa-solid fa-scale-balanced text-amber-400"></i> Directivas y Reglas de la IA
            </h3>
            <button type="button" id="btnCerrarModalReglas" class="text-lg text-white/80 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 space-y-4 overflow-y-auto p-6">
            <label id="zona-drop-reglas" for="input-archivo-reglas" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition-colors hover:border-blue-500 hover:bg-blue-50">
                <i class="fa-solid fa-file-arrow-up text-2xl text-slate-500"></i>
                <span class="text-xs font-semibold text-slate-700">Arrastrá un archivo .txt acá o hacé clic para subirlo</span>
                <span id="nombre-txt-reglas" class="text-[11px] text-slate-400">Solo archivos de texto (.txt)</span>
                <input id="input-archivo-reglas" type="file" accept=".txt,text/plain" class="hidden">
            </label>
            <div>
                <label for="textarea-reglas-ia" class="mb-1 block text-xs font-semibold uppercase text-slate-700">
                    Directivas activas (La IA las memoriza para todas las Obras Sociales):
                </label>
                <textarea id="textarea-reglas-ia" rows="10" placeholder="Escribe aquí las reglas que la IA debe respetar..." class="w-full rounded-lg border border-slate-300 bg-slate-50 p-3 font-mono text-xs leading-relaxed focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 md:text-sm"></textarea>
            </div>
        </div>
        <div class="flex flex-shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
            <button type="button" id="btnCancelarReglas" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
            <button type="button" id="btnGuardarReglas" class="flex items-center gap-2 rounded-lg bg-blue-700 px-5 py-2 text-xs font-medium text-white shadow-sm hover:bg-blue-800">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Directivas
            </button>
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
    var chartZonas = null;
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
        var pctDetalle = document.getElementById('kpiPctDetalle');
        var periodo = document.getElementById('kpiPeriodo');
        total.textContent = fmtEntero(kpis.total);
        periodo.textContent = kpis.periodo ? kpis.periodo : '—';
        if (kpis.es_padron_inicial) {
            variacion.textContent = '—';
            variacion.className = 'mt-2 text-2xl font-bold text-slate-600';
            detalle.innerHTML = '<span class="badge inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-600">Padrón Inicial</span>';
            pct.textContent = '—';
            pct.className = 'mt-2 text-2xl font-bold text-slate-600';
            pctDetalle.textContent = 'Sin corte previo';
            return;
        }
        variacion.textContent = fmtFirmado(kpis.variacion);
        variacion.className = 'mt-2 text-2xl font-bold ' + claseVariacion(kpis.variacion);
        pct.textContent = fmtPct(kpis.variacion_pct);
        pct.className = 'mt-2 text-2xl font-bold ' + claseVariacion(kpis.variacion_pct);
        pctDetalle.textContent = '';
        detalle.textContent = kpis.periodo_anterior ? ('Contra ' + kpis.periodo_anterior) : '';
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

    function leyendaMovimiento(reales, fuente) {
        var nodo = document.getElementById('leyendaMovimiento');
        var tecnica = document.getElementById('descTecnica_movimiento');
        if (fuente === 'pfile') {
            if (nodo) {
                nodo.textContent = 'Cada barra es un archivo PFILE cruzado con el archivo anterior, aunque compartan el período. Alta: documento que está en el archivo nuevo. Baja: documento que estaba en el anterior y no vino.';
            }
            if (tecnica) {
                tecnica.textContent = 'El nombre del archivo está en t_padron.PFILE, por ejemplo OA261030.DBF y OA261106.DBF dentro de 26/11. La discapacidad sale de enrol1 del archivo donde la persona está.';
            }
            return;
        }
        if (reales) {
            if (nodo) {
                nodo.textContent = 'Sale de unicos, del mismo período. Alta es PERIODO igual a ese mes. Baja es FBAJA igual a ese mes. Teal y naranja son las que tienen discapacidad en el padrón.';
            }
            if (tecnica) {
                tecnica.textContent = 'Un mismo período puede tener altas y bajas juntas. En OSPACA las bajas de 26/11 son las que salieron entre los padrones de ese mes. La discapacidad de una baja se busca en el padrón del mes anterior.';
            }
            return;
        }
        if (nodo) {
            nodo.textContent = 'El volumen (total, altas y bajas) usa el eje izquierdo. La línea naranja es la variación porcentual y usa el eje derecho. Elegí una obra para ver el cruce real de personas.';
        }
        if (tecnica) {
            tecnica.textContent = 'Monitorea la rotación mensual de la cartera, contrastando el volumen de altas y bajas frente a la tasa neta de crecimiento o contracción.';
        }
    }

    function dibujarMovimiento(bloque) {
        if (chartMovimiento) {
            chartMovimiento.destroy();
        }
        var reales = !!(bloque && bloque.reales);
        leyendaMovimiento(reales, bloque && bloque.fuente ? bloque.fuente : '');
        var datasets = [];
        if (!reales) {
            datasets.push({
                type: 'bar',
                label: 'Total',
                data: bloque.totales,
                backgroundColor: 'rgba(21,101,192,0.35)',
                borderColor: '#1565c0',
                borderWidth: 1,
                yAxisID: 'y',
                order: 3
            });
        }
        datasets.push({
            type: 'bar',
            label: 'Altas',
            data: bloque.altas,
            backgroundColor: 'rgba(46,125,50,0.85)',
            yAxisID: 'y',
            order: 2
        });
        datasets.push({
            type: 'bar',
            label: 'Bajas',
            data: bloque.bajas,
            backgroundColor: 'rgba(198,40,40,0.8)',
            yAxisID: 'y',
            order: 2
        });
        if (reales) {
            datasets.push({
                type: 'bar',
                label: 'Altas con discapacidad',
                data: bloque.altas_disca,
                backgroundColor: '#00695c',
                yAxisID: 'y',
                order: 2
            });
            datasets.push({
                type: 'bar',
                label: 'Bajas con discapacidad',
                data: bloque.bajas_disca,
                backgroundColor: '#ef6c00',
                yAxisID: 'y',
                order: 2
            });
        }
        datasets.push({
            type: 'line',
            label: reales ? 'Saldo %' : 'Variación %',
            data: bloque.variacion_pct,
            borderColor: reales ? '#5e35b1' : '#ef6c00',
            backgroundColor: reales ? '#5e35b1' : '#ef6c00',
            tension: 0.25,
            spanGaps: false,
            pointRadius: 3,
            yAxisID: 'y1',
            order: 1
        });
        chartMovimiento = new Chart(document.getElementById('graficoMovimiento'), {
            data: {
                labels: bloque.labels,
                datasets: datasets
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

    function diagnosticarMovimientoReal(bloque, filasTabla, caja) {
        var labels = bloque.labels || [];
        var altas = bloque.altas || [];
        var bajas = bloque.bajas || [];
        var altasDisca = bloque.altas_disca || [];
        var bajasDisca = bloque.bajas_disca || [];
        var totales = bloque.totales || [];
        var ultimo = labels.length - 1;
        var periodo = labels[ultimo];
        var nAltas = Number(altas[ultimo]) || 0;
        var nBajas = Number(bajas[ultimo]) || 0;
        var nAltasDisca = Number(altasDisca[ultimo]) || 0;
        var nBajasDisca = Number(bajasDisca[ultimo]) || 0;
        var porArchivo = bloque.fuente === 'pfile';
        var estado;
        if (porArchivo) {
            var archivo = (bloque.archivos && bloque.archivos[ultimo]) ? bloque.archivos[ultimo] : periodo;
            var anterior = (bloque.contra && bloque.contra[ultimo]) ? bloque.contra[ultimo] : 'el archivo anterior';
            estado = archivo + ' contra ' + anterior + ': ' + fmtEntero(nAltas) + ' altas y ' + fmtEntero(nBajas) + ' bajas.';
        } else {
            estado = 'En ' + periodo + ', unicos marca ' + fmtEntero(nAltas) + ' altas con ese período y ' + fmtEntero(nBajas) + ' bajas del mismo período.';
        }
        if (nAltasDisca > 0 || nBajasDisca > 0) {
            estado += ' Con discapacidad: ' + fmtEntero(nAltasDisca) + ' de las altas y ' + fmtEntero(nBajasDisca) + ' de las bajas.';
        } else if (nBajas > 0 && !porArchivo) {
            estado += ' Esas bajas ya no están en el padrón del mes, así que no se ve si tenían discapacidad.';
        }

        var picoAltas = null;
        var picoBajas = null;
        var i;
        for (i = 0; i < labels.length; i++) {
            if (picoAltas === null || Number(altas[i]) > picoAltas.valor) {
                picoAltas = { periodo: labels[i], valor: Number(altas[i]) || 0 };
            }
            if (picoBajas === null || Number(bajas[i]) > picoBajas.valor) {
                picoBajas = { periodo: labels[i], valor: Number(bajas[i]) || 0 };
            }
        }
        var textoAltas = picoAltas && picoAltas.valor > 0
            ? 'Mayor pico de altas en unicos: ' + picoAltas.periodo + ', con ' + fmtEntero(picoAltas.valor) + ' afiliados.'
            : 'Unicos no marca altas en el rango elegido.';
        var textoBajas = picoBajas && picoBajas.valor > 0
            ? 'Mayor pico de bajas en unicos: ' + picoBajas.periodo + ', con ' + fmtEntero(picoBajas.valor) + ' afiliados.'
            : 'Unicos no marca bajas en el rango elegido.';

        var alcance = 'la obra elegida';
        if (filasTabla && filasTabla.length && filasTabla[0].obra) {
            alcance = filasTabla[0].obra;
        }
        var inicioTotal = Number(totales[0]) || 0;
        var cierreTotal = Number(totales[ultimo]) || 0;
        var tendencia = etiquetaTendencia(inicioTotal, cierreTotal);
        var textoTendencia = 'Activos según unicos: ' + fmtEntero(cierreTotal) + ' en ' + periodo + '.';

        var saldo = nAltas - nBajas;
        var claseEstado = 'bg-slate-200 text-slate-700';
        if (saldo > 0) {
            claseEstado = 'bg-emerald-100 text-emerald-800';
        } else if (saldo < 0) {
            claseEstado = 'bg-red-100 text-red-800';
        }

        caja.innerHTML = ''
            + '<p>' + escaparHtml(estado) + '</p>'
            + '<div class="flex flex-wrap gap-2">'
            + '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ' + claseEstado + '">' + escaparHtml(periodo + ' · saldo ' + fmtFirmado(saldo)) + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">' + escaparHtml('Altas ' + fmtEntero(nAltas)) + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-800">' + escaparHtml('Bajas ' + fmtEntero(nBajas)) + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-teal-100 px-2.5 py-1 text-[11px] font-semibold text-teal-800">' + escaparHtml('Disca altas ' + fmtEntero(nAltasDisca)) + '</span>'
            + '<span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-1 text-[11px] font-semibold text-orange-800">' + escaparHtml('Disca bajas ' + fmtEntero(nBajasDisca)) + '</span>'
            + '</div>'
            + '<p>' + escaparHtml(textoAltas) + '</p>'
            + '<p>' + escaparHtml(textoBajas) + '</p>'
            + '<p>' + escaparHtml(textoTendencia) + '</p>';

        contextoDiagnosticoIa = [
            'Indicador: altas y bajas del mismo período en unicos.',
            'Alcance: ' + alcance + '.',
            estado,
            textoAltas,
            textoBajas,
            textoTendencia + ' Tendencia ' + tendencia + '.'
        ].join(' ');
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
        if (bloque.reales) {
            diagnosticarMovimientoReal(bloque, filas, caja);
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

    function filaRangoDisca(fila) {
        var otros = Number(fila.o) || 0;
        var discaOtros = Number(fila.disca_o) || 0;
        var extra = '';
        if (otros > 0 || discaOtros > 0) {
            extra = '<td class="px-2 py-1 text-right">' + fmtEntero(otros) + '</td>'
                + '<td class="px-2 py-1 text-right">' + fmtEntero(discaOtros) + '</td>';
        }
        return '<tr class="border-t border-slate-100">'
            + '<td class="px-2 py-1">' + escaparHtml(fila.rango || '') + '</td>'
            + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.m) + '</td>'
            + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.f) + '</td>'
            + '<td class="px-2 py-1 text-right font-semibold text-teal-800">' + fmtEntero(fila.disca_m) + '</td>'
            + '<td class="px-2 py-1 text-right font-semibold text-orange-800">' + fmtEntero(fila.disca_f) + '</td>'
            + extra
            + '</tr>';
    }

    function tablaEdadMovimiento(titulo, filas) {
        var lista = filas || [];
        var hayOtros = false;
        var k;
        for (k = 0; k < lista.length; k++) {
            if ((Number(lista[k].o) || 0) > 0 || (Number(lista[k].disca_o) || 0) > 0) {
                hayOtros = true;
                break;
            }
        }
        var html = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">'
            + '<p class="mb-2 text-xs font-semibold text-slate-700">' + escaparHtml(titulo) + '</p>'
            + '<table class="w-full"><thead><tr class="text-left text-[11px] uppercase text-slate-500">'
            + '<th class="px-2 py-1">Rango</th><th class="px-2 py-1 text-right">M</th><th class="px-2 py-1 text-right">F</th>'
            + '<th class="px-2 py-1 text-right">Disca M</th><th class="px-2 py-1 text-right">Disca F</th>'
            + (hayOtros ? '<th class="px-2 py-1 text-right">Otros</th><th class="px-2 py-1 text-right">Disca otros</th>' : '')
            + '</tr></thead><tbody>';
        var i;
        for (i = 0; i < lista.length; i++) {
            html += filaRangoDisca(lista[i]);
        }
        html += '</tbody></table></div>';
        return html;
    }

    function pintarDiscapacidadEtaria(bloque) {
        var caja = document.getElementById('tablaDiscapacidadEtaria');
        if (!caja) {
            return;
        }
        var disca = bloque.discapacidad || null;
        if (!disca || !disca.rangos || Number(disca.total) === 0) {
            caja.innerHTML = '';
            return;
        }
        var html = '<p class="mb-1 font-semibold text-slate-700">Discapacidad por edad y sexo: '
            + fmtEntero(disca.total) + ' (' + String(disca.porcentaje).replace('.', ',') + ' %)</p>'
            + '<table class="w-full"><thead><tr class="text-left text-[11px] uppercase text-slate-500">'
            + '<th class="px-2 py-1">Rango</th><th class="px-2 py-1 text-right">M</th><th class="px-2 py-1 text-right">F</th><th class="px-2 py-1 text-right">Total</th>'
            + '</tr></thead><tbody>';
        var i;
        for (i = 0; i < disca.rangos.length; i++) {
            var fila = disca.rangos[i];
            html += '<tr class="border-t border-slate-100">'
                + '<td class="px-2 py-1">' + escaparHtml(fila.rango || '') + '</td>'
                + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.m) + '</td>'
                + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.f) + '</td>'
                + '<td class="px-2 py-1 text-right font-semibold">' + fmtEntero(fila.total) + '</td>'
                + '</tr>';
        }
        html += '</tbody></table>';
        caja.innerHTML = html;
    }

    function pintarMovimientoReal(bloque) {
        var caja = document.getElementById('tablaMovimientoReal');
        var detalle = document.getElementById('detalleEdadMovimiento');
        if (!caja) {
            return;
        }
        var filas = (bloque && bloque.filas) ? bloque.filas : [];
        if (!filas.length) {
            caja.innerHTML = '<p class="text-slate-500">' + escaparHtml((bloque && bloque.mensaje) ? bloque.mensaje : 'Sin cortes para cruzar.') + '</p>';
            if (detalle) {
                detalle.innerHTML = '';
            }
            return;
        }

        var html = '<table class="w-full"><thead><tr class="text-left text-[11px] uppercase text-slate-500">'
            + '<th class="px-2 py-1">Período</th><th class="px-2 py-1">Contra</th>'
            + '<th class="px-2 py-1 text-right">Altas</th><th class="px-2 py-1 text-right">Altas disca</th>'
            + '<th class="px-2 py-1 text-right">Bajas</th><th class="px-2 py-1 text-right">Bajas disca</th>'
            + '</tr></thead><tbody>';
        var i;
        for (i = 0; i < filas.length; i++) {
            var fila = filas[i];
            html += '<tr class="border-t border-slate-100">'
                + '<td class="px-2 py-1 font-semibold">' + escaparHtml(fila.periodo || '') + (fila.pfile ? ' · ' + escaparHtml(fila.pfile) : '') + '</td>'
                + '<td class="px-2 py-1">' + escaparHtml(fila.contra || '') + '</td>'
                + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.altas) + '</td>'
                + '<td class="px-2 py-1 text-right font-semibold text-teal-800">' + fmtEntero(fila.altas_disca) + '</td>'
                + '<td class="px-2 py-1 text-right">' + fmtEntero(fila.bajas) + '</td>'
                + '<td class="px-2 py-1 text-right font-semibold text-orange-800">' + fmtEntero(fila.bajas_disca) + '</td>'
                + '</tr>';
        }
        html += '</tbody></table>';
        caja.innerHTML = html;

        if (!detalle) {
            return;
        }
        var ultima = filas[filas.length - 1];
        var tituloEdad = ultima.pfile
            ? ('Edad y sexo de ' + (ultima.pfile || '') + ' contra ' + (ultima.contra || ''))
            : ('Edad y sexo de las altas y bajas de ' + (ultima.periodo || '') + ' en unicos');
        detalle.innerHTML = '<p class="mb-2 text-xs font-semibold text-slate-700">' + escaparHtml(tituloEdad) + '</p>'
            + '<div class="grid gap-3 lg:grid-cols-2">'
            + tablaEdadMovimiento('Altas', ultima.altas_edad)
            + tablaEdadMovimiento('Bajas', ultima.bajas_edad)
            + '</div>';
    }

    function dibujarEtario(bloque) {
        chartEtario = destruirChart(chartEtario);
        var colores = { M: '#1565c0', F: '#c2185b', O: '#7b1fa2', DM: '#00695c', DF: '#ef6c00', DO: '#6d4c41' };
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
            if ((series[i].sexo === 'O' || series[i].sexo === 'DM' || series[i].sexo === 'DF' || series[i].sexo === 'DO') && suma === 0) {
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
        pintarDiscapacidadEtaria(data.etario || {});
        dibujarPlanes(data.planes || { labels: [], data: [] });
        dibujarLocalidades(data.localidades || { labels: [], data: [], titulo: 'Top 10 Localidades' });
        dibujarZonas(data.zonas || { labels: [], data: [] });
        pintarCaja('insightDinamico_familiar', (data.familiar || {}).lineas);
        pintarCaja('insightDinamico_etario', (data.etario || {}).lineas);
        pintarCaja('insightDinamico_planes', (data.planes || {}).lineas);
        pintarCaja('insightDinamico_localidades', (data.localidades || {}).lineas);
        pintarCaja('insightDinamico_zonas', (data.zonas || {}).lineas);
    }

    function dibujarZonas(bloque) {
        chartZonas = destruirChart(chartZonas);
        var lienzo = document.getElementById('graficoZonas');
        if (!lienzo) {
            return;
        }
        var series = bloque.series || [];
        var datasets = [];
        var i;
        if (!series.length && bloque.data && bloque.data.length) {
            series = [{ label: 'Discapacidad', data: bloque.data, color: '#00695c' }];
        }
        for (i = 0; i < series.length; i++) {
            datasets.push({
                label: series[i].label,
                data: series[i].data || [],
                backgroundColor: series[i].color || '#00695c'
            });
        }
        chartZonas = new Chart(lienzo, {
            type: 'bar',
            data: {
                labels: bloque.labels || [],
                datasets: datasets
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { stacked: true, beginAtZero: true, grace: '8%', ticks: { callback: function (v) { return fmtEntero(v); } } },
                    y: { stacked: true }
                }
            }
        });
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
            var movimientoReal = data.movimiento_real || { disponible: false, filas: [], mensaje: '' };
            var graficoMovimiento = (movimientoReal.grafico && movimientoReal.grafico.labels && movimientoReal.grafico.labels.length)
                ? movimientoReal.grafico
                : (data.movimiento || { labels: [], totales: [], altas: [], bajas: [], variacion_pct: [] });
            dibujarMovimiento(graficoMovimiento);
            pintarMovimientoReal(movimientoReal);
            dibujarTabla(data.tabla || []);
            diagnosticarMovimiento(graficoMovimiento, data.tabla || []);
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
        var obraNueva = document.getElementById('filtroObra').value;
        if (obraChat !== '' && obraNueva !== obraChat) {
            var panelAbierto = !document.getElementById('aiModalAuditor').classList.contains('hidden');
            vaciarAuditorIa();
            if (panelAbierto && obraNueva) {
                pedirAuditorIa('iniciar', '');
            }
        }
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

    var chatIniciado = false;
    var chatOcupado = false;
    var obraChat = '';
    var chatTicket = 0;

    function obraAuditoria() {
        var obra = document.getElementById('filtroObra').value;
        return obra ? obra : '';
    }

    function vaciarAuditorIa() {
        chatTicket++;
        chatOcupado = false;
        chatIniciado = false;
        obraChat = '';
        loaderAuditorIa(false);
        document.getElementById('aiChatHistorial').innerHTML = '';
        document.getElementById('aiInputTexto').value = '';
        document.getElementById('aiInputFile').value = '';
        document.getElementById('aiArchivoPreview').classList.add('hidden');
        document.getElementById('aiArchivoPreview').classList.remove('flex');
        var obra = obraAuditoria();
        document.getElementById('aiModalSubtitulo').textContent = obra ? obra : 'Seleccioná una obra y un período';
    }

    function abrirAuditorIa() {
        var panel = document.getElementById('aiModalAuditor');
        panel.classList.remove('hidden');
        panel.classList.add('flex');
        panel.setAttribute('aria-hidden', 'false');
    }

    function cerrarAuditorIa() {
        var panel = document.getElementById('aiModalAuditor');
        panel.classList.add('hidden');
        panel.classList.remove('flex');
        panel.setAttribute('aria-hidden', 'true');
    }

    function scrollAuditorIa() {
        var caja = document.getElementById('aiChatHistorial');
        caja.scrollTop = caja.scrollHeight;
    }

    function burbujaAuditorIa(rol, html) {
        var caja = document.getElementById('aiChatHistorial');
        var item = document.createElement('div');
        item.setAttribute('data-rol', rol);
        if (rol === 'user') {
            item.className = 'ml-auto max-w-xl rounded-2xl bg-[#1565c0] px-4 py-2.5 text-sm text-white';
            item.textContent = html;
        } else {
            item.className = 'max-w-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-relaxed text-slate-800 md:text-base';
            item.innerHTML = html;
        }
        caja.appendChild(item);
        scrollAuditorIa();
    }

    function loaderAuditorIa(visible) {
        var nodo = document.getElementById('loaderAuditorIa');
        if (visible) {
            nodo.classList.remove('hidden');
        } else {
            nodo.classList.add('hidden');
        }
        scrollAuditorIa();
    }

    function pedirAuditorIa(accion, mensaje) {
        if (chatOcupado) {
            return;
        }
        var obra = obraAuditoria();
        if (!obra) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta la obra social',
                text: 'Seleccione una obra antes de consultar al auditor.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        var datos = new FormData();
        datos.append('accion', accion);
        datos.append('obrasoc', obra);
        datos.append('csrf_token', document.getElementById('csrfPadron').value);
        if (mensaje) {
            datos.append('mensaje', mensaje);
        }
        var archivo = document.getElementById('aiInputFile').files[0];
        if (accion === 'mensaje' && archivo) {
            datos.append('archivo_adjunto', archivo);
        }
        chatOcupado = true;
        loaderAuditorIa(true);
        var ticket = ++chatTicket;
        fetch('index.php?c=dashboard&a=ajaxChatAi', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (ticket !== chatTicket) {
                return;
            }
            chatOcupado = false;
            loaderAuditorIa(false);
            if (!data || data.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: 'Sin diagnóstico',
                    text: (data && data.message) ? data.message : 'No se pudo consultar al auditor.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            if (data.obra || data.periodo) {
                obraChat = data.obra || obra;
                document.getElementById('aiModalSubtitulo').textContent = obraChat + (data.periodo ? ' · ' + data.periodo : '');
            }
            if (data.html) {
                burbujaAuditorIa('model', data.html);
                chatIniciado = true;
            }
            if (accion === 'mensaje') {
                document.getElementById('aiInputTexto').value = '';
                document.getElementById('aiInputFile').value = '';
                document.getElementById('aiArchivoPreview').classList.add('hidden');
                document.getElementById('aiArchivoPreview').classList.remove('flex');
            }
        }).catch(function () {
            if (ticket !== chatTicket) {
                return;
            }
            chatOcupado = false;
            loaderAuditorIa(false);
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo contactar al auditor.',
                confirmButtonColor: '#1565c0'
            });
        });
    }

    document.getElementById('btnProfundizarIa').addEventListener('click', function () {
        abrirAuditorIa();
        var obra = obraAuditoria();
        if (!chatIniciado || obraChat !== obra) {
            document.getElementById('aiChatHistorial').innerHTML = '';
            chatIniciado = false;
            pedirAuditorIa('iniciar', '');
        }
    });
    document.getElementById('btnAiCerrarModal').addEventListener('click', cerrarAuditorIa);
    document.getElementById('aiModalAuditor').addEventListener('click', function (e) {
        if (e.target === document.getElementById('aiModalAuditor')) {
            cerrarAuditorIa();
        }
    });
    document.getElementById('btnAiReiniciarChat').addEventListener('click', function () {
        vaciarAuditorIa();
        pedirAuditorIa('iniciar', '');
    });
    document.getElementById('aiInputFile').addEventListener('change', function () {
        var archivo = this.files[0];
        var preview = document.getElementById('aiArchivoPreview');
        if (!archivo) {
            preview.classList.add('hidden');
            preview.classList.remove('flex');
            return;
        }
        document.getElementById('aiArchivoNombre').textContent = archivo.name;
        preview.classList.remove('hidden');
        preview.classList.add('flex');
    });
    document.getElementById('btnAiRemoverArchivo').addEventListener('click', function () {
        document.getElementById('aiInputFile').value = '';
        document.getElementById('aiArchivoPreview').classList.add('hidden');
        document.getElementById('aiArchivoPreview').classList.remove('flex');
    });
    document.getElementById('aiChatForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var texto = document.getElementById('aiInputTexto').value.replace(/^\s+|\s+$/g, '');
        var archivo = document.getElementById('aiInputFile').files[0];
        if (!texto && !archivo) {
            return;
        }
        burbujaAuditorIa('user', texto || ('Adjunto: ' + archivo.name));
        pedirAuditorIa('mensaje', texto);
    });
    document.getElementById('aiInputTexto').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('aiChatForm').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });

    function escaparHtmlInforme(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fechaInforme() {
        var hoy = new Date();
        function dos(n) { return n < 10 ? '0' + n : String(n); }
        return dos(hoy.getDate()) + '/' + dos(hoy.getMonth() + 1) + '/' + hoy.getFullYear();
    }

    function obraInforme() {
        var obra = document.getElementById('filtroObra').value;
        return obra ? obra : 'Obra social';
    }

    function hayInformeAuditor() {
        var caja = document.getElementById('aiChatHistorial');
        var i;
        if (!caja) {
            return false;
        }
        for (i = 0; i < caja.children.length; i++) {
            if (caja.children[i].getAttribute('data-rol') === 'model') {
                return true;
            }
        }
        return false;
    }

    function htmlInformeAuditor() {
        var caja = document.getElementById('aiChatHistorial');
        var partes = [];
        var i;
        var html;
        for (i = 0; i < caja.children.length; i++) {
            if (caja.children[i].getAttribute('data-rol') !== 'model') {
                continue;
            }
            html = caja.children[i].innerHTML || '';
            html = html.replace(/^\s+|\s+$/g, '');
            if (html) {
                partes.push(html);
            }
        }
        return partes.join('');
    }

    function abrirCorreoInforme() {
        if (!hayInformeAuditor()) {
            Swal.fire({
                icon: 'info',
                title: 'Sin informe',
                text: 'Pedí un diagnóstico antes de enviarlo.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        document.getElementById('aiMailAsunto').value = 'Informe ' + obraInforme() + ' - ' + fechaInforme();
        document.getElementById('aiMailInforme').innerHTML = htmlInformeAuditor();
        document.getElementById('aiMailNota').value = '';
        var panel = document.getElementById('aiModalCorreo');
        panel.classList.remove('hidden');
        panel.classList.add('flex');
        panel.setAttribute('aria-hidden', 'false');
        document.getElementById('aiMailPara').focus();
    }

    function cerrarCorreoInforme() {
        var panel = document.getElementById('aiModalCorreo');
        panel.classList.add('hidden');
        panel.classList.remove('flex');
        panel.setAttribute('aria-hidden', 'true');
    }

    function exportarInformePdf() {
        var caja = document.getElementById('aiChatHistorial');
        var ventana;
        var html;
        var i;
        var nodo;
        var rol;
        if (!caja || !caja.children.length) {
            Swal.fire({
                icon: 'info',
                title: 'Sin informe',
                text: 'Pedí un diagnóstico antes de imprimirlo.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        ventana = window.open('', '_blank');
        if (!ventana) {
            Swal.fire({
                icon: 'warning',
                title: 'Ventana bloqueada',
                text: 'Permití las ventanas emergentes para guardar el informe en PDF.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            + escaparHtmlInforme('Informe ' + obraInforme() + ' - ' + fechaInforme())
            + '</title><style>body{font-family:Arial,sans-serif;color:#1e293b;margin:28px}h1{font-size:20px;margin:0}'
            + '.meta{color:#64748b;font-size:12px;margin:6px 0 22px}.burbuja{margin:0 0 14px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px}'
            + '.user{background:#1565c0;color:#fff;margin-left:18%}.model{background:#fff}table{border-collapse:collapse;width:100%;margin:8px 0}'
            + 'td,th{border:1px solid #cbd5e1;padding:6px 8px;text-align:left;vertical-align:top}ul{margin:8px 0 8px 18px}'
            + '</style></head><body><h1>Auditor Médico Pro</h1><p class="meta">'
            + escaparHtmlInforme(obraInforme() + ' · ' + fechaInforme())
            + '</p>';
        for (i = 0; i < caja.children.length; i++) {
            nodo = caja.children[i];
            rol = nodo.getAttribute('data-rol') === 'user' ? 'user' : 'model';
            html += '<div class="burbuja ' + rol + '">';
            html += rol === 'user' ? escaparHtmlInforme(nodo.textContent || '') : nodo.innerHTML;
            html += '</div>';
        }
        html += '</body></html>';
        ventana.document.open();
        ventana.document.write(html);
        ventana.document.close();
        ventana.focus();
        setTimeout(function () { ventana.print(); }, 300);
    }

    document.getElementById('btnAiExportarPdf').addEventListener('click', exportarInformePdf);
    document.getElementById('btnAiAbrirMail').addEventListener('click', abrirCorreoInforme);
    document.getElementById('btnAiCerrarCorreo').addEventListener('click', cerrarCorreoInforme);
    document.getElementById('aiModalCorreo').addEventListener('click', function (e) {
        if (e.target === document.getElementById('aiModalCorreo')) {
            cerrarCorreoInforme();
        }
    });
    document.getElementById('aiMailFile').addEventListener('change', function () {
        var archivo = this.files[0];
        var preview = document.getElementById('aiMailPreview');
        if (!archivo) {
            preview.classList.add('hidden');
            preview.classList.remove('flex');
            return;
        }
        document.getElementById('aiMailNombre').textContent = archivo.name;
        preview.classList.remove('hidden');
        preview.classList.add('flex');
    });
    document.getElementById('btnAiMailQuitar').addEventListener('click', function () {
        document.getElementById('aiMailFile').value = '';
        document.getElementById('aiMailPreview').classList.add('hidden');
        document.getElementById('aiMailPreview').classList.remove('flex');
    });
    document.getElementById('aiMailForm').addEventListener('submit', function (e) {
        var datos;
        var archivo;
        e.preventDefault();
        if (!document.getElementById('aiMailPara').value.replace(/^\s+|\s+$/g, '')) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta el destinatario',
                text: 'Escribí al menos un correo en Para.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        datos = new FormData();
        datos.append('csrf_token', document.getElementById('csrfPadron').value);
        datos.append('destinatarios', document.getElementById('aiMailPara').value);
        datos.append('con_copia', document.getElementById('aiMailCc').value);
        datos.append('asunto', document.getElementById('aiMailAsunto').value);
        datos.append('mensaje_html', htmlInformeAuditor());
        datos.append('nota', document.getElementById('aiMailNota').value);
        datos.append('obrasoc', obraInforme());
        datos.append('fecha', fechaInforme());
        archivo = document.getElementById('aiMailFile').files[0];
        if (archivo) {
            datos.append('archivo_correo', archivo);
        }
        Swal.fire({
            title: 'Enviando informe',
            allowOutsideClick: false,
            didOpen: function () { Swal.showLoading(); }
        });
        fetch('index.php?c=dashboard&a=ajaxEnviarInformeAi', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || data.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: 'No se envió',
                    text: (data && data.message) ? data.message : 'El servidor de correo rechazó el informe.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            cerrarCorreoInforme();
            Swal.fire({
                icon: 'success',
                title: 'Informe enviado',
                text: data.message || 'El correo salió con copia oculta.',
                confirmButtonColor: '#1565c0'
            });
        }).catch(function () {
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo contactar al servidor de correo.',
                confirmButtonColor: '#1565c0'
            });
        });
    });

    function abrirModalReglas() {
        var panel = document.getElementById('modal-reglas-ia');
        panel.classList.remove('hidden');
        panel.classList.add('flex');
        panel.setAttribute('aria-hidden', 'false');
    }

    function cerrarModalReglas() {
        var panel = document.getElementById('modal-reglas-ia');
        panel.classList.add('hidden');
        panel.classList.remove('flex');
        panel.setAttribute('aria-hidden', 'true');
        document.getElementById('input-archivo-reglas').value = '';
        document.getElementById('nombre-txt-reglas').textContent = 'Solo archivos de texto (.txt)';
    }

    function marcarZonaReglas(activa) {
        var zona = document.getElementById('zona-drop-reglas');
        if (activa) {
            zona.classList.add('border-blue-500', 'bg-blue-50');
        } else {
            zona.classList.remove('border-blue-500', 'bg-blue-50');
        }
    }

    function volcarTxtReglas(archivo) {
        var nombre = archivo && archivo.name ? archivo.name : '';
        var esTxt = /\.txt$/i.test(nombre) || archivo.type === 'text/plain';
        if (!archivo || !esTxt) {
            Swal.fire({
                icon: 'warning',
                title: 'Archivo no válido',
                text: 'Soltá un archivo de texto .txt.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }
        var lector = new FileReader();
        lector.onload = function () {
            document.getElementById('textarea-reglas-ia').value = String(lector.result || '');
            document.getElementById('nombre-txt-reglas').textContent = nombre;
        };
        lector.onerror = function () {
            Swal.fire({
                icon: 'error',
                title: 'No se pudo leer',
                text: 'El archivo .txt no se pudo abrir.',
                confirmButtonColor: '#1565c0'
            });
        };
        lector.readAsText(archivo, 'UTF-8');
    }

    document.getElementById('btnAiConfigReglas').addEventListener('click', function () {
        var datos = new FormData();
        datos.append('csrf_token', document.getElementById('csrfPadron').value);
        fetch('index.php?c=dashboard&a=ajaxObtenerReglasIa', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || data.status !== 'ok') {
                Swal.fire({
                    icon: 'error',
                    title: 'Sin reglas',
                    text: (data && data.message) ? data.message : 'No se pudieron leer las directivas.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            document.getElementById('textarea-reglas-ia').value = data.contenido || '';
            abrirModalReglas();
        }).catch(function () {
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo consultar las reglas.',
                confirmButtonColor: '#1565c0'
            });
        });
    });
    document.getElementById('btnCerrarModalReglas').addEventListener('click', cerrarModalReglas);
    document.getElementById('btnCancelarReglas').addEventListener('click', cerrarModalReglas);
    document.getElementById('modal-reglas-ia').addEventListener('click', function (e) {
        if (e.target === document.getElementById('modal-reglas-ia')) {
            cerrarModalReglas();
        }
    });
    document.getElementById('zona-drop-reglas').addEventListener('dragenter', function (e) {
        e.preventDefault();
        marcarZonaReglas(true);
    });
    document.getElementById('zona-drop-reglas').addEventListener('dragover', function (e) {
        e.preventDefault();
        marcarZonaReglas(true);
    });
    document.getElementById('zona-drop-reglas').addEventListener('dragleave', function (e) {
        e.preventDefault();
        marcarZonaReglas(false);
    });
    document.getElementById('zona-drop-reglas').addEventListener('drop', function (e) {
        e.preventDefault();
        marcarZonaReglas(false);
        var archivos = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
        if (archivos && archivos.length) {
            volcarTxtReglas(archivos[0]);
        }
    });
    document.getElementById('input-archivo-reglas').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            volcarTxtReglas(this.files[0]);
        }
    });
    document.getElementById('btnGuardarReglas').addEventListener('click', function () {
        var datos = new FormData();
        datos.append('csrf_token', document.getElementById('csrfPadron').value);
        datos.append('contenido', document.getElementById('textarea-reglas-ia').value);
        Swal.fire({
            title: 'Guardando directivas',
            allowOutsideClick: false,
            didOpen: function () { Swal.showLoading(); }
        });
        fetch('index.php?c=dashboard&a=ajaxGuardarReglasIa', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || data.status !== 'ok') {
                Swal.fire({
                    icon: 'error',
                    title: 'No se guardó',
                    text: (data && data.message) ? data.message : 'No se pudieron guardar las directivas.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }
            cerrarModalReglas();
            Swal.fire({
                icon: 'success',
                title: 'Directivas guardadas',
                text: data.mensaje || 'Reglas actualizadas correctamente.',
                confirmButtonColor: '#1565c0'
            });
        }).catch(function () {
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo guardar las reglas.',
                confirmButtonColor: '#1565c0'
            });
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
