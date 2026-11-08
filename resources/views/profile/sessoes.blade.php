<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0"><i class="bi bi-display me-2"></i>Sessões activas</h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body">
                    @if(config('session.driver') !== 'database')
                        <p class="text-muted mb-0">Sessões activas requerem <code>SESSION_DRIVER=database</code>.</p>
                    @elseif($sessoes->isEmpty())
                        <p class="text-muted mb-0">Nenhuma sessão activa encontrada.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>IP</th>
                                        <th>Última actividade</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sessoes as $s)
                                        <tr>
                                            <td>
                                                {{ Str::limit($s['agente'], 60) }}
                                                @if($s['actual'])
                                                    <span class="badge bg-success-subtle text-success">Actual</span>
                                                @endif
                                            </td>
                                            <td>{{ $s['ip'] }}</td>
                                            <td>{{ $s['ultima'] }}</td>
                                            <td class="text-end">
                                                @if(!$s['actual'])
                                                    <form method="POST" action="{{ route('profile.sessoes.terminar', $s['id']) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Terminar</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm mt-3">
                <i class="bi bi-arrow-left"></i> Voltar ao perfil
            </a>
        </div>
    </div>
</x-app-layout>
