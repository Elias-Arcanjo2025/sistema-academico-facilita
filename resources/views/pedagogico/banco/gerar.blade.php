<x-app-layout>
    <x-slot name="header">Gerar questões com IA</x-slot>

    <div class="mb-3">
        <a href="{{ route('pedagogico.banco.index') }}" class="btn btn-sm btn-outline-secondary">Voltar</a>
    </div>

    <x-tip-card icon="bi-stars" variant="info" title="Geração com IA" id="tip-banco-gerar-ia" compact>
        A IA cria questões reutilizáveis para o banco (enunciado, opções e resposta). Revise sempre o conteúdo antes de as usar numa prova. A geração é imediata e pode demorar alguns segundos.
    </x-tip-card>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="content-card">
        <div class="p-3">
            @if($disciplinas->isEmpty())
                <div class="alert alert-warning mb-0">
                    Não tem disciplinas disponíveis para o banco. Confirme a sua atribuição como professor ou coordenador.
                </div>
            @else
                <form method="POST" action="{{ route('pedagogico.banco.gerar') }}" id="formGerarBanco">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Disciplina <span class="text-danger">*</span></label>
                            <select name="disciplina_id" class="form-select" required>
                                @foreach($disciplinas as $d)
                                    <option value="{{ $d->id }}" @selected(old('disciplina_id') == $d->id)>{{ $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Classe (contexto)</label>
                            <select name="classe_id" class="form-select">
                                <option value="">Opcional</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}" @selected(old('classe_id') == $c->id)>{{ $c->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select" required>
                                @foreach($tipos as $v => $l)
                                    <option value="{{ $v }}" @selected(old('tipo', 'escolha_multipla') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dificuldade <span class="text-danger">*</span></label>
                            <select name="dificuldade" class="form-select" required>
                                @foreach($dificuldades as $v => $l)
                                    <option value="{{ $v }}" @selected(old('dificuldade', 'media') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantidade</label>
                            <input type="number" name="quantidade" class="form-control" min="1" max="10" value="{{ old('quantidade', 3) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cotação</label>
                            <input type="number" step="0.5" name="cotacao" class="form-control" value="{{ old('cotacao', 1) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tema / tópicos</label>
                            <input name="tema" class="form-control" value="{{ old('tema') }}" placeholder="Ex.: redes locais, TCP/IP, endereçamento IP">
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="btnGerarBanco">
                            <i class="bi bi-stars"></i> Gerar com IA
                        </button>
                        <a href="{{ route('pedagogico.banco.create') }}" class="btn btn-outline-secondary">Criar manualmente</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('formGerarBanco')?.addEventListener('submit', function () {
            const btn = document.getElementById('btnGerarBanco');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A gerar questões…';
        });
    </script>
    @endpush
</x-app-layout>
