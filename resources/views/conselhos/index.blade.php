<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-people-fill me-2 text-primary"></i>Conselhos de Classe
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Conselhos de Classe', 'url' => route('conselhos.index')]
            ]" />

            <x-tip-card icon="bi-people-fill" variant="warning" title="Conselhos de Classe" id="tip-conselhos-index">
                Cada conselho pertence a uma <strong>turma</strong>, a um <strong>trimestre</strong> e a um <strong>ano lectivo</strong> (ex.: TI10AT · 2.º trimestre · 2025-2026).
                O Conselho de Notas analisa o desempenho da turma e regista deliberações ao final de cada trimestre.
                Os ajustes devem <strong>beneficiar ou manter</strong> a classificação do estudante (aumentos, arredondamentos favoráveis).
                Reduzir notas só é permitido em casos excepcionais documentados (erro de cálculo, fraude ou processo disciplinar).
                Directores de turma, coordenadores e administração podem gerir os conselhos do ano lectivo actual.
            </x-tip-card>

            @if($modoConsulta)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-eye me-2"></i>
                    <strong>Modo consulta:</strong> está a visualizar o ano lectivo <strong>{{ $anoLectivo }}</strong>.
                    Não é possível iniciar, ajustar ou concluir conselhos de anos anteriores.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

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

            {{-- Filtros: Ano Lectivo + Turma --}}
            <div class="card shadow-soft mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('conselhos.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                class="mb-0"
                            />
                        </div>
                        <div class="col-md-4">
                            <label for="turma_id" class="form-label">
                                <i class="bi bi-people me-1"></i>Turma
                            </label>
                            <select name="turma_id" id="turma_id" class="form-select form-select-sm">
                                <option value="">— Selecione uma turma —</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}"
                                        {{ (string) request('turma_id') === (string) $turma->id ? 'selected' : '' }}>
                                        {{ $turma->nome }} — {{ $turma->curso->nome }}
                                    </option>
                                @endforeach
                            </select>
                            @if($turmas->isEmpty())
                                <small class="text-muted">Nenhuma turma disponível neste ano lectivo.</small>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('conselhos.index', ['ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle me-1"></i>Limpar turma
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Informações da Turma Selecionada --}}
            @if($turmaSelecionada)
                <div class="card shadow-soft mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            {{ $turmaSelecionada->nome }} - {{ $anoLectivo }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Curso:</strong> {{ $turmaSelecionada->curso->nome }}
                            </div>
                            <div class="col-md-4">
                                <strong>Classe:</strong> {{ $turmaSelecionada->classe->nome ?? 'N/A' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Ano Lectivo:</strong> {{ $anoLectivo }}
                            </div>
                        </div>
                    </div>
                </div>

                @if($isAnoLectivoActual && $conselhoActual && !$modoConsulta)
                    <div class="card border-primary border-opacity-50 shadow-sm mb-4" id="conselho-trimestre-actual">
                        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <span class="badge bg-primary mb-2">Trimestre actual</span>
                                <h5 class="mb-1">{{ $trimestreActual }}.º Trimestre — {{ $anoLectivo }}</h5>
                                <p class="text-muted small mb-0">
                                    @if($conselhoActual->isEmCurso())
                                        O conselho está em curso. Continue os ajustes de notas.
                                    @elseif($conselhoActual->isConcluido())
                                        Este trimestre já foi concluído. Pode consultar o histórico.
                                    @else
                                        Inicie o conselho do trimestre actual para registar deliberações e ajustes.
                                    @endif
                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('conselhos.show', $conselhoActual) }}" class="btn btn-primary">
                                    <i class="bi bi-eye me-1"></i>
                                    {{ $conselhoActual->isPendente() ? 'Abrir conselho' : 'Ver conselho' }}
                                </a>
                                @can('iniciar', $conselhoActual)
                                    @if($conselhoActual->isPendente())
                                        <form action="{{ route('conselhos.iniciar', $conselhoActual) }}" method="POST" class="d-inline form-iniciar-conselho" data-trimestre="{{ $conselhoActual->trimestre }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-play-circle me-1"></i>Iniciar agora
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Tabela de Conselhos --}}
                <div class="card shadow-soft">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar3 me-2"></i>Conselhos dos Trimestres
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Trimestre</th>
                                        <th>Estado</th>
                                        <th>Data Início</th>
                                        <th>Data Conclusão</th>
                                        <th>Total de Ajustes</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($conselhos as $conselho)
                                        @php
                                            $ehTrimestreActual = $isAnoLectivoActual
                                                && $trimestreActual
                                                && (int) $conselho->trimestre === (int) $trimestreActual;
                                            $podeIniciarEste = $ehTrimestreActual
                                                || auth()->user()->podeIgnorarTrimestreFechado();
                                        @endphp
                                        <tr class="{{ $ehTrimestreActual ? 'table-primary' : '' }}" @if($ehTrimestreActual) id="linha-trimestre-actual" @endif>
                                            <td>
                                                <strong>{{ $conselho->trimestre }}º Trimestre</strong>
                                                @if($ehTrimestreActual)
                                                    <span class="badge bg-primary ms-1">Actual</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($conselho->isPendente())
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-clock me-1"></i>Pendente
                                                    </span>
                                                @elseif($conselho->isEmCurso())
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-play-circle me-1"></i>Em Curso
                                                    </span>
                                                @elseif($conselho->isConcluido())
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Concluído
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $conselho->data_inicio ? $conselho->data_inicio->format('d/m/Y H:i') : '-' }}
                                            </td>
                                            <td>
                                                {{ $conselho->data_conclusao ? $conselho->data_conclusao->format('d/m/Y H:i') : '-' }}
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $conselho->total_ajustes }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('conselhos.show', $conselho) }}"
                                                   class="btn btn-sm btn-primary"
                                                   title="Ver Detalhes">
                                                    <i class="bi bi-eye me-1"></i>Ver
                                                </a>

                                                @can('iniciar', $conselho)
                                                    @if($conselho->isPendente() && !$modoConsulta && $podeIniciarEste)
                                                        <form action="{{ route('conselhos.iniciar', $conselho) }}"
                                                              method="POST"
                                                              class="d-inline form-iniciar-conselho"
                                                              data-trimestre="{{ $conselho->trimestre }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="bi bi-play-circle me-1"></i>Iniciar
                                                            </button>
                                                        </form>
                                                    @elseif($conselho->isPendente() && !$modoConsulta && $isAnoLectivoActual && !$ehTrimestreActual)
                                                        <span class="text-muted small" title="Apenas o trimestre actual pode ser iniciado">
                                                            <i class="bi bi-lock"></i>
                                                        </span>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Legenda de Estados --}}
                <div class="card shadow-soft mt-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Legenda de Estados</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <span class="badge bg-secondary me-2">Pendente</span>
                                O conselho ainda não foi iniciado
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-warning text-dark me-2">Em Curso</span>
                                Conselho em andamento, ajustes podem ser feitos
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-success me-2">Concluído</span>
                                Conselho finalizado, ajustes bloqueados
                            </div>
                        </div>
                    </div>
                </div>
            @elseif(request('turma_id'))
                <x-empty-state
                    icon="bi-people-fill"
                    message="A turma seleccionada não pertence ao ano lectivo {{ $anoLectivo }}. Escolha outra turma ou altere o ano lectivo."
                />
            @else
                {{-- Mensagem quando nenhuma turma selecionada --}}
                <x-empty-state
                    icon="bi-people-fill"
                    message="Seleccione o ano lectivo e uma turma para visualizar os conselhos de classe"
                />
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const linhaActual = document.getElementById('linha-trimestre-actual');
            if (linhaActual) {
                linhaActual.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Handler para iniciar conselho
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
        });
    </script>
    @endpush
</x-app-layout>
