import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const themeToggle = document.getElementById('theme-toggle');

if (themeToggle) {
    const updateThemeToggle = () => {
        const isDark = document.documentElement.dataset.theme === 'dark';
        const label = isDark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap';
        themeToggle.setAttribute('aria-label', label);
        themeToggle.setAttribute('title', label);
        themeToggle.querySelector('.theme-icon-moon')?.classList.toggle('hidden', isDark);
        themeToggle.querySelector('.theme-icon-sun')?.classList.toggle('hidden', !isDark);
    };

    updateThemeToggle();
    themeToggle.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';

        if (theme === 'dark') {
            document.documentElement.dataset.theme = 'dark';
        } else {
            delete document.documentElement.dataset.theme;
        }

        document.documentElement.style.colorScheme = theme;
        localStorage.setItem('app-theme', theme);
        updateThemeToggle();
    });
}
