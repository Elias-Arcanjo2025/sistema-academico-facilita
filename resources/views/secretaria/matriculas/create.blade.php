<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Nova matrícula</h2>
            <a href="{{ route('secretaria.matriculas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Matrículas', 'url' => route('secretaria.matriculas.index')],
                ['label' => 'Nova matrícula', 'url' => route('secretaria.matriculas.create')],
            ]" />

            <x-tip-card icon="bi-person-plus" variant="info" title="Matricular aluno" id="tip-secretaria-create" compact>
                Preencha os dados pessoais e seleccione a turma. O número de processo deve ser único. Para vários alunos, use Importar matrículas.
            </x-tip-card>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form action="{{ route('secretaria.matriculas.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @include('alunos.partials.form-cadastro', ['turmas' => $turmas, 'turmaSelectClass' => 'select2-single'])

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('secretaria.matriculas.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Registar matrícula
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
        $(function () {
            $('.select2-single').select2({
                theme: 'bootstrap-5',
                width: '100%',
                language: { noResults: () => 'Nenhum resultado', searching: () => 'A procurar...' }
            });
        });
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
