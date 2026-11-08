<x-app-layout>

    <x-slot name="header">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>

                <h2 class="h4 mb-0">

                    <i class="bi bi-bandaid me-2 text-primary"></i>Exame de Recuperação (10.ª / 11.ª)

                </h2>

                <small class="text-muted">Recuperação anual — Art.º 30.º DE 424/25</small>

            </div>

            <div class="d-flex gap-2">

                <a href="{{ route('exames.elegibilidade') }}" class="btn btn-outline-primary btn-sm">

                    <i class="bi bi-list-check"></i> Lista de elegíveis

                </a>

                @unless($visaoProfessor ?? false)
                    <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary btn-sm">

                        <i class="bi bi-arrow-left"></i> Pautas

                    </a>
                @endunless

            </div>

        </div>

    </x-slot>



    <div class="py-4">

        <div class="container-fluid">

            <x-tip-card icon="bi-info-circle" variant="info" title="Recuperação vs Recurso" compact>

                <strong>10.ª e 11.ª:</strong> uma única nota anual por disciplina, após fecho da pauta T3,

                para alunos em <strong>TRANS. C/DEF.</strong> (1 a 3 disciplinas negativas).

                A nota de recuperação <strong>substitui a CFD</strong> (ou CA) para efeitos de aproveitamento final.

                Alterações mantêm histórico das notas anteriores.

                <strong>12.ª:</strong> use o módulo de <a href="{{ route('recursos.index') }}">Exame de Recurso</a> (Art.º 31.º).

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

                                            <a href="{{ route('recuperacoes.lancar', ['turma' => $assoc->turma, 'ano_lectivo' => $anoLectivo]) }}"

                                               class="btn btn-sm btn-outline-primary">

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

                <div class="card-header">

                    <h5 class="mb-0">Turmas elegíveis (10.ª e 11.ª classes)</h5>

                </div>

                <div class="card-body p-0">

                    @if($turmas->isEmpty())

                        <div class="text-center text-muted py-4">Nenhuma turma encontrada.</div>

                    @else

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>Turma</th>

                                    <th>Classe</th>

                                    <th>Curso</th>

                                    <th class="text-end">Acções</th>

                                </tr>

                            </thead>

                            <tbody>

                                @foreach($turmas as $turma)

                                    <tr>

                                        <td class="fw-semibold">{{ $turma->nome }}</td>

                                        <td>{{ $turma->classe?->nome ?? '—' }}</td>

                                        <td>{{ $turma->curso?->nome ?? '—' }}</td>

                                        <td class="text-end">

                                            <a href="{{ route('recuperacoes.lancar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo]) }}"

                                               class="btn btn-sm btn-primary">

                                                <i class="bi bi-pencil-square"></i> Lançar recuperação

                                            </a>

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    @endif

                </div>

            </div>

        </div>

    </div>

</x-app-layout>


