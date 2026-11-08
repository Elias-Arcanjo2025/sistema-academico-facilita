<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Assistente Pedagógico</h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-calendar-week me-2"></i>Planificações</h5>
                            <p class="card-text text-muted">Criar e gerir planificações trimestrais.</p>
                            <a href="{{ route('pedagogico.planificacoes.index') }}" class="btn btn-primary btn-sm">Abrir</a>
                        </div>
                    </div>
                </div>
                @can('pedagogico.dashboard.ver')
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-graph-up me-2"></i>Dashboard</h5>
                            <p class="card-text text-muted">KPIs e indicadores pedagógicos.</p>
                            <a href="{{ route('pedagogico.dashboard.index') }}" class="btn btn-primary btn-sm">Abrir</a>
                        </div>
                    </div>
                </div>
                @endcan
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-journal-text me-2"></i>Exercícios</h5>
                            <p class="card-text text-muted">Gerar e consultar exercícios com IA.</p>
                            <a href="{{ route('pedagogico.exercicios.index') }}" class="btn btn-primary btn-sm">Abrir</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-file-earmark-text me-2"></i>Instrumentos de prova</h5>
                            <p class="card-text text-muted">Gerar provas MAC, PP e PT.</p>
                            <a href="{{ route('pedagogico.provas.index') }}" class="btn btn-primary btn-sm">Abrir</a>
                        </div>
                    </div>
                </div>
                @can('pedagogico.correcao.usar')
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-check2-square me-2"></i>Correcções</h5>
                            <p class="card-text text-muted">Correcção assistida de provas.</p>
                            <a href="{{ route('pedagogico.correcoes.index') }}" class="btn btn-primary btn-sm">Abrir</a>
                        </div>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
