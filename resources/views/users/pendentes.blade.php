<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Contas Pendentes de Aprovacao</h2>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if($users->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-check-circle display-1 text-success"></i>
                        <h4 class="mt-3">Nenhuma conta pendente</h4>
                        <p class="text-muted">Todas as contas foram processadas.</p>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    {{ $users->count() }} conta(s) aguardando aprovacao. Revise cada pedido antes de aprovar ou rejeitar.
                </div>

                <div class="row">
                    @foreach($users as $user)
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-warning bg-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">{{ $user->name }}</h5>
                                        <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Email</dt>
                                        <dd class="col-sm-8">{{ $user->email }}</dd>

                                        <dt class="col-sm-4">Registado em</dt>
                                        <dd class="col-sm-8">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                                    </dl>
                                </div>
                                <div class="card-footer">
                                    <form action="{{ route('users.aprovar', $user) }}" method="POST" class="form-aprovar-conta" data-user-name="{{ e($user->name) }}">
                                        @csrf
                                        <div class="mb-2">
                                            <label for="role_{{ $user->id }}" class="form-label small mb-1">Cargo a atribuir</label>
                                            <select name="role" id="role_{{ $user->id }}" class="form-select form-select-sm" required>
                                                @foreach($rolesAprovacao as $role)
                                                    <option value="{{ $role->name }}" @selected($role->name === 'professor')>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-success flex-grow-1 btn-aprovar-conta">
                                                <i class="bi bi-check-lg"></i> Aprovar
                                            </button>
                                            <a href="{{ route('users.form-rejeitar', $user) }}" class="btn btn-danger flex-grow-1">
                                                <i class="bi bi-x-lg"></i> Rejeitar
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Paginação após os cards --}}
                @if($users->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    document.querySelectorAll('.btn-aprovar-conta').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const form = btn.closest('.form-aprovar-conta');
            const userName = form.dataset.userName;

            const confirmed = await confirmAction({
                title: 'Aprovar conta?',
                html: `<p>Esta conta de utilizador será ativada:</p><p class="fw-bold">${userName}</p>`,
                icon: 'question',
                confirmText: 'Sim, aprovar',
                confirmColor: '#198754'
            });

            if (confirmed) {
                hideLoading();
                showLoading('Aprovando conta...');
                form.submit();
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
