<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Renovar matrícula — {{ $aluno->nome }}</h2>
            <a href="{{ route('secretaria.matriculas.historico', $aluno) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-6">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
                ['label' => $aluno->nome, 'url' => route('secretaria.matriculas.historico', $aluno)],
                ['label' => 'Renovar', 'url' => route('secretaria.matriculas.renovar', $aluno)],
            ]" />

            <x-tip-card icon="bi-arrow-repeat" variant="info" title="Renovação de matrícula" id="tip-secretaria-matriculas-renovar" compact>
                Escolha a turma do ano lectivo actual e, se quiser, indique o motivo. O histórico do aluno será actualizado.
            </x-tip-card>

            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-3">Turma actual: <strong>{{ $aluno->turma->nome ?? '—' }}</strong></p>
                    <form method="POST" action="{{ route('secretaria.matriculas.renovar.store', $aluno) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nova turma (ano actual) *</label>
                            <select name="turma_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}">{{ $turma->nome }} — {{ $turma->curso->nome ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="motivo" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Renovar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
