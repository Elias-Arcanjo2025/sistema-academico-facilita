@extends('layouts.portal-aluno')

@section('title', 'Executivo IA')

@section('content')
@php
    $semAno = ! empty($dados['sem_ano_lectivo']);
    $fonteLabel = match ($dados['fonte'] ?? 'local') {
        'ia' => 'IA',
        'setup' => 'Configuração inicial',
        default => 'Análise local',
    };
@endphp
<div class="container-fluid px-3 py-3">
    <div class="card border-0 shadow-sm portal-card mb-3">
        <div class="card-body p-3">
            @if ($semAno)
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x text-warning fs-1 mb-2 d-block"></i>
                    <h1 class="h5 mb-2">Ano lectivo ainda não disponível</h1>
                    <p class="text-muted small mb-3">
                        A escola ainda não configurou o ano lectivo. Quando estiver activo, verá aqui o seu resumo académico.
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExecutivoRefresh">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            @else
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h1 class="h5 mb-1"><i class="bi bi-stars text-primary me-1"></i> Executivo IA</h1>
                        <p class="text-muted small mb-0">Resumo do portal adaptado ao seu perfil de aluno.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExecutivoRefresh">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            @endif
            <div id="executivoResumo" class="small {{ $semAno ? 'text-start' : '' }}">
                {!! \Illuminate\Support\Str::markdown($dados['resumo']) !!}
            </div>
            <p class="text-muted small mt-3 mb-0">
                {{ $dados['perfil'] }} · {{ $dados['gerado_em'] }} · {{ $fonteLabel }}
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('btnExecutivoRefresh')?.addEventListener('click', async function () {
    this.disabled = true;
    try {
        await fetch(@json(route('portal.aluno.executivo-ia.actualizar')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        location.reload();
    } finally {
        this.disabled = false;
    }
});
</script>
@endpush
@endsection
