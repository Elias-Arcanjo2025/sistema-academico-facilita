<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Nova Planificação</h2>
    </x-slot>

    <div class="py-4">
        <div class="container col-lg-8">
            <x-tip-card icon="bi-calendar-plus" variant="info" title="Nova planificação" id="tip-planificacao-create" compact>
                Escolha turma, disciplina e período. No wizard seguinte define temas e dias de aula. Só após aprovação do coordenador poderá activar o calendário.
                @can('conteudos.ver')
                    Consulte os <a href="{{ route('pedagogico.conteudos.index') }}">conteúdos mínimos oficiais</a> da disciplina e classe, se existirem.
                @endcan
            </x-tip-card>
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('pedagogico.planificacoes.store') }}" id="formPlanificacao">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Tipo de planificação</label>
                            <select name="tipo_periodo" class="form-select" required>
                                <option value="quinzenal" @selected(old('tipo_periodo', 'quinzenal') === 'quinzenal')>Quinzenal (temas por 1–2 semanas)</option>
                                <option value="mensal" @selected(old('tipo_periodo') === 'mensal')>Mensal (temas por ~4 semanas)</option>
                            </select>
                            <div class="form-text">Define como organiza os períodos no wizard (quinzenas ou meses).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trimestre</label>
                            <select name="trimestre" class="form-select" required>
                                @for($t = 1; $t <= 3; $t++)
                                    <option value="{{ $t }}" @selected(old('trimestre') == $t)>{{ $t }}º Trimestre</option>
                                @endfor
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Turma</label>
                            <select name="turma_id" id="turma_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected(old('turma_id') == $turma->id)>
                                        {{ $turma->nome }} - {{ $turma->curso->nome ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Disciplina</label>
                            <select name="disciplina_id" id="disciplina_id" class="form-select" required>
                                <option value="">Seleccione a turma primeiro</option>
                            </select>
                        </div>
                        @if($podeEscolherProfessor ?? false)
                            <div class="mb-3">
                                <label class="form-label">Professor</label>
                                <select name="user_id" id="user_id" class="form-select">
                                    <option value="">Seleccione a disciplina primeiro</option>
                                </select>
                                <div class="form-text">
                                    Só aparecem professores com associação activa a esta turma e disciplina.
                                    Se houver apenas um, é seleccionado automaticamente.
                                </div>
                            </div>
                        @endif
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Criar</button>
                            <a href="{{ route('pedagogico.planificacoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const podeEscolherProfessor = @json((bool) ($podeEscolherProfessor ?? false));
        const urlDisciplinas = @json(route('pedagogico.planificacoes.disciplinas'));
        const urlProfessores = @json(route('pedagogico.planificacoes.professores'));

        function carregarDisciplinasTurma(turmaId, disciplinaSeleccionada) {
            const select = document.getElementById('disciplina_id');
            select.innerHTML = '<option value="">A carregar...</option>';
            resetProfessorSelect('Seleccione a disciplina primeiro');

            if (!turmaId) {
                select.innerHTML = '<option value="">Seleccione a turma primeiro</option>';
                return;
            }

            fetch(`${urlDisciplinas}?turma_id=${encodeURIComponent(turmaId)}`)
                .then(r => {
                    if (!r.ok) throw new Error('Erro ao carregar disciplinas');
                    return r.json();
                })
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        select.innerHTML = '<option value="">Nenhuma disciplina associada a esta turma</option>';
                        return;
                    }
                    select.innerHTML = '<option value="">Seleccione...</option>';
                    data.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.nome;
                        if (String(disciplinaSeleccionada) === String(d.id)) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                    if (select.value) {
                        carregarProfessores(turmaId, select.value, @json(old('user_id')));
                    }
                })
                .catch(() => {
                    select.innerHTML = '<option value="">Erro ao carregar disciplinas</option>';
                });
        }

        function resetProfessorSelect(mensagem) {
            if (!podeEscolherProfessor) return;
            const select = document.getElementById('user_id');
            if (!select) return;
            select.innerHTML = `<option value="">${mensagem}</option>`;
            select.required = false;
        }

        function carregarProfessores(turmaId, disciplinaId, professorSeleccionado) {
            if (!podeEscolherProfessor) return;
            const select = document.getElementById('user_id');
            if (!select) return;

            select.innerHTML = '<option value="">A carregar...</option>';
            if (!turmaId || !disciplinaId) {
                resetProfessorSelect('Seleccione a disciplina primeiro');
                return;
            }

            fetch(`${urlProfessores}?turma_id=${encodeURIComponent(turmaId)}&disciplina_id=${encodeURIComponent(disciplinaId)}`)
                .then(r => {
                    if (!r.ok) throw new Error('Erro ao carregar professores');
                    return r.json();
                })
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        select.innerHTML = '<option value="">Nenhum professor associado — crie a associação primeiro</option>';
                        select.required = true;
                        return;
                    }

                    select.innerHTML = data.length > 1
                        ? '<option value="">Seleccione o professor...</option>'
                        : '';
                    data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.nome;
                        select.appendChild(opt);
                    });

                    if (professorSeleccionado && data.some(p => String(p.id) === String(professorSeleccionado))) {
                        select.value = String(professorSeleccionado);
                    } else if (data.length === 1) {
                        select.value = String(data[0].id);
                    }

                    select.required = true;
                })
                .catch(() => {
                    select.innerHTML = '<option value="">Erro ao carregar professores</option>';
                });
        }

        document.getElementById('turma_id').addEventListener('change', function () {
            carregarDisciplinasTurma(this.value, '');
        });

        document.getElementById('disciplina_id').addEventListener('change', function () {
            const turmaId = document.getElementById('turma_id').value;
            carregarProfessores(turmaId, this.value, '');
        });

        document.addEventListener('DOMContentLoaded', function () {
            const turmaId = document.getElementById('turma_id').value;
            const disciplinaId = @json(old('disciplina_id'));
            if (turmaId) {
                carregarDisciplinasTurma(turmaId, disciplinaId);
            }
        });
    </script>
    @endpush
</x-app-layout>
