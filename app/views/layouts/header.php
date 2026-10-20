<?php
if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = 'COMEDICA — Sistema Médico';
}
if (!isset($breadcrumb) || !is_array($breadcrumb)) {
    $breadcrumb = array();
}
if (!isset($menuActivo)) {
    $menuActivo = '';
}

$nombreSesion = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolSesion = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$categSesion = isset($_SESSION['categ']) ? $_SESSION['categ'] : '';
$emailSesion = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$esAdmin = ($rolSesion === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="public/img/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: "Segoe UI", system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-[#f0f4f8] text-slate-800">

<header class="fixed inset-x-0 top-0 z-30 h-14 bg-gradient-to-r from-[#0d47a1] via-[#1565c0] to-[#1976d2] text-white shadow-md">
    <div class="relative flex h-full items-center justify-between gap-3 px-4">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-white/50 bg-white/20 text-sm font-bold">
                <?php echo h(dm_iniciales($nombreSesion)); ?>
            </div>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-semibold"><?php echo h($nombreSesion); ?></p>
                <p class="flex items-center gap-1 text-[11px] text-emerald-200">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                    En línea
                </p>
            </div>
        </div>

        <div class="pointer-events-none absolute left-1/2 hidden -translate-x-1/2 text-base font-bold tracking-[0.12em] md:block">
            COMEDICA
        </div>

        <button type="button" id="btnMenu"
                class="inline-flex items-center gap-2 rounded-md border border-white/35 bg-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/25">
            <i class="fa-solid fa-bars"></i>
            <span class="hidden sm:inline">Menú Principal</span>
        </button>
    </div>
</header>

<div id="menuOverlay" class="fixed inset-0 z-40 hidden bg-slate-900/40"></div>
<aside id="menuPrincipal" class="fixed bottom-0 right-0 top-0 z-50 w-80 max-w-full translate-x-full bg-white shadow-2xl transition-transform duration-200">
    <div class="flex items-center justify-between bg-gradient-to-br from-[#0d47a1] to-[#1565c0] px-5 py-4 text-white">
        <div>
            <p class="text-sm font-bold">Menú principal</p>
            <p class="text-xs text-white/75"><?php echo h(dm_rol_legible($rolSesion)); ?><?php echo $categSesion !== '' ? ' · ' . h($categSesion) : ''; ?></p>
        </div>
        <button type="button" id="btnCerrarMenu" class="rounded-md p-2 hover:bg-white/15" aria-label="Cerrar menú">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="border-b border-slate-100 px-5 py-4">
        <p class="truncate text-sm font-semibold text-slate-800"><?php echo h($nombreSesion); ?></p>
        <p class="truncate text-xs text-slate-500"><?php echo h($emailSesion); ?></p>
    </div>
    <nav class="py-2">
        <p class="px-5 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Módulos</p>
        <a href="index.php?c=dashboard&a=evolucionPadrones"
           class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm hover:bg-blue-50 hover:text-[#0d47a1] <?php echo $menuActivo === 'padrones' ? 'bg-blue-50 text-[#0d47a1]' : 'text-slate-700'; ?>">
            <i class="fa-solid fa-chart-line w-6 text-center text-[#1565c0]"></i>
            <span>Evolución de Padrones</span>
        </a>
        <?php if ($esAdmin): ?>
        <a href="index.php?c=dashboard&a=administradores"
           class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm hover:bg-blue-50 hover:text-[#0d47a1] <?php echo $menuActivo === 'admin' ? 'bg-blue-50 text-[#0d47a1]' : 'text-slate-700'; ?>">
            <i class="fa-solid fa-user-shield w-6 text-center text-[#1565c0]"></i>
            <span>Administradores</span>
        </a>
        <?php endif; ?>
        <a href="index.php?c=auth&a=logout"
           class="js-logout mt-2 flex items-center gap-3 border-t-2 border-red-100 px-5 py-3 text-sm text-red-700 hover:bg-red-50">
            <i class="fa-solid fa-right-from-bracket w-6 text-center"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</aside>

<div class="mx-auto max-w-7xl px-4 pb-20 pt-[72px]">
    <?php if (count($breadcrumb) > 0): ?>
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <?php foreach ($breadcrumb as $i => $item): ?>
            <?php if ($i > 0): ?><span>/</span><?php endif; ?>
            <?php if (isset($item['href'])): ?>
                <a class="hover:text-[#1565c0]" href="<?php echo h($item['href']); ?>"><?php echo h($item['label']); ?></a>
            <?php else: ?>
                <span class="font-medium text-slate-700"><?php echo h($item['label']); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
