<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-clipboard-data me-2 text-primary"></i>
                Conselho de Classe - {{ $conselho->turma->nome }} ({{ $conselho->trimestre }}º Trimestre)
            </h2>
            <a href="{{ route('conselhos.index', ['turma_id' => $conselho->turma_id, 'ano_lectivo_id' => $conselho->ano_lectivo_id]) }}"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4 conselho-show-page">
        <div class="container-fluid">
            <x-breadcrumb :items="[
                ['label' => 'Conselhos', 'url' => route('conselhos.index')],
                ['label' => $conselho->turma->nome, 'url' => '#']
            ]" />

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Não foi possível registar o ajuste:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($modoConsulta)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-eye me-2"></i>
                    <strong>Modo consulta:</strong> conselho do ano lectivo <strong>{{ $conselho->ano_lectivo }}</strong>.
                    Apenas visualização — não é possível iniciar, ajustar ou concluir.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Cabeçalho com informações do conselho --}}
            <div class="card shadow-soft mb-4 conselho-info-card">
                <div class="card-header conselho-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                {{ $conselho->turma->nome }} - {{ $conselho->trimestre }}º Trimestre - {{ $conselho->ano_lectivo }}
                            </h5>
                        </div>
                        <div class="col-md-4 text-end">
                            @if($conselho->isPendente())
                                <span class="badge bg-secondary fs-6">
                                    <i class="bi bi-clock me-1"></i>Pendente
                                </span>
                            @elseif($conselho->isEmCurso())
                                <span class="badge bg-warning text-dark fs-6">
                                    <i class="bi bi-play-circle me-1"></i>Em Curso
                                </span>
                            @elseif($conselho->isConcluido())
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-check-circle me-1"></i>Concluído
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <strong>Curso:</strong> {{ $conselho->turma->curso->nome }}
                        </div>
                        <div class="col-md-3">
                            <strong>Classe:</strong> {{ $conselho->turma->classe->nome ?? 'N/A' }}
                        </div>
                        <div class="col-md-3">
                            <strong>Turno:</strong> {{ $conselho->turma->turnoNomeResolvido() }}
                        </div>
                        <div class="col-md-3">
                            <strong>Ano Lectivo:</strong> {{ $conselho->ano_lectivo }}
                        </div>
                        <div class="col-md-3">
                            <strong>Data Início:</strong>
                            {{ $conselho->data_inicio ? $conselho->data_inicio->format('d/m/Y H:i') : '—' }}
                        </div>
                        <div class="col-md-3">
                            <strong>Data Conclusão:</strong>
                            {{ $conselho->data_conclusao ? $conselho->data_conclusao->format('d/m/Y H:i') : '—' }}
                        </div>
                        @if($conselho->iniciadoPor)
                            <div class="col-md-3">
                                <strong>Iniciado por:</strong> {{ $conselho->iniciadoPor->name }}
                            </div>
                        @endif
                        @if($conselho->concluidoPor)
                            <div class="col-md-3">
                                <strong>Concluído por:</strong> {{ $conselho->concluidoPor->name }}
                            </div>
                        @endif
                        <div class="col-md-3">
                            <strong>Alunos na lista:</strong> {{ $notasAgrupadasCompletas->pluck('aluno.id')->unique()->count() }}
                        </div>
                        <div class="col-md-3">
                            <strong>Disciplinas:</strong> {{ $notasAgrupadasCompletas->pluck('disciplina.id')->unique()->count() }}
                        </div>
                    </div>
                    @can('iniciar', $conselho)
                        @if($conselho->isPendente() && !$modoConsulta)
                            <div class="mt-3 pt-3 border-top">
                                <form action="{{ route('conselhos.iniciar', $conselho) }}" method="POST"
                                      class="d-inline form-iniciar-conselho"
                                      data-trimestre="{{ $conselho->trimestre }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-play-circle me-1"></i>Iniciar Conselho de Classe
                                    </button>
                                </form>
                                <small class="text-muted ms-2">Após iniciar, poderá registar ajustes favoráveis às notas.</small>
                            </div>
                        @endif
                    @else
                        @if($conselho->isPendente() && auth()->user()->hasRole('professor'))
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Como professor, pode consultar este conselho. O <strong>Director de Turma</strong> (ou coordenação/administração) é quem inicia e regista os ajustes.
                                </small>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>

            @if($visaoApenasSuasDisciplinas ?? false)
                <div class="conselho-callout conselho-callout-professor mb-4">
                    <i class="bi bi-mortarboard me-1"></i>
                    Está a acompanhar o conselho como <strong>professor</strong>.
                    Só vê as disciplinas que lecciona nesta turma
                    @if($disciplinas->count() === 1)
                        (<strong>{{ $disciplinas->first()->nome }}</strong>).
                    @else
                        ({{ $disciplinas->count() }} disciplinas).
                    @endif
                    O director de turma regista os ajustes no conselho.
                </div>
            @endif

            {{-- Procedimento: notas em falta --}}
            <div class="conselho-callout conselho-callout-muted mb-4">
                <h6 class="alert-heading mb-2">
                    <i class="bi bi-question-circle me-1"></i>Notas em falta (—) — qual o procedimento?
                </h6>
                <ul class="small mb-0 ps-3">
                    @if($substituiPtPorPg)
                        <li class="mb-1">
                            <strong>3.º trimestre (regime politécnico):</strong> não existe <strong>PT3</strong>.
                            A avaliação somativa é a <strong>PG (Prova Global)</strong>, mostrada na coluna PG.
                            O conselho ajusta MAC3, PP3 e PG (se já lançada).
                        </li>
                        <li class="mb-1">
                            <strong>PG em falta:</strong> o professor deve lançar a nota em
                            <a href="{{ route('notas.lancar-pg', ['turma_id' => $conselho->turma_id]) }}">Lançar PG</a>
                            antes do conselho. O traço (—) na coluna PG significa que a prova ainda não foi registada no sistema.
                        </li>
                    @else
                        <li class="mb-1">
                            <strong>PT{{ $conselho->trimestre }} em falta:</strong> o professor deve lançar a nota em
                            <a href="{{ route('notas.lancar', ['turma_id' => $conselho->turma_id, 'trimestre' => $conselho->trimestre]) }}">Lançar Notas</a>
                            antes do conselho. O conselho <strong>não cria</strong> notas — apenas ajusta notas já existentes.
                        </li>
                    @endif
                    <li class="mb-1">
                        <strong>Aluno não fez a prova:</strong> lança-se <strong>0</strong> (ou a nota definida pelo regulamento) em Lançar Notas;
                        no conselho pode aplicar-se arredondamento favorável em MAC/PP com justificação pedagógica.
                    </li>
                    <li>
                        <strong>Sem nota lançada:</strong> não é possível ajustar no conselho — o botão de ajuste só aparece quando existe nota pré-conselho.
                    </li>
                </ul>
            </div>

            {{-- Orientação legal (MED / Conselho de Notas) --}}
            <div class="conselho-callout conselho-callout-info mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Conselho de Notas (prática angolana):</strong>
                o objectivo é <strong>beneficiar ou manter</strong> a classificação do estudante com base no debate pedagógico da turma.
                Aumentos e arredondamentos favoráveis são permitidos com justificação.
                <strong>Reduzir uma nota já lançada não é permitido</strong> de forma punitiva ou arbitrária — apenas em casos excepcionais documentados
                (erro material de cálculo, fraude académica ou processo disciplinar formal).
            </div>

            {{-- Estatísticas --}}
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <x-stat-card
                        icon="bi-pencil-square"
                        icon-class="stat-icon-primary"
                        :value="$estatisticas['total_ajustes']"
                        label="Total de Ajustes"
                    />
                </div>
                <div class="col-md-2">
                    <x-stat-card
                        icon="bi-arrow-up-circle"
                        icon-class="stat-icon-success"
                        :value="$estatisticas['aumentos']"
                        label="Aumentos"
                    />
                </div>
                <div class="col-md-2">
                    <x-stat-card
                        icon="bi-arrow-down-circle"
                        icon-class="stat-icon-danger"
                        :value="$estatisticas['diminuicoes']"
                        label="Correcções excepcionais"
                    />
                </div>
                <div class="col-md-2">
                    <x-stat-card
                        icon="bi-person-check"
                        icon-class="stat-icon-info"
                        :value="$estatisticas['alunos_afetados']"
                        label="Alunos Afetados"
                    />
                </div>
                <div class="col-md-2">
                    <x-stat-card
                        icon="bi-book"
                        icon-class="stat-icon-warning"
                        :value="$estatisticas['disciplinas_afetadas']"
                        label="Disciplinas Afetadas"
                    />
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-calculator fs-3 text-secondary"></i>
                            <h4 class="mt-2">{{ number_format($estatisticas['diferenca_media'] ?? 0, 2) }}</h4>
                            <p class="text-muted small mb-0">Diferença Média</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            @php
                $temFiltroLocal = $filtros['disciplina_id'] || $filtros['aluno'] !== '' || $filtros['apenas_falhas'];
                $resumoFiltros = '<strong>'.e($conselho->turma->nome).'</strong> · '
                    .e($conselho->trimestre).'.º trim. · '.e($conselho->ano_lectivo);
                if ($filtros['disciplina_id']) {
                    $discSel = $disciplinas->firstWhere('id', (int) $filtros['disciplina_id']);
                    if ($discSel) {
                        $resumoFiltros .= ' · '.e($discSel->nome);
                    }
                }
                if ($filtros['aluno'] !== '') {
                    $resumoFiltros .= ' · aluno «'.e($filtros['aluno']).'»';
                }
                if ($filtros['apenas_falhas']) {
                    $resumoFiltros .= ' · só notas em falta';
                }
            @endphp
            <x-filtros-pauta-card
                titulo="Filtros de consulta"
                :form-action="route('conselhos.show', $conselho)"
                :limpar-url="route('conselhos.show', $conselho)"
                botao-texto="Aplicar"
                instrucao="Ao mudar turma, ano ou trimestre a página actualiza automaticamente com as disciplinas dessa turma."
                :resumo="$resumoFiltros"
                form-id="form-filtros-conselho"
            >
                <div class="col-sm-6 col-lg-3">
                    <label for="ano_lectivo_id" class="form-label">
                        <i class="bi bi-calendar-range me-1"></i>Ano Lectivo
                    </label>
                    <select class="form-select form-select-sm conselho-filtro-nav" id="ano_lectivo_id" name="ano_lectivo_id">
                        @foreach($anosLectivos as $id => $nome)
                            <option value="{{ $id }}" {{ (int) $conselho->ano_lectivo_id === (int) $id ? 'selected' : '' }}>
                                {{ $nome }}@if($id === \App\Models\Configuracao::getAnoLectivoActualId()) (Actual)@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="turma_id" class="form-label">
                        <i class="bi bi-people me-1"></i>Turma
                    </label>
                    <select class="form-select form-select-sm conselho-filtro-nav" id="turma_id" name="turma_id"
                            data-conselho-turma="{{ $conselho->turma_id }}">
                        @foreach($turmas as $turma)
                            <option value="{{ $turma->id }}" {{ (int) $conselho->turma_id === (int) $turma->id ? 'selected' : '' }}>
                                {{ $turma->nome }} · {{ $turma->curso->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="trimestre" class="form-label">
                        <i class="bi bi-calendar3 me-1"></i>Trimestre
                    </label>
                    <select class="form-select form-select-sm conselho-filtro-nav" id="trimestre" name="trimestre">
                        @for($t = 1; $t <= 3; $t++)
                            <option value="{{ $t }}" {{ (int) $conselho->trimestre === $t ? 'selected' : '' }}>
                                {{ $t }}.º Trimestre
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="disciplina_id" class="form-label">
                        <i class="bi bi-journal-text me-1"></i>Disciplina
                    </label>
                    <select class="form-select form-select-sm" id="disciplina_id" name="disciplina_id">
                        @if($disciplinas->count() !== 1)
                            <option value="">— Todas —</option>
                        @endif
                        @foreach($disciplinas as $disciplina)
                            <option value="{{ $disciplina->id }}"
                                {{ (string) $filtros['disciplina_id'] === (string) $disciplina->id ? 'selected' : '' }}>
                                {{ $disciplina->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="aluno" class="form-label">
                        <i class="bi bi-search me-1"></i>Aluno
                    </label>
                    <input type="text" class="form-control form-control-sm" id="aluno" name="aluno"
                           value="{{ $filtros['aluno'] }}"
                           placeholder="Nome ou n.º processo">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="apenas_falhas" value="1"
                               id="apenas_falhas" {{ $filtros['apenas_falhas'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="apenas_falhas">
                            Mostrar apenas linhas com notas em falta (—)
                        </label>
                    </div>
                </div>
            </x-filtros-pauta-card>

            @if($temFiltroLocal)
                <div class="alert alert-light border mb-4 py-2 small conselho-filtro-resumo">
                    <i class="bi bi-filter me-1"></i>
                    A mostrar <strong>{{ $notasAgrupadas->count() }}</strong> de
                    <strong>{{ $notasAgrupadasCompletas->count() }}</strong> registos (aluno × disciplina).
                </div>
            @endif

            {{-- Tabs: Notas e Histórico --}}
            <ul class="nav nav-tabs conselho-tabs" id="conselhoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas"
                            type="button" role="tab">
                        <i class="bi bi-list-check me-1"></i>Notas para Ajuste
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico"
                            type="button" role="tab">
                        <i class="bi bi-clock-history me-1"></i>Histórico de Ajustes
                    </button>
                </li>
            </ul>

            <div class="tab-content conselho-tab-content" id="conselhoTabsContent">
                {{-- Tab: Notas --}}
                <div class="tab-pane fade show active" id="notas" role="tabpanel">
                    <div class="card shadow-soft conselho-notas-card">
                        <div class="card-body p-0">
                            @if($notasAgrupadasCompletas->isEmpty())
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                    <p>Nenhuma nota encontrada para este trimestre.</p>
                                </div>
                            @elseif($notasAgrupadas->isEmpty())
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-funnel fs-1 d-block mb-3"></i>
                                    <p>Nenhum registo corresponde aos filtros aplicados.</p>
                                    <a href="{{ route('conselhos.show', $conselho) }}" class="btn btn-outline-secondary btn-sm mt-2">
                                        <i class="bi bi-x-lg me-1"></i>Limpar filtros
                                    </a>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 align-middle conselho-notas-table">
                                        <thead class="table-light sticky-top conselho-table-head">
                                            <tr>
                                                <th class="text-center" style="width:4rem">N.º</th>
                                                <th style="width:7rem">Processo</th>
                                                <th>Aluno</th>
                                                <th>Turma</th>
                                                <th>Disciplina</th>
                                                @foreach($colunasAvaliacao as $coluna)
                                                    <th class="text-center">{{ $coluna['header'] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($notasAgrupadas as $grupo)
                                                <tr>
                                                    <td class="text-center text-muted">{{ $grupo['aluno']->numero_ordem ?? '—' }}</td>
                                                    <td class="small text-muted">{{ $grupo['aluno']->numero_processo ?? '—' }}</td>
                                                    <td>{{ $grupo['aluno']->nome }}</td>
                                                    <td class="small">{{ $conselho->turma->nome }}</td>
                                                    <td>{{ $grupo['disciplina']->nome }}</td>
                                                    @foreach($colunasAvaliacao as $coluna)
                                                        @php
                                                            $chave = $coluna['key'];
                                                            $nota = $grupo[$chave] ?? null;
                                                            $foiAjustada = $nota && $nota->foiAjustada();
                                                            $notaPos = $foiAjustada ? $nota->notaAjustada : null;
                                                            $tipoLabel = $coluna['header'];
                                                        @endphp
                                                        <td class="text-center">
                                                            @if($nota)
                                                                @if($foiAjustada)
                                                                    <span class="text-decoration-line-through text-muted">
                                                                        {{ number_format($nota->valor, 1, ',', '') }}
                                                                    </span>
                                                                    <i class="bi bi-arrow-right mx-1"></i>
                                                                    <strong class="text-success">
                                                                        {{ number_format($notaPos->valor, 1, ',', '') }}
                                                                    </strong>
                                                                    <span class="badge bg-success ms-1">Ajustada</span>
                                                                @else
                                                                    <strong>{{ number_format($nota->valor, 1, ',', '') }}</strong>
                                                                @endif
                                                                @can('ajustar', $conselho)
                                                                    @if($conselho->isEmCurso() && !$foiAjustada && !$modoConsulta)
                                                                        <div class="mt-1">
                                                                            <button type="button"
                                                                                class="btn btn-sm btn-outline-primary btn-ajustar-nota-conselho"
                                                                                title="Ajustar {{ $tipoLabel }}"
                                                                                data-nota-id="{{ $nota->id }}"
                                                                                data-aluno="{{ $grupo['aluno']->nome }}"
                                                                                data-processo="{{ $grupo['aluno']->numero_processo ?? '' }}"
                                                                                data-turma="{{ $conselho->turma->nome }}"
                                                                                data-disciplina="{{ $grupo['disciplina']->nome }}"
                                                                                data-tipo="{{ $tipoLabel }}"
                                                                                data-valor="{{ (float) $nota->valor }}">
                                                                                <i class="bi bi-arrow-up-circle"></i>
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                @endcan
                                                            @else
                                                                <span class="text-muted" title="Nota não lançada — ver procedimento acima">—</span>
                                                                @if(!empty($coluna['substitui_pt']))
                                                                    <div>
                                                                        <a href="{{ route('notas.lancar-pg', ['turma_id' => $conselho->turma_id]) }}"
                                                                           class="badge badge-soft-info text-decoration-none"
                                                                           title="Lançar Prova Global">
                                                                            PG não lançada
                                                                        </a>
                                                                    </div>
                                                                @elseif(in_array($chave, ['pt', 'pg'], true))
                                                                    <div><span class="badge badge-soft-warning">Não lançada</span></div>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tab: Histórico --}}
                <div class="tab-pane fade" id="historico" role="tabpanel">
                    <div class="card shadow-soft">
                        <div class="card-body p-0">
                            @if($conselho->ajustes->isEmpty())
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-clock-history fs-1 d-block mb-3"></i>
                                    <p>Nenhum ajuste realizado neste conselho.</p>
                                </div>
                            @elseif($ajustesFiltrados->isEmpty())
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-funnel fs-1 d-block mb-3"></i>
                                    <p>Nenhum ajuste corresponde aos filtros aplicados.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Aluno</th>
                                                <th>Processo</th>
                                                <th>Turma</th>
                                                <th>Disciplina</th>
                                                <th>Tipo</th>
                                                <th class="text-center">Valor Anterior</th>
                                                <th class="text-center">Valor Novo</th>
                                                <th class="text-center">Diferença</th>
                                                <th>Justificação</th>
                                                <th>Motivo</th>
                                                <th>Ajustado Por</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($ajustesFiltrados as $ajuste)
                                                <tr>
                                                    <td>{{ $ajuste->created_at->format('d/m/Y H:i') }}</td>
                                                    <td>{{ $ajuste->notaPre->aluno->nome }}</td>
                                                    <td class="small text-muted">{{ $ajuste->notaPre->aluno->numero_processo ?? '—' }}</td>
                                                    <td class="small">{{ $conselho->turma->nome }}</td>
                                                    <td>{{ $ajuste->notaPre->disciplina->nome }}</td>
                                                    <td><strong>{{ $ajuste->notaPre->tipo }}</strong></td>
                                                    <td class="text-center">{{ number_format($ajuste->valor_anterior, 0) }}</td>
                                                    <td class="text-center">
                                                        <strong class="{{ $ajuste->foiAumentada() ? 'text-success' : 'text-danger' }}">
                                                            {{ number_format($ajuste->valor_novo, 0) }}
                                                        </strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge {{ $ajuste->getBadgeColor() }}">
                                                            {{ $ajuste->getIcone() }} {{ number_format($ajuste->getDiferenca(), 0) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small>{{ $ajuste->justificacao }}</small>
                                                    </td>
                                                    <td>
                                                        @if($ajuste->getMotivoReducaoRotulo())
                                                            <span class="badge bg-warning text-dark">{{ $ajuste->getMotivoReducaoRotulo() }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $ajuste->ajustadoPor->name }}</td>
                                                    <td class="text-center">
                                                        @can('ajustar', $conselho)
                                                            @if($conselho->isEmCurso() && !$modoConsulta)
                                                                <form action="{{ route('conselhos.ajustes.reverter', $ajuste) }}"
                                                                      method="POST"
                                                                      class="d-inline form-reverter-ajuste"
                                                                      data-aluno="{{ $ajuste->notaPre->aluno->nome }}"
                                                                      data-disciplina="{{ $ajuste->notaPre->disciplina->nome }}"
                                                                      data-tipo="{{ $ajuste->notaPre->tipo }}">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botão Concluir Conselho --}}
            @can('concluir', $conselho)
                @if($conselho->isEmCurso() && !$modoConsulta)
                    <div class="card shadow-soft mt-4">
                        <div class="card-body">
                            <form action="{{ route('conselhos.concluir', $conselho) }}"
                                  method="POST"
                                  id="form-concluir-conselho"
                                  data-trimestre="{{ $conselho->trimestre }}">
                                @csrf
                                <div class="row align-items-end">
                                    <div class="col-md-10">
                                        <label for="observacoes" class="form-label">Observações Finais (opcional)</label>
                                        <textarea name="observacoes" id="observacoes" class="form-control" rows="2"
                                                  placeholder="Observações sobre o conselho..."></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-check-circle me-1"></i>Concluir Conselho
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    </div>

    {{-- Modal para Ajustar Nota --}}
    <div class="modal fade" id="modalAjustarNota" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('conselhos.ajustar', $conselho) }}" method="POST">
                    @csrf
                    <input type="hidden" name="nota_id" id="nota_id" value="{{ old('nota_id') }}">
                    @if($filtros['disciplina_id'])
                        <input type="hidden" name="disciplina_id" value="{{ $filtros['disciplina_id'] }}">
                    @endif
                    @if($filtros['aluno'] !== '')
                        <input type="hidden" name="aluno" value="{{ $filtros['aluno'] }}">
                    @endif
                    @if($filtros['apenas_falhas'])
                        <input type="hidden" name="apenas_falhas" value="1">
                    @endif

                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-square me-2"></i>Ajustar Nota
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-light border mb-3 py-2 small">
                            <i class="bi bi-shield-check text-primary me-1"></i>
                            Indique um valor <strong>superior</strong> ao actual para beneficiar o estudante.
                            Reduções só com motivo excepcional documentado.
                        </div>

                        <div class="mb-3">
                            <p class="mb-1"><strong>Aluno:</strong> <span id="modal_aluno"></span></p>
                            <p class="mb-1"><strong>N.º processo:</strong> <span id="modal_processo" class="text-muted"></span></p>
                            <p class="mb-1"><strong>Turma:</strong> <span id="modal_turma"></span></p>
                            <p class="mb-1"><strong>Disciplina:</strong> <span id="modal_disciplina"></span></p>
                            <p class="mb-1"><strong>Tipo:</strong> <span id="modal_tipo"></span></p>
                            <p class="mb-0"><strong>Valor Actual:</strong> <span id="modal_valor_atual" class="text-primary fs-5"></span></p>
                        </div>

                        <div class="mb-3">
                            <label for="novo_valor" class="form-label">Novo Valor <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" min="0" max="20" name="novo_valor" id="novo_valor"
                                   class="form-control @error('novo_valor') is-invalid @enderror"
                                   value="{{ old('novo_valor') }}" required>
                            @error('novo_valor')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted" id="novo_valor_ajuda">Valor entre 0 e 20 (recomendado: ≥ valor actual)</small>
                        </div>

                        <div class="mb-3 d-none" id="grupo_motivo_reducao">
                            <label for="motivo_reducao" class="form-label">Motivo excepcional da redução <span class="text-danger">*</span></label>
                            <select name="motivo_reducao" id="motivo_reducao" class="form-select @error('motivo_reducao') is-invalid @enderror">
                                <option value="">Seleccione o motivo...</option>
                                @foreach($motivosReducao as $codigo => $rotulo)
                                    <option value="{{ $codigo }}" @selected(old('motivo_reducao') === $codigo)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('motivo_reducao')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="justificacao" class="form-label">Justificação pedagógica <span class="text-danger">*</span></label>
                            <textarea name="justificacao" id="justificacao" class="form-control @error('justificacao') is-invalid @enderror" rows="4"
                                      required minlength="10" maxlength="1000"
                                      placeholder="Descreva o consenso do conselho: evolução do aluno, assiduidade, participação, arredondamento favorável...">{{ old('justificacao') }}</textarea>
                            @error('justificacao')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted" id="justificacao_ajuda">Mínimo 10 caracteres</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Confirmar Ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $ctxReabrirModal = null;
        if (old('nota_id')) {
            foreach ($notasAgrupadasCompletas as $grupo) {
                foreach ($colunasAvaliacao as $coluna) {
                    $notaCtx = $grupo[$coluna['key']] ?? null;
                    if ($notaCtx && (int) $notaCtx->id === (int) old('nota_id')) {
                        $ctxReabrirModal = [
                            'id' => $notaCtx->id,
                            'aluno' => $grupo['aluno']->nome,
                            'processo' => $grupo['aluno']->numero_processo ?? '—',
                            'turma' => $conselho->turma->nome,
                            'disciplina' => $grupo['disciplina']->nome,
                            'tipo' => $coluna['header'],
                            'valor' => (float) $notaCtx->valor,
                        ];
                        break 2;
                    }
                }
            }
        }
    @endphp

    @push('scripts')
    <script>
        let valorAtualConselho = 0;

        function abrirModalAjuste(notaId, aluno, processo, turma, disciplina, tipo, valorAtual) {
            valorAtualConselho = parseFloat(valorAtual);
            document.getElementById('nota_id').value = notaId;
            document.getElementById('modal_aluno').textContent = aluno;
            document.getElementById('modal_processo').textContent = processo || '—';
            document.getElementById('modal_turma').textContent = turma;
            document.getElementById('modal_disciplina').textContent = disciplina;
            document.getElementById('modal_tipo').textContent = tipo;
            document.getElementById('modal_valor_atual').textContent = valorAtualConselho.toFixed(1);
            document.getElementById('novo_valor').value = '';
            document.getElementById('justificacao').value = '';
            document.getElementById('motivo_reducao').value = '';
            actualizarModalAjusteConselho();

            const modal = new bootstrap.Modal(document.getElementById('modalAjustarNota'));
            modal.show();
        }

        function actualizarModalAjusteConselho() {
            const input = document.getElementById('novo_valor');
            const grupoMotivo = document.getElementById('grupo_motivo_reducao');
            const motivo = document.getElementById('motivo_reducao');
            const ajuda = document.getElementById('novo_valor_ajuda');
            const ajudaJust = document.getElementById('justificacao_ajuda');
            const novo = parseFloat(String(input.value).replace(',', '.'));

            if (Number.isNaN(novo)) {
                grupoMotivo.classList.add('d-none');
                motivo.required = false;
                ajuda.textContent = `Valor recomendado: igual ou superior a ${valorAtualConselho.toFixed(1)}`;
                ajudaJust.textContent = 'Mínimo 10 caracteres';
                return;
            }

            if (novo < valorAtualConselho) {
                grupoMotivo.classList.remove('d-none');
                motivo.required = true;
                ajuda.textContent = 'Redução detectada — seleccione motivo excepcional e justifique com detalhe (mín. 20 caracteres).';
                ajudaJust.textContent = 'Reduções excepcionais: mínimo 20 caracteres com referência ao caso.';
            } else {
                grupoMotivo.classList.add('d-none');
                motivo.required = false;
                motivo.value = '';
                ajuda.textContent = novo > valorAtualConselho
                    ? 'Aumento favorável ao estudante — descreva o consenso do conselho.'
                    : `Informe um valor superior a ${valorAtualConselho.toFixed(1)} para registar benefício ao estudante.`;
                ajudaJust.textContent = 'Mínimo 10 caracteres';
            }
        }

        document.getElementById('novo_valor')?.addEventListener('input', actualizarModalAjusteConselho);

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-ajustar-nota-conselho').forEach(btn => {
                btn.addEventListener('click', function() {
                    abrirModalAjuste(
                        this.dataset.notaId,
                        this.dataset.aluno,
                        this.dataset.processo,
                        this.dataset.turma,
                        this.dataset.disciplina,
                        this.dataset.tipo,
                        this.dataset.valor,
                    );
                });
            });

            const formsIniciarConselho = document.querySelectorAll('.form-iniciar-conselho');

            formsIniciarConselho.forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const trimestre = this.dataset.trimestre;
                    const confirmed = await confirmAction({
                        title: 'Iniciar Conselho de Classe',
                        text: `Deseja iniciar o conselho do ${trimestre}º trimestre? Após iniciar, poderá ajustar as notas dos alunos.`,
                        confirmButtonText: 'Sim, iniciar',
                        cancelButtonText: 'Cancelar',
                        icon: 'question'
                    });
                    if (confirmed) {
                        this.submit();
                    }
                });
            });

            // Handler para reverter ajuste
            const formsReverterAjuste = document.querySelectorAll('.form-reverter-ajuste');

            formsReverterAjuste.forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const aluno = this.dataset.aluno;
                    const disciplina = this.dataset.disciplina;
                    const tipo = this.dataset.tipo;

                    const confirmed = await confirmAction({
                        title: 'Reverter Ajuste',
                        text: `Deseja reverter o ajuste de ${tipo} para ${aluno} em ${disciplina}?`,
                        confirmButtonText: 'Sim, reverter',
                        cancelButtonText: 'Cancelar',
                        icon: 'warning'
                    });

                    if (confirmed) {
                        this.submit();
                    }
                });
            });

            // Handler CRÍTICO para concluir conselho
            const formConcluirConselho = document.getElementById('form-concluir-conselho');

            if (formConcluirConselho) {
                formConcluirConselho.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const trimestre = this.dataset.trimestre;

                    const confirmed = await confirmAction({
                        title: '⚠️ Concluir Conselho de Classe',
                        html: `
                            <p class="mb-3">Deseja concluir o conselho do <strong>${trimestre}º trimestre</strong>?</p>
                            <div class="alert alert-warning text-start mb-0">
                                <strong>ATENÇÃO:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Após conclusão, <strong>não será possível fazer mais ajustes</strong></li>
                                    <li>Todas as notas ajustadas serão <strong>permanentemente aplicadas</strong></li>
                                    <li>Esta ação será <strong>registada na auditoria</strong></li>
                                </ul>
                            </div>
                        `,
                        confirmButtonText: 'Sim, concluir',
                        cancelButtonText: 'Cancelar',
                        icon: 'warning',
                        dangerMode: true
                    });

                    if (confirmed) {
                        this.submit();
                    }
                });
            }

            @if($ctxReabrirModal)
            abrirModalAjuste(
                @json((string) $ctxReabrirModal['id']),
                @json($ctxReabrirModal['aluno']),
                @json($ctxReabrirModal['processo'] ?? ''),
                @json($ctxReabrirModal['turma'] ?? ''),
                @json($ctxReabrirModal['disciplina']),
                @json($ctxReabrirModal['tipo']),
                @json($ctxReabrirModal['valor']),
            );
            const novoValorInput = document.getElementById('novo_valor');
            const justificacaoInput = document.getElementById('justificacao');
            const motivoInput = document.getElementById('motivo_reducao');
            if (novoValorInput && @json(old('novo_valor')) !== null) {
                novoValorInput.value = @json(old('novo_valor'));
            }
            if (justificacaoInput && @json(old('justificacao')) !== null) {
                justificacaoInput.value = @json(old('justificacao'));
            }
            if (motivoInput && @json(old('motivo_reducao')) !== null) {
                motivoInput.value = @json(old('motivo_reducao'));
            }
            actualizarModalAjusteConselho();
            @endif

            // Filtros: disciplinas por turma + navegação automática
            const disciplinasPorTurma = @json($disciplinasPorTurma);
            const formFiltros = document.getElementById('form-filtros-conselho');
            const turmaSelect = document.getElementById('turma_id');
            const disciplinaSelect = document.getElementById('disciplina_id');
            const conselhoTurmaAtual = parseInt(turmaSelect?.dataset.conselhoTurma || '0', 10);

            function preencherDisciplinas(turmaId, disciplinaSeleccionada) {
                if (!disciplinaSelect) return;

                const lista = disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)] || [];
                disciplinaSelect.innerHTML = '';

                if (lista.length !== 1) {
                    const todas = document.createElement('option');
                    todas.value = '';
                    todas.textContent = '— Todas —';
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
                } else if (!disciplinaSeleccionada) {
                    disciplinaSelect.value = '';
                }

                disciplinaSelect.disabled = lista.length === 0;
            }

            function carregarDisciplinasRemotas(turmaId) {
                return fetch(`{{ route('conselhos.disciplinas') }}?turma_id=${turmaId}`, {
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
                    const mudouConselho = turmaId !== conselhoTurmaAtual;

                    const aplicar = (lista) => {
                        preencherDisciplinas(turmaId, mudouConselho ? null : disciplinaSelect.value);
                        if (mudouConselho && formFiltros) {
                            disciplinaSelect.value = lista.length === 1 ? String(lista[0]?.id || '') : '';
                            formFiltros.submit();
                        }
                    };

                    if (disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)]) {
                        aplicar(disciplinasPorTurma[turmaId] || disciplinasPorTurma[String(turmaId)]);
                    } else {
                        carregarDisciplinasRemotas(turmaId).then(aplicar);
                    }
                });
            }

            document.querySelectorAll('.conselho-filtro-nav').forEach((el) => {
                if (el.id === 'turma_id') return;
                el.addEventListener('change', function () {
                    if (disciplinaSelect) disciplinaSelect.value = '';
                    formFiltros?.submit();
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
