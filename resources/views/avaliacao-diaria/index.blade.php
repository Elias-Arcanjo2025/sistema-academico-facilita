<x-app-layout>
    <style>
        .presenca-estado {
            font-weight: 600;
        }
        .presenca-estado--presente {
            color: #146c43;
            background-color: #d1e7dd;
            border-color: #a3cfbb;
        }
        .presenca-estado--ausente {
            color: #b02a37;
            background-color: #f8d7da;
            border-color: #f1aeb5;
        }
        .presenca-estado--atrasado {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }
        .presenca-estado--justificado {
            color: #41464b;
            background-color: #e2e3e5;
            border-color: #c4c8cb;
        }
    </style>

    <x-slot name="header">
        <div>
            <h2 class="h4 mb-0">Avaliação Diária</h2>
            <small class="text-muted">Registe as presenças e o desempenho diário dos alunos.</small>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Verifique os dados introduzidos:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Turma, disciplina e data</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('avaliacao-diaria.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                            <select class="form-select" id="turma_id" name="turma_id" required
                                    onchange="document.getElementById('disciplina_id').value = ''; this.form.submit();">
                                <option value="">Seleccione a turma</option>
                                @foreach($turmas as $opcaoTurma)
                                    <option value="{{ $opcaoTurma->id }}" @selected($turmaId === $opcaoTurma->id)>
                                        {{ $opcaoTurma->nome }}@if($opcaoTurma->curso) · {{ $opcaoTurma->curso->nome }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="disciplina_id" class="form-label">Disciplina <span class="text-danger">*</span></label>
                            <select class="form-select" id="disciplina_id" name="disciplina_id" required @disabled(!$turmaId)>
                                <option value="">Seleccione a disciplina</option>
                                @foreach($disciplinas as $opcaoDisciplina)
                                    <option value="{{ $opcaoDisciplina->id }}" @selected($disciplinaId === $opcaoDisciplina->id)>
                                        {{ $opcaoDisciplina->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" id="data" name="data" value="{{ $data }}" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Consultar
                            </button>
                        </div>
                    </form>
                    @if($turmas->isEmpty())
                        <p class="text-muted mb-0 mt-3">Não existem turmas associadas ao seu utilizador no ano lectivo actual.</p>
                    @elseif($turmaId && $disciplinas->isEmpty())
                        <p class="text-warning mb-0 mt-3">Não existem disciplinas activas associadas a esta turma.</p>
                    @endif
                </div>
            </div>

            @if($turma && $disciplina)
                <div class="card shadow-sm">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0">{{ $turma->nome }} · {{ $disciplina->nome }}</h5>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($data)->format('d/m/Y') }} · {{ $alunos->count() }} aluno(s)</small>
                        </div>
                        @if($alunos->isNotEmpty())
                            <button type="submit" form="avaliacao-diaria-form" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i>Guardar registos
                            </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($alunos->isEmpty())
                            <div class="alert alert-info m-3 mb-0">Não existem alunos matriculados nesta turma.</div>
                        @else
                            <form id="avaliacao-diaria-form" method="POST" action="{{ route('avaliacao-diaria.store') }}">
                                @csrf
                                <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                                <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
                                <input type="hidden" name="data" value="{{ $data }}">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle mb-0" style="min-width: 1900px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" class="text-center" style="width:60px;">N.º</th>
                                                <th rowspan="2" style="min-width:210px;">Aluno</th>
                                                <th colspan="2" class="text-center">Presença</th>
                                                <th colspan="5" class="text-center">Avaliação diária (0–20)</th>
                                                <th rowspan="2" style="min-width:220px;">Observação geral</th>
                                            </tr>
                                            <tr>
                                                <th style="min-width:170px;">Estado</th>
                                                <th style="min-width:190px;">Observação</th>
                                                <th style="min-width:115px;">Participação</th>
                                                <th style="min-width:115px;">Empenho</th>
                                                <th style="min-width:125px;">Comportamento</th>
                                                <th style="min-width:135px;">Actividades</th>
                                                <th style="min-width:125px;">Compreensão</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($alunos as $aluno)
                                                @php
                                                    $presenca = $presencas->get($aluno->id);
                                                    $avaliacao = $avaliacoes->get($aluno->id);
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $aluno->numero_ordem ?? '—' }}</td>
                                                    <td>{{ $aluno->nome }}</td>
                                                    <td>
                                                        <select class="form-select form-select-sm presenca-estado"
                                                                name="presencas[{{ $aluno->id }}][estado]"
                                                                onchange="actualizarCorPresenca(this)">
                                                            <option value="" @selected(old("presencas.{$aluno->id}.estado", $presenca->estado ?? '') === '')>— Não registada —</option>
                                                            @foreach(\App\Models\AlunoPresenca::ESTADOS as $estado => $rotulo)
                                                                <option value="{{ $estado }}" @selected(old("presencas.{$aluno->id}.estado", $presenca->estado ?? '') === $estado)>
                                                                    {{ $rotulo }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input class="form-control form-control-sm" type="text" maxlength="500"
                                                               name="presencas[{{ $aluno->id }}][observacao]"
                                                               value="{{ old("presencas.{$aluno->id}.observacao", $presenca->observacao ?? '') }}"
                                                               aria-label="Observação da presença de {{ $aluno->nome }}">
                                                    </td>
                                                    @foreach([
                                                        'participacao' => 'Participação',
                                                        'empenho' => 'Empenho',
                                                        'comportamento' => 'Comportamento',
                                                        'realizacao_actividades' => 'Realização das actividades',
                                                        'compreensao' => 'Compreensão dos conteúdos',
                                                    ] as $criterio => $rotulo)
                                                        <td>
                                                            <input class="form-control form-control-sm" type="number" min="0" max="20" step="0.01"
                                                                   name="avaliacoes[{{ $aluno->id }}][{{ $criterio }}]"
                                                                   value="{{ old("avaliacoes.{$aluno->id}.{$criterio}", $avaliacao?->{$criterio}) }}"
                                                                   aria-label="{{ $rotulo }} de {{ $aluno->nome }}">
                                                        </td>
                                                    @endforeach
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="1" maxlength="2000"
                                                                  name="avaliacoes[{{ $aluno->id }}][observacao_geral]"
                                                                  aria-label="Observação geral de {{ $aluno->nome }}">{{ old("avaliacoes.{$aluno->id}.observacao_geral", $avaliacao?->observacao_geral) }}</textarea>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end p-3">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check2-circle me-1"></i>Guardar presenças e avaliações
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    <script>
        function actualizarCorPresenca(select) {
            select.classList.remove(
                'presenca-estado--presente',
                'presenca-estado--ausente',
                'presenca-estado--atrasado',
                'presenca-estado--justificado'
            );
            if (select.value) {
                select.classList.add(`presenca-estado--${select.value}`);
            }
        }

        document.querySelectorAll('.presenca-estado').forEach(actualizarCorPresenca);
    </script>
</x-app-layout>
