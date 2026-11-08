<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Nova Correcção</h2></x-slot>
    <div class="py-4">
        <div class="container col-lg-6">
            <x-tip-card icon="bi-check2-square" variant="info" title="Nova correcção" id="tip-correcao-create" compact>
                Envie foto ou PDF da folha de respostas do aluno. O processamento (OCR + IA) é imediato e pode demorar 20–60 segundos.
            </x-tip-card>
            <div class="card">
                <div class="card-body">
                    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                    <form method="POST" action="{{ route('pedagogico.correcoes.store') }}" enctype="multipart/form-data" id="formCorrecao">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Prova</label>
                            <select name="prova_id" class="form-select" required>
                                @foreach($provas as $p)
                                    <option value="{{ $p->id }}" @selected($provaId == $p->id)>
                                        {{ $p->tipo }} — {{ $p->turma->nome ?? '' }} / {{ $p->disciplina->nome ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if($alunos->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label">Aluno (opcional)</label>
                            <select name="aluno_id" class="form-select">
                                <option value="">—</option>
                                @foreach($alunos as $a)
                                    <option value="{{ $a->id }}">{{ $a->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Ficheiro da prova (PDF/imagem)</label>
                            <input type="file" name="ficheiro" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Envie a folha <strong>preenchida pelo aluno</strong> (foto ou PDF), não o plano de aula nem a prova em branco.</div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="btnCorrecao">Processar correcção</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        document.getElementById('formCorrecao')?.addEventListener('submit', function () {
            const btn = document.getElementById('btnCorrecao');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A processar…';
        });
    </script>
    @endpush
</x-app-layout>
