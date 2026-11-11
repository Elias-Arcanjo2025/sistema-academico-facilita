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

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Não foi possível guardar:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Indicador de Modo Consulta --}}
            @if($modoConsulta)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo anterior selecionado. Lançamento de notas desabilitado.
                    <a href="{{ route('notas.lancar') }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Alerta de Trimestre Fechado --}}
            @if(isset($trimestreFechado) && $trimestreFechado)
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-lock-fill me-2"></i>
                    <strong>Trimestre Fechado:</strong> O {{ $trimestre }}.º trimestre está fechado para lançamento de notas.
                    @if(isset($trimestreActual) && $trimestreActual != $trimestre)
                        <br>
                        <small><i class="bi bi-info-circle me-1"></i>O trimestre atual configurado no sistema é o <strong>{{ $trimestreActual }}.º trimestre</strong>.</small>
                    @endif
                    <br>
                    Pode consultar as notas existentes, mas não pode lançar ou editar notas. Contacte a administração para mais informações.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @include('layouts.partials.assistente-contextual')

            @include('notas.partials.modo-lancamento')

            <x-tip-card icon="bi-pencil-square" variant="success" title="Lançar Notas — Por tipo" id="tip-notas-lancar">
                Seleccione a turma e disciplina para iniciar o lançamento.
                Nos 1.º e 2.º trimestres: <strong>MAC</strong>, <strong>PP</strong> e <strong>PT</strong>.
                No 3.º trimestre: <strong>MAC</strong>, <strong>PP</strong> e <strong>PG</strong> (Prova Global) — não existe PT3.
                No 3.º trimestre, professores podem também lançar <strong>Exame de Recuperação</strong> (10.ª/11.ª) ou
                <strong>Exame de Recurso</strong> (12.ª), apenas para alunos elegíveis na disciplina.
                O <strong>Exame de Melhoria de Notas</strong> é gerido pelos coordenadores de curso.
                A média trimestral (MT) é calculada automaticamente.
                Para preencher MAC, PP e PT/PG de uma vez, use <strong>Folha completa</strong>.
            </x-tip-card>

            <!-- Filtros -->
            <x-filtros-pauta-card
                titulo="Filtros de lançamento"
                :form-action="route('notas.lancar')"
                :limpar-url="route('notas.lancar')"
                botao-texto="Continuar"
                instrucao="Seleccione ano lectivo, turma/disciplina, trimestre e tipo de nota para começar."
                :resumo="($turmaId && $disciplinaId && ($turma ?? null) && ($disciplina ?? null))
                    ? e($turma->nome).' · '.e($disciplina->nome).' · '.e($trimestre).'.º trim. · '.e($tipos[$tipo] ?? $tipo)
                    : null"
            >
                <input type="hidden" name="modo" value="tipo">
                <div class="col-sm-6 col-lg-3">
                    <x-ano-lectivo-selector
                        :anoLectivoId="$anoLectivoId"
                        :anosLectivos="$anosLectivos"
                        size="sm"
                        label="Ano Lectivo"
                        class="mb-0"
                    />
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label for="associacao" class="form-label">
                        <i class="bi bi-mortarboard me-1"></i>Turma / Disciplina <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="associacao" onchange="updateSelects(this)">
                        <option value="">— Seleccione —</option>
                        @foreach($associacoes as $assoc)
                            <option value="{{ $assoc->turma_id }}-{{ $assoc->disciplina_id }}"
                                {{ ($turmaId == $assoc->turma_id && $disciplinaId == $assoc->disciplina_id) ? 'selected' : '' }}>
                                {{ $assoc->turma->nome }} · {{ $assoc->disciplina->nome }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="turma_id" id="turma_id" value="{{ $turmaId }}">
                    <input type="hidden" name="disciplina_id" id="disciplina_id" value="{{ $disciplinaId }}">
                    <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="trimestre_select" class="form-label">
                        <i class="bi bi-calendar-week me-1"></i>Trimestre <span class="text-danger">*</span>
                    </label>
                    @if($modoConsulta)
                        <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                    @endif
                    <select class="form-select form-select-sm" id="trimestre_select" name="trimestre"
                            onchange="this.form.submit()" {{ $modoConsulta ? 'disabled' : '' }}
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
                <div class="col-sm-6 col-lg-3">
                    <label for="tipo_select" class="form-label">
                        <i class="bi bi-pencil-square me-1"></i>Tipo de Nota <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="tipo_select" name="tipo" onchange="this.form.submit()">
                        @foreach($tipos as $key => $label)
                            @php
                                $mostrarTipo = \App\Support\NotaLancamentoRules::tipoVisivelNoTrimestre(
                                    $key,
                                    (int) $trimestre,
                                    $usaProvaTrimestral ?? true,
                                );
                            @endphp
                            @if($mostrarTipo)
                                <option value="{{ $key }}" {{ $tipo == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </x-filtros-pauta-card>

            @if(isset($trimestreActual))
                <p class="text-muted small mb-4 mt-n3 ms-1">
                    <i class="bi bi-calendar3 me-1"></i>Trimestre actual no sistema: <strong>{{ $trimestreActual }}.º</strong>
                </p>
            @endif

            @if($turmaId && $disciplinaId && ($alunos->isNotEmpty() || ($modoExameEspecial ?? false)))
                @if($modoExameEspecial ?? false)
                    @php
                        $totalElegiveisExame = collect($elegibilidadeExame)->filter(fn ($e) => $e['elegivel_formal'] ?? $e['elegivel'] ?? false)->count();
                    @endphp
                    <div class="alert alert-info">
                        @if($tipo === \App\Models\Nota::TIPO_RECUPERACAO)
                            <strong>Exame de Recuperação</strong> — alunos elegíveis podem lançar nota que substitui a CFD/CA.
                            Campo vazio remove a nota activa (histórico mantido).
                        @else
                            <strong>Exame de Recurso</strong> — alunos elegíveis podem lançar nota que substitui a CFD anterior.
                            Campo vazio remove a nota activa.
                        @endif
                        @if($permiteLancamentoSemElegibilidade ?? false)
                            <br><small><i class="bi bi-info-circle me-1"></i>
                                A escola permite lançamento <strong>fora da elegibilidade formal</strong> — estes casos ficam marcados no histórico.</small>
                        @endif
                        @if($totalElegiveisExame === 0 && !($permiteLancamentoSemElegibilidade ?? false))
                            <br><small class="text-muted">Nenhum aluno elegível nesta disciplina — consulte a pauta T3 ou a
                                <a href="{{ route('exames.elegibilidade', array_filter(['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => 3])) }}">lista de elegibilidade</a>.</small>
                        @else
                            <br><small class="text-muted">{{ $totalElegiveisExame }} aluno(s) elegível(eis) para lançamento nesta disciplina.</small>
                        @endif
                    </div>
                @elseif((int) $trimestre === 3)
                    <div class="alert alert-info">
                        No 3.º trimestre (pauta final) não existe PT3. Lance <strong>MAC3</strong>, <strong>PP3</strong> e
                        <strong>Prova Global (PG)</strong>; a PG entra na fórmula CA = 0,6×CF + 0,4×PG.
                    </div>
                @endif
                @if($alunos->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            {{ $turma->nome }} - {{ $disciplina->nome }}
                            <span class="badge bg-secondary">{{ $trimestre }}.o Trimestre</span>
                            <span class="badge bg-info">{{ $tipos[$tipo] }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('notas.store') }}" method="POST" data-atalho-guardar>
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
                            <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                            <input type="hidden" name="tipo" value="{{ $tipo }}">

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Nome do Aluno</th>
                                            @if($modoExameEspecial ?? false)
                                                <th style="min-width: 220px;">{{ $rotuloReferenciaExame ?? 'CA/CFD' }} → Exame</th>
                                            @else
                                                <th style="width: 150px;">Nota (0-20)</th>
                                            @endif
                                            <th style="width: 140px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alunos as $aluno)
                                            @php
                                                $notaExistente = $notasExistentes[$aluno->id] ?? null;
                                                $notaPosConselho = $notasPosConselho[$aluno->id] ?? null;
                                                $bloqueadaPosConselho = $notaExistente === null && $notaPosConselho !== null;
                                                $dadosExame = $elegibilidadeExame[$aluno->id] ?? null;
                                                $podeLancarExame = $dadosExame['pode_lancar'] ?? false;
                                                $refExame = $dadosExame['referencia'] ?? null;
                                                $rotuloRef = $dadosExame['rotulo_referencia'] ?? ($rotuloReferenciaExame ?? 'CA');
                                                $inputDesactivado = $modoConsulta
                                                    || (isset($trimestreFechado) && $trimestreFechado)
                                                    || $bloqueadaPosConselho
                                                    || (($modoExameEspecial ?? false) && ! $podeLancarExame);
                                            @endphp
                                            <tr class="{{ ($modoExameEspecial ?? false) && ($dadosExame['fora_elegibilidade'] ?? false) ? 'table-warning' : '' }}">
                                                <td>{{ $aluno->numero_ordem }}</td>
                                                <td>{{ $aluno->nome }}</td>
                                                <td>
                                                    @if($modoExameEspecial ?? false)
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <div class="text-center border rounded px-2 py-1 bg-light" style="min-width: 72px;">
                                                                <small class="text-muted d-block lh-1" style="font-size:.7rem;">{{ $rotuloRef }}</small>
                                                                <span class="fw-bold {{ $refExame !== null && $refExame < 10 ? 'text-danger' : ($refExame !== null ? 'text-success' : 'text-muted') }}">
                                                                    {{ $refExame !== null ? number_format($refExame, 0, ',', '') : '—' }}
                                                                </span>
                                                            </div>
                                                            <i class="bi bi-arrow-right text-muted"></i>
                                                            <input type="text"
                                                                class="form-control form-control-sm nota-input"
                                                                name="notas[{{ $aluno->id }}]"
                                                                value="{{ old('notas.' . $aluno->id, $notaExistente !== null ? number_format($notaExistente, 1, '.', '') : '') }}"
                                                                inputmode="decimal"
                                                                autocomplete="off"
                                                                placeholder="Exame"
                                                                style="max-width: 90px;"
                                                                data-estado-badge="estado-nota-{{ $aluno->id }}"
                                                                {{ $inputDesactivado ? 'disabled' : '' }}>
                                                        </div>
                                                    @else
                                                    <input type="text"
                                                        class="form-control form-control-sm nota-input"
                                                        name="notas[{{ $aluno->id }}]"
                                                        value="{{ old('notas.' . $aluno->id, $notaExistente !== null ? number_format($notaExistente, 1, '.', '') : '') }}"
                                                        inputmode="decimal"
                                                        autocomplete="off"
                                                        placeholder="—"
                                                        data-estado-badge="estado-nota-{{ $aluno->id }}"
                                                        {{ $inputDesactivado ? 'disabled' : '' }}>
                                                    @endif
                                                </td>
                                                <td id="estado-nota-{{ $aluno->id }}">
                                                    @if(($modoExameEspecial ?? false) && ($dadosExame['fora_elegibilidade'] ?? false))
                                                        <span class="badge bg-warning text-dark" title="{{ $dadosExame['motivo'] ?? '' }}">Fora elegibilidade</span>
                                                    @elseif(($modoExameEspecial ?? false) && ! $podeLancarExame)
                                                        <span class="badge bg-secondary" title="{{ $dadosExame['motivo'] ?? '' }}">Bloqueado</span>
                                                    @elseif($notaExistente !== null)
                                                        <span class="badge {{ $notaExistente >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                            Lançada: {{ number_format($notaExistente, 1, ',', '') }}
                                                        </span>
                                                    @elseif($bloqueadaPosConselho)
                                                        <span class="badge bg-warning text-dark" title="Nota confirmada no conselho de classe — não pode ser removida aqui">
                                                            Pós-conselho: {{ number_format($notaPosConselho, 1, ',', '') }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Pendente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Deixe o campo <strong>vazio</strong> para remover uma nota já lançada (pré-conselho) ou para não lançar nessa avaliação.
                                Notas com valor serão criadas ou actualizadas.
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" {{ ($modoConsulta || (isset($trimestreFechado) && $trimestreFechado)) ? 'disabled' : '' }}>
                                    <i class="bi bi-check-lg"></i> Guardar Notas
                                </button>
                                <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
                @elseif($modoExameEspecial ?? false)
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Nenhum aluno activo encontrado nesta turma.
                    </div>
                @endif
            @elseif($turmaId && $disciplinaId)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Nenhum aluno ativo encontrado nesta turma.
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Selecione uma turma/disciplina, trimestre e tipo de nota para começar a lançar.
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function updateSelects(select) {
            const value = select.value;
            if (value) {
                const parts = value.split('-');
                document.getElementById('turma_id').value = parts[0];
                document.getElementById('disciplina_id').value = parts[1];
                select.form.submit();
            }
        }

        // Validação visual das notas e actualização do badge de estado
        document.querySelectorAll('.nota-input').forEach(input => {
            const actualizarEstado = () => {
                const badgeContainer = document.getElementById(input.dataset.estadoBadge);
                if (!badgeContainer || input.disabled) {
                    return;
                }

                const raw = input.value.trim().replace(',', '.');
                if (raw === '') {
                    badgeContainer.innerHTML = '<span class="badge bg-secondary">Pendente</span>';
                    input.classList.remove('is-invalid', 'text-danger', 'text-success');
                    return;
                }

                const value = parseFloat(raw);
                if (Number.isNaN(value) || value < 0 || value > 20) {
                    input.classList.add('is-invalid');
                    input.classList.remove('text-danger', 'text-success');
                    return;
                }

                input.classList.remove('is-invalid');
                const badgeClass = value >= 10 ? 'bg-success' : 'bg-danger';
                const display = raw.replace('.', ',');
                badgeContainer.innerHTML = `<span class="badge ${badgeClass}">A guardar: ${display}</span>`;
                input.classList.toggle('text-danger', value < 10);
                input.classList.toggle('text-success', value >= 10);
            };

            input.addEventListener('input', actualizarEstado);
        });
    </script>
    @endpush
</x-app-layout>
