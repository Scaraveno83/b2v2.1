(function(){
    var channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('theme-mode') : null;

    function applyMode(mode) {
        if (mode === 'light') {
            document.body.classList.add('light');
            } else {
            document.body.classList.remove('light');
        }
    }

    function detectPreferredMode() {
        var saved = localStorage.getItem('themeMode');
        if (saved) {
            return saved;
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }
        return 'dark';
    }

    function persistMode(mode) {
        localStorage.setItem('themeMode', mode);
        if (channel) {
            channel.postMessage(mode);
        }
    }

    function applySavedTheme() {
        applyMode(detectPreferredMode());
    }

    document.addEventListener('DOMContentLoaded', applySavedTheme);

    if (channel) {
        channel.addEventListener('message', function (event) {
            applyMode(event.data);
        });
    }

    window.toggleThemeMode = function() {
        var mode = document.body.classList.contains('light') ? 'dark' : 'light';
        applyMode(mode);
        persistMode(mode);
    }
})();
