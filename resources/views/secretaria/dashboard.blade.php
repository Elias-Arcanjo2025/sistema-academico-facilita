<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Secretaria</h2>
            @can('secretaria.matriculas.gerir')
                <a href="{{ route('secretaria.matriculas.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nova matrícula
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
            ]" />

            <x-tip-card icon="bi-building-check" variant="info" title="Painel da Secretaria" id="tip-secretaria-dashboard" compact>
                Atalhos para matrículas, documentos e cartões. Acompanhe abaixo as matrículas e transferências mais recentes.
            </x-tip-card>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-people me-1"></i>Alunos activos</div>
                            <div class="fs-3 fw-semibold">{{ $totalAlunos }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                            @can('secretaria.matriculas.gerir')
                                <a href="{{ route('secretaria.matriculas.index') }}" class="btn btn-outline-primary btn-sm">Histórico de matrículas</a>
                                <a href="{{ route('secretaria.matriculas.import') }}" class="btn btn-outline-primary btn-sm">Importar matrículas</a>
                                <a href="{{ route('secretaria.distribuicao.create') }}" class="btn btn-outline-primary btn-sm">Distribuir alunos</a>
                            @endcan
                            @can('secretaria.campanhas.enviar')
                                <a href="{{ route('secretaria.campanhas.create') }}" class="btn btn-outline-primary btn-sm">Campanha de notificação</a>
                            @endcan
                            @can('secretaria.documentos.emitir')
                                <a href="{{ route('secretaria.documentos.index') }}" class="btn btn-outline-secondary btn-sm">Emitir documentos</a>
                                <a href="{{ route('secretaria.documentos.pedidos') }}" class="btn btn-outline-secondary btn-sm">Pedidos do portal</a>
                                <a href="{{ route('secretaria.cartao.emitir') }}" class="btn btn-outline-secondary btn-sm">Cartões</a>
                            @endcan
                            @can('turmas.ver')
                                <a href="{{ route('turmas.index') }}" class="btn btn-outline-dark btn-sm">Turmas</a>
                            @endcan
                            @can('secretaria.cartao.configurar')
                                <a href="{{ route('secretaria.cartao.configurar') }}" class="btn btn-outline-dark btn-sm">Personalizar cartão</a>
                                <a href="{{ route('secretaria.boletim.configurar') }}" class="btn btn-outline-dark btn-sm">Personalizar boletim</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    @if($matriculasRecentes->isEmpty())
                        <div class="card h-100">
                            <div class="card-body">
                                <x-empty-state icon="bi-journal-plus" message="Sem matrículas recentes." :can-create="false" />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="Matrículas recentes"
                            icon="bi-journal-plus"
                            :badge="$matriculasRecentes->count()"
                            :searchable="true">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Aluno</th>
                                        <th>Turma</th>
                                        <th>Quando</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($matriculasRecentes as $h)
                                        <tr>
                                            <td class="fw-semibold">{{ $h->aluno->nome ?? '—' }}</td>
                                            <td>{{ $h->turma->nome ?? '—' }}</td>
                                            <td class="text-nowrap"><small class="text-muted">{{ $h->created_at?->format('d/m H:i') }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>
                <div class="col-lg-6">
                    @if($transferenciasRecentes->isEmpty())
                        <div class="card h-100">
                            <div class="card-body">
                                <x-empty-state icon="bi-arrow-left-right" message="Sem transferências recentes." :can-create="false" />
                            </div>
                        </div>
                    @else
                        <x-data-table
                            title="Transferências recentes"
                            icon="bi-arrow-left-right"
                            :badge="$transferenciasRecentes->count()"
                            :searchable="true">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Aluno</th>
                                        <th>De → Para</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transferenciasRecentes as $h)
                                        <tr>
                                            <td class="fw-semibold">{{ $h->aluno->nome ?? '—' }}</td>
                                            <td>
                                                <small class="text-muted">{{ $h->deTurma->nome ?? '—' }} → {{ $h->paraTurma->nome ?? '—' }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
