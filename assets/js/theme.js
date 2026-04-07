(function() {
    const theme = localStorage.getItem('lats-theme') || 'light';
    document.documentElement.classList.add(theme);
})();

function toggleTheme() {
    const html = document.documentElement;
    const current = html.classList.contains('dark') ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    
    html.classList.remove(current);
    html.classList.add(next);
    localStorage.setItem('lats-theme', next);
    
    // Optional: Trigger custom event
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: next }));
}
