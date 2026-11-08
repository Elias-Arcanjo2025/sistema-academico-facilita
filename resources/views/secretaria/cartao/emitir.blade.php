<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Emitir cartões de estudante</h2>
            <a href="{{ route('secretaria.cartao.configurar') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-sliders me-1"></i> Personalizar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Cartões', 'url' => route('secretaria.cartao.emitir')],
            ]" />

            <x-tip-card icon="bi-person-vcard" variant="info" title="Cartões de estudante" id="tip-secretaria-cartao-emitir" compact>
                Emita PDF por turma (todos os alunos activos) ou por aluno individual. Se preencher ambos, prevalece o aluno. Personalize layout e cores em Personalizar cartão.
            </x-tip-card>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('secretaria.cartao.emitir.store') }}" target="_blank">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Turma (emite todos os alunos activos)</label>
                            <select name="turma_id" id="turma_id" class="form-select">
                                <option value="">—</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}">{{ $turma->nome }} — {{ $turma->curso->nome ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ou aluno individual</label>
                            <select name="aluno_id" id="aluno_id" class="form-select">
                                <option value="">Seleccione um aluno (pesquisável)...</option>
                            </select>
                            <div class="form-text">Se preencher aluno e turma, prevalece o aluno. Filtre pela turma acima para reduzir a lista.</div>
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
            const alunosTodos = @json($alunosOptions);

            const $turma = $('#turma_id');
            const $aluno = $('#aluno_id');

            $turma.select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Seleccione a turma',
                language: { noResults: () => 'Nenhum resultado', searching: () => 'A procurar...' }
            });

            function popularAlunos(lista) {
                $aluno.empty().append(new Option('Seleccione um aluno (pesquisável)...', '', true, true));
                (lista || []).forEach(a => {
                    $aluno.append(new Option(a.text, a.id, false, false));
                });
                $aluno.trigger('change');
            }

            $aluno.select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Seleccione um aluno (pesquisável)...',
                language: { noResults: () => 'Nenhum aluno', searching: () => 'A procurar...' }
            });

            popularAlunos(alunosTodos);

            $turma.on('change', function () {
                const turmaId = $(this).val();
                if (!turmaId) {
                    popularAlunos(alunosTodos);
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
                        popularAlunos((data || []).map(a => ({
                            id: a.id,
                            text: `${a.numero_processo} — ${a.nome}`
                        })));
                    })
                    .catch(() => popularAlunos([]));
            });
        });
    </script>
    @endpush
</x-app-layout>
