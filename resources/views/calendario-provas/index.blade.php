<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">
            <i class="bi bi-calendar-event me-2 text-primary"></i>Calendário de avaliações
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[['label' => 'Calendário de avaliações']]" />

            <x-tip-card icon="bi-calendar-event" variant="warning" title="Calendário de provas (modelo institucional)">
                O PDF segue o formato oficial: grelha com dias da semana, 1ª/2ª prova, supervisores e legenda de horários.
                Na geração com IA, o turno é inferido do nome da turma (ex.: TI11<strong>AM</strong> → Manhã) e usa
                <strong>1 prova por dia</strong> (2 no mesmo dia só quando faltam dias).
                Supervisores: professores <strong>não associados à turma</strong> da prova.
                Professores de <strong>outros cursos</strong> podem (e são preferidos para) supervisionar turmas de cursos diferentes,
                desde que estejam na escola nesse dia segundo o horário anual.
            </x-tip-card>

            @can('calendario_provas.ia.gerar')
                @if(\App\Services\HorarioEscolarAccess::podeGerir(auth()->user()))
                    <div class="card shadow-soft mb-4 border-primary border-opacity-25">
                        <div class="card-header bg-transparent">
                            <h3 class="h6 mb-0"><i class="bi bi-stars me-1 text-primary"></i>Gerar calendário com IA</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('calendario-provas.gerar-ia') }}" id="form-gerar-ia-calendario"
                                  class="row g-3 align-items-end">
                                @csrf
                                <div class="col-md-3">
                                    <label class="form-label">Turma (opcional)</label>
                                    <select name="turma_id" class="form-select">
                                        <option value="">Todas as turmas acessíveis</option>
                                        @foreach($turmas as $t)
                                            <option value="{{ $t->id }}" @selected($turma && $turma->id === $t->id)>
                                                {{ $t->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Início do período</label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Fim do período</label>
                                    <input type="date" name="data_fim" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Designação</label>
                                    <input type="text" name="periodo" class="form-control" value="Período de Provas" placeholder="Ex: 1º Trimestre">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-stars me-1"></i>Gerar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="card shadow-soft mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Turma</label>
                            <select name="turma_id" class="form-select" onchange="this.form.submit()">
                                @foreach($turmas as $t)
                                    <option value="{{ $t->id }}" @selected($turma && $turma->id === $t->id)>
                                        {{ $t->nome }} — {{ $t->curso?->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if($turma && $provas->isNotEmpty())
                            <div class="col-md-6 text-md-end">
                                <a href="{{ route('calendario-provas.pdf', $turma) }}" class="btn btn-outline-danger">
                                    <i class="bi bi-file-pdf me-1"></i> Baixar PDF (modelo oficial)
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if($turma)
                <div class="alert alert-light border small mb-4 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Turno detectado: <strong>{{ $turma->turnoNomeResolvido() }}</strong>
                    @if($turma->inferirTurnoLetra())
                        (letra <strong>{{ $turma->inferirTurnoLetra() }}</strong> no nome/código)
                    @endif
                    — horários das provas ajustados automaticamente na geração com IA.
                </div>
            @endif

            @if($turma)
                <div class="row g-4">
                    @can('calendario_provas.gerir')
                        @if(\App\Services\HorarioEscolarAccess::podeGerir(auth()->user()))
                            <div class="col-lg-4">
                                @if($calendarioConfig)
                                    <div class="card shadow-soft mb-4">
                                        <div class="card-header bg-transparent">
                                            <h3 class="h6 mb-0">Horários das provas (legenda PDF)</h3>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="{{ route('calendario-provas.config', $turma) }}">
                                                @csrf
                                                @method('PUT')
                                                <p class="small text-muted">1ª Prova</p>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <input type="time" name="hora_1a_inicio" class="form-control form-control-sm"
                                                               value="{{ substr($calendarioConfig->hora_1a_inicio ?? '08:30:00', 0, 5) }}" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="time" name="hora_1a_fim" class="form-control form-control-sm"
                                                               value="{{ substr($calendarioConfig->hora_1a_fim ?? '10:20:00', 0, 5) }}" required>
                                                    </div>
                                                </div>
                                                <p class="small text-muted">2ª Prova</p>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <input type="time" name="hora_2a_inicio" class="form-control form-control-sm"
                                                               value="{{ substr($calendarioConfig->hora_2a_inicio ?? '10:30:00', 0, 5) }}" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="time" name="hora_2a_fim" class="form-control form-control-sm"
                                                               value="{{ substr($calendarioConfig->hora_2a_fim ?? '12:20:00', 0, 5) }}" required>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Guardar horários</button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                                <div class="card shadow-soft">
                                    <div class="card-header bg-transparent">
                                        <h3 class="h6 mb-0">Nova prova — {{ $turma->nome }}</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="{{ route('calendario-provas.store') }}">
                                            @csrf
                                            <input type="hidden" name="turma_id" value="{{ $turma->id }}">
                                            <div class="mb-3">
                                                <label class="form-label">Disciplina</label>
                                                <select name="disciplina_id" class="form-select" required>
                                                    <option value="">Seleccionar...</option>
                                                    @foreach($disciplinas as $disc)
                                                        <option value="{{ $disc->id }}">{{ $disc->nome }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Data da prova</label>
                                                <input type="date" name="data_prova" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Turno da prova</label>
                                                <select name="slot" class="form-select" required>
                                                    <option value="1">1ª Prova (manhã cedo)</option>
                                                    <option value="2">2ª Prova (manhã tarde)</option>
                                                </select>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label">Início</label>
                                                    <input type="time" name="hora_inicio" class="form-control" value="08:30">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Fim</label>
                                                    <input type="time" name="hora_fim" class="form-control" value="10:20">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Tipo</label>
                                                <select name="tipo" class="form-select">
                                                    @foreach(\App\Models\CalendarioProva::tipos() as $valor => $rotulo)
                                                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Período / Trimestre</label>
                                                <input type="text" name="periodo" class="form-control" placeholder="Ex: 1º Trimestre">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Supervisor (opcional)</label>
                                                <input type="text" name="supervisor_nome" class="form-control" placeholder="Nome do professor supervisor">
                                                <div class="form-text">Deixe vazio para atribuição automática na geração com IA.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Observações</label>
                                                <textarea name="observacoes" class="form-control" rows="2" placeholder="Ex: ENCERRAMENTO"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-plus-lg me-1"></i>Adicionar prova
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endcan

                    <div class="col-lg-8">
                        <div class="card shadow-soft">
                            <div class="card-header bg-transparent">
                                <h3 class="h6 mb-0">Provas agendadas</h3>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Dia</th>
                                            <th>Turno</th>
                                            <th>Horário</th>
                                            <th>Disciplina</th>
                                            <th>Supervisor</th>
                                            <th>Tipo</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($provas as $prova)
                                            <tr>
                                                <td class="fw-semibold">{{ $prova->dataFormatada() }}</td>
                                                <td>{{ $prova->diaSemanaNome() }}</td>
                                                <td>{{ $prova->slot }}ª</td>
                                                <td>{{ $prova->horarioFormatado() }}</td>
                                                <td>{{ $prova->disciplina?->nome }}</td>
                                                <td class="small">{{ $prova->supervisor_nome ?: '—' }}</td>
                                                <td><span class="badge bg-light text-dark">{{ \App\Models\CalendarioProva::tipos()[$prova->tipo] ?? $prova->tipo }}</span></td>
                                                <td class="text-end">
                                                    @can('calendario_provas.gerir')
                                                        @if(\App\Services\HorarioEscolarAccess::podeGerir(auth()->user()))
                                                            <form action="{{ route('calendario-provas.destroy', $prova) }}" method="POST" class="d-inline form-remover-prova">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Nenhuma prova definida para esta turma.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('form-gerar-ia-calendario')?.addEventListener('submit', async function (e) {
                e.preventDefault();

                const turmaSelect = this.querySelector('[name="turma_id"]');
                const turmaLabel = turmaSelect?.value
                    ? turmaSelect.options[turmaSelect.selectedIndex].text.trim()
                    : 'todas as turmas acessíveis';
                const inicio = this.querySelector('[name="data_inicio"]')?.value || '—';
                const fim = this.querySelector('[name="data_fim"]')?.value || '—';

                const confirmed = await confirmAction({
                    title: 'Gerar calendário com IA?',
                    html: `
                        <p class="mb-2">Será criada uma proposta automática respeitando:</p>
                        <ul class="text-start small mb-3">
                            <li>Período de <strong>${inicio}</strong> a <strong>${fim}</strong></li>
                            <li><strong>1 prova por dia</strong> (2 só se faltarem dias para todas as disciplinas)</li>
                            <li>Sem duas disciplinas difíceis no mesmo dia</li>
                            <li>Turno inferido pelo nome da turma (ex.: CG10<strong>T</strong> = Tarde)</li>
                            <li>Supervisores de <strong>outros cursos</strong> podem supervisionar turmas de cursos diferentes</li>
                            <li>Docentes associados à turma da prova ficam excluídos</li>
                            <li>Presença no dia conforme o <strong>horário anual</strong></li>
                        </ul>
                        <p class="small text-muted mb-0">Turma(s): <strong>${turmaLabel}</strong><br>
                        O calendário existente será substituído.</p>
                    `,
                    icon: 'question',
                    confirmText: '<i class="bi bi-stars"></i> Sim, gerar',
                    confirmColor: '#0d6efd'
                });

                if (! confirmed) {
                    return;
                }

                showLoading('A gerar calendário de provas...');
                this.submit();
            });

            document.querySelectorAll('.form-remover-prova').forEach(function (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const confirmed = await confirmDelete('esta prova do calendário');
                    if (confirmed) {
                        form.submit();
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
