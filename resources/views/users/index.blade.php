<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Gestão de Utilizadores</h2>
            <a href="{{ route('users.pendentes') }}" class="btn btn-warning">
                <i class="bi bi-hourglass-split"></i> Pendentes
                @if($contagens['pendente'] > 0)
                    <span class="badge bg-danger">{{ $contagens['pendente'] }}</span>
                @endif
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-people-fill" variant="warning" title="Gestão de Utilizadores" id="tip-users-index">
                Contas organizadas por grupo: <strong>funcionários</strong> (professores, secretaria, direcção),
                <strong>alunos</strong> (portal estudantil) e <strong>encarregados</strong> (portal familiar).
                Use os separadores abaixo para não misturar os tipos.
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

            {{-- Grupos principais --}}
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <a href="{{ route('users.index', array_filter(['tipo' => 'funcionario', 'estado' => $estado, 'pesquisa' => $pesquisa])) }}"
                       class="card h-100 text-decoration-none users-group-card {{ $tipo === 'funcionario' ? 'border-primary shadow-sm' : 'border-0 shadow-sm' }}">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:3rem;height:3rem;background:rgba(55,65,81,.08);color:#374151">
                                <i class="bi bi-briefcase fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Funcionários</div>
                                <div class="h4 mb-0 text-dark">{{ $contagens['funcionarios'] }}</div>
                                <div class="small text-muted">Professores, secretaria, direcção</div>
                            </div>
                            @if($tipo === 'funcionario')
                                <i class="bi bi-check-circle-fill text-primary"></i>
                            @endif
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('users.index', array_filter(['tipo' => 'aluno', 'estado' => $estado, 'pesquisa' => $pesquisa])) }}"
                       class="card h-100 text-decoration-none users-group-card {{ $tipo === 'aluno' ? 'border-info shadow-sm' : 'border-0 shadow-sm' }}">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:3rem;height:3rem;background:rgba(6,182,212,.1);color:#0891b2">
                                <i class="bi bi-mortarboard fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Alunos</div>
                                <div class="h4 mb-0 text-info">{{ $contagens['alunos'] }}</div>
                                <div class="small text-muted">Portal estudantil</div>
                            </div>
                            @if($tipo === 'aluno')
                                <i class="bi bi-check-circle-fill text-info"></i>
                            @endif
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('users.index', array_filter(['tipo' => 'encarregado', 'estado' => $estado, 'pesquisa' => $pesquisa])) }}"
                       class="card h-100 text-decoration-none users-group-card {{ $tipo === 'encarregado' ? 'border-success shadow-sm' : 'border-0 shadow-sm' }}">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:3rem;height:3rem;background:rgba(16,185,129,.1);color:#059669">
                                <i class="bi bi-people fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Encarregados</div>
                                <div class="h4 mb-0 text-success">{{ $contagens['encarregados'] }}</div>
                                <div class="small text-muted">Portal familiar</div>
                            </div>
                            @if($tipo === 'encarregado')
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @endif
                        </div>
                    </a>
                </div>
            </div>

            {{-- Estados rápidos --}}
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="{{ route('users.index', array_filter(['tipo' => $tipo === 'todos' ? 'todos' : $tipo, 'pesquisa' => $pesquisa])) }}"
                   class="btn btn-sm {{ !$estado ? 'btn-dark' : 'btn-outline-secondary' }}">
                    Todos os estados · {{ $contagens['total'] }}
                </a>
                <a href="{{ route('users.index', array_filter(['tipo' => $tipo, 'estado' => 'activo', 'pesquisa' => $pesquisa])) }}"
                   class="btn btn-sm {{ $estado === 'activo' ? 'btn-success' : 'btn-outline-success' }}">
                    Activos · {{ $contagens['activo'] }}
                </a>
                <a href="{{ route('users.index', array_filter(['tipo' => $tipo, 'estado' => 'pendente', 'pesquisa' => $pesquisa])) }}"
                   class="btn btn-sm {{ $estado === 'pendente' ? 'btn-warning' : 'btn-outline-warning' }}">
                    Pendentes · {{ $contagens['pendente'] }}
                </a>
                <a href="{{ route('users.index', ['tipo' => 'todos', 'estado' => $estado, 'pesquisa' => $pesquisa]) }}"
                   class="btn btn-sm {{ $tipo === 'todos' ? 'btn-primary' : 'btn-outline-primary' }} ms-auto">
                    <i class="bi bi-layers me-1"></i> Ver todos (agrupados)
                </a>
            </div>

            {{-- Filtros --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form action="{{ route('users.index') }}" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Pesquisar</label>
                            <input type="text" class="form-control" name="pesquisa" value="{{ $pesquisa }}"
                                   placeholder="Nome ou email...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Grupo</label>
                            <select class="form-select" name="tipo">
                                <option value="funcionario" @selected($tipo === 'funcionario')>Funcionários</option>
                                <option value="aluno" @selected($tipo === 'aluno')>Alunos</option>
                                <option value="encarregado" @selected($tipo === 'encarregado')>Encarregados</option>
                                <option value="todos" @selected($tipo === 'todos')>Todos (agrupados)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos os estados</option>
                                @foreach($estados as $key => $label)
                                    <option value="{{ $key }}" @selected($estado === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="{{ route('users.index', ['tipo' => 'funcionario']) }}" class="btn btn-outline-secondary" title="Limpar">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $tituloGrupo = match ($tipo) {
                    'aluno' => 'Alunos',
                    'encarregado' => 'Encarregados',
                    'todos' => 'Todos os utilizadores',
                    default => 'Funcionários',
                };
                $grupoActual = null;
            @endphp

            <x-data-table :pagination="$users" :title="$tituloGrupo" icon="bi-people" :badge="$users->total()">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Tipo</th>
                            <th class="text-nowrap">Nome</th>
                            <th class="text-nowrap">Email</th>
                            <th class="text-nowrap">Cargos / Detalhe</th>
                            <th class="text-nowrap">Estado</th>
                            <th class="text-nowrap">Registado em</th>
                            <th class="text-nowrap text-end">Acções</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php
                                $isAluno = $user->eUtilizadorAluno();
                                $isEncarregado = ! $isAluno && $user->eUtilizadorEncarregado();
                                $grupo = $isAluno ? 'aluno' : ($isEncarregado ? 'encarregado' : 'funcionario');
                            @endphp

                            @if($tipo === 'todos' && $grupo !== $grupoActual)
                                @php $grupoActual = $grupo; @endphp
                                <tr class="table-light">
                                    <td colspan="7" class="py-2">
                                        <span class="fw-semibold small text-uppercase letter-spacing">
                                            @if($grupo === 'funcionario')
                                                <i class="bi bi-briefcase me-1"></i> Funcionários
                                            @elseif($grupo === 'encarregado')
                                                <i class="bi bi-people me-1"></i> Encarregados
                                            @else
                                                <i class="bi bi-mortarboard me-1"></i> Alunos
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endif

                            <tr>
                                <td class="text-nowrap">
                                    @if($isAluno)
                                        <span class="badge bg-info-subtle text-info-emphasis">
                                            <i class="bi bi-mortarboard"></i> Aluno
                                        </span>
                                    @elseif($isEncarregado)
                                        <span class="badge bg-success-subtle text-success-emphasis">
                                            <i class="bi bi-people"></i> Encarregado
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                            <i class="bi bi-briefcase"></i> Funcionário
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $user->name }}</strong>
                                    @if($isAluno && $user->aluno?->numero_processo)
                                        <br><small class="text-muted">Proc. {{ $user->aluno->numero_processo }}</small>
                                    @elseif($isEncarregado && $user->encarregado?->telefone)
                                        <br><small class="text-muted">{{ $user->encarregado->telefone }}</small>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $user->email }}</td>
                                <td>
                                    @if($isAluno)
                                        @if($user->aluno?->turma)
                                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                                                {{ $user->aluno->turma->nome }}
                                                @if($user->aluno->turma->curso)
                                                    · {{ $user->aluno->turma->curso->nome }}
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-muted small">Sem turma associada</span>
                                        @endif
                                    @elseif($isEncarregado)
                                        <span class="text-muted small">Portal do encarregado</span>
                                    @else
                                        @forelse($user->roles as $role)
                                            <span class="badge bg-dark">{{ \App\Support\CargosInstitucionais::rotulo($role->name) }}</span>
                                        @empty
                                            <span class="text-muted small">Sem cargo</span>
                                        @endforelse
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @switch($user->estado)
                                        @case('activo')
                                            <span class="badge bg-success">Activo</span>
                                            @break
                                        @case('pendente')
                                            <span class="badge bg-warning">Pendente</span>
                                            @break
                                        @case('rejeitado')
                                            <span class="badge bg-danger">Rejeitado</span>
                                            @break
                                        @case('inactivo')
                                            <span class="badge bg-secondary">Inactivo</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-nowrap">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-nowrap text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('users.show', $user) }}" class="btn btn-outline-primary" title="Ver detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($user->estado === 'pendente')
                                            <form action="{{ route('users.aprovar', $user) }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleAprovarConta(this, '{{ $user->name }}');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" title="Aprovar">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('users.form-rejeitar', $user) }}" class="btn btn-outline-danger" title="Rejeitar">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        @endif
                                        @if(auth()->user()->hasRole('admin_full') && $isAluno && $user->id !== auth()->id() && !$user->hasRole('admin_full'))
                                            <form action="{{ route('users.eliminar-aluno', $user) }}" method="POST" class="d-inline"
                                                  onsubmit="event.preventDefault(); handleEliminarAluno(this, @json($user->name), {{ (int) ($user->aluno?->notas_count ?? 0) }});">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="confirmar_eliminacao" value="0">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar aluno permanentemente">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state
                                        icon="bi-person"
                                        message="Nenhum utilizador neste grupo."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-data-table>
        </div>
    </div>

    @push('scripts')
    <script>
        async function handleAprovarConta(form, userName) {
            const confirmed = await confirmAction({
                title: 'Aprovar Conta de Utilizador?',
                html: `<p>Deseja aprovar a conta de <strong>${userName}</strong>?</p><p class="text-muted"><small>O utilizador poderá aceder ao sistema após aprovação.</small></p>`,
                icon: 'question',
                confirmText: '<i class="bi bi-check-lg"></i> Sim, aprovar conta'
            });

            if (confirmed) {
                showLoading('Aprovando conta...');
                form.submit();
            }
        }

        async function handleEliminarAluno(form, userName, notasCount) {
            const notasInfo = notasCount > 0
                ? `<p class="text-danger mb-2"><strong>Atenção:</strong> este aluno tem <strong>${notasCount}</strong> avaliação(ões) lançada(s). Todas serão eliminadas.</p>`
                : '<p class="text-muted">Este aluno não tem avaliações registadas.</p>';

            const confirmed = await confirmAction({
                title: 'Eliminar aluno permanentemente?',
                html: `<p>Deseja eliminar <strong>${userName}</strong> e a respectiva ficha académica?</p>${notasInfo}<p class="text-muted mb-0"><small>Esta acção é irreversível e só pode ser feita pelo administrador do sistema.</small></p>`,
                icon: 'warning',
                confirmText: '<i class="bi bi-trash"></i> Sim, eliminar tudo',
                confirmColor: '#dc3545'
            });

            if (confirmed) {
                form.querySelector('[name="confirmar_eliminacao"]').value = '1';
                showLoading('A eliminar aluno e avaliações...');
                form.submit();
            }
        }
    </script>
    @endpush
</x-app-layout>
