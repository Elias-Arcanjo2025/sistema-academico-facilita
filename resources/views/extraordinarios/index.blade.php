<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-clipboard-check me-2 text-warning"></i>Exame Extraordinário
                </h2>
                <small class="text-muted">
                    Lançamento de notas extraordinárias para finalistas em condições especiais.
                </small>
            </div>
            <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-info-circle" variant="warning" title="Quando é elegível? (Art.º 32.º)" id="tip-extra">
                Base legal: <strong>Decreto Executivo n.º 424/25, Art.º 32.º</strong>.
                Um aluno (9.ª ou 12.ª) é elegível ao Extraordinário quando se verifica uma das condições:
                <ul class="mb-0 mt-2">
                    <li><strong>a)</strong> Reprovado simultaneamente em todas as disciplinas <em>blindadas</em> do curso (LP+Mat na 9.ª; LP/Mat+específica na 12.ª);</li>
                    <li><strong>b)</strong> Faltou à época normal ou ao recurso por motivo justificado (configurar no perfil do aluno);</li>
                    <li><strong>c)</strong> Tem aprovação em ≥ {{ $config['min_aprovacoes_anteriores'] }} disciplina(s) em extraordinários anteriores;</li>
                    <li><strong>d)</strong> 12.ª: obteve &lt;10 em ≥ {{ $config['max_negativas_recurso'] }} dos exames de recurso realizados;</li>
                    <li><strong>e)</strong> Atleta de alta competição / selecção provincial ou nacional.</li>
                </ul>
                Aprovação na disciplina: ≥ 10 valores. A nota <em>substitui</em> a anterior (n.º 5).
            </x-tip-card>

            <div class="card shadow-soft mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Turmas da 9.ª e 12.ª classe</h5>
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
                                <th style="width: 240px;" class="text-end">Acção</th>
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
                                        <a href="{{ route('extraordinarios.lancar', ['turma' => $t, 'ano_lectivo' => $anoLectivo]) }}"
                                           class="btn btn-sm btn-warning text-dark">
                                            <i class="bi bi-pencil-square me-1"></i>Lançar Extraordinário
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Nenhuma turma da 9.ª/12.ª encontrada.
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
