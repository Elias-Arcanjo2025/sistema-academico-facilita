<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Pré-visualização da importação</h2>
            <a href="{{ route('secretaria.matriculas.import') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Cancelar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
                ['label' => 'Importar', 'url' => route('secretaria.matriculas.import')],
                ['label' => 'Pré-visualização', 'url' => '#'],
            ]" />

            <x-tip-card icon="bi-eye" variant="info" title="Confirme antes de importar" id="tip-secretaria-import-preview" compact>
                Reveja os registos abaixo. Só confirme se os dados estiverem correctos — a importação cria ou actualiza matrículas.
            </x-tip-card>

            @if($exceedsLimit)
                <div class="alert alert-danger">
                    O ficheiro tem {{ $totalRows }} linhas (máximo {{ $maxRows }}). Reduza o ficheiro antes de confirmar.
                </div>
            @else
                <div class="alert alert-info">
                    Ficheiro: <strong>{{ $fileName }}</strong> —
                    {{ $totalRows }} registo(s)
                    @if($turma)
                        — Turma padrão: <strong>{{ $turma->nome }}</strong>
                    @endif
                </div>
            @endif

            <x-data-table
                title="Pré-visualização"
                icon="bi-table"
                :badge="$totalRows"
                :searchable="true">
                <table>
                    <thead>
                        <tr>
                            <th>Processo</th>
                            <th>Nome</th>
                            <th>Nascimento</th>
                            <th>Género</th>
                            <th>BI</th>
                            <th>Pai</th>
                            <th>Mãe</th>
                            <th>Turma ID</th>
                            <th>Activo</th>
                            <th>Educ. especial</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewRows as $row)
                            <tr>
                                <td><code>{{ $row['numero_processo'] ?? '' }}</code></td>
                                <td class="fw-semibold">{{ $row['nome'] ?? '' }}</td>
                                <td>{{ $row['data_nascimento'] ?? '' }}</td>
                                <td>{{ $row['genero'] ?? '' }}</td>
                                <td>{{ $row['bi_numero'] ?? '' }}</td>
                                <td>{{ $row['pai_nome'] ?? '' }}</td>
                                <td>{{ $row['mae_nome'] ?? '' }}</td>
                                <td>{{ $row['turma_id'] ?? $turmaId }}</td>
                                <td>{{ $row['activo'] ?? '1' }}</td>
                                <td>{{ $row['educacao_especial'] ?? '0' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-data-table>

            <form method="POST" action="{{ route('secretaria.matriculas.import.store') }}" class="d-flex gap-2 mt-3">
                @csrf
                <input type="hidden" name="import_token" value="{{ $importToken }}">
                @if($turmaId)<input type="hidden" name="turma_id" value="{{ $turmaId }}">@endif
                <button class="btn btn-primary" @disabled($exceedsLimit || $totalRows < 1)>
                    <i class="bi bi-check-lg me-1"></i> Confirmar importação
                </button>
                <a href="{{ route('secretaria.matriculas.import') }}" class="btn btn-outline-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</x-app-layout>
