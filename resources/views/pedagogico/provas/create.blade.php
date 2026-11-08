<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Gerar instrumento</h2></x-slot>
    <div class="py-4">
        <div class="container col-lg-6">
            <x-tip-card icon="bi-stars" variant="info" title="Gerar instrumento com IA" id="tip-prova-create" compact>
                A geração é imediata (pode demorar 15–40 segundos). Usa conteúdos ministrados, planos dados ou temas da planificação. Depois pode ainda importar questões do banco.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.provas.gerar') }}" id="formGerarProva">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Planificação</label>
                            <select name="planificacao_id" class="form-select" required>
                                @foreach($planificacoes as $p)
                                    <option value="{{ $p->id }}" @selected($planificacaoId == $p->id)>
                                        {{ $p->turma->nome ?? '' }} — {{ $p->disciplina->nome ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                @foreach($tipos as $tipo)
                                    <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tempo (min)</label>
                            <input type="number" name="tempo_minutos" class="form-control" value="90">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cotação total</label>
                            <input type="number" step="0.5" name="cotacao_total" class="form-control" value="20">
                        </div>
                        <button type="submit" class="btn btn-primary" id="btnGerarProva">
                            <i class="bi bi-stars"></i> Gerar com IA
                        </button>
                        <a href="{{ route('pedagogico.provas.index', ['planificacao_id' => $planificacaoId]) }}" class="btn btn-outline-secondary">Voltar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('formGerarProva')?.addEventListener('submit', function () {
            const btn = document.getElementById('btnGerarProva');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A gerar prova…';
        });
    </script>
    @endpush
</x-app-layout>
