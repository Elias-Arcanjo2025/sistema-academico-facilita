<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Execução da Planificação</h2>
            @can('pedagogico.ia.gerar')
            <form method="POST" action="{{ route('pedagogico.execucao.analisar', $planificacao) }}">
                @csrf
                <button class="btn btn-primary btn-sm">Analisar com IA</button>
            </form>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-activity" variant="info" title="Execução" id="tip-execucao-show" compact>
                Acompanhe a percentagem de aulas dadas versus planeadas. A análise com IA resume desvios e sugere ajustes ao resto do trimestre.
            </x-tip-card>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-primary">{{ number_format($percentagem, 1) }}%</h3>
                            <p class="text-muted mb-0">Execução</p>
                        </div>
                    </div>
                </div>
                @foreach($estadosAulas as $estado => $total)
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4>{{ $total }}</h4>
                            <small class="text-muted">{{ $estado }}</small>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="card mb-4">
                <div class="card-header">Aulas por estado</div>
                <div class="card-body">
                    <canvas id="chartEstados" height="120"></canvas>
                </div>
            </div>

            @if($ultimaAnalise)
            <div class="card">
                <div class="card-header">Última análise ({{ $ultimaAnalise->calculado_em?->format('d/m/Y H:i') }})</div>
                <div class="card-body">
                    <p><strong>Previsão conclusão:</strong> {{ $ultimaAnalise->previsao_conclusao?->format('d/m/Y') ?? '-' }}</p>
                    @if($ultimaAnalise->recomendacoes_json)
                    <ul>
                        @foreach($ultimaAnalise->recomendacoes_json as $rec)
                            <li>{{ is_array($rec) ? ($rec['texto'] ?? json_encode($rec)) : $rec }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('chartEstados'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($estadosAulas->keys()->values()) !!},
                datasets: [{
                    label: 'Aulas',
                    data: {!! json_encode($estadosAulas->values()) !!},
                    backgroundColor: '#0d6efd'
                }]
            },
            options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>
    @endpush
</x-app-layout>
