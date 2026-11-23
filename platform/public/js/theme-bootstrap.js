(() => {
    const root = document.documentElement;
    let theme = root.dataset.defaultTheme === 'light' ? 'light' : 'dark';
    if (root.dataset.themeLocked === 'true') theme = 'light';
    else {
        try {
            const saved = localStorage.getItem('ais:color-theme');
            if (saved === 'dark' || saved === 'light') theme = saved;
        } catch {}
    }
    root.dataset.colorTheme = theme;
    const color = document.querySelector('meta[name="theme-color"]');
    if (color) color.content = theme === 'dark' ? '#090a0c' : '#f5f6fa';
})();
