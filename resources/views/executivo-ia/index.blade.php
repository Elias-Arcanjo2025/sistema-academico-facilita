<x-app-layout>
    <x-slot name="header">Executivo IA</x-slot>

    @push('styles')
        <link href="{{ asset('css/dashboard-admin.css') }}" rel="stylesheet">
    @endpush

    @php
        $semAno = ! empty($dados['sem_ano_lectivo']);
        $fonteLabel = match ($dados['fonte'] ?? 'local') {
            'ia' => 'Inteligência artificial',
            'setup' => 'Configuração inicial',
            default => 'Análise local',
        };
    @endphp

    @if ($semAno)
        <div class="content-card mb-4 border-0 shadow-sm">
            <div class="content-card-body py-5 px-4 text-center">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning" style="width:4rem;height:4rem;">
                        <i class="bi bi-calendar-x fs-3"></i>
                    </span>
                </div>
                <h2 class="h4 mb-2">Ano lectivo ainda não configurado</h2>
                <p class="text-muted mx-auto mb-4" style="max-width:32rem;">
                    O Executivo IA precisa de um ano lectivo activo para analisar turmas, notas e indicadores.
                    Crie o primeiro ano em Configurações para desbloquear o resumo.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    <a href="{{ route('configuracoes.index') }}#anos-lectivos-section" class="btn btn-primary">
                        <i class="bi bi-calendar-plus me-1"></i> Criar ano lectivo
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="btnExecutivoRefresh">
                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                    </button>
                </div>
                <div class="text-start mx-auto small bg-light rounded-3 p-3" style="max-width:36rem;" id="executivoResumo">
                    {!! \Illuminate\Support\Str::markdown($dados['resumo']) !!}
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ $fonteLabel }} · {{ $dados['perfil'] }} · {{ $dados['gerado_em'] }}
                </p>
            </div>
        </div>
    @else
        <div class="content-card mb-4">
            <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="content-card-title mb-0">
                    <i class="bi bi-stars me-2 text-primary"></i>Resumo executivo
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-subtle text-secondary">{{ $dados['perfil'] }}</span>
                    <span class="small text-muted">{{ $dados['gerado_em'] }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExecutivoRefresh">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>
            <div class="content-card-body">
                <div id="executivoResumo" class="executivo-markdown small">
                    {!! \Illuminate\Support\Str::markdown($dados['resumo']) !!}
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Fonte: {{ $fonteLabel }}
                    — adaptado ao seu perfil no sistema.
                </p>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
    document.getElementById('btnExecutivoRefresh')?.addEventListener('click', async function () {
        var btn = this;
        btn.disabled = true;
        try {
            var r = await fetch(@json(route('executivo-ia.actualizar')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            var data = await r.json();
            if (data.success) {
                location.reload();
            }
        } finally {
            btn.disabled = false;
        }
    });
    </script>
    @endpush
</x-app-layout>
