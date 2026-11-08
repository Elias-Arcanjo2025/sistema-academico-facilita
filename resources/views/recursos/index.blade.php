<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Exame de Recurso (12.ª Classe)
                </h2>
                <small class="text-muted">
                    Lançamento das notas de recurso para alunos elegíveis após o conselho final.
                </small>
            </div>
            <div class="d-flex gap-2">
                @unless($visaoProfessor ?? false)
                    <a href="{{ route('exames.elegibilidade') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-list-check"></i> Lista de elegíveis
                    </a>
                    <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                @else
                    <a href="{{ route('exames.elegibilidade') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-list-check"></i> Elegíveis
                    </a>
                @endunless
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-info-circle" variant="info" title="Regras de elegibilidade" id="tip-recursos">
                Base legal: <strong>Decreto Executivo n.º 424/25, Art.º 31.º</strong>.
                Um aluno da 12.ª é elegível ao Exame de Recurso quando:
                <ul class="mb-0 mt-2">
                    <li>Tem entre 1 e <strong>{{ $config['max_disciplinas'] }}</strong> disciplinas com CFD na faixa <strong>{{ $config['nota_min'] }} – {{ $config['nota_max'] }}</strong> valores;</li>
                    <li><strong>Não</strong> tem nenhuma disciplina com CFD inferior a {{ $config['nota_min'] }};</li>
                    <li><strong>Não</strong> tem TODAS as disciplinas blindadas (LP, Mat, etc.) simultaneamente com nota negativa.</li>
                </ul>
                A nota do recurso <em>substitui</em> a CFD anterior (Art.º 31.º, n.º 3). Pode ajustar os limites em <a href="{{ route('configuracoes.index') }}">Configurações</a>.
            </x-tip-card>

            @if(!empty($visaoProfessor) && ($associacoesProfessor ?? collect())->isNotEmpty())
                <div class="card shadow-soft mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-workspace me-2"></i>As minhas disciplinas — acesso rápido</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Turma</th>
                                    <th class="text-end">Acção</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($associacoesProfessor as $assoc)
                                    <tr>
                                        <td>{{ $assoc->disciplina?->codigo ?: $assoc->disciplina?->nome }}</td>
                                        <td>{{ $assoc->turma?->nome }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('recursos.lancar', ['turma' => $assoc->turma, 'ano_lectivo' => $anoLectivo]) }}"
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-pencil-square"></i> Lançar
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

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
                                <th style="width: 200px;" class="text-end">Acção</th>
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
                                        <a href="{{ route('recursos.lancar', ['turma' => $t, 'ano_lectivo' => $anoLectivo]) }}"
                                           class="btn btn-sm btn-danger">
                                            <i class="bi bi-pencil-square me-1"></i>Lançar Recurso
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
