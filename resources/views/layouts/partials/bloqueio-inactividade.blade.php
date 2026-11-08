@if($bloqueioInactividadeActivo ?? false)
<div id="facilita-lock-overlay" class="facilita-lock-overlay" aria-hidden="true"></div>
<script>
    window.facilitaBloqueioInactividade = {
        activo: true,
        minutos: {{ (int) ($bloqueioInactividadeMinutos ?? 30) }},
        url: @json(route('sessao.desbloquear')),
        urlBloquear: @json(route('sessao.bloquear')),
        lockedOnServer: @json((bool) session('facilita_sessao_bloqueada', false)),
        ultimaActividadeServidor: {{ (int) (session('facilita_ultima_actividade', 0) * 1000) }},
    };
</script>
@include('layouts.partials.bloqueio-inactividade-script')
@endif
