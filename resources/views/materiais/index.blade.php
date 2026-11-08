<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Materiais académicos</h2>
            @can('materiais.gerir')
                <div class="d-flex gap-2">
                    @can('pedagogico.ia.gerar')
                        <a href="{{ route('materiais.gerar-ia') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-magic"></i> Gerar com IA
                        </a>
                    @endcan
                    <a href="{{ route('materiais.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload"></i> Carregar
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Materiais', 'url' => route('materiais.index')],
            ]" />

            <x-tip-card icon="bi-folder2-open" variant="info" title="Repositório de materiais" id="tip-materiais-index" compact>
                Partilhe PDFs e ficheiros por disciplina ou turma (didáctico). Documentos administrativos do aluno (BI, certidão) ficam na Secretaria. O banco de questões serve só para itens de prova.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('materiais.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Ano lectivo</label>
                            <select name="ano_lectivo_id" class="form-select form-select-sm">
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" @selected((string)$anoLectivoId === (string)$id)>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-book me-1"></i>Disciplina</label>
                            <select name="disciplina_id" class="form-select form-select-sm">
                                <option value="">Todas as disciplinas</option>
                                @foreach($disciplinas as $d)
                                    <option value="{{ $d->id }}" @selected(($filtros['disciplina_id'] ?? '') == $d->id)>{{ $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-people me-1"></i>Turma</label>
                            <select name="turma_id" class="form-select form-select-sm">
                                <option value="">Todas as turmas</option>
                                @foreach($turmas as $t)
                                    <option value="{{ $t->id }}" @selected(($filtros['turma_id'] ?? '') == $t->id)>{{ $t->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('materiais.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if($materiais->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-folder2-open"
                            message="Nenhum material encontrado."
                            :create-url="route('materiais.create')"
                            create-label="Carregar primeiro material"
                            :can-create="auth()->user()->can('materiais.gerir')"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Lista de materiais"
                    icon="bi-folder2-open"
                    :badge="$materiais->total()"
                    :pagination="$materiais">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Título</th>
                                <th class="text-nowrap">Âmbito</th>
                                <th class="text-nowrap">Tamanho</th>
                                <th class="text-nowrap">Estado</th>
                                <th class="text-nowrap text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materiais as $m)
                                <tr>
                                    <td>{{ $m->titulo }}</td>
                                    <td class="text-nowrap">
                                        {{ $m->disciplina?->nome ?? 'Geral' }}
                                        @if($m->turma)
                                            <br><small class="text-muted">{{ $m->turma->nome }}</small>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ number_format($m->tamanho / 1024, 0) }} KB</td>
                                    <td class="text-nowrap">
                                        @if($m->publicado)
                                            <span class="badge bg-success">Publicado</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Rascunho</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('materiais.download', $m) }}" class="btn btn-sm btn-outline-primary" title="Descarregar">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            @can('materiais.gerir')
                                                <form method="POST" action="{{ route('materiais.destroy', $m) }}" class="d-inline"
                                                      data-confirm-title="Remover material"
                                                      data-confirm="Remover este material?"
                                                      data-confirm-ok="Sim, remover"
                                                      data-confirm-danger="1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
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
