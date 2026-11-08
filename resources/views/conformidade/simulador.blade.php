<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Simulador — {{ $turma->nome ?? $turma->codigo }}</h2>
            <a href="{{ route('pauta-certificada.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Análise', 'url' => route('radar.index')],
                ['label' => 'Pauta Certificada', 'url' => route('pauta-certificada.index')],
                ['label' => $turma->nome ?? $turma->codigo, 'url' => '#'],
                ['label' => 'Simulador', 'url' => '#'],
            ]" />

            <x-tip-card icon="bi-sliders" variant="info" title="Simulação de conselho" id="tip-pauta-certificada-simulador" compact>
                Resultado hipotético: {{ $resultado['total_mudancas'] }} aluno(s) mudariam de situação (de {{ $resultado['total_alunos'] }}).
                Nada foi gravado na base de dados.
            </x-tip-card>

            @if(empty($resultado['mudancas']))
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-check2-circle"
                            message="Nenhuma mudança de situação com este ajuste."
                            :can-create="false"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Mudanças de situação"
                    icon="bi-arrow-left-right"
                    :badge="count($resultado['mudancas'])"
                    :searchable="true">
                    <table>
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Antes</th>
                                <th>Depois</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($resultado['mudancas'] as $m)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $m['nome'] }}</div>
                                        <small class="text-muted"><code>{{ $m['numero_processo'] }}</code></small>
                                    </td>
                                    <td>
                                        <strong>{{ $m['antes']['resultado'] }}</strong>
                                        <div class="small text-muted">{{ $m['antes']['resumo'] }}</div>
                                    </td>
                                    <td>
                                        <strong>{{ $m['depois']['resultado'] }}</strong>
                                        <div class="small text-muted">{{ $m['depois']['resumo'] }}</div>
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
