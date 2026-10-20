<?php
if (!isset($listado) || !is_array($listado)) {
    $listado = array();
}
$error = isset($errorListado) ? $errorListado : '';
$total = count($listado);
?>

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-lg font-bold text-[#0d47a1]">
            <i class="fa-solid fa-user-shield mr-2"></i>Administradores
        </h1>
        <p class="text-sm text-slate-500">Cuentas internas de <span class="font-medium">vfpmedicos.users</span>. El alta y la edición quedan para el módulo siguiente.</p>
    </div>
    <span class="inline-flex items-center gap-1 rounded-full bg-[#1565c0] px-3 py-1.5 text-xs font-semibold text-white">
        <i class="fa-solid fa-users"></i>
        <?php echo (int) $total; ?> cuenta<?php echo $total === 1 ? '' : 's'; ?>
    </span>
</div>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <?php if ($error !== ''): ?>
        <div class="border-b border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i><?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-100">
                <tr>
                    <th class="px-4 py-3 font-semibold">#</th>
                    <th class="px-4 py-3 font-semibold">Usuario</th>
                    <th class="px-4 py-3 font-semibold">Nombre</th>
                    <th class="px-4 py-3 font-semibold">Correo</th>
                    <th class="px-4 py-3 font-semibold">Rol interno</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($total === 0): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No hay administradores para mostrar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listado as $fila): ?>
                        <?php
                        $nombreFila = trim($fila['FIRST_NAME'] . ' ' . $fila['LAST_NAME']);
                        $activado = isset($fila['ACTIVATED']) ? $fila['ACTIVATED'] : null;
                        $bloqueado = ($activado !== null && $activado !== '' && (int) $activado === 0);
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-500"><?php echo (int) $fila['USER_ID']; ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?php echo h($fila['USERNAME']); ?></td>
                            <td class="px-4 py-3"><?php echo h($nombreFila); ?></td>
                            <td class="px-4 py-3 text-slate-600"><?php echo isset($fila['EMAIL']) && trim((string) $fila['EMAIL']) !== '' ? h($fila['EMAIL']) : '—'; ?></td>
                            <td class="px-4 py-3"><?php echo isset($fila['id_rol']) ? (int) $fila['id_rol'] : 0; ?></td>
                            <td class="px-4 py-3">
                                <?php if ($bloqueado): ?>
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Bloqueado</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
