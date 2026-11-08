<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Ficheiros de Pautas</h2>
            @can('create', App\Models\PautaArquivo::class)
                <div class="d-flex gap-2">
                    <a href="{{ route('pauta-arquivos.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-magic"></i> Arquivar pauta
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-folder-fill" variant="info" title="Ficheiros de Pautas" id="tip-pautaarquivos-index">
                Arquive pautas geradas pelo sistema (com um clique) ou envie ficheiros Excel externos. Organize por curso, turma, disciplina e trimestre — cada arquivo mantém histórico de versões.
            </x-tip-card>

            @isset($estatisticas)
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body text-center py-3">
                                <div class="fs-3 fw-bold text-primary">{{ $estatisticas['total'] }}</div>
                                <small class="text-muted">Ficheiros (filtro actual)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary bg-opacity-10 h-100">
                            <div class="card-body text-center py-3">
                                <div class="fs-3 fw-bold text-primary">{{ $estatisticas['sistema'] }}</div>
                                <small class="text-muted">Gerados pelo sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-secondary bg-opacity-10 h-100">
                            <div class="card-body text-center py-3">
                                <div class="fs-3 fw-bold">{{ $estatisticas['externa'] }}</div>
                                <small class="text-muted">Uploads externos</small>
                            </div>
                        </div>
                    </div>
                </div>
            @endisset

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

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('pauta-arquivos.index') }}" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="curso_id" class="form-label">Curso</label>
                            <select class="form-select" id="curso_id" name="curso_id" onchange="carregarTurmas(this.value)">
                                <option value="">Todos os cursos</option>
                                @foreach($cursos as $curso)
                                    <option value="{{ $curso->id }}" {{ request('curso_id') == $curso->id ? 'selected' : '' }}>
                                        {{ $curso->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="turma_id" class="form-label">Turma</label>
                            <select class="form-select" id="turma_id" name="turma_id">
                                <option value="">Todas as turmas</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" {{ request('turma_id') == $turma->id ? 'selected' : '' }}>
                                        {{ $turma->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="ano_lectivo" class="form-label">Ano Lectivo</label>
                            <select class="form-select" id="ano_lectivo" name="ano_lectivo">
                                <option value="">Todos</option>
                                @foreach($anosLectivos as $ano)
                                    <option value="{{ $ano }}" {{ request('ano_lectivo') == $ano ? 'selected' : '' }}>
                                        {{ $ano }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="trimestre" class="form-label">Trimestre</label>
                            <select class="form-select" id="trimestre" name="trimestre">
                                <option value="">Todos</option>
                                <option value="0" {{ request('trimestre') === '0' ? 'selected' : '' }}>Anual</option>
                                <option value="1" {{ request('trimestre') == '1' ? 'selected' : '' }}>1.º Trimestre</option>
                                <option value="2" {{ request('trimestre') == '2' ? 'selected' : '' }}>2.º Trimestre</option>
                                <option value="3" {{ request('trimestre') == '3' ? 'selected' : '' }}>3.º Trimestre</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="tipo_pauta" class="form-label">Tipo de Pauta</label>
                            <select class="form-select" id="tipo_pauta" name="tipo_pauta">
                                <option value="">Todos</option>
                                @foreach(\App\Models\PautaArquivo::getTiposPauta() as $key => $label)
                                    <option value="{{ $key }}" {{ request('tipo_pauta') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="origem" class="form-label">Origem</label>
                            <select class="form-select" id="origem" name="origem">
                                <option value="">Todas</option>
                                @foreach(\App\Models\PautaArquivo::getOrigensArquivo() as $key => $label)
                                    <option value="{{ $key }}" {{ request('origem') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-10"></div>

                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="{{ route('pauta-arquivos.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela de Ficheiros -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-folder-fill"></i> Ficheiros Encontrados
                        <span class="badge bg-secondary">{{ $arquivos->total() }}</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if($arquivos->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-nowrap">Curso</th>
                                        <th class="text-nowrap">Turma</th>
                                        <th class="text-nowrap">Ano Lectivo</th>
                                        <th class="text-nowrap">Trimestre</th>
                                        <th class="text-nowrap">Tipo</th>
                                        <th class="text-nowrap">Disciplina</th>
                                        <th class="text-nowrap">Ficheiro</th>
                                        <th class="text-nowrap text-center">Versão</th>
                                        <th class="text-nowrap text-center">Tamanho</th>
                                        <th class="text-nowrap text-center">Origem</th>
                                        <th class="text-nowrap">Enviado por</th>
                                        <th class="text-nowrap">Data</th>
                                        <th class="text-nowrap text-center">Acções</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($arquivos as $arquivo)
                                        <tr>
                                            <td class="text-nowrap">{{ $arquivo->curso->nome }}</td>
                                            <td class="text-nowrap">
                                                @if($arquivo->turma)
                                                    {{ $arquivo->turma->nome }}
                                                @elseif($arquivo->turma_manual)
                                                    {{ $arquivo->turma_manual }}
                                                    <span class="badge bg-secondary small">Manual</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $arquivo->ano_lectivo }}</td>
                                            <td class="text-nowrap">
                                                @if($arquivo->trimestre === 0)
                                                    <span class="badge bg-info">Anual</span>
                                                @elseif($arquivo->trimestre === 1)
                                                    <span class="badge bg-primary">1.º Trim</span>
                                                @elseif($arquivo->trimestre === 2)
                                                    <span class="badge bg-warning">2.º Trim</span>
                                                @elseif($arquivo->trimestre === 3)
                                                    <span class="badge bg-success">3.º Trim</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $arquivo->nome_tipo }}</td>
                                            <td class="text-nowrap">
                                                @if($arquivo->disciplina)
                                                    {{ $arquivo->disciplina->nome }}
                                                @elseif($arquivo->disciplina_manual)
                                                    {{ $arquivo->disciplina_manual }}
                                                    <span class="badge bg-secondary small">Manual</span>
                                                @else
                                                    Turma Completa
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <i class="bi bi-file-earmark-excel text-success"></i>
                                                {{ Str::limit($arquivo->nome_original, 30) }}
                                            </td>
                                            <td class="text-nowrap text-center">
                                                <span class="badge bg-secondary">{{ $arquivo->versao_formatada }}</span>
                                            </td>
                                            <td class="text-nowrap text-center">
                                                {{ $arquivo->tamanho_formatado }}
                                            </td>
                                            <td class="text-nowrap text-center">
                                                @if($arquivo->origem === 'sistema')
                                                    <span class="badge bg-primary">Sistema</span>
                                                @else
                                                    <span class="badge bg-secondary">Externa</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $arquivo->uploadedBy->name }}</td>
                                            <td class="text-nowrap">
                                                {{ $arquivo->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="text-nowrap text-center">
                                                <div class="btn-group btn-group-sm">
                                                    @can('view', $arquivo)
                                                        <a href="{{ route('pauta-arquivos.show', $arquivo) }}"
                                                           class="btn btn-outline-primary"
                                                           title="Ver Detalhes">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    @endcan

                                                    @can('download', $arquivo)
                                                        <a href="{{ route('pauta-arquivos.download', $arquivo) }}"
                                                           class="btn btn-outline-success"
                                                           title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    @endcan

                                                    @can('update', $arquivo)
                                                        <a href="{{ route('pauta-arquivos.edit', $arquivo) }}"
                                                           class="btn btn-outline-warning"
                                                           title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    @endcan

                                                    @can('delete', $arquivo)
                                                        <form action="{{ route('pauta-arquivos.destroy', $arquivo) }}"
                                                              method="POST"
                                                              class="d-inline"
                                                              onsubmit="event.preventDefault(); handleDeactivatePauta(this);">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="btn btn-outline-danger"
                                                                    title="Eliminar">
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
                        </div>

                        <!-- Paginação -->
                        <div class="card-footer">
                            {{ $arquivos->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="mt-3 text-muted">Nenhum ficheiro encontrado.</p>
                            @can('create', App\Models\PautaArquivo::class)
                                <a href="{{ route('pauta-arquivos.create') }}" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-lg"></i> Enviar Primeiro Ficheiro
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Handler para confirmação de desactivação de pauta
        async function handleDeactivatePauta(form) {
            const confirmed = await confirmAction({
                title: 'Desactivar Ficheiro de Pauta?',
                html: '<p>Tem a certeza que deseja desactivar este ficheiro?</p><p class="text-muted"><small>O ficheiro ficará inativo mas não será eliminado.</small></p>',
                icon: 'warning',
                confirmText: '<i class="bi bi-trash"></i> Sim, desactivar'
            });

            if (confirmed) {
                showLoading('Desactivando ficheiro...');
                form.submit();
            }
        }

        function carregarTurmas(cursoId) {
            const select = document.getElementById('turma_id');
            select.innerHTML = '<option value="">A carregar...</option>';

            if (!cursoId) {
                select.innerHTML = '<option value="">Todas as turmas</option>';
                return;
            }

            fetch(`{{ route('pauta-arquivos.turmas') }}?curso_id=${cursoId}`)
                .then(response => response.json())
                .then(data => {
                    select.innerHTML = '<option value="">Todas as turmas</option>';
                    data.forEach(turma => {
                        const selected = '{{ request("turma_id") }}' == turma.id ? 'selected' : '';
                        select.innerHTML += `<option value="${turma.id}" ${selected}>${turma.nome}</option>`;
                    });
                })
                .catch(error => {
                    select.innerHTML = '<option value="">Erro ao carregar</option>';
                    console.error('Erro:', error);
                });
        }

        // Carregar turmas automaticamente se curso estiver selecionado
        document.addEventListener('DOMContentLoaded', function() {
            const cursoSelect = document.getElementById('curso_id');
            if (cursoSelect.value) {
                carregarTurmas(cursoSelect.value);
            }
        });
    </script>
    @endpush
</x-app-layout>
