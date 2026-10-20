</div>

<div class="fixed bottom-4 right-4 z-20 inline-flex items-center gap-2 rounded-full bg-[#1565c0] px-3 py-1.5 text-xs font-medium text-white shadow-lg">
    <i class="fa-solid fa-clock"></i>
    <span id="reloj-panel"></span>
</div>

<script>
(function () {
    var menu = document.getElementById('menuPrincipal');
    var overlay = document.getElementById('menuOverlay');
    var btn = document.getElementById('btnMenu');
    var cerrar = document.getElementById('btnCerrarMenu');

    function abrirMenu() {
        menu.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
    }

    function cerrarMenu() {
        menu.classList.add('translate-x-full');
        overlay.classList.add('hidden');
    }

    if (btn) {
        btn.addEventListener('click', abrirMenu);
    }
    if (cerrar) {
        cerrar.addEventListener('click', cerrarMenu);
    }
    if (overlay) {
        overlay.addEventListener('click', cerrarMenu);
    }

    document.addEventListener('click', function (e) {
        var nodo = e.target;
        var link = null;
        while (nodo && nodo !== document) {
            if (nodo.classList && nodo.classList.contains('js-logout')) {
                link = nodo;
                break;
            }
            nodo = nodo.parentNode;
        }
        if (!link) {
            return;
        }
        e.preventDefault();
        var href = link.getAttribute('href');
        Swal.fire({
            title: 'Cerrar sesión',
            text: '¿Desea salir del sistema?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c62828',
            cancelButtonColor: '#64748b'
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                window.location.href = href;
            }
        });
    });

    var dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    var meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function dos(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function pintarReloj() {
        var now = new Date();
        var el = document.getElementById('reloj-panel');
        if (!el) {
            return;
        }
        el.textContent = dos(now.getHours()) + ':' + dos(now.getMinutes()) + ':' + dos(now.getSeconds())
            + ' | ' + dias[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
    }

    pintarReloj();
    setInterval(pintarReloj, 1000);
})();
</script>
</body>
</html>
