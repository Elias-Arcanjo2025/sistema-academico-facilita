<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Emitir documentos</h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('export.lista-alunos.pdf') }}" class="btn btn-outline-secondary btn-sm" id="btn-lista" target="_blank">
                    <i class="bi bi-list-ul me-1"></i> Lista nominal
                </a>
                <a href="{{ route('export.livro-turma.pdf') }}" class="btn btn-outline-secondary btn-sm" id="btn-livro" target="_blank">
                    <i class="bi bi-journal-text me-1"></i> Livro de turma
                </a>
                <a href="{{ route('export.etiquetas-alunos.pdf') }}" class="btn btn-outline-secondary btn-sm" id="btn-etiquetas" target="_blank">
                    <i class="bi bi-tags me-1"></i> Etiquetas
                </a>
                <a href="{{ route('secretaria.documentos.configurar') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-sliders me-1"></i> Personalizar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Documentos', 'url' => route('secretaria.documentos.index')],
            ]" />

            <x-tip-card icon="bi-file-earmark-text" variant="info" title="Documentos académicos" id="tip-secretaria-documentos-index" compact>
                Seleccione a turma e o aluno para gerar PDF (frequência, matrícula, comprovativo, ficha ou cartão). Personalize títulos e campos em Personalizar documentos.
            </x-tip-card>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('secretaria.documentos.emitir') }}" id="formDocs" target="_blank">
                        <div class="mb-3">
                            <label class="form-label">Turma (filtro)</label>
                            <select id="turma_filtro" class="form-select select2-turma">
                                <option value="">Todas / pesquisar aluno abaixo</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}">{{ $turma->nome }} — {{ $turma->curso->nome ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aluno *</label>
                            <select name="aluno_id" id="aluno_id" class="form-select" required>
                                <option value="">Seleccione a turma primeiro...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de documento *</label>
                            <select name="tipo" class="form-select" required>
                                <option value="frequencia">Declaração de frequência</option>
                                <option value="matricula">Declaração de matrícula</option>
                                <option value="comprovativo">Comprovativo de inscrição</option>
                                <option value="ficha">Ficha do aluno</option>
                                <option value="cartao">Cartão de estudante</option>
                            </select>
                        </div>
                        <button class="btn btn-primary">
                            <i class="bi bi-file-pdf me-1"></i> Gerar PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        $(function () {
            const alunosUrl = @json(route('secretaria.ajax.alunos-por-turma'));
            const $turma = $('#turma_filtro');
            const $aluno = $('#aluno_id');

            $turma.select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Todas / pesquisar aluno abaixo',
                language: { noResults: () => 'Nenhum resultado', searching: () => 'A procurar...' }
            });

            $aluno.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccione o aluno',
                language: { noResults: () => 'Nenhum aluno', searching: () => 'A procurar...' }
            });

            function carregarAlunos(turmaId) {
                $aluno.empty().append(new Option('A carregar...', '', true, true)).trigger('change');
                if (!turmaId) {
                    $aluno.empty().append(new Option('Seleccione uma turma', '', true, true)).trigger('change');
                    return;
                }
                fetch(`${alunosUrl}?turma_id=${encodeURIComponent(turmaId)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(r => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        $aluno.empty().append(new Option('Seleccione...', '', true, true));
                        (data || []).forEach(a => {
                            $aluno.append(new Option(`${a.numero_processo} — ${a.nome}`, a.id, false, false));
                        });
                        if (!data || !data.length) {
                            $aluno.empty().append(new Option('Sem alunos activos nesta turma', '', true, true));
                        }
                        $aluno.trigger('change');
                    })
                    .catch(() => {
                        $aluno.empty().append(new Option('Erro ao carregar', '', true, true)).trigger('change');
                    });
            }

            $turma.on('change', function () {
                const tid = $(this).val();
                carregarAlunos(tid);
                ['btn-lista', 'btn-livro', 'btn-etiquetas'].forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const url = new URL(el.href);
                    if (tid) url.searchParams.set('turma_id', tid);
                    else url.searchParams.delete('turma_id');
                    el.href = url.toString();
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
