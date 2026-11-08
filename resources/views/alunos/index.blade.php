<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Alunos</h2>
            @can('create', App\Models\Aluno::class)
                @if(!$modoConsulta)
                    <div class="d-flex gap-2">
                        <a href="{{ route('alunos.import') }}" class="btn btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Importar
                        </a>
                        @if(auth()->user()->hasRole('secretaria') && auth()->user()->can('secretaria.matriculas.gerir') && ! auth()->user()->temVisaoInstitucional())
                            <a href="{{ route('secretaria.matriculas.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> Nova matrícula
                            </a>
                        @else
                            <a href="{{ route('alunos.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> Novo Aluno
                            </a>
                        @endif
                    </div>
                @else
                    <button class="btn btn-secondary" disabled title="Modo consulta: apenas leitura">
                        <i class="bi bi-lock me-1"></i> Ações Bloqueadas
                    </button>
                @endif
            @else
                @can('secretaria.matriculas.gerir')
                    @if(!$modoConsulta)
                        <a href="{{ route('secretaria.matriculas.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Nova matrícula
                        </a>
                    @endif
                @endcan
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')]
            ]" />

            <x-tip-card icon="bi-person-badge" variant="primary" title="Gestão de Alunos" id="tip-alunos-index">
                Consulte e gerir todos os alunos matriculados. Pode pesquisar por nome ou número de processo, filtrar por turma, e importar alunos em lote através de ficheiro Excel. Clique num aluno para ver os detalhes completos.
            </x-tip-card>

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

            {{-- Indicador de Modo Consulta --}}
            @if($modoConsulta)
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Está a visualizar dados de um ano lectivo anterior. Apenas leitura permitida.
                    <a href="{{ route('alunos.index') }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('alunos.index') }}" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                            />
                        </div>
                        <div class="col-md-4">
                            <label for="turma_id" class="form-label">
                                <i class="bi bi-people me-1"></i>Turma
                            </label>
                            <select class="form-select form-select-sm" id="turma_id" name="turma_id" onchange="this.form.submit()">
                                <option value="">Todas as turmas</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" {{ $turmaId == $turma->id ? 'selected' : '' }}>
                                        {{ $turma->nome }} - {{ $turma->curso->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="situacao" class="form-label">
                                <i class="bi bi-trophy me-1"></i>Situação Acadêmica
                            </label>
                            <select class="form-select form-select-sm" id="situacao" name="situacao" onchange="this.form.submit()">
                                <option value="">Todas as situações</option>
                                <option value="matriculado" {{ request('situacao') == 'matriculado' ? 'selected' : '' }}>Matriculado</option>
                                <option value="aprovado" {{ request('situacao') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                <option value="reprovado" {{ request('situacao') == 'reprovado' ? 'selected' : '' }}>Reprovado</option>
                                <option value="desistente" {{ request('situacao') == 'desistente' ? 'selected' : '' }}>Desistente</option>
                                <option value="transferido" {{ request('situacao') == 'transferido' ? 'selected' : '' }}>Transferido</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if($alunos->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-person"
                            message="Nenhum aluno encontrado."
                            :create-url="route('alunos.create')"
                            create-label="Criar primeiro aluno"
                            :can-create="Gate::allows('create', App\Models\Aluno::class)"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Lista de Alunos"
                    icon="bi-person"
                    :badge="$alunos->total()"
                    :pagination="$alunos">
                    <table>
                        <thead>
                            <tr>
                                <th>N.º Processo</th>
                                <th>Nome</th>
                                <th>Turma</th>
                                <th>N.º Ordem</th>
                                <th>Género</th>
                                <th>Situação Académica</th>
                                <th>Estado</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alunos as $aluno)
                                <tr>
                                    <td><code>{{ $aluno->numero_processo }}</code></td>
                                    <td class="fw-semibold text-wrap" style="min-width: 11rem;">{{ $aluno->nome }}</td>
                                    <td>
                                        @if($aluno->turma)
                                            <a href="{{ route('turmas.show', $aluno->turma) }}" class="fw-semibold text-decoration-none">
                                                {{ $aluno->turma->nome }}
                                            </a>
                                            <div class="small text-muted">{{ $aluno->turma->curso->nome }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $aluno->numero_ordem }}</td>
                                    <td>
                                        @if($aluno->genero === 'M')
                                            <span class="badge bg-primary">Masculino</span>
                                        @elseif($aluno->genero === 'F')
                                            <span class="badge bg-pink">Feminino</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{!! $aluno->situacao_academica_badge !!}</td>
                                    <td>
                                        @if($aluno->activo)
                                            <span class="badge bg-success">Ativo</span>
                                        @else
                                            <span class="badge bg-secondary">Inativo</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <x-action-buttons
                                            :view-url="Gate::allows('view', $aluno) ? route('alunos.show', $aluno) : null"
                                            :edit-url="Gate::allows('update', $aluno) ? route('alunos.edit', $aluno) : null"
                                            :delete-route="Gate::allows('delete', $aluno) ? 'alunos.destroy' : null"
                                            :delete-id="$aluno->id"
                                            :delete-name="'o aluno ' . $aluno->nome . ($aluno->notas()->exists() ? ' (inclui ' . $aluno->notas()->count() . ' avaliação(ões))' : '')"
                                        />
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
