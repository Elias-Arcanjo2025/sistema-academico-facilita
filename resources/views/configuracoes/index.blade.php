<x-app-layout>
    <x-slot name="header">Configurações do Sistema</x-slot>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted mb-0"><i class="bi bi-gear me-1"></i>Gerir as configurações do sistema académico</p>
        <form action="{{ route('configuracoes.limpar-cache') }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleClearCache(this);">
            @csrf
            <button type="submit" class="btn btn-modern btn-sm" style="border: 1px solid #e2e8f0;">
                <i class="bi bi-arrow-clockwise me-1"></i>Limpar Cache
            </button>
        </form>
    </div>

    @include('configuracoes.partials.estilos')

    @if(! \App\Models\AnoLectivo::exists() || session('highlight_ano_lectivo'))
        <div class="alert alert-warning border-0 shadow-sm mb-4" id="aviso-sem-ano-lectivo" role="alert">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                    <strong>Ano lectivo obrigatório</strong>
                    <p class="mb-2">Ainda não existe nenhum ano lectivo. Crie o primeiro em <strong>Gestão de Anos Lectivos</strong> (botão «Novo Ano Lectivo») para desbloquear turmas, alunos, notas e pautas.</p>
                    <a href="#anos-lectivos-section" class="btn btn-sm btn-warning" id="btn-ir-anos-lectivos">
                        <i class="bi bi-calendar-plus me-1"></i>Ir para Gestão de Anos Lectivos
                    </a>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var section = document.getElementById('anos-lectivos-section');
                if (section) {
                    setTimeout(function () {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        section.classList.add('highlight-section');
                    }, 300);
                }
            });
        </script>
        <style>
            @keyframes highlight-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.45); }
                50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            }
            .highlight-section {
                animation: highlight-pulse 2s ease-in-out 3;
                border: 2px solid #f59e0b !important;
                border-radius: .5rem;
            }
        </style>
    @endif

    <x-tip-card icon="bi-gear" variant="warning" title="Configurações do Sistema" id="tip-configuracoes">
        Configure os parâmetros da instituição, ano lectivo activo, períodos de avaliação, pesos das notas e dados para os documentos PDF. As alterações aqui afectam o funcionamento global do sistema.
    </x-tip-card>

    @include('configuracoes.partials.resumo-estado')

    @include('configuracoes.partials.anos-lectivos')

    @include('configuracoes.partials.ensino-perfis')

    <div class="py-0">
        <div class="container-fluid px-0">
            <div class="row">
                <!-- Coluna Esquerda -->
                <div class="col-lg-6">
                    <!-- Trimestre Actual -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0">
                                <i class="bi bi-calendar3 me-2"></i>Trimestre Actual
                            </h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-trimestre') }}" method="POST" id="trimestreForm">
                                @csrf
                                @method('PUT')

                                <div class="row align-items-end">
                                    <div class="col-md-9">
                                        <label for="trimestre_actual" class="form-label fw-bold">
                                            Seleccione o Trimestre Actual
                                        </label>
                                        <select class="form-select @error('trimestre_actual') is-invalid @enderror" id="trimestre_actual" name="trimestre_actual" required>
                                            <option value="1" {{ ($configuracoes['ano_lectivo']['trimestre_actual'] ?? 1) == 1 ? 'selected' : '' }}>
                                                1º Trimestre (Setembro - Novembro)
                                            </option>
                                            <option value="2" {{ ($configuracoes['ano_lectivo']['trimestre_actual'] ?? 1) == 2 ? 'selected' : '' }}>
                                                2º Trimestre (Janeiro - Março)
                                            </option>
                                            <option value="3" {{ ($configuracoes['ano_lectivo']['trimestre_actual'] ?? 1) == 3 ? 'selected' : '' }}>
                                                3º Trimestre (Abril - Junho)
                                            </option>
                                        </select>
                                        @error('trimestre_actual')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-info-circle"></i>
                                            Define o trimestre actual para lançamento de notas e pautas
                                        </small>
                                    </div>

                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-check-circle me-1"></i>Actualizar
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="alert alert-info mt-3 mb-0 py-2">
                                <small>
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <strong>Nota:</strong> Apenas o trimestre actual está habilitado para lançamento de notas. Os restantes trimestres ficam bloqueados automaticamente. Administradores (admin_full) podem contornar esta restrição.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Avaliação -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-clipboard-check me-2"></i>Avaliação</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-avaliacao') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nota Mínima de Aprovação</label>
                                        <input type="number" step="0.1" min="0" max="20" class="form-control @error('nota_minima_aprovacao') is-invalid @enderror"
                                               name="nota_minima_aprovacao" id="nota_minima_aprovacao" value="{{ $configuracoes['avaliacao']['nota_minima_aprovacao'] }}">
                                        @error('nota_minima_aprovacao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de Cálculo</label>
                                        <select class="form-select @error('tipo_calculo_media') is-invalid @enderror" name="tipo_calculo_media" id="tipo_calculo_media">
                                            <option value="aritmetica" {{ $configuracoes['avaliacao']['tipo_calculo_media'] == 'aritmetica' ? 'selected' : '' }}>
                                                Média Aritmética
                                            </option>
                                            <option value="ponderada" {{ $configuracoes['avaliacao']['tipo_calculo_media'] == 'ponderada' ? 'selected' : '' }}>
                                                Média Ponderada
                                            </option>
                                        </select>
                                        @error('tipo_calculo_media')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @php
                                        $pesos = explode(',', $configuracoes['avaliacao']['pesos_trimestres']);
                                    @endphp

                                    <div class="col-12" id="pesos_container" style="{{ $configuracoes['avaliacao']['tipo_calculo_media'] != 'ponderada' ? 'display: none;' : '' }}">
                                        <label class="form-label">Pesos por Trimestre</label>
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">T1</span>
                                                    <input type="number" step="0.1" min="0" max="10" class="form-control"
                                                           name="peso_t1" value="{{ $pesos[0] ?? 1 }}">
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">T2</span>
                                                    <input type="number" step="0.1" min="0" max="10" class="form-control"
                                                           name="peso_t2" value="{{ $pesos[1] ?? 1 }}">
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">T3</span>
                                                    <input type="number" step="0.1" min="0" max="10" class="form-control"
                                                           name="peso_t3" value="{{ $pesos[2] ?? 1 }}">
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted">Peso relativo de cada trimestre no cálculo da média final.</small>
                                    </div>

                                    @include('configuracoes.partials.regras-resultado-painel')

                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="exames_permitir_lancamento_nao_elegivel" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   name="exames_permitir_lancamento_nao_elegivel" id="exames_permitir_lancamento_nao_elegivel"
                                                   value="1"
                                                   @checked($configuracoes['avaliacao']['exames_permitir_lancamento_nao_elegivel'] ?? true)>
                                            <label class="form-check-label" for="exames_permitir_lancamento_nao_elegivel">
                                                Permitir lançamento de recuperação/recurso <strong>fora da elegibilidade formal</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Quando activo, professores podem lançar notas de exame mesmo para alunos marcados como não elegíveis.
                                            Todas as alterações ficam no histórico (notas anteriores arquivadas + registo de actividade).
                                        </small>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Exame de Recurso -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Exame de Recurso (12.ª Classe)
                            </h6>
                        </div>
                        <div class="content-card-body">
                            <small class="text-muted d-block mb-3">
                                Base legal: <strong>Decreto Executivo n.º 424/25 — Art.º 31.º</strong>.
                                A nota lançada no recurso <em>substitui</em> a CFD anterior.
                                Aprovação na disciplina: ≥ 10 valores.
                            </small>
                            <form action="{{ route('configuracoes.update-recurso') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Nota Mínima Elegível</label>
                                        <input type="number" min="0" max="19" class="form-control @error('recurso_nota_min') is-invalid @enderror"
                                               name="recurso_nota_min" value="{{ $configuracoes['recurso']['recurso_nota_min'] }}">
                                        <small class="text-muted">CFD ≥ este valor (default 6)</small>
                                        @error('recurso_nota_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nota Máxima Elegível</label>
                                        <input type="number" min="1" max="19" class="form-control @error('recurso_nota_max') is-invalid @enderror"
                                               name="recurso_nota_max" value="{{ $configuracoes['recurso']['recurso_nota_max'] }}">
                                        <small class="text-muted">CFD ≤ este valor (default 9)</small>
                                        @error('recurso_nota_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Máx. Disciplinas / Aluno</label>
                                        <input type="number" min="1" max="10" class="form-control @error('recurso_max_disciplinas') is-invalid @enderror"
                                               name="recurso_max_disciplinas" value="{{ $configuracoes['recurso']['recurso_max_disciplinas'] }}">
                                        <small class="text-muted">Limite por aluno (default 3)</small>
                                        @error('recurso_max_disciplinas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Guardar
                                        </button>
                                        <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary btn-sm ms-2">
                                            <i class="bi bi-shield-fill me-1"></i>Gerir disciplinas blindadas (por curso)
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Exame de Melhoria de Nota -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0">
                                <i class="bi bi-graph-up-arrow me-2"></i>Exame de Melhoria de Nota
                            </h6>
                        </div>
                        <div class="content-card-body">
                            <small class="text-muted d-block mb-3">
                                Base legal: <strong>Decreto Executivo n.º 424/25 — Art.º 34.º</strong>.
                                Aplica-se a alunos <em>aprovados</em>. A nota final é o <strong>MAX(original, melhoria)</strong> — nunca baixa a nota.
                            </small>
                            <form action="{{ route('configuracoes.update-melhoria') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Nota Mínima Elegível</label>
                                        <input type="number" min="0" max="19" class="form-control @error('melhoria_nota_min') is-invalid @enderror"
                                               name="melhoria_nota_min" value="{{ $configuracoes['melhoria']['melhoria_nota_min'] }}">
                                        <small class="text-muted">CFD ≥ este valor (default 10)</small>
                                        @error('melhoria_nota_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nota Máxima Elegível</label>
                                        <input type="number" min="1" max="19" class="form-control @error('melhoria_nota_max') is-invalid @enderror"
                                               name="melhoria_nota_max" value="{{ $configuracoes['melhoria']['melhoria_nota_max'] }}">
                                        <small class="text-muted">CFD ≤ este valor (default 16)</small>
                                        @error('melhoria_nota_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Máx. Disciplinas / Aluno</label>
                                        <input type="number" min="0" max="20" class="form-control @error('melhoria_max_disciplinas') is-invalid @enderror"
                                               name="melhoria_max_disciplinas" value="{{ $configuracoes['melhoria']['melhoria_max_disciplinas'] }}">
                                        <small class="text-muted">0 = sem limite</small>
                                        @error('melhoria_max_disciplinas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Períodos de Lançamento -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-calendar-range me-2"></i>Períodos de Lançamento de Notas</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-periodos') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <!-- 1o Trimestre -->
                                    <div class="col-12">
                                        <h6>1.o Trimestre</h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label small">Início</label>
                                                <input type="date" class="form-control" name="lancamento_t1_inicio"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t1_inicio'] }}">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Fim</label>
                                                <input type="date" class="form-control" name="lancamento_t1_fim"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t1_fim'] }}">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2o Trimestre -->
                                    <div class="col-12">
                                        <h6>2.o Trimestre</h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label small">Início</label>
                                                <input type="date" class="form-control" name="lancamento_t2_inicio"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t2_inicio'] }}">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Fim</label>
                                                <input type="date" class="form-control" name="lancamento_t2_fim"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t2_fim'] }}">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3o Trimestre -->
                                    <div class="col-12">
                                        <h6>3.o Trimestre</h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label small">Início</label>
                                                <input type="date" class="form-control" name="lancamento_t3_inicio"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t3_inicio'] }}">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Fim</label>
                                                <input type="date" class="form-control" name="lancamento_t3_fim"
                                                       value="{{ $configuracoes['periodos_lancamento']['lancamento_t3_fim'] }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <small class="text-muted">Deixe vazio para permitir lançamento a qualquer momento.</small>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Configurações de PDF -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Configurações de PDF</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-pdf') }}" method="POST">
                                @csrf

                                <!-- Margens -->
                                <div class="mb-3">
                                    <h6 class="fw-bold border-bottom pb-1 mb-2"><small>Margens (mm)</small></h6>
                                    <div class="row g-2">
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small">Topo</label>
                                            <input type="number" step="1" min="0" max="50"
                                                   class="form-control @error('pdf_margin_top') is-invalid @enderror"
                                                   name="pdf_margin_top"
                                                   value="{{ old('pdf_margin_top', $configuracoes['pdf']['pdf_margin_top']) }}">
                                            @error('pdf_margin_top')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small">Direita</label>
                                            <input type="number" step="1" min="0" max="50"
                                                   class="form-control @error('pdf_margin_right') is-invalid @enderror"
                                                   name="pdf_margin_right"
                                                   value="{{ old('pdf_margin_right', $configuracoes['pdf']['pdf_margin_right']) }}">
                                            @error('pdf_margin_right')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small">Baixo</label>
                                            <input type="number" step="1" min="0" max="50"
                                                   class="form-control @error('pdf_margin_bottom') is-invalid @enderror"
                                                   name="pdf_margin_bottom"
                                                   value="{{ old('pdf_margin_bottom', $configuracoes['pdf']['pdf_margin_bottom']) }}">
                                            @error('pdf_margin_bottom')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small">Esquerda</label>
                                            <input type="number" step="1" min="0" max="50"
                                                   class="form-control @error('pdf_margin_left') is-invalid @enderror"
                                                   name="pdf_margin_left"
                                                   value="{{ old('pdf_margin_left', $configuracoes['pdf']['pdf_margin_left']) }}">
                                            @error('pdf_margin_left')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Fontes -->
                                <div class="mb-3">
                                    <h6 class="fw-bold border-bottom pb-1 mb-2"><small>Fontes</small></h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Família da Fonte</label>
                                            <select class="form-select @error('pdf_font_family') is-invalid @enderror"
                                                    name="pdf_font_family">
                                                <option value="DejaVu Sans" {{ old('pdf_font_family', $configuracoes['pdf']['pdf_font_family']) == 'DejaVu Sans' ? 'selected' : '' }}>
                                                    DejaVu Sans (Recomendado)
                                                </option>
                                                <option value="Helvetica" {{ old('pdf_font_family', $configuracoes['pdf']['pdf_font_family']) == 'Helvetica' ? 'selected' : '' }}>
                                                    Helvetica
                                                </option>
                                                <option value="Times-Roman" {{ old('pdf_font_family', $configuracoes['pdf']['pdf_font_family']) == 'Times-Roman' ? 'selected' : '' }}>
                                                    Times Roman
                                                </option>
                                                <option value="Courier" {{ old('pdf_font_family', $configuracoes['pdf']['pdf_font_family']) == 'Courier' ? 'selected' : '' }}>
                                                    Courier
                                                </option>
                                            </select>
                                            @error('pdf_font_family')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Corpo (px)</label>
                                            <input type="number" step="1" min="6" max="16"
                                                   class="form-control @error('pdf_font_size_body') is-invalid @enderror"
                                                   name="pdf_font_size_body"
                                                   value="{{ old('pdf_font_size_body', $configuracoes['pdf']['pdf_font_size_body']) }}">
                                            @error('pdf_font_size_body')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Cabeçalho (px)</label>
                                            <input type="number" step="1" min="8" max="24"
                                                   class="form-control @error('pdf_font_size_header') is-invalid @enderror"
                                                   name="pdf_font_size_header"
                                                   value="{{ old('pdf_font_size_header', $configuracoes['pdf']['pdf_font_size_header']) }}">
                                            @error('pdf_font_size_header')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Tabela (px)</label>
                                            <input type="number" step="0.5" min="6" max="14"
                                                   class="form-control @error('pdf_font_size_table') is-invalid @enderror"
                                                   name="pdf_font_size_table"
                                                   value="{{ old('pdf_font_size_table', $configuracoes['pdf']['pdf_font_size_table']) }}">
                                            @error('pdf_font_size_table')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Cores -->
                                <div class="mb-3">
                                    <h6 class="fw-bold border-bottom pb-1 mb-2"><small>Cores</small></h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Cor Primária</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color"
                                                       id="pdf_color_primary_picker"
                                                       value="{{ old('pdf_color_primary', $configuracoes['pdf']['pdf_color_primary']) }}">
                                                <input type="text"
                                                       class="form-control @error('pdf_color_primary') is-invalid @enderror"
                                                       name="pdf_color_primary"
                                                       id="pdf_color_primary_text"
                                                       value="{{ old('pdf_color_primary', $configuracoes['pdf']['pdf_color_primary']) }}"
                                                       pattern="^#[0-9A-Fa-f]{6}$"
                                                       placeholder="#0066cc">
                                                @error('pdf_color_primary')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <small class="text-muted">Títulos e destaques</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Cor Cabeçalho Tabela</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color"
                                                       id="pdf_color_table_header_picker"
                                                       value="{{ old('pdf_color_table_header', $configuracoes['pdf']['pdf_color_table_header']) }}">
                                                <input type="text"
                                                       class="form-control @error('pdf_color_table_header') is-invalid @enderror"
                                                       name="pdf_color_table_header"
                                                       id="pdf_color_table_header_text"
                                                       value="{{ old('pdf_color_table_header', $configuracoes['pdf']['pdf_color_table_header']) }}"
                                                       pattern="^#[0-9A-Fa-f]{6}$"
                                                       placeholder="#d0d0d0">
                                                @error('pdf_color_table_header')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <small class="text-muted">Fundo dos cabeçalhos</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Papel e Logo -->
                                <div class="mb-3">
                                    <h6 class="fw-bold border-bottom pb-1 mb-2"><small>Papel e Logo</small></h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Tamanho Papel</label>
                                            <select class="form-select @error('pdf_paper_size') is-invalid @enderror"
                                                    name="pdf_paper_size">
                                                <option value="A4" {{ old('pdf_paper_size', $configuracoes['pdf']['pdf_paper_size']) == 'A4' ? 'selected' : '' }}>
                                                    A4 (210 × 297 mm)
                                                </option>
                                                <option value="Letter" {{ old('pdf_paper_size', $configuracoes['pdf']['pdf_paper_size']) == 'Letter' ? 'selected' : '' }}>
                                                    Letter (216 × 279 mm)
                                                </option>
                                            </select>
                                            @error('pdf_paper_size')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Orientação Padrão</label>
                                            <select class="form-select @error('pdf_paper_orientation') is-invalid @enderror"
                                                    name="pdf_paper_orientation">
                                                <option value="portrait" {{ old('pdf_paper_orientation', $configuracoes['pdf']['pdf_paper_orientation']) == 'portrait' ? 'selected' : '' }}>
                                                    Retrato
                                                </option>
                                                <option value="landscape" {{ old('pdf_paper_orientation', $configuracoes['pdf']['pdf_paper_orientation']) == 'landscape' ? 'selected' : '' }}>
                                                    Paisagem
                                                </option>
                                            </select>
                                            @error('pdf_paper_orientation')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Alguns relatórios sempre usam landscape</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Largura Logo (px)</label>
                                            <input type="number" step="5" min="30" max="150"
                                                   class="form-control @error('pdf_logo_width') is-invalid @enderror"
                                                   name="pdf_logo_width"
                                                   value="{{ old('pdf_logo_width', $configuracoes['pdf']['pdf_logo_width']) }}">
                                            @error('pdf_logo_width')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Botões -->
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Configurações
                                    </button>
                                </div>
                            </form>

                            <div class="alert alert-info mt-3 mb-0 py-2">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Nota:</strong> As alterações aplicam-se a <strong>todos os PDFs</strong> do sistema (boletins, pautas, listas, relatórios e pedagógico). Alguns documentos em paisagem mantêm margens compactas por layout.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coluna Direita -->
                <div class="col-lg-6">
                    <!-- Instituicao -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-building me-2"></i>Dados da Instituição</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-instituicao') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Nome da Instituição</label>
                                        <input type="text" class="form-control @error('instituicao_nome') is-invalid @enderror"
                                               name="instituicao_nome" value="{{ $configuracoes['instituicao']['instituicao_nome'] }}">
                                        @error('instituicao_nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Logo</label>
                                        @if($configuracoes['instituicao']['instituicao_logo'])
                                            <div class="mb-2 d-flex align-items-center">
                                                <img src="{{ Storage::url($configuracoes['instituicao']['instituicao_logo']) }}"
                                                     alt="Logo" class="img-thumbnail" style="max-height: 100px;">
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="handleRemoveLogo()">
                                                    <i class="bi bi-trash"></i> Remover
                                                </button>
                                            </div>
                                        @endif
                                        <input type="file" class="form-control @error('instituicao_logo') is-invalid @enderror"
                                               name="instituicao_logo" accept="image/*">
                                        @error('instituicao_logo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Formatos aceites: JPEG, PNG, GIF. Tamanho máximo: 2MB</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Endereço</label>
                                        <textarea class="form-control @error('instituicao_endereco') is-invalid @enderror"
                                                  name="instituicao_endereco" rows="2">{{ $configuracoes['instituicao']['instituicao_endereco'] }}</textarea>
                                        @error('instituicao_endereco')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" class="form-control @error('instituicao_telefone') is-invalid @enderror"
                                               name="instituicao_telefone" value="{{ $configuracoes['instituicao']['instituicao_telefone'] }}">
                                        @error('instituicao_telefone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control @error('instituicao_email') is-invalid @enderror"
                                               name="instituicao_email" value="{{ $configuracoes['instituicao']['instituicao_email'] }}">
                                        @error('instituicao_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control @error('instituicao_website') is-invalid @enderror"
                                               name="instituicao_website" value="{{ $configuracoes['instituicao']['instituicao_website'] }}"
                                               placeholder="https://www.exemplo.com">
                                        @error('instituicao_website')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Nome do Director (Pautas)</label>
                                        <input type="text" class="form-control @error('director_geral_nome') is-invalid @enderror"
                                               name="director_geral_nome"
                                               value="{{ $configuracoes['instituicao']['director_geral_nome'] ?? '' }}"
                                               placeholder="Ex.: FERREIRA MANUEL FRAGOSO Ph,D">
                                        @error('director_geral_nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Aparece no cabeçalho da Pauta de Aproveitamento (assinatura "O DIRECTOR").</small>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @include('configuracoes.partials.link-pagina-publica')

                    <!-- Segurança -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-shield-lock me-2"></i>Segurança</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-seguranca') }}" method="POST">
                                @csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="bloqueio_inactividade_activo" value="0">
                                    <input class="form-check-input" type="checkbox" name="bloqueio_inactividade_activo" value="1" id="bloqueio_inactividade_activo"
                                        {{ ($configuracoes['seguranca']['bloqueio_inactividade_activo'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="bloqueio_inactividade_activo">
                                        Bloquear sessão após inactividade
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="bloqueio_inactividade_minutos">Tempo de inactividade (minutos)</label>
                                    <input type="number" class="form-control @error('bloqueio_inactividade_minutos') is-invalid @enderror"
                                           name="bloqueio_inactividade_minutos" id="bloqueio_inactividade_minutos"
                                           min="1" max="480" step="1"
                                           value="{{ old('bloqueio_inactividade_minutos', $configuracoes['seguranca']['bloqueio_inactividade_minutos'] ?? 30) }}">
                                    @error('bloqueio_inactividade_minutos')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted d-block mt-1">
                                        Após este período sem actividade, o utilizador terá de introduzir a palavra-passe para continuar (1–480 min).
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                            </form>
                        </div>
                    </div>

                    <!-- Portal do Aluno -->
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-phone me-2"></i>Portal do Aluno</h6>
                        </div>
                        <div class="content-card-body">
                            <form action="{{ route('configuracoes.update-portal') }}" method="POST">
                                @csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="portal_aluno_notas_activo" value="0">
                                    <input class="form-check-input" type="checkbox" name="portal_aluno_notas_activo" value="1" id="portal_aluno_notas_activo"
                                        {{ ($configuracoes['portal']['portal_aluno_notas_activo'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="portal_aluno_notas_activo">
                                        Permitir consulta de notas e boletim no portal (global)
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mensagem quando bloqueado (pagamento boletim, etc.)</label>
                                    <textarea name="portal_aluno_mensagem_bloqueio" class="form-control" rows="3">{{ $configuracoes['portal']['portal_aluno_mensagem_bloqueio'] ?? '' }}</textarea>
                                    <small class="text-muted">Pode bloquear alunos individualmente na ficha do aluno.</small>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                            </form>
                        </div>
                    </div>

                    <!-- Fecho / Transferência de Ano -->
                    @can('isAdminFull')
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-calendar2-check me-2"></i>Fecho de Ano Lectivo</h6>
                        </div>
                        <div class="content-card-body">
                            <p class="card-text small mb-2">
                                Assistente guiado: transferir estrutura, transitar alunos e activar o novo ano lectivo.
                            </p>
                            <a href="{{ route('fecho-ano.index') }}" class="btn btn-primary btn-sm me-2">
                                <i class="bi bi-calendar2-check me-1"></i>Abrir Wizard
                            </a>
                            <a href="{{ route('ano-lectivo-transfer.index') }}" class="btn btn-outline-warning btn-sm">
                                Transferência avançada
                            </a>
                        </div>
                    </div>
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-arrow-repeat me-2"></i>Transferência de Ano Lectivo</h6>
                        </div>
                        <div class="content-card-body">
                            <p class="card-text small mb-2">
                                Transfira dados (turmas, associações, directores, coordenadores) de um ano lectivo para outro.
                            </p>
                            <a href="{{ route('ano-lectivo-transfer.index') }}" class="btn btn-warning btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>Iniciar Transferência
                            </a>
                        </div>
                    </div>
                    @endcan

                    <!-- Gestão de Backups -->
                    @can('isAdminFull')
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-database me-2"></i>Gestão de Backups</h6>
                        </div>
                        <div class="content-card-body">
                            <p class="card-text small mb-2">
                                Sistema de backup automatizado da base de dados. Backups criados diariamente às 02:00 e mantidos por 30 dias.
                            </p>
                            <div class="alert alert-success mb-3 py-2">
                                <small>
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <strong>Automático:</strong> Backups comprimidos (GZIP) criados automaticamente.
                                </small>
                            </div>
                            <a href="{{ route('backups.index') }}" class="btn btn-success btn-sm">
                                <i class="bi bi-database me-1"></i>Gerir Backups
                            </a>
                        </div>
                    </div>
                    @endcan

                    <!-- Gerenciamento de Filas -->
                    @can('isAdminFull')
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-list-task me-2"></i>Gerenciamento de Filas</h6>
                        </div>
                        <div class="content-card-body">
                            <p class="card-text small mb-2">
                                Monitore e gerencie filas de processamento assíncrono. Acompanhe jobs pendentes e falhados.
                            </p>
                            <div class="alert alert-info mb-3 py-2">
                                <small>
                                    <i class="bi bi-lightning-fill me-1"></i>
                                    <strong>Assíncrono:</strong> Jobs pesados processados em segundo plano.
                                </small>
                            </div>
                            <a href="{{ route('queue.index') }}" class="btn btn-info btn-sm">
                                <i class="bi bi-list-task me-1"></i>Gerir Filas
                            </a>
                        </div>
                    </div>
                    @endcan

                    <!-- Informações do Sistema -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h6 class="content-card-title mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h6>
                        </div>
                        <div class="content-card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Versão Laravel</dt>
                                <dd class="col-sm-8">{{ app()->version() }}</dd>

                                <dt class="col-sm-4">Versão PHP</dt>
                                <dd class="col-sm-8">{{ phpversion() }}</dd>

                                <dt class="col-sm-4">Ambiente</dt>
                                <dd class="col-sm-8">{{ app()->environment() }}</dd>

                                <dt class="col-sm-4">Fuso Horário</dt>
                                <dd class="col-sm-8">{{ config('app.timezone') }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal para Novo Ano -->
    <div class="modal fade" id="novoAnoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('configuracoes.anos-lectivos.store') }}" method="POST">
                    @csrf
                    <div class="modal-header py-2">
                        <h6 class="modal-title">Novo Ano Lectivo</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome (formato: YYYY-YYYY)</label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   pattern="[0-9]{4}-[0-9]{4}" placeholder="2025-2026" required>
                            <div class="form-text">Exemplo: 2025-2026</div>
                        </div>
                        <div class="mb-3">
                            <label for="data_inicio" class="form-label">Data de Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio">
                        </div>
                        <div class="mb-3">
                            <label for="data_fim" class="form-label">Data de Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Criar Ano Lectivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Toggle pesos container
        document.getElementById('tipo_calculo_media').addEventListener('change', function() {
            var container = document.getElementById('pesos_container');
            if (this.value === 'ponderada') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        });

        // Pré-visualização dinâmica das regras de resultado
        function actualizarPreviewRegras() {
            const notaMin = parseFloat(document.getElementById('nota_minima_aprovacao')?.value) || 10;
            const maxReprov = parseInt(document.getElementById('max_disciplinas_reprovacao')?.value, 10);
            const maxDesist = parseInt(document.getElementById('max_disciplinas_sem_nota_desistente')?.value, 10);

            const notaFmt = notaMin.toLocaleString('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
            document.querySelectorAll('.preview-nota-min').forEach(el => { el.textContent = notaFmt; });
            document.getElementById('resumo-nota-minima')?.replaceChildren(document.createTextNode(notaFmt));
            document.querySelectorAll('.preview-nota-exemplo').forEach(el => {
                el.textContent = String(Math.max(0, Math.floor(notaMin - 1)));
            });

            if (!isNaN(maxReprov)) {
                document.querySelectorAll('.preview-max-reprov').forEach(el => { el.textContent = maxReprov; });
                document.getElementById('resumo-max-reprov')?.replaceChildren(document.createTextNode(String(maxReprov)));
                document.querySelectorAll('.preview-reprov-ok-qtd').forEach(el => { el.textContent = maxReprov; });
                document.querySelectorAll('.preview-reprov-fail-qtd').forEach(el => { el.textContent = maxReprov + 1; });
            }

            if (!isNaN(maxDesist)) {
                document.querySelectorAll('.preview-max-desist').forEach(el => { el.textContent = maxDesist; });
                document.getElementById('resumo-max-desist')?.replaceChildren(document.createTextNode(String(maxDesist)));
                document.querySelectorAll('.preview-desist-ok-qtd').forEach(el => { el.textContent = maxDesist; });
                document.querySelectorAll('.preview-desist-fail-qtd').forEach(el => { el.textContent = maxDesist + 1; });
            }
        }

        ['nota_minima_aprovacao', 'max_disciplinas_reprovacao', 'max_disciplinas_sem_nota_desistente'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', actualizarPreviewRegras);
                el.addEventListener('change', actualizarPreviewRegras);
            }
        });

        // Activar ano lectivo (com confirmação SweetAlert2)
        async function activarAno(nome, turmas = 0, alunos = 0, notas = 0) {
            const confirmed = await confirmAction({
                title: 'Activar Ano Lectivo?',
                html: `
                    <div class="text-start">
                        <p class="mb-3">Confirma que deseja definir como ano lectivo actual:</p>
                        <div class="p-3 rounded mb-3" style="background:linear-gradient(135deg,#eef2ff,#fff);border:1px solid #c7d2fe;">
                            <h5 class="mb-2" style="color:#4338ca;"><i class="bi bi-calendar-range"></i> ${nome}</h5>
                            <div class="small text-muted">
                                <span class="badge bg-light text-dark border me-1">${turmas} turmas</span>
                                <span class="badge bg-light text-dark border me-1">${alunos} alunos</span>
                                <span class="badge bg-light text-dark border">${notas} notas</span>
                            </div>
                        </div>
                        <p class="text-warning small mb-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            O ano actual passará a <strong>Anterior</strong> e todo o sistema usará este contexto.
                        </p>
                        ${turmas === 0 && alunos === 0 ? '<p class="text-danger small mb-0"><i class="bi bi-info-circle"></i> Este ano não tem turmas nem alunos — confirme se é o correcto.</p>' : ''}
                    </div>
                `,
                icon: 'warning',
                confirmText: '<i class="bi bi-check-circle"></i> Sim, activar',
                confirmColor: '#6366f1'
            });

            if (confirmed) {
                showLoading('Activando ano lectivo...');

                fetch('{{ route("configuracoes.anos-lectivos.activar") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ nome: nome })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        let msg = data.message || 'Ano lectivo activado com sucesso.';
                        if (data.avisos && data.avisos.length) {
                            msg += '\n\n' + data.avisos.join('\n');
                        }
                        showNotification('success', msg);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification('error', data.message || 'Erro ao activar ano lectivo');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    hideLoading();
                    showNotification('error', 'Ocorreu um erro ao activar o ano lectivo.');
                });
            }
        }

        // Sincronizar color pickers com inputs de texto
        ['pdf_color_primary', 'pdf_color_table_header'].forEach(function(fieldName) {
            const picker = document.getElementById(fieldName + '_picker');
            const text = document.getElementById(fieldName + '_text');

            if (picker && text) {
                // Picker → Text
                picker.addEventListener('input', function() {
                    text.value = this.value;
                });

                // Text → Picker (validar formato hex)
                text.addEventListener('input', function() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                        picker.value = this.value;
                    }
                });
            }
        });

        // Função para remover logo (com confirmação SweetAlert2)
        async function handleRemoveLogo() {
            const confirmed = await confirmAction({
                title: 'Remover Logo?',
                html: `
                    <div class="text-start">
                        <p class="mb-2">O logo da instituição será removido de:</p>
                        <ul class="small">
                            <li>PDFs gerados (pautas, boletins, relatórios)</li>
                            <li>Cabeçalhos de documentos oficiais</li>
                        </ul>
                        <p class="text-muted small mb-0">Poderá fazer upload de um novo logo a qualquer momento.</p>
                    </div>
                `,
                icon: 'warning',
                confirmText: '<i class="bi bi-trash"></i> Sim, remover',
                confirmColor: '#dc3545',
                dangerMode: true
            });

            if (confirmed) {
                showLoading('Removendo logo...');
                document.getElementById('formRemoverLogo').submit();
            }
        }

        // Função para limpar cache (com confirmação SweetAlert2)
        async function handleClearCache(form) {
            const confirmed = await confirmAction({
                title: 'Limpar Cache?',
                html: `
                    <p>O cache de configurações será limpo.</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle"></i> Isso força o sistema a recarregar todas as configurações do banco de dados.
                    </p>
                `,
                icon: 'question',
                confirmText: '<i class="bi bi-arrow-clockwise"></i> Sim, limpar',
                confirmColor: '#6c757d'
            });

            if (confirmed) {
                showLoading('Limpando cache...');
                form.submit();
            }
        }
    </script>

    {{-- Formulário separado para remover logo --}}
    <form id="formRemoverLogo" action="{{ route('configuracoes.remover-logo') }}" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    @endpush
</x-app-layout>
