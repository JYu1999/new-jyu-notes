import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Theme toggle (light/dark) — persists in cookie
function initTheme() {
    const stored = document.cookie
        .split('; ')
        .find((r) => r.startsWith('theme='))
        ?.split('=')[1];
    if (stored) {
        document.documentElement.setAttribute('data-theme', stored);
    }
}
initTheme();

window.toggleTheme = function () {
    const current = document.documentElement.getAttribute('data-theme') ?? 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.cookie = `theme=${next}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
};

Alpine.start();
