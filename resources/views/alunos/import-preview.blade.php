<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-eye me-2"></i>
                Preview da Importação
            </h2>
            <a href="{{ route('alunos.import') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Importar', 'url' => route('alunos.import')],
                ['label' => 'Preview']
            ]" />

            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <x-card class="border-primary">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-file-earmark-spreadsheet text-primary" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ $totalRows }}</h5>
                                <p class="text-muted mb-0">Alunos no ficheiro</p>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-md-4">
                    <x-card class="{{ $exceedsLimit ? 'border-danger' : 'border-success' }}">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi {{ $exceedsLimit ? 'bi-exclamation-triangle text-danger' : 'bi-check-circle text-success' }}"
                                   style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ \App\Imports\AlunosImport::MAX_ROWS }}</h5>
                                <p class="text-muted mb-0">Limite máximo</p>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-md-4">
                    <x-card class="border-info">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-eye text-info" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ $previewRows->count() }}</h5>
                                <p class="text-muted mb-0">Linhas em preview</p>
                            </div>
                        </div>
                    </x-card>
                </div>
            </div>

            @if($exceedsLimit)
                <x-alert type="danger" class="mb-4">
                    <h5 class="alert-heading">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Limite Excedido
                    </h5>
                    <p class="mb-0">
                        O ficheiro contém <strong>{{ $totalRows }} alunos</strong>, mas o limite máximo é de
                        <strong>{{ \App\Imports\AlunosImport::MAX_ROWS }} alunos</strong> por importação.
                        Por favor, divida os dados em múltiplos arquivos menores.
                    </p>
                </x-alert>
            @else
                <x-alert type="success" class="mb-4">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Ficheiro válido!</strong> Pode prosseguir com a importação.
                </x-alert>
            @endif

            <!-- Preview dos dados -->
            <x-card title="📋 Preview dos Dados" icon="bi bi-table">
                @if($turmaPadrao)
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Turma padrão selecionada:</strong> {{ $turmaPadrao->nome }} - {{ $turmaPadrao->curso->nome }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Linha</th>
                                <th>Número Processo</th>
                                <th>Nome</th>
                                <th>Data Nascimento</th>
                                <th>Gênero</th>
                                <th>Turma</th>
                                <th>N° Ordem</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previewRows as $index => $row)
                                <tr>
                                    <td class="text-muted">{{ $index + 2 }}</td>
                                    <td>
                                        <strong>{{ $row['numero_processo'] ?? '-' }}</strong>
                                    </td>
                                    <td>{{ $row['nome'] ?? '-' }}</td>
                                    <td>
                                        @if(isset($row['data_nascimento']))
                                            {{ is_numeric($row['data_nascimento'])
                                                ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['data_nascimento'])->format('d/m/Y')
                                                : $row['data_nascimento']
                                            }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($row['genero']))
                                            <span class="badge bg-{{ strtoupper($row['genero']) === 'M' ? 'primary' : 'danger' }}">
                                                {{ strtoupper($row['genero']) }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $turmaRow = $row['turma_id'] ?? null;
                                            $turmaFinal = $turmaRow ? ($turmas[$turmaRow] ?? null) : $turmaPadrao;
                                        @endphp

                                        @if($turmaFinal)
                                            <span class="badge bg-info">
                                                {{ $turmaFinal->codigo }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning">Não especificada</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($row['numero_ordem']) && $row['numero_ordem'])
                                            {{ $row['numero_ordem'] }}
                                        @else
                                            <span class="badge bg-secondary">Auto</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(($row['activo'] ?? true))
                                            <i class="bi bi-check-circle text-success"></i>
                                        @else
                                            <i class="bi bi-x-circle text-danger"></i>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($totalRows > 100)
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Mostrando apenas as primeiras 100 linhas. O ficheiro completo contém {{ $totalRows }} linhas.
                    </div>
                @endif
            </x-card>

            <!-- Ações -->
            <div class="mt-4 d-flex gap-2">
                @if(!$exceedsLimit)
                    <form id="formConfirmarImportacao" action="{{ route('alunos.import.store') }}" method="POST" class="d-inline"
                          data-total-alunos="{{ $totalRows }}"
                          data-turma-nome="{{ $turmaPadrao ? $turmaPadrao->nome : 'múltiplas turmas' }}">
                        @csrf
                        <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                        <input type="hidden" name="import_token" value="{{ $importToken }}">

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-upload me-2"></i>
                            Confirmar Importação
                        </button>
                    </form>

                    <a href="{{ route('alunos.import') }}" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>
                        Cancelar
                    </a>
                @else
                    <a href="{{ route('alunos.import') }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>
                        Voltar e Corrigir Ficheiro
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

@once
@push('scripts')
<script>
/**
 * Bulk Import Confirmation Handler
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formConfirmarImportacao');

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const totalAlunos = this.dataset.totalAlunos;
            const turmaNome = this.dataset.turmaNome;

            const confirmed = await confirmAction({
                title: 'Confirmar Importação em Massa?',
                html: `
                    <div class="text-start">
                        <p class="mb-3">Serão importados <strong class="text-primary">${totalAlunos} alunos</strong>:</p>
                        <div class="bg-info bg-opacity-10 p-3 rounded mb-3">
                            <p class="mb-2"><i class="bi bi-mortarboard-fill text-info"></i> <strong>Turma:</strong> ${turmaNome}</p>
                            <p class="mb-0"><i class="bi bi-file-earmark-spreadsheet text-info"></i> <strong>Total de Registos:</strong> ${totalAlunos}</p>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-clock"></i> Esta operação pode levar alguns minutos. Aguarde até a conclusão.
                        </p>
                    </div>
                `,
                icon: 'question',
                confirmText: '<i class="bi bi-upload"></i> Sim, importar',
                confirmColor: '#0d6efd'
            });

            if (confirmed) {
                showLoading('Importando alunos...');
                this.submit();
            }
        });
    }
});
</script>
@endpush
@endonce
