<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Editar orientação</h2>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 720px;">
            <div class="card shadow-soft">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.orientacoes.update', $orientacao) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" value="{{ old('titulo', $orientacao->titulo) }}" maxlength="200" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trimestre (opcional)</label>
                            <select name="trimestre" class="form-select">
                                <option value="">—</option>
                                @for($t = 1; $t <= 3; $t++)
                                    <option value="{{ $t }}" @selected(old('trimestre', $orientacao->trimestre) == $t)>{{ $t }}º</option>
                                @endfor
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Conteúdo</label>
                            <textarea name="conteudo" class="form-control" rows="10" required>{{ old('conteudo', $orientacao->conteudo) }}</textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Guardar</button>
                            <a href="{{ route('pedagogico.orientacoes.show', $orientacao) }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
