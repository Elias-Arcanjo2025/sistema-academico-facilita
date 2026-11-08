<script>
(function () {
    var preview = document.getElementById('ppPreview');
    if (!preview) return;

    var colorFields = {
        primary: document.getElementById('pp_cor_primaria'),
        dark: document.getElementById('pp_cor_primaria_escura'),
        secondary: document.getElementById('pp_cor_secundaria')
    };

    function hexToRgb(hex) {
        hex = (hex || '').replace('#', '');
        if (hex.length !== 6) return null;
        return { r: parseInt(hex.substr(0, 2), 16), g: parseInt(hex.substr(2, 2), 16), b: parseInt(hex.substr(4, 2), 16) };
    }

    function mixHex(a, b, ratio) {
        var ra = hexToRgb(a), rb = hexToRgb(b);
        if (!ra || !rb) return a || '#4f46e5';
        ratio = Math.max(0, Math.min(1, ratio));
        return '#' + [ra.r + (rb.r - ra.r) * ratio, ra.g + (rb.g - ra.g) * ratio, ra.b + (rb.b - ra.b) * ratio]
            .map(function (n) { return ('0' + Math.round(n).toString(16)).slice(-2); }).join('');
    }

    function validHex(v) { return /^#[0-9A-Fa-f]{6}$/.test(v || ''); }

    function getColor(input, fallback) {
        var v = input && input.value ? input.value : fallback;
        return validHex(v) ? v : fallback;
    }

    function updatePreview() {
        var primary = getColor(colorFields.primary, '#4f46e5');
        var dark = getColor(colorFields.dark, '#3730a3');
        var secondary = getColor(colorFields.secondary, '#06b6d4');
        var accent = mixHex(primary, secondary, 0.42);
        var pr = hexToRgb(primary), sr = hexToRgb(secondary);

        preview.style.setProperty('--pp-primary', primary);
        preview.style.setProperty('--pp-primary-dark', dark);
        preview.style.setProperty('--pp-secondary', secondary);
        preview.style.setProperty('--pp-accent', accent);
        if (pr) preview.style.setProperty('--pp-primary-rgb', pr.r + ', ' + pr.g + ', ' + pr.b);
        if (sr) preview.style.setProperty('--pp-secondary-rgb', sr.r + ', ' + sr.g + ', ' + sr.b);

        ['ppSwatchPrimary', 'ppSwatchDark', 'ppSwatchSecondary', 'ppSwatchAccent'].forEach(function (id, i) {
            var el = document.getElementById(id);
            if (el) el.style.background = [primary, dark, secondary, accent][i];
        });
    }

    document.querySelectorAll('[data-sync-color]').forEach(function (textInput) {
        var name = textInput.getAttribute('data-sync-color');
        var colorInput = document.querySelector('input[type="color"][name="' + name + '"]');
        if (!colorInput) return;
        colorInput.addEventListener('input', function () { textInput.value = colorInput.value; updatePreview(); });
        textInput.addEventListener('input', function () {
            if (validHex(textInput.value)) { colorInput.value = textInput.value; updatePreview(); }
        });
    });

    updatePreview();
})();
</script>
