@php
    $dias = \App\Models\TurmaHorario::diasSemana();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Gerir Horário — {{ $turma->nome }}
            </h2>
            <a href="{{ route('horarios.show', $turma) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i> Ver grade
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Horários', 'url' => route('horarios.index')],
                ['label' => $turma->nome, 'url' => route('horarios.show', $turma)],
                ['label' => 'Gerir'],
            ]" />

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card shadow-soft mb-4">
                        <div class="card-header bg-transparent">
                            <h3 class="h6 mb-0"><i class="bi bi-pause-circle me-1"></i>Intervalo no horário</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('horarios.config', $turma) }}">
                                @csrf
                                @method('PUT')
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="intervalo_activo" value="1" id="intervaloActivo"
                                           @checked(old('intervalo_activo', $horarioConfig->intervalo_activo))>
                                    <label class="form-check-label" for="intervaloActivo">Mostrar linha de intervalo</label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Após qual tempo (período)</label>
                                    <input type="number" name="intervalo_apos_periodo" class="form-control" min="1" max="12"
                                           value="{{ old('intervalo_apos_periodo', $horarioConfig->intervalo_apos_periodo ?? 3) }}" required>
                                    <div class="form-text">Ex.: 3 = intervalo depois do 3.º tempo</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Duração do intervalo (minutos)</label>
                                    <input type="number" name="intervalo_duracao_minutos" class="form-control" min="5" max="120"
                                           value="{{ old('intervalo_duracao_minutos', $horarioConfig->intervalo_duracao_minutos ?? 25) }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Texto no PDF (opcional)</label>
                                    <input type="text" name="intervalo_texto" class="form-control" maxlength="120"
                                           value="{{ old('intervalo_texto', $horarioConfig->intervalo_texto) }}"
                                           placeholder="Ex.: INTERVALO DE 25 MINUTOS">
                                    <div class="form-text">Se vazio, usa automaticamente «INTERVALO DE X MINUTOS».</div>
                                </div>
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-check-lg me-1"></i>Guardar intervalo
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-soft">
                        <div class="card-header bg-transparent">
                            <h3 class="h6 mb-0">Adicionar aula</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('horarios.store', $turma) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Disciplina</label>
                                    <select name="disciplina_id" class="form-select" required id="disciplinaSelect">
                                        <option value="">Seleccionar...</option>
                                        @foreach($disciplinas as $assoc)
                                            <option value="{{ $assoc->disciplina_id }}"
                                                    data-professor="{{ $assoc->user_id }}"
                                                    @selected(old('disciplina_id') == $assoc->disciplina_id)>
                                                {{ $assoc->disciplina?->nome }} — {{ $assoc->professor?->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="professor_id" id="professorId" value="{{ old('professor_id') }}">
                                <div class="mb-3">
                                    <label class="form-label">Dia da semana</label>
                                    <select name="dia_semana" class="form-select" required>
                                        @foreach($dias as $num => $nome)
                                            <option value="{{ $num }}" @selected(old('dia_semana') == $num)>{{ $nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Início</label>
                                        <input type="time" name="hora_inicio" class="form-control" value="{{ old('hora_inicio', '07:30') }}" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Fim</label>
                                        <input type="time" name="hora_fim" class="form-control" value="{{ old('hora_fim', '08:20') }}" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i>Adicionar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-soft">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h3 class="h6 mb-0">Aulas registadas</h3>
                            <span class="badge bg-secondary">{{ $horarios->count() }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dia</th>
                                        <th>Horário</th>
                                        <th>Disciplina</th>
                                        <th>Professor</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($horarios as $h)
                                        <tr>
                                            <td>{{ $h->diaSemanaNome() }}</td>
                                            <td>{{ substr($h->hora_inicio, 0, 5) }} – {{ substr($h->hora_fim, 0, 5) }}</td>
                                            <td>{{ $h->disciplina?->nome }}</td>
                                            <td>{{ $h->professor?->name }}</td>
                                            <td class="text-end">
                                                <form action="{{ route('horarios.destroy', $h) }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Remover esta aula do horário?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Sem aulas definidas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('disciplinaSelect')?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            document.getElementById('professorId').value = opt?.dataset?.professor || '';
        });
        document.getElementById('disciplinaSelect')?.dispatchEvent(new Event('change'));
    </script>
    @endpush
</x-app-layout>
