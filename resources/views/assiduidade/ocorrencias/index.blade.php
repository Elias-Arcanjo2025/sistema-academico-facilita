<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Ocorrências</h2>
            @can('ocorrencias.registar')
                @if($podeEditar ?? true)
                    <a href="{{ route('ocorrencias.create', ['ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Nova ocorrência
                    </a>
                @endif
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Ocorrências', 'url' => route('ocorrencias.index')],
            ]" />

            <x-tip-card icon="bi-exclamation-triangle" variant="info" title="Disciplina escolar" id="tip-ocorrencias-index" compact>
                Registe e acompanhe ocorrências disciplinares por aluno e turma. Filtre por estado, turma ou severidade; abra um registo para ver detalhes e resolver.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('ocorrencias.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="bi bi-flag me-1"></i>Estado</label>
                            <select name="estado" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <option value="aberta" @selected(($filtros['estado'] ?? '') === 'aberta')>Abertas</option>
                                <option value="resolvida" @selected(($filtros['estado'] ?? '') === 'resolvida')>Resolvidas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="bi bi-people me-1"></i>Turma</label>
                            <select name="turma_id" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected((string) ($filtros['turma_id'] ?? '') === (string) $turma->id)>{{ $turma->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="bi bi-lightning me-1"></i>Severidade</label>
                            <select name="severidade" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach($severidades as $valor => $label)
                                    <option value="{{ $valor }}" @selected(($filtros['severidade'] ?? '') === $valor)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('ocorrencias.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                    @if(!($podeEditar ?? true))
                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-lock-fill me-1"></i>Ano anterior em modo consulta — só o administrador pode registar ou alterar ocorrências.
                        </p>
                    @endif
                </div>
            </div>

            @if($ocorrencias->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-exclamation-triangle"
                            message="Nenhuma ocorrência encontrada."
                            :create-url="route('ocorrencias.create', ['ano_lectivo_id' => $anoLectivoId])"
                            create-label="Registar primeira ocorrência"
                            :can-create="auth()->user()->can('ocorrencias.registar') && ($podeEditar ?? true)"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Lista de ocorrências"
                    icon="bi-exclamation-triangle"
                    :badge="$ocorrencias->total()"
                    :pagination="$ocorrencias">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Aluno</th>
                                <th class="text-nowrap">Turma</th>
                                <th class="text-nowrap">Data</th>
                                <th class="text-nowrap">Tipo</th>
                                <th class="text-nowrap">Severidade</th>
                                <th class="text-nowrap">Estado</th>
                                <th class="text-nowrap text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ocorrencias as $oc)
                                <tr>
                                    <td class="text-nowrap">{{ $oc->aluno?->nome ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $oc->turma?->nome ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $oc->data->format('d/m/Y') }}</td>
                                    <td class="text-nowrap">{{ $tipos[$oc->tipo] ?? $oc->tipo }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-secondary">{{ $severidades[$oc->severidade] ?? $oc->severidade }}</span>
                                    </td>
                                    <td class="text-nowrap">
                                        @if($oc->estado === 'aberta')
                                            <span class="badge bg-warning text-dark">Aberta</span>
                                        @else
                                            <span class="badge bg-success">Resolvida</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('ocorrencias.show', $oc) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            @endif
        </div>
    </div>
</x-app-layout>
