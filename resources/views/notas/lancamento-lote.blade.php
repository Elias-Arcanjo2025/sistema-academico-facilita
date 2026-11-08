@php
    $urlFolha = route('notas.lancar', ['modo' => 'folha']);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Lançar Notas</h2>
            <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($modoConsulta ?? false)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo anterior seleccionado — lançamento desactivado.
                    <a href="{{ $urlFolha }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @include('layouts.partials.assistente-contextual')

            @include('notas.partials.modo-lancamento')

            <x-tip-card icon="bi-table" variant="success" title="Lançar Notas — Folha completa" id="tip-notas-lote">
                Preencha <strong>MAC</strong>, <strong>PP</strong> e <strong>PT</strong> (ou <strong>PG</strong> no 3.º trimestre) na mesma grelha para toda a turma.
                Para exames (recuperação, recurso, melhoria) ou um tipo de cada vez, use <strong>Por tipo</strong>.
                Trimestres fechados ficam bloqueados para lançamento.
                @if(\App\Support\NotaLancamentoRules::anoLectivoEhHistorico($anoLectivoId ?? null))
                    Em anos anteriores, as notas arquivadas na transição voltam a aparecer aqui; só o administrador pode alterá-las.
                @endif
            </x-tip-card>

            <!-- Filtros -->
            <x-filtros-pauta-card
                titulo="Filtros de lançamento"
                :form-action="$urlFolha"
                :limpar-url="$urlFolha"
                botao-texto="Carregar alunos"
                instrucao="Seleccione ano lectivo, turma, disciplina e trimestre para carregar a lista de alunos."
                :resumo="($turmaId && $disciplinaId && ($turmaSeleccionada ?? null))
                    ? '<strong>'.e($turmaSeleccionada->nome).'</strong> · '.e($disciplinas->firstWhere('id', (int) $disciplinaId)?->nome).' · '.e($trimestre).'.º trim. · '.e($anosLectivos[$anoLectivoId] ?? '')
                    : null"
            >
                <input type="hidden" name="modo" value="folha">
                <div class="col-sm-6 col-lg-3">
                    <x-ano-lectivo-selector
                        :anoLectivoId="$anoLectivoId"
                        :anosLectivos="$anosLectivos"
                        size="sm"
                        label="Ano Lectivo"
                        class="mb-0"
                    />
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="turma_id" class="form-label">
                        <i class="bi bi-people me-1"></i>Turma <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="turma_id" name="turma_id" required
                            onchange="document.getElementById('disciplina_id').value=''; this.form.submit();">
                        <option value="">— Seleccione —</option>
                        @foreach($turmas as $turma)
                            <option value="{{ $turma->id }}" {{ $turmaId == $turma->id ? 'selected' : '' }}>
                                {{ $turma->nome }} · {{ $turma->curso->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="disciplina_id" class="form-label">
                        <i class="bi bi-journal-text me-1"></i>Disciplina <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="disciplina_id" name="disciplina_id" required
                            {{ !$turmaId ? 'disabled' : '' }}>
                        <option value="">{{ $turmaId ? '— Seleccione —' : 'Seleccione a turma primeiro' }}</option>
                        @foreach($disciplinas as $disciplina)
                            <option value="{{ $disciplina->id }}" {{ $disciplinaId == $disciplina->id ? 'selected' : '' }}>
                                {{ $disciplina->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="trimestre" class="form-label">
                        <i class="bi bi-calendar-week me-1"></i>Trimestre <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="trimestre" name="trimestre" required
                            title="{{ !auth()->user()->podeIgnorarTrimestreFechado() ? 'Apenas o trimestre actual está disponível para lançamento' : '' }}">
                        @for($t = 1; $t <= 3; $t++)
                            @php
                                $status = $trimestresStatus[$t] ?? ['aberto' => true, 'actual' => false];
                                $isAberto = $status['aberto'];
                                $isActual = $status['actual'];
                                $ignoraTrimestreFechado = auth()->user()->podeIgnorarTrimestreFechado();
                                $disabled = !$ignoraTrimestreFechado && !$isAberto;
                                $label = "{$t}.º Trimestre";
                                if ($isActual) {
                                    $label .= ' · Actual';
                                }
                                if (!$isAberto && !$ignoraTrimestreFechado) {
                                    $label .= ' · Fechado';
                                }
                            @endphp
                            <option value="{{ $t }}"
                                    {{ $trimestre == $t ? 'selected' : '' }}
                                    {{ $disabled ? 'disabled' : '' }}>
                                {{ $label }}
                            </option>
                        @endfor
                    </select>
                </div>
            </x-filtros-pauta-card>

            @if(isset($trimestreActual))
                <p class="text-muted small mb-4 mt-n3 ms-1">
                    <i class="bi bi-calendar3 me-1"></i>Trimestre actual no sistema: <strong>{{ $trimestreActual }}.º</strong>
                </p>
            @endif

            {{-- Alerta de Trimestre Fechado --}}
            @if(isset($trimestreFechado) && $trimestreFechado)
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-lock-fill me-2"></i>
                    <strong>Trimestre Fechado:</strong> O {{ $trimestre }}.º trimestre não está aberto para lançamento de notas.
                    @if(isset($trimestreActual) && $trimestreActual != $trimestre)
                        <br>
                        <small><i class="bi bi-info-circle me-1"></i>O trimestre actual configurado no sistema é o <strong>{{ $trimestreActual }}.º trimestre</strong>.</small>
                    @endif
                    <br>
                    <small>Pode consultar as notas existentes, mas não pode lançar ou editar notas.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Formulário de Notas -->
            @if($alunos->isNotEmpty())
                <div class="card shadow-sm">
                    <div class="card-header bg-success bg-opacity-10">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Lançar Notas - {{ $alunos->count() }} aluno(s)
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('notas.lancamento-lote.store') }}" method="POST" id="lancamentoForm">
                            @csrf
                            <input type="hidden" name="modo" value="folha">
                            <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">
                            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
                            <input type="hidden" name="trimestre" value="{{ $trimestre }}">

                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 5%">#</th>
                                            <th style="width: 35%">Aluno</th>
                                            <th style="width: 20%" class="text-center">
                                                MAC
                                                <x-help-icon type="tooltip" content="Média de Avaliação Contínua (0-20)" />
                                            </th>
                                            <th style="width: 20%" class="text-center">
                                                PP
                                                <x-help-icon type="tooltip" content="Prova dos Professores (0-20)" />
                                            </th>
                                            @if((int) $trimestre !== 3)
                                            <th style="width: 20%" class="text-center">
                                                PT
                                                <x-help-icon type="tooltip" content="Prova Trimestral (0-20)" />
                                            </th>
                                            @else
                                            <th style="width: 20%" class="text-center">
                                                PG
                                                <x-help-icon type="tooltip" content="Prova Global (0-20)" />
                                            </th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alunos as $aluno)
                                            <tr>
                                                <td>{{ $aluno->numero_ordem }}</td>
                                                <td>
                                                    <strong>{{ $aluno->nome }}</strong>
                                                    <input type="hidden" name="notas[{{ $loop->index }}][aluno_id]" value="{{ $aluno->id }}">
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           class="form-control form-control-sm"
                                                           name="notas[{{ $loop->index }}][mac]"
                                                           value="{{ old("notas.{$loop->index}.mac", $aluno->nota_mac) }}"
                                                           min="0"
                                                           max="20"
                                                           step="0.5"
                                                           placeholder="0-20"
                                                           {{ ((isset($trimestreFechado) && $trimestreFechado) || ($modoConsulta ?? false)) ? 'disabled' : '' }}>
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           class="form-control form-control-sm"
                                                           name="notas[{{ $loop->index }}][pp]"
                                                           value="{{ old("notas.{$loop->index}.pp", $aluno->nota_pp) }}"
                                                           min="0"
                                                           max="20"
                                                           step="0.5"
                                                           placeholder="0-20"
                                                           {{ ((isset($trimestreFechado) && $trimestreFechado) || ($modoConsulta ?? false)) ? 'disabled' : '' }}>
                                                </td>
                                                @if((int) $trimestre !== 3)
                                                <td>
                                                    <input type="number"
                                                           class="form-control form-control-sm"
                                                           name="notas[{{ $loop->index }}][pt]"
                                                           value="{{ old("notas.{$loop->index}.pt", $aluno->nota_pt) }}"
                                                           min="0"
                                                           max="20"
                                                           step="0.5"
                                                           placeholder="0-20"
                                                           {{ ((isset($trimestreFechado) && $trimestreFechado) || ($modoConsulta ?? false)) ? 'disabled' : '' }}>
                                                </td>
                                                @else
                                                <td>
                                                    <input type="number"
                                                           class="form-control form-control-sm"
                                                           name="notas[{{ $loop->index }}][pg]"
                                                           value="{{ old("notas.{$loop->index}.pg", $aluno->nota_pg) }}"
                                                           min="0"
                                                           max="20"
                                                           step="0.5"
                                                           placeholder="0-20"
                                                           {{ ((isset($trimestreFechado) && $trimestreFechado) || ($modoConsulta ?? false)) ? 'disabled' : '' }}>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Instruções:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Preencha as notas dos alunos avaliados (0 a 20)</li>
                                    <li><strong>Deixe vazio</strong> para alunos não avaliados — remove a nota existente (ex.: desistente ou lançamento por engano)</li>
                                    <li>Não use 0 se o aluno não foi avaliado; 0 é nota zero, campo vazio é sem avaliação</li>
                                    @if((int) $trimestre === 3)
                                        <li>No 3.º trimestre não há PT — use a coluna <strong>PG</strong> (Prova Global)</li>
                                    @endif
                                    <li>Use vírgula ou ponto para decimais (ex: 15.5 ou 15,5)</li>
                                </ul>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-lg"
                                        {{ ((isset($trimestreFechado) && $trimestreFechado) || ($modoConsulta ?? false)) ? 'disabled' : '' }}>
                                    <i class="bi bi-check-lg"></i> Lançar Todas as Notas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif($turmaId && $disciplinaId)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Nenhum aluno encontrado nesta turma.
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Selecione uma turma, disciplina e trimestre acima para começar.
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Confirmar antes de enviar
        document.getElementById('lancamentoForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const inputs = this.querySelectorAll('input[type="number"]');
            let preenchidos = 0;

            inputs.forEach(input => {
                if (input.value !== '') {
                    preenchidos++;
                }
            });

            if (preenchidos === 0) {
                showNotification('warning', 'Por favor, preencha pelo menos uma nota antes de enviar.', 'Atenção');
                return false;
            }

            const confirmed = await confirmAction({
                title: 'Guardar folha completa?',
                html: `<p>Tem a certeza que deseja lançar <strong>${preenchidos} nota(s)</strong>?</p>`,
                icon: 'question',
                confirmText: '<i class="bi bi-check-lg"></i> Sim, lançar notas'
            });

            if (confirmed) {
                showLoading('Lançando notas...');
                this.submit();
            }
        });
    </script>
    @endpush
</x-app-layout>
