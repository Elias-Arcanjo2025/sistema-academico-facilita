<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">Encarregados</h2>
                <p class="text-muted small mb-0">Portal de transparência · WhatsApp opt-in · assinatura de boletim</p>
            </div>
            <a href="{{ route('encarregados.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Registar encarregado
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-people" variant="info" title="Gestão de encarregados" id="tip-encarregados-index" compact>
                Contas distintas das dos alunos. Aqui regista pais/encarregados, associa educandos e, se quiser, cria acesso ao portal.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <x-data-table
                title="Lista de encarregados"
                icon="bi-people"
                :badge="$encarregados->total()"
                :pagination="$encarregados">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Nome</th>
                            <th class="text-nowrap">Telefone</th>
                            <th class="text-nowrap">Email</th>
                            <th class="text-nowrap">WhatsApp</th>
                            <th class="text-nowrap text-center">Educandos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($encarregados as $e)
                            <tr>
                                <td class="fw-semibold">{{ $e->nome }}</td>
                                <td>{{ $e->telefone ?: '—' }}</td>
                                <td>{{ $e->email ?: '—' }}</td>
                                <td>
                                    @if($e->whatsapp_opt_in)
                                        <span class="badge bg-success-subtle text-success-emphasis">Sim</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Não</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary-emphasis">{{ $e->alunos_count }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state
                                        icon="bi-people"
                                        message="Ainda sem encarregados."
                                        :create-url="route('encarregados.create')"
                                        create-label="Registar encarregado"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-data-table>
        </div>
    </div>
</x-app-layout>
