export function initTheme() {
    const html = document.documentElement;
    
    // 1. On récupère la valeur injectée dans le HTML par Blade
    const dbTheme = html.dataset.dbTheme || 'auto';

    // 2. On récupère le localStorage
    const localTheme = localStorage.getItem('theme');

    // 3. Détermination du thème
    let themeToApply = localTheme;

    if (!themeToApply && dbTheme !== 'auto') {
        themeToApply = dbTheme;
    }

    if (!themeToApply || themeToApply === 'auto') {
        themeToApply = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    // Application
    html.setAttribute('data-theme', themeToApply);

    // 4. Écouteur pour le changement système
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        const currentLocal = localStorage.getItem('theme');
        if (!currentLocal || currentLocal === 'auto') {
            html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        }
    });
}