<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Nova campanha</h2>
            <a href="{{ route('secretaria.campanhas.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-7">
            <x-tip-card icon="bi-send" variant="info" title="Enviar aviso" id="tip-campanha-create" compact>
                A notificação aparece no sino do destinatário (staff ou portal). Escolha o público com cuidado.
            </x-tip-card>

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('secretaria.campanhas.store') }}"
                          data-confirm="Enviar esta campanha agora?"
                          data-confirm-title="Confirmar envio"
                          data-confirm-icon="question"
                          data-confirm-ok="Sim, enviar">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" value="{{ old('titulo') }}" maxlength="160" required>
                            @error('titulo')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensagem *</label>
                            <textarea name="mensagem" class="form-control" rows="5" maxlength="2000" required>{{ old('mensagem') }}</textarea>
                            @error('mensagem')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Público *</label>
                                <select name="publico" id="publico" class="form-select" required>
                                    @foreach($publicos as $v => $l)
                                        <option value="{{ $v }}" @selected(old('publico') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6" id="wrap-turma">
                                <label class="form-label">Turma</label>
                                <select name="turma_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach($turmas as $t)
                                        <option value="{{ $t->id }}" @selected(old('turma_id') == $t->id)>{{ $t->nome }}</option>
                                    @endforeach
                                </select>
                                @error('turma_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Enviar campanha
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function syncPublico() {
            const v = document.getElementById('publico')?.value;
            document.getElementById('wrap-turma').style.display = v === 'turma' ? '' : 'none';
        }
        document.getElementById('publico')?.addEventListener('change', syncPublico);
        syncPublico();
    </script>
    @endpush
</x-app-layout>
