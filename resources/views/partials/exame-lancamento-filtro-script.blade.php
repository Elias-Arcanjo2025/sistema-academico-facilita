<script>
    document.querySelectorAll('[data-filtro-exame]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var filtro = this.getAttribute('data-filtro-exame');
            document.querySelectorAll('[data-filtro-exame]').forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            document.querySelectorAll('[data-estado-exame]').forEach(function (row) {
                var estado = row.getAttribute('data-estado-exame');
                var mostrar = filtro === 'todos' || estado === filtro;
                row.style.display = mostrar ? '' : 'none';
            });
        });
    });
</script>
