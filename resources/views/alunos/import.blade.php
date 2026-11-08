<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                Importar Alunos
            </h2>
            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Importar']
            ]" />

            <x-tip-card icon="bi-file-earmark-excel" variant="info" title="Importação de Alunos" id="tip-alunos-import">
                Importe alunos em lote a partir de um ficheiro Excel. Descarregue o modelo fornecido, preencha os dados e faça upload. O sistema validará cada registo antes da importação final.
            </x-tip-card>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Instruções -->
                    <x-card title="📋 Instruções" icon="bi bi-info-circle" class="mb-4">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="bi bi-lightbulb me-2"></i>Como importar alunos</h6>
                            <ol class="mb-0 ps-3">
                                <li>Baixe o <strong>modelo de planilha</strong> abaixo</li>
                                <li>Preencha os dados dos alunos na planilha</li>
                                <li>Guarde o ficheiro no formato <strong>Excel (.xlsx)</strong> ou <strong>CSV (.csv)</strong></li>
                                <li>Faça o upload do ficheiro preenchido</li>
                            </ol>
                        </div>

                        <h6 class="mt-3"><i class="bi bi-check-circle me-2 text-success"></i>Campos obrigatórios:</h6>
                        <ul class="mb-3">
                            <li><strong>numero_processo:</strong> Número único do processo do aluno</li>
                            <li><strong>nome:</strong> Nome completo do aluno</li>
                            <li><strong>data_nascimento:</strong> Data no formato DD/MM/YYYY</li>
                            <li><strong>genero:</strong> M (Masculino) ou F (Feminino)</li>
                        </ul>

                        <h6><i class="bi bi-plus-circle me-2 text-primary"></i>Campos opcionais:</h6>
                        <ul class="mb-3">
                            <li><strong>bi_numero:</strong> Número do Bilhete de Identidade</li>
                            <li><strong>turma_id:</strong> ID da turma (se não informado, usa a turma selecionada abaixo)</li>
                            <li><strong>pai_nome / pai_telefone:</strong> Filiação (pai)</li>
                            <li><strong>mae_nome / mae_telefone:</strong> Filiação (mãe)</li>
                            <li><strong>activo:</strong> 1 (Ativo) ou 0 (Inativo) - padrão: 1</li>
                            <li><strong>atleta_alta_competicao:</strong> 1 ou 0</li>
                            <li><strong>faltou_exame_normal:</strong> 1 ou 0</li>
                            <li><strong>educacao_especial:</strong> 1 ou 0</li>
                            <li><strong>necessidades_especificas:</strong> texto (se educacao_especial=1)</li>
                        </ul>

                        <div class="alert alert-warning">
                            <i class="bi bi-shield-check me-2"></i>
                            <strong>Limite de importação:</strong> Máximo de 500 alunos por ficheiro.
                        </div>

                        <a href="{{ route('alunos.download-template') }}" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>
                            Baixar Modelo de Planilha
                        </a>
                    </x-card>

                    <!-- Formulário de Upload -->
                    <x-card title="📤 Upload da Planilha" icon="bi bi-upload">
                        <form action="{{ route('alunos.import.preview') }}" method="POST" enctype="multipart/form-data" id="importForm">
                            @csrf

                            <div class="mb-3">
                                <label for="turma_id" class="form-label">
                                    Turma Padrão <span class="text-muted">(opcional)</span>
                                </label>
                                <select name="turma_id" id="turma_id" class="form-select @error('turma_id') is-invalid @enderror">
                                    <option value="">Nenhuma (usar turma_id da planilha)</option>
                                    @foreach($turmas as $turma)
                                        <option value="{{ $turma->id }}" {{ old('turma_id') == $turma->id ? 'selected' : '' }}>
                                            {{ $turma->nome }} - {{ $turma->curso->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('turma_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i>
                                    Esta turma será usada para alunos que não tiverem turma_id na planilha
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="file" class="form-label">
                                    Ficheiro da Folha de Cálculo <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       class="form-control @error('file') is-invalid @enderror"
                                       id="file"
                                       name="file"
                                       accept=".xlsx,.xls,.csv"
                                       required>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="bi bi-file-earmark-excel"></i>
                                    Formatos aceitos: Excel (.xlsx, .xls) ou CSV (.csv)
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-info">
                                    <i class="bi bi-eye me-2"></i>
                                    Visualizar Dados
                                </button>
                                <button type="submit"
                                        class="btn btn-primary"
                                        formaction="{{ route('alunos.import.store') }}"
                                        id="saveImportButton">
                                    <i class="bi bi-check-lg me-2"></i>
                                    Guardar
                                </button>
                                <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                            </div>

                            <div class="mt-3 text-muted small">
                                <i class="bi bi-info-circle me-1"></i>
                                Clique em "Visualizar Dados" para rever os dados ou em "Guardar" para importar diretamente
                            </div>
                        </form>
                    </x-card>

                    @if(session('import_errors'))
                        <x-card title="⚠️ Erros na Importação" icon="bi bi-exclamation-triangle" class="mt-4">
                            <x-alert type="warning" :dismissible="false">
                                <strong>Alguns registos não puderam ser importados:</strong>
                            </x-alert>

                            @if(session('error_csv_path'))
                                <div class="mb-3">
                                    <a href="{{ route('alunos.download-errors', ['path' => session('error_csv_path')]) }}"
                                       class="btn btn-outline-danger">
                                        <i class="bi bi-download me-2"></i>
                                        Baixar Ficheiro de Erros (CSV)
                                    </a>
                                    <p class="text-muted small mt-2 mb-0">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Baixe este ficheiro para ver todos os erros e corrigir a sua folha de cálculo
                                    </p>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Linha</th>
                                            <th>Erros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(session('import_errors') as $failure)
                                            <tr>
                                                <td>{{ $failure->row() }}</td>
                                                <td>
                                                    @foreach($failure->errors() as $error)
                                                        <span class="badge bg-danger">{{ $error }}</span>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-card>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('importForm');
    const saveButton = document.getElementById('saveImportButton');

    if (form && saveButton) {
        form.addEventListener('submit', async function(event) {
            if (event.submitter !== saveButton) {
                return;
            }

            event.preventDefault();

            const confirmed = await confirmAction({
                title: 'Guardar importação de alunos?',
                text: 'Os alunos deste ficheiro serão importados.',
                icon: 'question',
                confirmText: 'Sim, guardar',
                confirmColor: '#0d6efd'
            });

            if (confirmed) {
                showLoading('A guardar alunos...');
                form.action = saveButton.formAction;
                form.submit();
            }
        });
    }
});
</script>
@endpush
@endonce
