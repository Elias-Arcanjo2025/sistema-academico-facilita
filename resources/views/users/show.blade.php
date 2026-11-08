<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Utilizador</h2>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row">
                <!-- Informações do Utilizador -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person"></i> Informações</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Nome</dt>
                                <dd class="col-sm-8">{{ $user->name }}</dd>

                                <dt class="col-sm-4">Email</dt>
                                <dd class="col-sm-8">{{ $user->email }}</dd>

                                <dt class="col-sm-4">Tipo</dt>
                                <dd class="col-sm-8">
                                    @if($user->eUtilizadorAluno())
                                        <span class="badge bg-info text-dark"><i class="bi bi-mortarboard"></i> Aluno</span>
                                        @if($user->aluno?->turma)
                                            <br><small class="text-muted mt-1 d-inline-block">{{ $user->aluno->turma->nome }}@if($user->aluno->turma->curso) · {{ $user->aluno->turma->curso->nome }}@endif</small>
                                        @endif
                                        @if($user->aluno)
                                            <br><small class="text-muted">{{ $user->aluno->notas_count ?? 0 }} avaliação(ões) registada(s)</small>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary"><i class="bi bi-briefcase"></i> Funcionário</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">
                                    @switch($user->estado)
                                        @case('activo')
                                            <span class="badge bg-success">Ativo</span>
                                            @break
                                        @case('pendente')
                                            <span class="badge bg-warning">Pendente</span>
                                            @break
                                        @case('rejeitado')
                                            <span class="badge bg-danger">Rejeitado</span>
                                            @if($user->motivo_rejeicao)
                                                <br><small class="text-muted">{{ $user->motivo_rejeicao }}</small>
                                            @endif
                                            @break
                                        @case('inactivo')
                                            <span class="badge bg-secondary">Inativo</span>
                                            @break
                                    @endswitch
                                </dd>

                                <dt class="col-sm-4">Registado em</dt>
                                <dd class="col-sm-8">{{ $user->created_at->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Último acesso</dt>
                                <dd class="col-sm-8">{{ $user->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                        @if($user->estado !== 'pendente' && $user->id !== auth()->id())
                            <div class="card-footer">
                                <form action="{{ route('users.alterar-estado', $user) }}" method="POST" class="d-inline" id="form-alterar-estado">
                                    @csrf
                                    @if($user->estado === 'activo')
                                        <input type="hidden" name="estado" value="inactivo">
                                        <button type="button" class="btn btn-warning" id="btn-alterar-estado">
                                            <i class="bi bi-pause-circle"></i> Desativar
                                        </button>
                                    @elseif($user->estado === 'inactivo')
                                        <input type="hidden" name="estado" value="activo">
                                        <button type="button" class="btn btn-success" id="btn-alterar-estado">
                                            <i class="bi bi-play-circle"></i> Reativar
                                        </button>
                                    @endif
                                </form>
                            </div>
                        @elseif($user->id === auth()->id() && in_array($user->estado, ['activo', 'inactivo']))
                            <div class="card-footer">
                                <small class="text-muted">Não pode alterar o estado da sua própria conta.</small>
                            </div>
                        @endif
                        @can('utilizadores.editar')
                            @if(!$user->hasRole('aluno') && $user->id !== auth()->id())
                                <div class="card-footer border-top">
                                    <form action="{{ route('users.repor-palavra-passe', $user) }}" method="POST"
                                          data-confirm="Gerar nova palavra-passe temporária? O utilizador terá de alterá-la no próximo acesso."
                                          data-confirm-title="Repor palavra-passe"
                                          data-confirm-icon="warning"
                                          data-confirm-ok="Sim, repor"
                                          data-confirm-color="#e0a800">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i> Repor palavra-passe
                                        </button>
                                        <small class="d-block text-muted mt-2">Gera uma palavra-passe temporária (mostrada uma vez).</small>
                                    </form>
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>

                <!-- Gestão de Cargos -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield"></i> Cargos</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h6>Cargos Atuais</h6>
                                @if($user->roles->isEmpty())
                                    <p class="text-muted">Nenhum cargo atribuído.</p>
                                @else
                                    @foreach($user->roles as $role)
                                        <span class="badge bg-info fs-6 me-1 mb-1">
                                            {{ \App\Support\CargosInstitucionais::rotulo($role->name) }}
                                            @if($user->roles->count() > 1)
                                                <form action="{{ route('users.remover-cargo', $user) }}" method="POST" class="d-inline" onsubmit="event.preventDefault(); handleRemoveRole(this, '{{ $role->name }}');">
                                                    @csrf
                                                    <input type="hidden" name="role" value="{{ $role->name }}">
                                                    <button type="submit" class="btn-close btn-close-white" style="font-size: 0.6rem;"></button>
                                                </form>
                                            @endif
                                        </span>
                                    @endforeach
                                @endif
                            </div>

                            <hr>

                            <h6>Actualizar Cargos</h6>
                            <form action="{{ route('users.sync-cargos', $user) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    @foreach($roles as $role)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}"
                                                {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="role_{{ $role->id }}">
                                                {{ \App\Support\CargosInstitucionais::rotulo($role->name) }}
                                                <small class="text-muted">({{ $role->name }})</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Guardar Cargos
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestão de Permissões Extras -->
            @if(auth()->user()->hasAnyRole(['admin_full', 'directora_geral']))
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-key"></i> Permissões Extras</h5>
                </div>
                <div class="card-body">
                    <!-- Info Box -->
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Permissões Extras</strong> são atribuídas individualmente ao utilizador,
                        além das permissões herdadas do cargo.
                    </div>

                    <!-- Permissões Extras Atuais -->
                    <div class="mb-4">
                        <h6>Permissões Extras Atuais</h6>
                        @php
                            $extraPermissions = $user->getDirectPermissions();
                        @endphp

                        @if($extraPermissions->isEmpty())
                            <p class="text-muted">Nenhuma permissão extra atribuída.</p>
                        @else
                            @foreach($extraPermissions as $permission)
                                <span class="badge bg-warning text-dark fs-6 me-1 mb-1">
                                    <i class="bi bi-star-fill"></i> {{ $permission->name }}
                                    <form action="{{ route('users.remover-permissao', $user) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="event.preventDefault(); handleRemovePermission(this, '{{ $permission->name }}');">
                                        @csrf
                                        <input type="hidden" name="permission" value="{{ $permission->name }}">
                                        <button type="submit"
                                                class="btn-close btn-close-white"
                                                style="font-size: 0.6rem;">
                                        </button>
                                    </form>
                                </span>
                            @endforeach
                        @endif
                    </div>

                    <hr>

                    <!-- Formulário de Gestão de Permissões -->
                    <h6>Gerir Permissões Extras</h6>

                    <form action="{{ route('users.sync-permissoes', $user) }}" method="POST">
                        @csrf

                        <!-- Permissões do Cargo (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Permissões do Cargo (não editáveis):</label>
                            <div class="border rounded p-3 bg-light">
                                @php
                                    $rolePermissions = $user->getPermissionsViaRoles();
                                @endphp

                                @if($rolePermissions->isEmpty())
                                    <p class="text-muted mb-0">Nenhuma permissão do cargo.</p>
                                @else
                                    @foreach($rolePermissions as $permission)
                                        <span class="badge bg-secondary me-1 mb-1">
                                            <i class="bi bi-shield-check"></i> {{ $permission->name }}
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                            <small class="text-muted">
                                Estas permissões são herdadas dos cargos e não podem ser removidas individualmente.
                            </small>
                        </div>

                        <!-- Permissões Extras Disponíveis -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Permissões Extras Disponíveis:</label>
                            <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                @php
                                    $allPermissions = \Spatie\Permission\Models\Permission::all();
                                    $rolePermissionNames = $rolePermissions->pluck('name')->toArray();
                                    $extraPermissionNames = $extraPermissions->pluck('name')->toArray();

                                    // Agrupar permissões por categoria
                                    $permissionGroups = [];
                                    foreach ($allPermissions as $perm) {
                                        $parts = explode('.', $perm->name);
                                        $category = $parts[0] ?? 'outros';
                                        if (!isset($permissionGroups[$category])) {
                                            $permissionGroups[$category] = [];
                                        }
                                        $permissionGroups[$category][] = $perm;
                                    }
                                    ksort($permissionGroups);
                                @endphp

                                @foreach($permissionGroups as $category => $permissions)
                                    <div class="mb-3">
                                        <h6 class="text-primary text-uppercase" style="font-size: 0.85rem;">
                                            {{ ucfirst($category) }}
                                        </h6>
                                        @foreach($permissions as $permission)
                                            @php
                                                $isFromRole = in_array($permission->name, $rolePermissionNames);
                                                $isExtra = in_array($permission->name, $extraPermissionNames);
                                                $isDisabled = $isFromRole;
                                            @endphp

                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $permission->name }}"
                                                       id="perm_{{ $permission->id }}"
                                                       {{ $isExtra ? 'checked' : '' }}
                                                       {{ $isDisabled ? 'disabled' : '' }}>
                                                <label class="form-check-label {{ $isDisabled ? 'text-muted' : '' }}"
                                                       for="perm_{{ $permission->id }}">
                                                    {{ $permission->name }}
                                                    @if($isFromRole)
                                                        <span class="badge bg-secondary ms-1" style="font-size: 0.7rem;">
                                                            do cargo
                                                        </span>
                                                    @endif
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">
                                Permissões marcadas como "do cargo" não podem ser selecionadas pois já são herdadas.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar Permissões Extras
                        </button>
                    </form>
                </div>
            </div>
            @endif

            @if(auth()->user()->hasRole('admin_full') && $user->eUtilizadorAluno() && $user->id !== auth()->id() && !$user->hasRole('admin_full'))
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Zona de perigo — eliminar aluno</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        Elimina permanentemente a conta e a ficha académica de <strong>{{ $user->name }}</strong>.
                        @if(($user->aluno?->notas_count ?? 0) > 0)
                            Serão removidas <strong>{{ $user->aluno->notas_count }}</strong> avaliação(ões), incluindo notas arquivadas.
                        @else
                            Este aluno não tem avaliações registadas.
                        @endif
                    </p>
                    <p class="text-muted small mb-3">Operação irreversível. Apenas o administrador do sistema pode executar esta acção.</p>
                    <form action="{{ route('users.eliminar-aluno', $user) }}" method="POST" id="form-eliminar-aluno">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="confirmar_eliminacao" value="0">
                        <button type="button" class="btn btn-danger" id="btn-eliminar-aluno">
                            <i class="bi bi-trash"></i> Eliminar aluno permanentemente
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <!-- Histórico de Atividade -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Histórico de Atividade</h5>
                </div>
                <div class="card-body">
                    @if($actividades->isEmpty())
                        <p class="text-muted mb-0">Nenhuma atividade registada.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Ação</th>
                                        <th>Por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($actividades as $activity)
                                        <tr>
                                            <td>{{ $activity->created_at->format('d/m/Y H:i') }}</td>
                                            <td>{{ $activity->description }}</td>
                                            <td>{{ $activity->causer?->name ?? 'Sistema' }}</td>
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

    @push('scripts')
    <script>
    (function () {
        const userEstado = @json($user->estado);
        const userName = @json($user->name);
        const notasCount = {{ (int) ($user->aluno?->notas_count ?? 0) }};

        const formEliminar = document.getElementById('form-eliminar-aluno');
        const btnEliminar = document.getElementById('btn-eliminar-aluno');
        if (formEliminar && btnEliminar) {
            btnEliminar.addEventListener('click', async function () {
                const notasInfo = notasCount > 0
                    ? `<p class="text-danger"><strong>${notasCount}</strong> avaliação(ões) serão eliminadas.</p>`
                    : '';
                const confirmed = await confirmAction({
                    title: 'Eliminar aluno permanentemente?',
                    html: `<p>Confirma a eliminação de <strong>${userName}</strong>?</p>${notasInfo}<p class="text-muted small mb-0">Esta acção não pode ser desfeita.</p>`,
                    icon: 'warning',
                    confirmText: '<i class="bi bi-trash"></i> Sim, eliminar',
                    confirmColor: '#dc3545'
                });
                if (confirmed) {
                    formEliminar.querySelector('[name="confirmar_eliminacao"]').value = '1';
                    showLoading('A eliminar...');
                    formEliminar.submit();
                }
            });
        }

        const formEstado = document.getElementById('form-alterar-estado');
        const btnEstado = document.getElementById('btn-alterar-estado');

        if (formEstado && btnEstado) {
            btnEstado.addEventListener('click', async function () {
                const isDeactivating = userEstado === 'activo';
                const action = isDeactivating ? 'desativar' : 'reativar';
                const actionCapitalized = isDeactivating ? 'Desativar' : 'Reativar';

                const confirmed = await confirmAction({
                    title: `${actionCapitalized} conta?`,
                    html: `<p>Confirma que deseja ${action} a conta:</p><p class="fw-bold">${userName}</p>`,
                    icon: 'warning',
                    confirmText: `Sim, ${action}`,
                    confirmColor: isDeactivating ? '#ffc107' : '#198754',
                    dangerMode: isDeactivating
                });

                if (confirmed) {
                    hideLoading();
                    showLoading(`${isDeactivating ? 'Desativando' : 'Reativando'} conta...`);
                    formEstado.submit();
                }
            });
        }

        window.handleRemoveRole = async function (form, roleName) {
            const confirmed = await confirmDelete(`o cargo "${roleName}"`);

            if (confirmed) {
                hideLoading();
                showLoading('Removendo cargo...');
                form.submit();
            }
        };

        window.handleRemovePermission = async function (form, permissionName) {
            const confirmed = await confirmDelete(`a permissão extra "${permissionName}"`);

            if (confirmed) {
                hideLoading();
                showLoading('Removendo permissão...');
                form.submit();
            }
        };
    })();
    </script>
    @endpush
</x-app-layout>
