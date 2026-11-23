(function () {
    const savedTheme = localStorage.getItem('tecnostock_theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    actualizarIconoTema();
    inicializarToastsFlotantes();
});

function toggleTemaOscuro() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('tecnostock_theme', isDark ? 'dark' : 'light');
    actualizarIconoTema();
}

function actualizarIconoTema() {
    const btn = document.getElementById('btnToggleTema');
    if (!btn) return;
    const isDark = document.body.classList.contains('dark-mode');
    btn.innerHTML = isDark
        ? '<i class="bi bi-sun-fill text-warning"></i>'
        : '<i class="bi bi-moon-stars-fill"></i>';
    btn.title = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
}

function inicializarToastsFlotantes() {
    const toasts = document.querySelectorAll('.ts-toast');
    if (toasts.length === 0) return;

    toasts.forEach(toast => {
        setTimeout(() => cerrarToastFlotante(toast), 4000);
    });

    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('msg') || url.searchParams.has('error')) {
            url.searchParams.delete('msg');
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.toString());
        }
    }
}

function cerrarToastFlotante(toastEl) {
    if (!toastEl || toastEl.classList.contains('ts-toast-hide')) return;
    toastEl.classList.add('ts-toast-hide');
    setTimeout(() => toastEl.remove(), 300);
}