<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Novo Aluno</h2>
            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Alunos', 'url' => route('alunos.index')],
                ['label' => 'Novo Aluno']
            ]" />

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <x-tip-card icon="bi-info-circle" variant="info" title="Matricular Aluno" id="tip-alunos-create" compact>
                                Preencha os dados pessoais e seleccione a turma do aluno. O número de processo deve ser único. Pode também importar alunos em lote através da opção "Importação Excel".
                            </x-tip-card>
                            <form action="{{ route('alunos.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @include('alunos.partials.form-cadastro', ['turmas' => $turmas])

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Guardar Aluno
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        document.getElementById('educacao_especial')?.addEventListener('change', function () {
            document.getElementById('nee-detalhes').style.display = this.checked ? '' : 'none';
        });
        (function () {
            const input = document.getElementById('foto');
            const wrap = document.getElementById('foto-preview-wrap');
            const img = document.getElementById('foto-preview');
            const remover = document.getElementById('foto-remover');
            if (!input || !wrap || !img) return;
            input.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file || !file.type.startsWith('image/')) {
                    wrap.classList.add('d-none');
                    img.removeAttribute('src');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => { img.src = e.target.result; wrap.classList.remove('d-none'); };
                reader.readAsDataURL(file);
            });
            remover?.addEventListener('click', function () {
                input.value = '';
                img.removeAttribute('src');
                wrap.classList.add('d-none');
            });
        })();
    </script>
    @endpush
</x-app-layout>
