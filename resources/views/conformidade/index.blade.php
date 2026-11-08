<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Pauta Certificada</h2>
            <a href="{{ route('radar.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-radar me-1"></i> Radar Pedagógico
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Análise', 'url' => route('radar.index')],
                ['label' => 'Pauta Certificada', 'url' => route('pauta-certificada.index')],
            ]" />

            <x-tip-card icon="bi-patch-check" variant="info" title="Pauta Certificada Facilita" id="tip-pauta-certificada-index" compact>
                Conformidade DE 424/25: analise a pauta da turma (selo), simule o impacto no conselho e exporte JSON para inspecção.
                @if(!empty($anoLectivo))
                    · {{ is_object($anoLectivo) ? ($anoLectivo->nome ?? $anoLectivo) : $anoLectivo }} · T{{ $trimestre }}
                @endif
            </x-tip-card>

            @if(!empty($semAnoLectivo))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Ano lectivo ainda não configurado.</strong>
                    Crie e active um ano em
                    <a href="{{ route('configuracoes.index') }}#anos-lectivos-section" class="alert-link">Configurações</a>
                    para listar turmas.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($turmas->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-patch-check"
                            :message="!empty($semAnoLectivo)
                                ? 'Sem ano lectivo activo — não é possível listar turmas.'
                                : 'Sem turmas activas no ano lectivo '.($anoLectivo->nome ?? $anoLectivo ?? 'actual').'.'"
                            :can-create="false"
                        />
                    </div>
                </div>
            @else
                <x-data-table
                    title="Turmas para análise"
                    icon="bi-patch-check"
                    :badge="$turmas->count()"
                    :searchable="true">
                    <table>
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Curso</th>
                                <th>Classe</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($turmas as $turma)
                                <tr>
                                    <td>
                                        <a href="{{ route('turmas.show', $turma) }}" class="fw-semibold text-decoration-none">
                                            {{ $turma->nome ?? $turma->codigo }}
                                        </a>
                                    </td>
                                    <td>{{ $turma->curso?->nome ?? '—' }}</td>
                                    <td>{{ $turma->classe?->nome ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                            <form method="POST" action="{{ route('pauta-certificada.analisar', $turma) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                                                <button type="submit" class="btn btn-sm btn-primary" title="Analisar conformidade">
                                                    <i class="bi bi-search me-1"></i> Analisar T{{ $trimestre }}
                                                </button>
                                            </form>
                                            <a href="{{ route('pauta-certificada.inspeccao', ['turma' => $turma, 'trimestre' => $trimestre]) }}"
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Exportar JSON para inspecção">
                                                <i class="bi bi-download me-1"></i> Exportar JSON
                                            </a>
                                        </div>
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
