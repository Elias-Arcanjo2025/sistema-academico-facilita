<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0"><i class="bi bi-megaphone me-2"></i>Aviso à disciplina</h2>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 720px;">
            <x-tip-card icon="bi-people" variant="info" title="Só professores da disciplina" id="tip-coord-avisos" compact>
                O aviso chega apenas a professores que leccionam a disciplina nas classes que coordena. Não notifica outras disciplinas.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.coordenacao.avisos.enviar') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Âmbito</label>
                            <select name="ambito" id="ambito" class="form-select" required>
                                <option value="">Seleccione disciplina / classe</option>
                                @foreach($opcoes as $op)
                                    <option
                                        value="{{ $op['disciplina_id'] }}:{{ $op['classe_id'] }}"
                                        data-disciplina="{{ $op['disciplina_id'] }}"
                                        data-classe="{{ $op['classe_id'] }}"
                                    >
                                        {{ $op['disciplina'] }} — {{ $op['classe'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="disciplina_id" id="disciplina_id" value="{{ old('disciplina_id') }}">
                            <input type="hidden" name="classe_id" id="classe_id" value="{{ old('classe_id') }}">
                            @error('disciplina_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" value="{{ old('titulo') }}" maxlength="160" required>
                            @error('titulo')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensagem</label>
                            <textarea name="mensagem" class="form-control" rows="5" maxlength="2000" required>{{ old('mensagem') }}</textarea>
                            @error('mensagem')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Enviar aviso
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('ambito')?.addEventListener('change', function () {
            const opt = this.selectedOptions[0];
            document.getElementById('disciplina_id').value = opt?.dataset.disciplina || '';
            document.getElementById('classe_id').value = opt?.dataset.classe || '';
        });
    </script>
    @endpush
</x-app-layout>
