{{-- Footer visível apenas em páginas públicas --}}
@guest
<footer class="bg-light border-top py-3 mt-auto">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <small class="text-muted">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Sistema Académico Facilita') }}
                </small>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <small class="text-muted">
                    Sistema de Gestão de Notas Académicas
                </small>
            </div>
        </div>
    </div>
</footer>
@endguest
