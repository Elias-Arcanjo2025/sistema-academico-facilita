@php
    $momentoAtual = $momento_conselho ?? \App\Support\PautaMomentoConselho::POS;
    $momentoLabel = $momento_conselho_label ?? \App\Support\PautaMomentoConselho::rotulo($momentoAtual);
    $paramsPauta = fn (string $momento) => [
        'turma_id' => $turma->id,
        'trimestre' => $trimestre,
        'ano_lectivo' => $ano_lectivo,
        'momento' => $momento,
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Pauta de Aproveitamento — {{ $trimestre }}.º Trimestre</h2>
                <small class="text-muted">
                    {{ $turma->nome }} ({{ $turma->classe->nome ?? '-' }}) — {{ $turma->curso->nome }} | Ano Lectivo {{ $ano_lectivo }}
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if(!empty($podeAprovarPauta))
                    @if(($pautaAprovacao ?? null)?->estaAprovada())
                        <span class="badge bg-success">Pauta aprovada</span>
                        <form method="POST" action="{{ route('pautas.reabrir') }}">
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                            <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                            <input type="hidden" name="ano_lectivo" value="{{ $ano_lectivo }}">
                            <button type="submit" class="btn btn-outline-warning btn-sm">Reabrir aprovação</button>
                        </form>
                    @else
                        <span class="badge bg-secondary">Aguarda aprovação</span>
                        <form method="POST" action="{{ route('pautas.aprovar') }}">
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                            <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                            <input type="hidden" name="ano_lectivo" value="{{ $ano_lectivo }}">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check2-circle"></i> Aprovar pauta
                            </button>
                        </form>
                    @endif
                @elseif(($pautaAprovacao ?? null)?->estaAprovada())
                    <span class="badge bg-success">Pauta aprovada</span>
                @endif
                <div class="btn-group">
                <a href="{{ route('pautas.index', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <a href="{{ route('export.pauta-trimestral-completa.excel', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo, 'momento' => $momentoAtual]) }}"
                   class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Excel ({{ $momentoLabel }})
                </a>
                @if((int) $trimestre === 3 && in_array((int) ($classe_numero ?? 10), [10, 11, 12], true))
                    <a href="{{ route('export.lista-transicao-turma.pdf', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo]) }}"
                       class="btn btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf"></i> Listas por resultado (PDF)
                    </a>
                @endif
            </div>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid pauta-completa" style="{{ \App\Support\PautaCores::cssVariablesStyle() }}">
            <div class="mb-4">
            <x-tip-card icon="bi-file-earmark-spreadsheet" variant="success" title="Pauta de Aproveitamento Trimestral" id="tip-pauta-trim">
                Pré-visualização da pauta oficial do trimestre. Carrega em <strong>Descarregar Excel</strong> para obter o documento no formato oficial da escola
                (logótipo, ÁREA + CURSO, professores por disciplina e assinaturas).
                @if($trimestre === 3 && in_array((int) ($classe_numero ?? 10), [10, 11, 12], true))
                    No ficheiro Excel, as colunas <strong>RESULTADO</strong> e <strong>OBSERVAÇÃO</strong> contêm fórmulas que actualizam automaticamente quando alterar notas.
                    Notas de <strong>recuperação</strong> (10.ª/11.ª) ou <strong>recurso</strong> (12.ª) substituem imediatamente a CA/CFD na pauta;
                    a nota original aparece riscada com indicação do tipo de exame, e as colunas <strong>RECUP.</strong> / <strong>RECURSO</strong> mostram a classificação do exame.
                @endif
            </x-tip-card>
            </div>

            <div class="card border-0 pauta-completa__toolbar">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h6 class="mb-1 fw-bold" style="color: var(--pauta-navy);">
                                <i class="bi bi-layers"></i> Versão da pauta
                            </h6>
                            <p class="text-muted small mb-0">
                                {{ \App\Support\PautaMomentoConselho::descricao($momentoAtual) }}
                            </p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="btn-group" role="group" aria-label="Versão da pauta">
                                <a href="{{ route('pautas.trimestral-completa', $paramsPauta(\App\Support\PautaMomentoConselho::PRE)) }}"
                                   class="btn btn-sm {{ $momentoAtual === \App\Support\PautaMomentoConselho::PRE ? 'btn-warning' : 'btn-outline-warning' }}">
                                    <i class="bi bi-clock-history"></i> Pré-conselho
                                </a>
                                <a href="{{ route('pautas.trimestral-completa', $paramsPauta(\App\Support\PautaMomentoConselho::POS)) }}"
                                   class="btn btn-sm {{ $momentoAtual === \App\Support\PautaMomentoConselho::POS ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="bi bi-check2-circle"></i> Pós-conselho
                                </a>
                            </div>
                            <a href="{{ route('export.pauta-trimestral-completa.excel', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo, 'momento' => $momentoAtual]) }}"
                               class="btn btn-sm btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Excel
                            </a>
                        </div>
                    </div>
                    @if(!empty($conselho_estado))
                        <div class="mt-2 small text-muted">
                            <i class="bi bi-people"></i> Conselho de classe:
                            @if($conselho_estado === 'pendente')
                                <span class="badge bg-secondary">Pendente</span>
                            @elseif($conselho_estado === 'em_curso')
                                <span class="badge bg-warning text-dark">Em curso</span>
                            @elseif($conselho_estado === 'concluido')
                                <span class="badge bg-success">Concluído</span>
                                @if(!empty($data_conselho))
                                    — {{ $data_conselho }}
                                @endif
                            @endif
                            <a href="{{ route('conselhos.index', ['turma_id' => $turma->id, 'ano_lectivo_id' => $turma->ano_lectivo_id]) }}" class="ms-2">Abrir conselho</a>
                        </div>
                    @endif
                </div>
            </div>

            @php
                // Esquema dinâmico de colunas — pode variar por disciplina (ex.: RECURSO só onde há recurso)
                $colunasPorDisc = $colunas_por_disciplina ?? [];
                $colsDisc = $colunas_disc ?? \App\Services\PautaService::esquemaColunasDisciplina(
                    (int) $trimestre,
                    (int) ($classe_numero ?? 10),
                    (bool) ($tem_recurso ?? false),
                    (bool) ($tem_terminal_classe_atual ?? false)
                );
                $nCols = count($colsDisc);
                $colsParaDisc = fn ($discId) => $colunasPorDisc[$discId] ?? $colsDisc;
                $termAtualIds = $disciplinas_terminais_classe_atual_ids ?? [];
                // Detectar grupo FALTAS para o cabeçalho (J|I separados vs coluna única na 12.ª)
                $temFaltas = collect($colsDisc)->contains(fn ($c) => ($c['group'] ?? null) === 'FALTAS');
                $temFaltasSeparadas = collect($colsDisc)->contains(
                    fn ($c) => ($c['group'] ?? null) === 'FALTAS' && (int) ($c['merged'] ?? 2) > 1
                );
                $colunasSemFaltas = collect($colsDisc)->filter(fn ($c) => ($c['group'] ?? null) !== 'FALTAS')->values()->all();

                // CFDs de disciplinas terminais anteriores (carry-over para T3 11ª/12ª — só politécnico)
                $cfdTerm = $disciplinas_terminais_anteriores ?? [];

                // Coluna MÉDIA do aluno (regime administração no T3)
                $temColMedia = (bool) ($tem_coluna_media_aluno ?? false);

                $labelOk = $trimestre === 3 ? 'APROVADO' : 'APTO';
                $labelKo = $trimestre === 3 ? 'REPROVADO' : 'NÃO APTO';

                $regimePauta = $turma->curso?->regime();
                $classeNum = (int) ($classe_numero ?? 10);
                $usaRegrasTransicaoPauta = (int) $trimestre === 3
                    && in_array($classeNum, [10, 11, 12], true);
                $cursoClasseMax = (int) ($turma->curso?->classe_max ?? 12);
                $chaveResDisc = function (int $discId) use ($regimePauta, $trimestre, $classe_numero, $colsParaDisc, $usaRegrasTransicaoPauta) {
                    if (! $regimePauta) {
                        return (int) $trimestre === 3 ? 'ca' : 'mt';
                    }

                    return $regimePauta->chaveColunaResultado(
                        (int) $trimestre,
                        (int) ($classe_numero ?? 10),
                        $colsParaDisc($discId)
                    );
                };

                $resumoResultados = collect($alunos)->groupBy(function ($a) {
                    if (! empty($a['desistente'])) {
                        return 'DESISTENTE';
                    }
                    $r = trim((string) ($a['resultado'] ?? ''));
                    return $r !== '' ? $r : 'Pendente';
                })->map->count();

                $ordemResumo = \App\Services\ListaTransicaoTurmaService::gruposOrdem($classeNum);
                $resumoResultados = collect($ordemResumo)
                    ->mapWithKeys(fn ($g) => [$g => (int) ($resumoResultados[$g] ?? 0)])
                    ->merge($resumoResultados->except($ordemResumo));
            @endphp

            @if($usaRegrasTransicaoPauta)
                <div class="card pauta-resumo">
                    <div class="card-body">
                        <div class="pauta-resumo__hero">
                            <div>
                                <h6 class="mb-2 fw-bold" style="color: var(--pauta-navy);">
                                    <i class="bi bi-journal-check"></i>
                                    Condições de Transição de Classe
                                    <span class="text-muted fw-normal">(DE 424/25)</span>
                                </h6>
                                <span class="text-muted small">
                                    {{ $classeNum }}ª classe · {{ $turma->curso->nome ?? '' }}
                                    @if(!empty($data_conselho))
                                        · Conselho: <strong>{{ $data_conselho }}</strong>
                                    @endif
                                </span>
                            </div>
                            <div class="pauta-resumo__hero-actions">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-file-earmark-pdf"></i> Listas por resultado
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item fw-semibold"
                                               href="{{ route('export.lista-transicao-turma.pdf', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo]) }}">
                                                <i class="bi bi-file-earmark-pdf text-danger"></i> PDF — todas as listas
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item"
                                               href="{{ route('export.lista-transicao-turma.excel', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo]) }}">
                                                <i class="bi bi-file-earmark-spreadsheet text-success"></i> Excel — todas as listas
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        @foreach(\App\Services\ListaTransicaoTurmaService::gruposOrdem($classeNum) as $grupo)
                                            @php $qtdGrupo = $resumoResultados[$grupo] ?? 0; @endphp
                                            <li>
                                                <a class="dropdown-item {{ $qtdGrupo === 0 ? 'disabled text-muted' : '' }}"
                                                   href="{{ $qtdGrupo > 0 ? route('export.lista-transicao-turma.pdf', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo, 'resultado' => $grupo]) : '#' }}">
                                                    <i class="bi bi-file-earmark-pdf text-danger"></i> {{ $grupo }}
                                                    <span class="badge bg-secondary float-end">{{ $qtdGrupo }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="pauta-resumo__stats" aria-label="Resumo por resultado">
                            @foreach($resumoResultados as $rotulo => $qtd)
                                @php
                                    $statClass = match (true) {
                                        $rotulo === 'TRANSITA', $rotulo === 'APTO' => 'is-ok',
                                        str_starts_with($rotulo, 'TRANSITA/C'),
                                        $rotulo === 'RECURSO',
                                        $rotulo === 'RECUPERAÇÃO' => 'is-exam',
                                        str_starts_with($rotulo, 'N/') => 'is-ko',
                                        default => 'is-muted',
                                    };
                                    $exportGrupo = str_starts_with($rotulo, 'TRANSITA/C') ? 'TRANSITA/C DEF' : $rotulo;
                                @endphp
                                @if($qtd > 0)
                                    <a href="{{ route('export.lista-transicao-turma.pdf', ['turma_id' => $turma->id, 'trimestre' => $trimestre, 'ano_lectivo' => $ano_lectivo, 'resultado' => $exportGrupo]) }}"
                                       class="pauta-resumo__stat {{ $statClass }}"
                                       title="Descarregar PDF — {{ $rotulo }}">
                                        <span>{{ $rotulo }}</span>
                                        <strong>{{ $qtd }}</strong>
                                    </a>
                                @else
                                    <span class="pauta-resumo__stat {{ $statClass }}">
                                        <span>{{ $rotulo }}</span>
                                        <strong>{{ $qtd }}</strong>
                                    </span>
                                @endif
                            @endforeach
                        </div>

                        <div class="pauta-resumo__panels">
                            <div class="pauta-resumo__panel">
                                <strong>Resultado possível</strong>
                                @if($classeNum === 12)
                                    @php $maxRec12 = \App\Support\PautaResultadoTransicao::maxNegativasRecurso12(); @endphp
                                    <div class="pauta-resumo__rule"><span class="badge bg-primary">APTO</span> <span class="text-muted">0 negativas</span></div>
                                    <div class="pauta-resumo__rule"><span class="badge bg-warning text-dark">RECURSO</span> <span class="text-muted">1 a {{ $maxRec12 }}</span></div>
                                    <div class="pauta-resumo__rule"><span class="badge bg-danger">N/APTO</span> <span class="text-muted">&gt;{{ $maxRec12 }}</span></div>
                                @else
                                    @php $maxRec = \App\Support\PautaResultadoTransicao::maxNegativasRecuperacao(); @endphp
                                    <div class="pauta-resumo__rule"><span class="badge bg-primary">TRANSITA</span> <span class="text-muted">0 negativas</span></div>
                                    <div class="pauta-resumo__rule"><span class="badge bg-warning text-dark">TRANSITA/C DEF</span> <span class="text-muted">só contínuas (≤{{ $maxRec }})</span></div>
                                    <div class="pauta-resumo__rule"><span class="badge bg-warning text-dark">RECUPERAÇÃO</span> <span class="text-muted">terminais (≤{{ $maxRec }})</span></div>
                                    <div class="pauta-resumo__rule"><span class="badge bg-danger">N/TRANSITA</span> <span class="text-muted">&gt;{{ $maxRec }} ou 3+ continuidade</span></div>
                                @endif
                            </div>
                            <div class="pauta-resumo__panel">
                                <strong>O que conta como negativa</strong>
                                <ul>
                                    <li>Disciplinas de <strong>continuidade</strong> → CA &lt; 10</li>
                                    <li>Disciplinas <strong>terminais</strong> → CFD &lt; 10</li>
                                    @if($classeNum >= 11 && count($cfdTerm) > 0)
                                        <li>CFD de terminais anteriores
                                            ({{ collect($cfdTerm)->pluck('codigo')->filter()->implode(', ') }})
                                        </li>
                                    @endif
                                    <li>Colunas <strong>CA-10ª</strong> / <strong>CA-11ª</strong> não entram directamente no resultado</li>
                                </ul>
                            </div>
                            <div class="pauta-resumo__panel">
                                <strong>Regras complementares</strong>
                                <ul>
                                    <li><strong>Regra dos 7:</strong> CFD por média só se CA do ano terminal ≥ 7</li>
                                    <li>Disciplina <strong>sem professor</strong> no ano não conta</li>
                                    @if(in_array($classeNum, [11, 12], true))
                                        <li>Máx. <strong>5 exames</strong> em disciplinas terminais</li>
                                    @endif
                                    @if($classeNum === 12 && $cursoClasseMax >= 13)
                                        <li><strong>PAP</strong> (12.ª→13.ª): exige aprovação em todas as disciplinas</li>
                                    @endif
                                    <li>Observação: <em>Def.</em> (contínuas) · <em>Rec.</em> (terminais)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="pauta-resumo__legend">
                            <span><span class="fw-bold" style="color: var(--pauta-vermelho-nota);">Vermelho</span> = nota &lt; 10</span>
                            <span><span class="fw-bold" style="color: var(--pauta-azul-nota);">Azul</span> = nota ≥ 10</span>
                            <span><span class="pauta-substituicao-exame__badge badge">Rec.</span> = exame substitui CA/CFD</span>
                            <span><i class="bi bi-file-earmark-excel text-success"></i> Excel recalcula RESULTADO/OBS ao editar</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card pauta-grid-card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0 fw-bold" style="color: var(--pauta-navy);">
                                PAUTA DE APROVEITAMENTO - {{ $trimestre }}º TRIMESTRE
                                @if($trimestre === 3)
                                    <small class="text-muted ms-2">({{ $classe_numero ?? '?' }}ª classe)</small>
                                @endif
                                <span class="badge ms-2 {{ $momentoAtual === \App\Support\PautaMomentoConselho::PRE ? 'bg-warning text-dark' : 'bg-primary' }}">
                                    {{ $momentoLabel }}
                                </span>
                            </h5>
                            @if(! $usaRegrasTransicaoPauta)
                                <small class="text-muted">
                                    Resultado: <strong>{{ $labelKo }}</strong> se mais de 2 disciplinas tiverem
                                    {{ $trimestre === 3 ? 'CA/CFD' : 'MT' }} &lt; 10
                                </small>
                            @endif
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary">{{ count($alunos) }} alunos</span>
                            <span class="badge bg-info">{{ count($disciplinas) }} disciplinas</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="pauta-mobile-hint d-md-none px-3 py-2 small text-muted border-bottom">
                        <i class="bi bi-arrows-move me-1"></i>
                        Deslize horizontalmente para ver todas as disciplinas. Pode também ampliar o ecrã.
                    </div>
                    <div class="pauta-grid-wrap">
                        <table class="table table-bordered table-sm pauta-table">
                            <thead>
                                <tr class="text-center align-middle">
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" class="pauta-col-or">N.º<br>Ordem</th>
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" class="pauta-col-pr">N.º<br>Proc.</th>
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" class="pauta-col-nome">Nome Completo</th>
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" style="width: 40px;">Sexo</th>
                                    @foreach($cfdTerm as $dt)
                                        <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" style="width: 60px;" class="text-navy">
                                            <small>{{ $dt['codigo'] }}<br>CFD</small>
                                        </th>
                                    @endforeach
                                    @foreach($disciplinas as $disc)
                                        <th colspan="{{ count($colsParaDisc($disc->id)) }}" class="text-success fw-bold pauta-disc-head">
                                            {{ $disc->codigo ?: \Illuminate\Support\Str::limit($disc->nome, 12, '') }}
                                        </th>
                                    @endforeach
                                    @if($temColMedia)
                                        <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" style="width: 60px; background: var(--pauta-verde-palido);">MÉDIA</th>
                                    @endif
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" class="pauta-col-obs">Observação</th>
                                    <th rowspan="{{ $temFaltasSeparadas ? 3 : 2 }}" class="pauta-col-res">Resultado</th>
                                </tr>
                                <tr class="text-center align-middle">
                                    @foreach($disciplinas as $disc)
                                        @php $colsD = $colsParaDisc($disc->id); @endphp
                                        @foreach($colsD as $i => $colDef)
                                            @if(($colDef['group'] ?? null) === 'FALTAS')
                                                @if((int) ($colDef['merged'] ?? 2) === 1)
                                                    @php $fundoCol = \App\Support\PautaCores::fundoHex($colDef); @endphp
                                                    <th rowspan="{{ $temFaltasSeparadas ? 2 : 1 }}"
                                                        class="{{ $i === 0 ? 'pauta-disc-start' : '' }}"
                                                        style="font-size: 0.65rem;{{ $fundoCol ? ' background:'.$fundoCol.';' : '' }}">
                                                        <strong>{{ $colDef['header'] }}</strong>
                                                    </th>
                                                @elseif(($colsD[$i-1]['group'] ?? null) !== 'FALTAS')
                                                    <th colspan="2" class="{{ $i === 0 ? 'pauta-disc-start' : '' }}" style="font-size: 0.65rem;">FALTAS</th>
                                                @endif
                                            @else
                                                @php $fundoCol = \App\Support\PautaCores::fundoHex($colDef); @endphp
                                                <th rowspan="{{ $temFaltasSeparadas ? 2 : 1 }}"
                                                    class="{{ $i === 0 ? 'pauta-disc-start' : '' }}"
                                                    style="font-size: 0.65rem;{{ $fundoCol ? ' background:'.$fundoCol.';' : '' }}">
                                                    <strong>{{ $colDef['header'] }}</strong>
                                                </th>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </tr>
                                @if($temFaltasSeparadas)
                                    <tr class="text-center align-middle">
                                        @foreach($disciplinas as $disc)
                                            @foreach($colsParaDisc($disc->id) as $fi => $colDef)
                                                @if(($colDef['group'] ?? null) === 'FALTAS')
                                                    <th class="{{ $fi === 0 ? 'pauta-disc-start' : '' }}" style="font-size: 0.65rem;">{{ $colDef['header'] }}</th>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @forelse($alunos as $a)
                                    <tr>
                                        <td class="text-center fw-bold pauta-col-or">{{ $a['numero_ordem'] }}</td>
                                        <td class="text-center pauta-col-pr">{{ $a['numero_processo'] ?? '-' }}</td>
                                        <td class="pauta-col-nome">
                                            {{ $a['nome'] }}
                                            @if(!empty($a['educacao_especial']))
                                                <span class="badge bg-info ms-1" title="{{ $a['necessidades_especificas'] ?? 'Aluno público-alvo da Educação Especial' }}" data-bs-toggle="tooltip">
                                                    <i class="bi bi-universal-access"></i> NEE
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ strtoupper(substr($a['genero'] ?? '-', 0, 1)) }}</td>

                                        @foreach($cfdTerm as $dt)
                                            @php
                                                $cv = $a['cfd_terminais'][$dt['disciplina_id']] ?? null;
                                                $cvNeg = is_numeric($cv) && (float) $cv < 10;
                                            @endphp
                                            <td class="text-center fw-bold {{ $cvNeg ? 'pauta-nota-neg' : (is_numeric($cv) ? 'pauta-nota-pos' : '') }}">
                                                {{ is_numeric($cv) ? (int) round($cv) : '' }}
                                            </td>
                                        @endforeach

                                        @foreach($disciplinas as $disc)
                                            @php
                                                $d = $a['disciplinas'][$disc->id] ?? [];
                                                $discTerminalAtual = in_array($disc->id, $termAtualIds);
                                            @endphp
                                            @foreach($colsParaDisc($disc->id) as $ci => $colDef)
                                                @php
                                                    $val = $d[$colDef['key']] ?? null;
                                                    $isInt = ($colDef['tipo'] ?? '') === 'int';
                                                    $isNotaLanc = ($colDef['tipo'] ?? '') === 'nota';
                                                    $ehDesistAluno = !empty($a['desistente']);
                                                    $fundoCol = \App\Support\PautaCores::fundoHex($colDef);
                                                    $realce = $fundoCol !== null;
                                                    $colKey = $colDef['key'] ?? '';
                                                    $contaCorResultado = ($colDef['tipo'] ?? '') !== 'int'
                                                        && $colKey === $chaveResDisc($disc->id);
                                                    $neg = $contaCorResultado && is_numeric($val) && (float) $val < 10;
                                                    $display = $isInt
                                                        ? (($val ?? 0) > 0 ? (int) $val : '')
                                                        : (is_numeric($val) ? (int) round($val) : '');
                                                    $mostrarX = $ehDesistAluno && $isNotaLanc && $display === '';
                                                    $substExame = ($d['substituicao_exame'] ?? null)
                                                        && in_array($colKey, ['ca', 'cfd'], true);
                                                    $badgeExame = match ($d['substituicao_exame']['tipo'] ?? '') {
                                                        'recuperacao' => 'Rec.',
                                                        'recurso' => 'Recurso',
                                                        'melhoria' => 'Melh.',
                                                        'extraordinario' => 'Extr.',
                                                        default => '',
                                                    };
                                                    $notaClass = $contaCorResultado && ! $isInt && $neg
                                                        ? 'pauta-nota-neg'
                                                        : ($contaCorResultado && ! $isInt && $display !== '' ? 'pauta-nota-pos' : '');
                                                @endphp
                                                <td class="text-center {{ $realce ? 'fw-bold' : '' }} {{ $notaClass }} {{ $ci === 0 ? 'pauta-disc-start' : '' }}"
                                                    style="{{ $fundoCol ? 'background:'.$fundoCol.';' : '' }}{{ $mostrarX ? 'background-image:linear-gradient(to top right,transparent calc(50% - 0.5px),#000 calc(50% - 0.5px),#000 calc(50% + 0.5px),transparent calc(50% + 0.5px)),linear-gradient(to bottom right,transparent calc(50% - 0.5px),#000 calc(50% - 0.5px),#000 calc(50% + 0.5px),transparent calc(50% + 0.5px));' : '' }}"
                                                    @if($substExame)
                                                        title="{{ $d['substituicao_exame']['rotulo'] }}: {{ strtoupper($colKey) }} {{ $d['substituicao_exame']['nota_original'] }} → {{ $d['substituicao_exame']['nota_exame'] }} (efectiva {{ $d['substituicao_exame']['nota_efectiva'] }})"
                                                    @endif
                                                >
                                                    @if($substExame && $display !== '')
                                                        <div class="pauta-substituicao-exame">
                                                            <span>{{ $display }}</span>
                                                            <div class="pauta-substituicao-exame__meta">
                                                                <span class="text-decoration-line-through">{{ $d['substituicao_exame']['nota_original'] }}</span>
                                                                @if($badgeExame !== '')
                                                                    <span class="badge pauta-substituicao-exame__badge">{{ $badgeExame }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @else
                                                        {{ $display }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        @endforeach

                                        @if($temColMedia)
                                            @php
                                                $mediaA = $a['media_aluno'] ?? null;
                                                $mediaNeg = is_numeric($mediaA) && (float) $mediaA < 10;
                                            @endphp
                                            <td class="text-center fw-bold {{ $mediaNeg ? 'pauta-nota-neg' : (is_numeric($mediaA) ? 'pauta-nota-pos' : '') }}"
                                                style="background: var(--pauta-verde-palido);">
                                                {{ is_numeric($mediaA) ? rtrim(rtrim(number_format((float) $mediaA, 1), '0'), '.') : '' }}
                                            </td>
                                        @endif
                                        <td class="small text-start text-wrap pauta-col-obs">
                                            @if(!empty($a['observacao']))
                                                @php
                                                    $obsParts = array_map('trim', explode('|', (string) $a['observacao']));
                                                @endphp
                                                @foreach($obsParts as $i => $parte)
                                                    @if($i > 0)<span class="text-muted"> | </span>@endif
                                                    @if(str_starts_with($parte, 'Def.:'))
                                                        <span class="pauta-nota-neg">{{ $parte }}</span>
                                                    @elseif(str_starts_with($parte, 'Rec.:'))
                                                        <span class="text-warning-emphasis fw-semibold">{{ $parte }}</span>
                                                    @else
                                                        {{ $parte }}
                                                    @endif
                                                @endforeach
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold pauta-col-res">
                                            {{-- Cores conforme Decreto Executivo 424/25, Art.º 35 --}}
                                            @php
                                                $resu = (string) ($a['resultado'] ?? '');
                                                $ehDesist = !empty($a['desistente']) || strtoupper($resu) === 'DESISTENTE';
                                                $negativo = \App\Support\PautaResultadoTransicao::ehNApto($resu)
                                                    || str_starts_with($resu, 'REPROV')
                                                    || collect(['RETIDO', 'EXCLUÍDO', 'PERDEU O ANO'])
                                                        ->contains(fn ($k) => str_contains(strtoupper($resu), $k));
                                                $positivo = \App\Support\PautaResultadoTransicao::ehApto($resu);
                                                $exame = \App\Support\PautaResultadoTransicao::ehRecuperacao($resu)
                                                    || \App\Support\PautaResultadoTransicao::ehRecurso12($resu)
                                                    || \App\Support\PautaResultadoTransicao::ehTransitaComDeficiencia($resu);
                                            @endphp
                                            @if($ehDesist)
                                                <span class="badge bg-secondary">DESISTENTE</span>
                                            @elseif($exame)
                                                <span class="badge bg-warning text-dark">{{ $resu }}</span>
                                            @elseif($positivo)
                                                <span class="badge bg-primary">{{ $resu }}</span>
                                            @elseif($negativo)
                                                <span class="badge bg-danger">{{ $resu }}</span>
                                            @elseif($resu !== '')
                                                <span class="badge bg-secondary">{{ $resu }}</span>
                                            @else
                                                <span class="badge bg-secondary">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 4 + count($cfdTerm) + (count($disciplinas) * $nCols) + ($temColMedia ? 1 : 0) + 2 }}" class="text-center text-muted py-4">
                                            Sem alunos para apresentar.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($alunos) > 0)
                                <tfoot>
                                    <tr>
                                        <td colspan="{{ 4 + count($cfdTerm) }}" class="text-end fw-bold pauta-col-or" style="left: auto; position: static;">Professores:</td>
                                        @foreach($disciplinas as $disc)
                                            <td colspan="{{ count($colsParaDisc($disc->id)) }}" class="text-center fst-italic text-primary pauta-disc-start" style="font-size: 0.65rem;">
                                                {{ $professores[$disc->id] ?? '—' }}
                                            </td>
                                        @endforeach
                                        <td colspan="{{ ($temColMedia ? 1 : 0) + 2 }}"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row text-center small">
                        <div class="col-md-4">
                            <strong>O DIRECTOR DE TURMA</strong><br>
                            {{ $director_turma_nome ?? '____________________________' }}
                        </div>
                        <div class="col-md-4">
                            <strong>COORDENADOR DO CURSO</strong><br>
                            {{ $coordenador_curso_nome ?? '____________________________' }}
                        </div>
                        <div class="col-md-4">
                            <strong>O SUBDIRECTOR PEDAGÓGICO</strong><br>
                            {{ $subdirector_nome ?? '____________________________' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
