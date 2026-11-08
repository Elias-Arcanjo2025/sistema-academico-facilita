<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Importar matrículas</h2>
            <a href="{{ route('secretaria.matriculas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-8">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
                ['label' => 'Importar', 'url' => route('secretaria.matriculas.import')],
            ]" />

            <x-tip-card icon="bi-file-earmark-spreadsheet" variant="info" title="Importação em lote" id="tip-secretaria-matriculas-import" compact>
                Descarregue o modelo Excel, preencha os alunos e faça upload. Pode definir uma turma padrão se a planilha não tiver turma_id.
            </x-tip-card>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-list-ol me-1"></i>Instruções</h6>
                    <ol class="mb-3">
                        <li>Descarregue o modelo Excel (igual ao de Alunos)</li>
                        <li>Preencha os dados dos alunos</li>
                        <li>Seleccione a turma padrão (se não indicar <code>turma_id</code> na planilha)</li>
                        <li>Faça o upload e confirme a pré-visualização</li>
                    </ol>
                    <p class="mb-2 small"><strong>Obrigatórios:</strong> numero_processo, nome, data_nascimento, genero</p>
                    <p class="mb-3 small text-muted"><strong>Opcionais:</strong> bi_numero, turma_id, pai_nome, pai_telefone, mae_nome, mae_telefone, activo, atleta_alta_competicao, faltou_exame_normal, educacao_especial, necessidades_especificas</p>
                    <a href="{{ route('secretaria.matriculas.import.template') }}" class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i> Baixar modelo Excel
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('secretaria.matriculas.import.preview') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Turma padrão</label>
                            <select name="turma_id" class="form-select select2-single">
                                <option value="">Usar turma_id da planilha</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected(old('turma_id')==$turma->id)>
                                        {{ $turma->nome }} — {{ $turma->curso->nome ?? '' }} (ID {{ $turma->id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ficheiro Excel *</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <button class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i> Visualizar dados
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        $(function () {
            $('.select2-single').select2({ theme: 'bootstrap-5', width: '100%' });
        });
    </script>
    @endpush
</x-app-layout>
