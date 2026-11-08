<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">Lançar Prova Global (PG)</h2>
                <small class="text-muted">Prova Global — substitui PT3 · nota anual (sem trimestre)</small>
            </div>
            <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Indicador de Modo Consulta --}}
            @if($modoConsulta)
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo anterior selecionado. Lançamento de notas desabilitado.
                    <a href="{{ route('notas.lancar-pg') }}" class="alert-link">Voltar ao ano actual</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Informação sobre PG -->
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Prova Global (PG)</strong> — nota anual que <strong>substitui a PT3</strong> no 3.º trimestre.
                <ul class="mb-0 mt-2 small">
                    <li>No 3.º trimestre lança-se apenas <strong>MAC3</strong> e <strong>PP3</strong>; a PG entra na classificação final.</li>
                    <li><strong>MT3</strong> = (MAC3 + PP3) / 2</li>
                    <li><strong>CF</strong> = (MFT2 + MT3) / 2</li>
                    <li><strong>CA</strong> = 0,6 × CF + 0,4 × PG</li>
                </ul>
            </div>

            @include('layouts.partials.assistente-contextual')

            <!-- Selecção de Turma/Disciplina -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('notas.lancar-pg') }}" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Filtrar Ano Lectivo"
                            />
                        </div>
                        <div class="col-md-6">
                            <label for="turma_id" class="form-label">Turma/Disciplina <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="associacao" name="associacao" onchange="updateSelects(this)">
                                <option value="">Selecione uma turma e disciplina</option>
                                @foreach($associacoes as $assoc)
                                    <option value="{{ $assoc->turma_id }}-{{ $assoc->disciplina_id }}"
                                        {{ ($turmaId == $assoc->turma_id && $disciplinaId == $assoc->disciplina_id) ? 'selected' : '' }}>
                                        {{ $assoc->turma->nome }} - {{ $assoc->disciplina->nome }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="turma_id" id="turma_id" value="{{ $turmaId }}">
                            <input type="hidden" name="disciplina_id" id="disciplina_id" value="{{ $disciplinaId }}">
                            <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-search"></i> Carregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if($turmaId && $disciplinaId && $alunos->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            {{ $turma->nome }} - {{ $disciplina->nome }}
                            <span class="badge bg-warning text-dark">Prova Global (PG)</span>
                            <span class="badge bg-secondary">Ano Lectivo {{ $anoLectivo }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('notas.store-pg') }}" method="POST" data-atalho-guardar>
                            @csrf
                            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
                            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">

                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">N.º</th>
                                            <th>Nome do Aluno</th>
                                            <th style="width: 150px;">PG (Prova Global)</th>
                                            <th style="width: 120px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alunos as $aluno)
                                            @php
                                                $notaExistente = $notasExistentes[$aluno->id] ?? null;
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ $aluno->numero_ordem }}</td>
                                                <td>{{ $aluno->nome }}</td>
                                                <td>
                                                    <input type="hidden" name="notas[{{ $loop->index }}][aluno_id]" value="{{ $aluno->id }}">
                                                    <input type="number"
                                                        class="form-control form-control-sm nota-input"
                                                        name="notas[{{ $loop->index }}][valor]"
                                                        value="{{ old('notas.' . $loop->index . '.valor', $notaExistente) }}"
                                                        min="0"
                                                        max="20"
                                                        step="0.5"
                                                        placeholder="0-20">
                                                </td>
                                                <td class="text-center">
                                                    @if($notaExistente !== null)
                                                        <span class="badge {{ $notaExistente >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                            Lançada: {{ number_format($notaExistente, 1) }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Pendente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Atenção:</strong> Lance a PG após MAC3 e PP3 estarem concluídas.
                                A PG entra na fórmula <strong>CA = 0,6×CF + 0,4×PG</strong>.
                                <strong>Deixe vazio</strong> para alunos não avaliados — remove a PG existente (não use 0 nesse caso).
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg"></i> Guardar Prova Global
                                </button>
                                <a href="{{ route('notas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif($turmaId && $disciplinaId)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Nenhum aluno ativo encontrado nesta turma.
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Selecione uma turma e disciplina para começar a lançar a Prova Global.
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function updateSelects(select) {
            const value = select.value;
            if (value) {
                const parts = value.split('-');
                document.getElementById('turma_id').value = parts[0];
                document.getElementById('disciplina_id').value = parts[1];
                select.form.submit();
            }
        }

        // Validação visual das notas
        document.querySelectorAll('.nota-input').forEach(input => {
            input.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0 || value > 20) {
                    this.classList.add('is-invalid');
                } else if (value < 10) {
                    this.classList.remove('is-invalid');
                    this.classList.add('text-danger');
                    this.classList.remove('text-success');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.remove('text-danger');
                    this.classList.add('text-success');
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
