<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-list-check me-2"></i>Alunos sujeitos a exames
                </h2>
                <small class="text-muted">Recuperação anual (10.ª/11.ª) e Recurso (12.ª)</small>
            </div>
            <div class="d-flex gap-2">
                @if($visaoProfessor ?? false)
                    <a href="{{ route('notas.lancar', array_filter(['turma_id' => $turmaId, 'disciplina_id' => $disciplinaId, 'trimestre' => 3])) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-pencil-square"></i> Lançar Notas
                    </a>
                @else
                    <a href="{{ route('recuperacoes.index') }}" class="btn btn-outline-primary btn-sm">Recuperação</a>
                    <a href="{{ route('recursos.index') }}" class="btn btn-outline-danger btn-sm">Recurso</a>
                    <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary btn-sm">Pautas</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <x-tip-card icon="bi-info-circle" variant="info" title="{{ ($visaoProfessor ?? false) ? 'Elegibilidade na sua disciplina' : 'Regras de transição (T3 — 10.ª/11.ª)' }}">
                @if($visaoProfessor ?? false)
                    Lista de alunos da sua turma/disciplina sujeitos a <strong>exame de recuperação</strong> (10.ª/11.ª)
                    ou <strong>exame de recurso</strong> (12.ª). Os dados reflectem a pauta final (T3).
                @else
                    <strong>Disciplinas terminais</strong> (terminam nesta classe): nota &lt; 10 → exame de recuperação (máx. 2 negativas no total).<br>
                    <strong>Disciplinas contínuas</strong>: até <strong>2 deficiências</strong> (nota &lt; 10) — transitam com pendência; não vão a exame de recuperação.<br>
                    <strong>Reprovação directa</strong>: mais de 2 negativas no total ou 3+ em disciplinas de continuidade.<br>
                    <span class="text-muted">Só contam CAs com PG lançada (avaliação completa).</span>
                @endif
            </x-tip-card>

            <div class="row g-3 mb-4">
                @if($mostrarResumoTransicao)
                    <div class="col-md-3">
                        <div class="card border-warning h-100">
                            <div class="card-body text-center">
                                <div class="display-6 text-warning">{{ $stats['alunos_recuperacao_t3'] }}</div>
                                <small class="text-muted">Alunos c/ rec. terminal</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-secondary h-100">
                            <div class="card-body text-center">
                                <div class="display-6 text-secondary">{{ $stats['alunos_deficiencias'] }}</div>
                                <small class="text-muted">Alunos c/ deficiências</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger h-100">
                            <div class="card-body text-center">
                                <div class="display-6 text-danger">{{ $stats['alunos_reprova'] }}</div>
                                <small class="text-muted">Reprovação directa</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">{{ $stats['recuperacao_anual'] }}</div>
                                <small class="text-muted">Disciplinas p/ recuperar</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="col-md-4">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <div class="display-6 text-primary">{{ $stats['recuperacao_anual'] }}</div>
                                <small class="text-muted">Recuperação anual (10.ª/11.ª)</small>
                                <div class="small mt-1">{{ $stats['alunos_recuperacao_anual'] }} aluno(s)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-danger h-100">
                            <div class="card-body text-center">
                                <div class="display-6 text-danger">{{ $stats['recurso'] }}</div>
                                <small class="text-muted">Disciplinas em recurso</small>
                                <div class="small mt-1">{{ $stats['alunos_recurso'] }} aluno(s)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">{{ $stats['total'] }}</div>
                                <small class="text-muted">Total de registos</small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" id="form-filtros-elegibilidade" class="row g-3 align-items-end">
                        @unless($visaoProfessor ?? false)
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                <option value="todos" @selected($tipo === 'todos')>Todos (T3: resumo + detalhe)</option>
                                <option value="resumo_transicao" @selected($tipo === 'resumo_transicao')>Resumo por aluno</option>
                                <option value="recuperacao_terminal" @selected($tipo === 'recuperacao_terminal')>Recuperação anual</option>
                                <option value="recuperacao" @selected($tipo === 'recuperacao')>Recuperação anual (detalhe)</option>
                                <option value="recurso" @selected($tipo === 'recurso')>Recurso 12.ª</option>
                            </select>
                        </div>
                        @else
                            <input type="hidden" name="tipo" value="{{ $tipo }}">
                        @endunless
                        <div class="col-md-{{ ($visaoProfessor ?? false) ? '3' : '3' }}">
                            <label class="form-label">Turma</label>
                            <select name="turma_id" id="turma_id" class="form-select form-select-sm">
                                <option value="">Seleccione…</option>
                                @foreach($turmas as $t)
                                    <option value="{{ $t->id }}" @selected($turmaId === $t->id)>{{ $t->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3" id="col-disciplina-elegibilidade" @if(!($turmaId ?? null)) style="display:none" @endif>
                            <label class="form-label">Disciplina</label>
                            <select name="disciplina_id" id="disciplina_id" class="form-select form-select-sm">
                                @if(!($visaoProfessor ?? false))
                                    <option value="">Todas</option>
                                @endif
                                @foreach($disciplinasTurma ?? [] as $d)
                                    <option value="{{ $d->id }}" @selected($disciplinaId === $d->id)>{{ $d->codigo ?: $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        @unless($visaoProfessor ?? false)
                        <div class="col-md-2">
                            <label class="form-label">Trimestre</label>
                            <select name="trimestre" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @for($t = 1; $t <= 3; $t++)
                                    <option value="{{ $t }}" @selected($trimestre === $t)>{{ $t }}.º</option>
                                @endfor
                            </select>
                        </div>
                        @else
                            <input type="hidden" name="trimestre" value="3">
                        @endunless
                        <div class="col-md-2">
                            <label class="form-label">Ano lectivo</label>
                            <input type="text" name="ano_lectivo" class="form-control form-control-sm" value="{{ $anoLectivo }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            @if($mostrarResumoTransicao)
                @php
                    $listaResumo = $tipo === 'resumo_transicao' ? $resumoTransicao : $resumoFiltrado;
                @endphp
                <div class="card shadow-soft mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-bold">
                            Resumo T3 — {{ $turmaSelecionada?->nome ?? 'Turma' }} ({{ $classeNumero }}ª)
                        </span>
                        @if($tipo === 'todos' && $resumoFiltrado->isNotEmpty())
                            <small class="text-muted">Alunos com recuperação, deficiências ou reprovação</small>
                        @elseif($tipo === 'resumo_transicao')
                            <small class="text-muted">Todos os alunos da turma</small>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($listaResumo->isEmpty())
                            <div class="text-center text-muted py-5 px-3">
                                @if($stats['alunos_sem_avaliacao'] > 0 && $resumoTransicao->where('situacao', '!=', 'sem_avaliacao')->isEmpty())
                                    <p class="mb-2"><i class="bi bi-exclamation-circle text-warning"></i> Nenhum aluno com CA completa (CF + PG) lançada.</p>
                                    <p class="small mb-0">{{ $stats['alunos_sem_avaliacao'] }} aluno(s) ainda sem avaliação T3 finalizada na pauta.</p>
                                @else
                                    <p class="mb-2">Nenhum aluno sujeito a recuperação terminal ou reprovação directa.</p>
                                    <p class="small mb-0">Todos transitam ou ainda não têm CAs negativas com PG lançada.</p>
                                @endif
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>N.º</th>
                                            <th>Aluno</th>
                                            <th>Resultado</th>
                                            <th>Rec. terminal</th>
                                            <th>Def. contínuas</th>
                                            <th>Motivo reprova</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($listaResumo as $a)
                                            <tr class="{{ $a['reprova_directamente'] ? 'table-danger' : (count($a['terminais_recuperacao']) ? 'table-warning' : '') }}">
                                                <td class="fw-bold">{{ $a['numero_ordem'] ?? '—' }}</td>
                                                <td>{{ $a['nome'] }}</td>
                                                <td>
                                                    @if($a['resultado'] === 'REPROVADO' || $a['resultado'] === 'N/TRANSITA')
                                                        <span class="badge bg-danger">{{ $a['resultado'] }}</span>
                                                    @elseif(\App\Support\PautaResultadoTransicao::ehTransitaComDeficiencia($a['resultado'] ?? ''))
                                                        <span class="badge bg-warning text-dark">{{ $a['resultado'] }}</span>
                                                    @elseif($a['resultado'] === 'APROVADO' || $a['resultado'] === 'TRANSITA')
                                                        <span class="badge bg-primary">{{ $a['resultado'] }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @foreach($a['terminais_recuperacao'] as $tr)
                                                        <span class="badge bg-warning text-dark me-1">{{ $tr['disciplina_nome'] }} ({{ (int) round($tr['ca']) }})</span>
                                                    @endforeach
                                                    @if(!count($a['terminais_recuperacao'])) — @endif
                                                </td>
                                                <td>
                                                    @foreach($a['continuas_deficientes'] as $dc)
                                                        <span class="badge bg-secondary me-1">{{ $dc['disciplina_nome'] }} ({{ (int) round($dc['ca']) }})</span>
                                                    @endforeach
                                                    @if(!count($a['continuas_deficientes'])) — @endif
                                                </td>
                                                <td><small>{{ implode('; ', $a['motivos']) ?: '—' }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @elseif($turmaId && $trimestre === 3 && !in_array($classeNumero, [10, 11], true))
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-1"></i>
                    A turma seleccionada é da <strong>{{ $classeNumero }}ª</strong> classe. O resumo de transição (terminais vs contínuas) aplica-se às turmas da 10.ª e 11.ª. Para a 12.ª, use o filtro <strong>Recurso 12.ª</strong>.
                </div>
            @elseif(!$turmaId && ($trimestre === 3 || $trimestre === null))
                <div class="alert alert-light border mb-4">
                    <i class="bi bi-lightbulb me-1"></i>
                    Seleccione uma <strong>turma</strong> da 10.ª ou 11.ª para ver o resumo de transição T3 (recuperação terminal, deficiências e reprovações).
                </div>
            @endif

            @if($tipo !== 'resumo_transicao')
                <div class="card shadow-soft">
                    <div class="card-header fw-bold">
                        @if($visaoProfessor ?? false)
                            Alunos elegíveis — {{ $disciplinaSelecionada?->nome ?? 'disciplina' }}
                            @if($turmaSelecionada)
                                <span class="text-muted fw-normal">({{ $turmaSelecionada->nome }})</span>
                            @endif
                        @else
                            Detalhe por disciplina (exames a realizar)
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if(($visaoProfessor ?? false) && ! $turmaId)
                            <div class="text-center text-muted py-4 px-3">
                                <p class="mb-0">Seleccione a turma e a disciplina para ver os alunos elegíveis ao exame.</p>
                            </div>
                        @elseif(($visaoProfessor ?? false) && $turmaId && ! $disciplinaId)
                            <div class="text-center text-muted py-4 px-3">
                                <p class="mb-0">Seleccione a disciplina que lecciona nesta turma.</p>
                            </div>
                        @elseif($linhas->isEmpty())
                            <div class="text-center text-muted py-4 px-3">
                                @if($mostrarResumoTransicao && $resumoFiltrado->isNotEmpty())
                                    <p class="mb-0 small">Sem disciplinas terminais individuais listadas — consulte o resumo acima.</p>
                                @elseif($trimestre === 3)
                                    <p class="mb-1">Nenhuma disciplina terminal com CA &lt; 10 (com PG lançada).</p>
                                    <p class="small mb-0">Verifique se as CAs e PGs estão lançadas na pauta T3.</p>
                                @else
                                    <p class="mb-0">Nenhum registo com os filtros seleccionados.</p>
                                @endif
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Turma</th>
                                            <th>Cl.</th>
                                            <th>Trim.</th>
                                            <th>N.º</th>
                                            <th>Aluno</th>
                                            <th>Disciplina</th>
                                            <th>Nota ref.</th>
                                            <th>Detalhe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($linhas as $linha)
                                            <tr>
                                                <td>
                                                    @if($linha['tipo_exame'] === 'Recurso')
                                                        <span class="badge bg-danger">Recurso</span>
                                                    @elseif(in_array($linha['tipo_exame'], ['Recuperação terminal', 'Recuperação anual'], true))
                                                        <span class="badge bg-success">Rec. anual</span>
                                                    @else
                                                        <span class="badge bg-primary">Recuperação</span>
                                                    @endif
                                                </td>
                                                <td>{{ $linha['turma_nome'] }}</td>
                                                <td>{{ $linha['classe'] }}ª</td>
                                                <td>{{ $linha['trimestre'] }}º</td>
                                                <td class="fw-bold">{{ $linha['numero_ordem'] ?? '—' }}</td>
                                                <td>{{ $linha['aluno_nome'] }}</td>
                                                <td>{{ $linha['disciplina_nome'] }}</td>
                                                <td>
                                                    @if($linha['nota_referencia'] !== null)
                                                        <span class="text-danger fw-bold">{{ (int) round($linha['nota_referencia']) }}</span>
                                                    @else — @endif
                                                </td>
                                                <td><small class="text-muted">{{ $linha['detalhe'] ?? '—' }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const disciplinasPorTurma = @json($disciplinasPorTurma ?? []);
            const visaoProfessor = @json($visaoProfessor ?? false);
            const formFiltros = document.getElementById('form-filtros-elegibilidade');
            const turmaSelect = document.getElementById('turma_id');
            const disciplinaSelect = document.getElementById('disciplina_id');
            const colDisciplina = document.getElementById('col-disciplina-elegibilidade');
            const turmaInicial = parseInt(turmaSelect?.value || '0', 10);

            function preencherDisciplinas(turmaId, disciplinaSeleccionada) {
                if (!disciplinaSelect) return [];

                const lista = disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)] || [];
                disciplinaSelect.innerHTML = '';

                if (!visaoProfessor && lista.length !== 1) {
                    const todas = document.createElement('option');
                    todas.value = '';
                    todas.textContent = 'Todas';
                    disciplinaSelect.appendChild(todas);
                }

                lista.forEach((d) => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.nome;
                    if (disciplinaSeleccionada && String(d.id) === String(disciplinaSeleccionada)) {
                        opt.selected = true;
                    }
                    disciplinaSelect.appendChild(opt);
                });

                if (lista.length === 1) {
                    disciplinaSelect.value = String(lista[0].id);
                } else if (!disciplinaSeleccionada && visaoProfessor && lista.length > 0) {
                    disciplinaSelect.value = String(lista[0].id);
                }

                disciplinaSelect.disabled = lista.length === 0;
                if (colDisciplina) {
                    colDisciplina.style.display = turmaId ? '' : 'none';
                }

                return lista;
            }

            function carregarDisciplinasRemotas(turmaId) {
                return fetch(`{{ route('exames.disciplinas') }}?turma_id=${turmaId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((r) => r.json())
                    .then((lista) => {
                        disciplinasPorTurma[turmaId] = lista;
                        disciplinasPorTurma[String(turmaId)] = lista;
                        return lista;
                    })
                    .catch(() => []);
            }

            if (turmaSelect && disciplinaSelect) {
                turmaSelect.addEventListener('change', function () {
                    const turmaId = parseInt(this.value, 10);

                    if (!turmaId) {
                        disciplinaSelect.innerHTML = '';
                        disciplinaSelect.disabled = true;
                        if (colDisciplina) colDisciplina.style.display = 'none';
                        return;
                    }

                    const aplicar = (lista) => {
                        preencherDisciplinas(turmaId, null);
                        if (formFiltros) {
                            formFiltros.submit();
                        }
                    };

                    if (disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)]) {
                        aplicar(disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)]);
                    } else {
                        carregarDisciplinasRemotas(turmaId).then(aplicar);
                    }
                });

                disciplinaSelect.addEventListener('change', function () {
                    if (formFiltros && this.value) {
                        formFiltros.submit();
                    }
                });

                if (turmaInicial) {
                    preencherDisciplinas(turmaInicial, disciplinaSelect.value || null);
                }
            }
        });
    </script>
    @endpush
</x-app-layout>
