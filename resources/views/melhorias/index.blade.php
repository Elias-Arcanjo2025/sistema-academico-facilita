<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-graph-up-arrow me-2 text-success"></i>Exame de Melhoria de Nota
                </h2>
                <small class="text-muted">
                    Lançamento de notas de melhoria solicitadas pelo aluno ou encarregado de educação.
                </small>
            </div>
            <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-info-circle" variant="info" title="Regras do Exame de Melhoria de Nota" id="tip-melhorias">
                Base legal: <strong>Decreto Executivo n.º 424/25, Art.º 34.º</strong>.
                <ul class="mb-0 mt-2">
                    <li>Só se aplica a alunos <strong>aprovados</strong> na disciplina (CFD ≥ 10) com nota na faixa configurável <strong>{{ $config['nota_min'] }} – {{ $config['nota_max'] }}</strong>;</li>
                    <li>Solicitado por escrito pelo aluno, pais e/ou encarregado de educação (n.º 1 e 2);</li>
                    <li>A nota final é sempre <strong>a mais alta</strong> entre a original e a obtida na melhoria (n.º 5);</li>
                    <li>Realizado <em>exclusivamente</em> em época de Exame de Recurso (n.º 4).</li>
                </ul>
                Pode ajustar os limites em <a href="{{ route('configuracoes.index') }}">Configurações</a>.
            </x-tip-card>

            <div class="card shadow-soft mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Turmas da 12.ª classe</h5>
                    <span class="badge bg-primary">{{ count($turmas) }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Turma</th>
                                <th>Classe</th>
                                <th>Curso</th>
                                <th>Ano Lectivo</th>
                                <th style="width: 220px;" class="text-end">Acção</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($turmas as $t)
                                @php $anoTurma = $t->anoLectivo?->nome ?? $t->ano_lectivo ?? '—'; @endphp
                                <tr>
                                    <td class="fw-bold">{{ $t->nome }}</td>
                                    <td>{{ $t->classe?->nome ?? '—' }}</td>
                                    <td>{{ $t->curso?->nome ?? '—' }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $anoTurma }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('melhorias.lancar', ['turma' => $t, 'ano_lectivo' => $anoLectivo]) }}"
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-pencil-square me-1"></i>Lançar Melhoria
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Nenhuma turma da 12.ª encontrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
