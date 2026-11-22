// Runs before first paint (blocking, no inline script needed under the CSP): applies the saved theme.
(function () {
    try {
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch (e) {}
})();
