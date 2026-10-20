<?php
$csrfToken = isset($csrf) ? $csrf : '';
$mostrarSalida = !empty($salida);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMEDICA — Iniciar sesión</title>
    <link rel="icon" type="image/x-icon" href="public/img/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: "Segoe UI", system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#0a3d91] via-[#1565c0] to-[#42a5f5] flex items-center justify-center p-4">

    <div class="w-full max-w-[420px] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.35)] overflow-hidden">
        <div class="bg-gradient-to-br from-[#0d47a1] to-[#1976d2] px-6 pt-8 pb-6 text-center">
            <div class="mx-auto mb-4 flex h-[110px] w-[110px] items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_4px_20px_rgba(0,0,0,0.25)]">
                <img src="public/img/logo.jfif" alt="COMEDICA" class="h-full w-full object-cover"
                     onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\'fa-solid fa-hospital text-4xl text-[#1565c0]\'></i>';">
            </div>
            <h1 class="text-white text-xl font-bold tracking-wide">COMEDICA</h1>
            <p class="mt-1 text-sm text-white/75">Sistema de Gestión Médica</p>
        </div>

        <div class="px-8 pb-7 pt-7">
            <form id="formLogin" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

                <div class="mb-4">
                    <label for="usuario" class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                        <i class="fa-solid fa-user text-[#1565c0]"></i>
                        Usuario
                    </label>
                    <div class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 focus-within:border-[#1565c0] focus-within:ring-2 focus-within:ring-[#1565c0]/20">
                        <span class="flex h-11 w-11 items-center justify-center text-[#1565c0]">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" id="usuario" name="usuario" maxlength="150" autofocus
                               placeholder="Usuario o correo"
                               class="h-11 w-full border-0 bg-transparent pr-3 text-sm text-slate-800 outline-none">
                    </div>
                </div>

                <div class="mb-5">
                    <label for="password" class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                        <i class="fa-solid fa-lock text-[#1565c0]"></i>
                        Contraseña
                    </label>
                    <div class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 focus-within:border-[#1565c0] focus-within:ring-2 focus-within:ring-[#1565c0]/20">
                        <span class="flex h-11 w-11 items-center justify-center text-[#1565c0]">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" maxlength="128"
                               placeholder="Ingrese su contraseña"
                               class="h-11 w-full border-0 bg-transparent text-sm text-slate-800 outline-none">
                        <button type="button" id="togglePass" title="Mostrar u ocultar contraseña"
                                class="flex h-11 w-11 items-center justify-center text-slate-500 hover:text-[#1565c0]">
                            <i class="fa-solid fa-eye" id="togglePassIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="btnIngresar"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-[#0d47a1] to-[#1976d2] text-sm font-semibold tracking-wide text-white shadow-sm transition hover:opacity-90">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Ingresar al Sistema
                </button>
            </form>

            <p id="login-clock" class="mt-4 text-center text-xs text-slate-400"></p>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-center text-xs text-slate-400">
            &copy; <?php echo date('Y'); ?> COMEDICA — Todos los derechos reservados
        </div>
    </div>

<script>
(function () {
    var mostrarSalida = <?php echo $mostrarSalida ? 'true' : 'false'; ?>;

    document.getElementById('togglePass').addEventListener('click', function () {
        var pwd = document.getElementById('password');
        var icon = document.getElementById('togglePassIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    var dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    var meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function dos(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function actualizarReloj() {
        var now = new Date();
        var texto = dos(now.getHours()) + ':' + dos(now.getMinutes()) + ':' + dos(now.getSeconds())
            + ' | ' + dias[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
        document.getElementById('login-clock').textContent = texto;
    }

    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    if (mostrarSalida) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Sesión cerrada correctamente',
            showConfirmButton: false,
            timer: 2800,
            timerProgressBar: true
        });
    }

    document.getElementById('formLogin').addEventListener('submit', function (e) {
        e.preventDefault();

        var usuario = document.getElementById('usuario').value.replace(/^\s+|\s+$/g, '');
        var password = document.getElementById('password').value;
        var token = document.querySelector('input[name="csrf_token"]').value;

        if (!usuario || !password) {
            Swal.fire({
                icon: 'warning',
                title: 'Datos incompletos',
                text: 'Complete usuario y contraseña.',
                confirmButtonColor: '#1565c0'
            });
            return;
        }

        Swal.fire({
            title: 'Verificando credenciales',
            text: 'Aguarde un momento...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        var cuerpo = 'usuario=' + encodeURIComponent(usuario)
            + '&password=' + encodeURIComponent(password)
            + '&csrf_token=' + encodeURIComponent(token);

        fetch('index.php?c=auth&a=ajaxLogin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo,
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.text().then(function (texto) {
                var data = null;
                try {
                    data = JSON.parse(texto);
                } catch (err) {
                    data = null;
                }
                return { okHttp: respuesta.ok, data: data };
            });
        }).then(function (resultado) {
            var data = resultado.data;
            if (!data || !data.status) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sin respuesta',
                    text: 'No se pudo contactar al servidor.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }

            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Acceso correcto',
                    text: data.message || 'Bienvenido.',
                    timer: 1400,
                    showConfirmButton: false
                }).then(function () {
                    window.location.href = data.redirect || 'index.php?c=dashboard&a=evolucionPadrones';
                });
                return;
            }

            if (data.status === 'blocked') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Acceso bloqueado',
                    text: data.message || 'No es posible ingresar en este momento.',
                    confirmButtonColor: '#1565c0'
                });
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'No se pudo ingresar',
                text: data.message || 'Usuario o contraseña incorrectos.',
                confirmButtonColor: '#1565c0'
            });
        }).catch(function () {
            Swal.fire({
                icon: 'error',
                title: 'Sin respuesta',
                text: 'No se pudo contactar al servidor.',
                confirmButtonColor: '#1565c0'
            });
        });
    });
})();
</script>
</body>
</html>
