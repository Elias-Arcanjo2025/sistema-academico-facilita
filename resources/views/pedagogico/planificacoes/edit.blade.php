<x-app-layout>
    @php
        $tipoPeriodo = old('tipo_periodo', $planificacao->tipo_periodo ?? 'quinzenal');
        $labelPeriodo = $tipoPeriodo === 'mensal' ? 'Mês' : 'Quinzena';
        $labelPeriodos = $tipoPeriodo === 'mensal' ? 'Meses' : 'Quinzenas';
        $temposOpts = [];
        for ($t = 1; $t <= 8; $t++) {
            $temposOpts[] = ['v' => $t, 'l' => $t.' ('.($t * 45).' min)'];
        }
    @endphp

    <x-slot name="header">
        <h2 class="h4 mb-0">Editar Planificação — Wizard</h2>
    </x-slot>

    <div class="py-4">
        <div class="container-fluid">
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="alert alert-light border small mb-4">
                <strong>Planificação {{ $tipoPeriodo === 'mensal' ? 'mensal' : 'quinzenal' }}.</strong>
                Em cada período defina <strong>vários temas</strong> (ex.: 2.1, 2.2, 2.3), objectivos gerais/específicos e o intervalo de datas (ex.: 12/01 – 16/01).
                Opcionalmente detalhe os <strong>dias de aula</strong> com tempos (1 tempo = 45 min).
            </div>

            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabQuinzenas" type="button">{{ $labelPeriodos }} e dias</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHorarios" type="button">Horário base (opcional)</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabQuinzenas">
                    <form method="POST" action="{{ route('pedagogico.planificacoes.store-quinzenas', $planificacao) }}" id="formQuinzenas">
                        @csrf
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de planificação</label>
                                <select name="tipo_periodo" id="tipo_periodo" class="form-select">
                                    <option value="quinzenal" @selected($tipoPeriodo === 'quinzenal')>Quinzenal (1–2 semanas por período)</option>
                                    <option value="mensal" @selected($tipoPeriodo === 'mensal')>Mensal (~4 semanas por período)</option>
                                </select>
                            </div>
                        </div>

                        <div id="quinzenasContainer">
                            @php
                                $quinzenas = old('quinzenas', $planificacao->quinzenas->map(function ($q) {
                                    $temas = $q->temas->isNotEmpty()
                                        ? $q->temas->map(fn ($t) => ['codigo' => $t->codigo, 'titulo' => $t->titulo])->values()->all()
                                        : collect($q->subtemas ?? [])->map(function ($item) {
                                            if (is_array($item)) {
                                                return ['codigo' => $item['codigo'] ?? null, 'titulo' => $item['titulo'] ?? $item['nome'] ?? ''];
                                            }
                                            return ['codigo' => null, 'titulo' => (string) $item];
                                        })->filter(fn ($t) => ! empty($t['titulo']))->values()->all();

                                    if ($temas === [] && ! empty($q->tema)) {
                                        $temas = [['codigo' => null, 'titulo' => $q->tema]];
                                    }

                                    return array_merge($q->toArray(), [
                                        'temas' => $temas,
                                        'dias' => $q->dias->map(fn ($d) => [
                                            'data' => $d->data?->format('Y-m-d'),
                                            'tema' => $d->tema,
                                            'objetivos_geral' => $d->objetivos_geral,
                                            'objetivos_especificos' => $d->objetivos_especificos,
                                            'tempos' => $d->tempos,
                                            'hora_inicio' => $d->hora_inicio ? substr($d->hora_inicio, 0, 5) : '',
                                        ])->values()->all(),
                                    ]);
                                })->values()->all() ?: [['numero'=>1,'data_inicial'=>'','data_final'=>'','objetivos_geral'=>'','objetivos_especificos'=>'','temas'=>[['codigo'=>'','titulo'=>'']],'dias'=>[]]]);
                            @endphp
                            @foreach($quinzenas as $qi => $q)
                            <div class="card mb-4 quinzena-block border-primary-subtle">
                                <div class="card-header bg-light">
                                    <strong class="label-periodo">{{ $labelPeriodo }} {{ $q['numero'] ?? ($qi+1) }}</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-1">
                                            <label class="form-label">Nº</label>
                                            <input type="number" name="quinzenas[{{ $qi }}][numero]" class="form-control" value="{{ $q['numero'] ?? $qi+1 }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Data início</label>
                                            <input type="date" name="quinzenas[{{ $qi }}][data_inicial]" class="form-control q-inicio" value="{{ isset($q['data_inicial']) ? (\Illuminate\Support\Str::contains($q['data_inicial'],' ') ? substr($q['data_inicial'],0,10) : $q['data_inicial']) : '' }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Data fim</label>
                                            <input type="date" name="quinzenas[{{ $qi }}][data_final]" class="form-control q-fim" value="{{ isset($q['data_final']) ? (\Illuminate\Support\Str::contains($q['data_final'],' ') ? substr($q['data_final'],0,10) : $q['data_final']) : '' }}" required>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label text-muted small mb-0">Duração do período</label>
                                            <div class="form-control-plaintext small text-muted">Ex.: 12/01 – 16/01 (uma ou duas semanas)</div>
                                        </div>
                                    </div>

                                    <h6 class="text-muted mb-2"><i class="bi bi-journal-text"></i> Temas a ministrar neste período</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered temas-table" data-qindex="{{ $qi }}">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:90px">Código</th>
                                                    <th>Tema / conteúdo</th>
                                                    <th style="width:40px"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $temas = $q['temas'] ?? [['codigo'=>'','titulo'=>'']]; @endphp
                                                @foreach($temas as $ti => $tema)
                                                <tr class="tema-row">
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][temas][{{ $ti }}][codigo]" class="form-control form-control-sm" value="{{ $tema['codigo'] ?? '' }}" placeholder="2.1"></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][temas][{{ $ti }}][titulo]" class="form-control form-control-sm" value="{{ $tema['titulo'] ?? '' }}" placeholder="Arquitectura TCP/IP" required></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-tema">&times;</button></td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-add-tema mb-3" data-qindex="{{ $qi }}">
                                        <i class="bi bi-plus"></i> Adicionar tema
                                    </button>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Objectivos gerais do período</label>
                                            <textarea name="quinzenas[{{ $qi }}][objetivos_geral]" class="form-control" rows="2" placeholder="Ex.: Caracterizar Arquitectura TCP/IP">{{ $q['objetivos_geral'] ?? '' }}</textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Objectivos específicos do período</label>
                                            <textarea name="quinzenas[{{ $qi }}][objetivos_especificos]" class="form-control" rows="2" placeholder="Ex.: Identificar os diferentes tipos de topologia de redes (um por linha)">{{ $q['objetivos_especificos'] ?? '' }}</textarea>
                                        </div>
                                    </div>

                                    <h6 class="text-muted mb-2"><i class="bi bi-calendar-day"></i> Dias de aula <span class="fw-normal">(opcional — agendamento)</span></h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered dias-table" data-qindex="{{ $qi }}">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tema</th>
                                                    <th>Obj. geral</th>
                                                    <th>Obj. específicos</th>
                                                    <th style="width:100px">Tempos</th>
                                                    <th style="width:110px">Hora</th>
                                                    <th style="width:40px"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $dias = $q['dias'] ?? []; @endphp
                                                @forelse($dias as $di => $dia)
                                                <tr class="dia-row">
                                                    <td><input type="date" name="quinzenas[{{ $qi }}][dias][{{ $di }}][data]" class="form-control form-control-sm" value="{{ $dia['data'] ?? '' }}" required></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][{{ $di }}][tema]" class="form-control form-control-sm" value="{{ $dia['tema'] ?? '' }}" required></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][{{ $di }}][objetivos_geral]" class="form-control form-control-sm" value="{{ $dia['objetivos_geral'] ?? '' }}" placeholder="Geral"></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][{{ $di }}][objetivos_especificos]" class="form-control form-control-sm" value="{{ $dia['objetivos_especificos'] ?? '' }}" placeholder="Específicos"></td>
                                                    <td>
                                                        <select name="quinzenas[{{ $qi }}][dias][{{ $di }}][tempos]" class="form-select form-select-sm">
                                                            @foreach([1,2,3,4,5,6,7,8] as $t)
                                                                <option value="{{ $t }}" @selected(($dia['tempos'] ?? 1) == $t)>{{ $t }}T</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td><input type="time" name="quinzenas[{{ $qi }}][dias][{{ $di }}][hora_inicio]" class="form-control form-control-sm" value="{{ $dia['hora_inicio'] ?? '' }}"></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-dia">&times;</button></td>
                                                </tr>
                                                @empty
                                                <tr class="dia-row">
                                                    <td><input type="date" name="quinzenas[{{ $qi }}][dias][0][data]" class="form-control form-control-sm"></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][0][tema]" class="form-control form-control-sm" placeholder="Tema"></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][0][objetivos_geral]" class="form-control form-control-sm"></td>
                                                    <td><input type="text" name="quinzenas[{{ $qi }}][dias][0][objetivos_especificos]" class="form-control form-control-sm"></td>
                                                    <td>
                                                        <select name="quinzenas[{{ $qi }}][dias][0][tempos]" class="form-select form-select-sm">
                                                            @foreach([1,2,3,4,5,6,7,8] as $t)
                                                                <option value="{{ $t }}">{{ $t }}T</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td><input type="time" name="quinzenas[{{ $qi }}][dias][0][hora_inicio]" class="form-control form-control-sm"></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-dia">&times;</button></td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-add-dia" data-qindex="{{ $qi }}">
                                        <i class="bi bi-plus"></i> Adicionar dia
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="btnAddQuinzena">
                            <i class="bi bi-plus"></i> <span class="label-periodo-singular">{{ $labelPeriodo }}</span>
                        </button>
                        <div>
                            <button type="submit" class="btn btn-primary">Guardar {{ $labelPeriodos }} e Dias</button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="tabHorarios">
                    <p class="text-muted small">Opcional se já definiu hora de início em cada dia. Usado como fallback quando o dia não tem hora definida.</p>
                    <form method="POST" action="{{ route('pedagogico.planificacoes.store-horarios', $planificacao) }}">
                        @csrf
                        @php $horarios = old('horarios', $planificacao->horariosSemanais->toArray() ?: [['dia_semana'=>1,'hora_inicio'=>'08:00','hora_fim'=>'09:30']]); @endphp
                        @foreach($horarios as $i => $h)
                        <div class="card mb-3">
                            <div class="card-body row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">Dia (1=Seg)</label>
                                    <input type="number" name="horarios[{{ $i }}][dia_semana]" class="form-control" min="1" max="7" value="{{ $h['dia_semana'] ?? 1 }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Início</label>
                                    <input type="time" name="horarios[{{ $i }}][hora_inicio]" class="form-control" value="{{ isset($h['hora_inicio']) ? substr($h['hora_inicio'],0,5) : '08:00' }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Fim</label>
                                    <input type="time" name="horarios[{{ $i }}][hora_fim]" class="form-control" value="{{ isset($h['hora_fim']) ? substr($h['hora_fim'],0,5) : '09:30' }}" required>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        <button type="submit" class="btn btn-primary">Guardar Horários</button>
                    </form>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('pedagogico.planificacoes.show', $planificacao) }}" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const temposOpts = {!! json_encode($temposOpts) !!};

        function buildTemaRow(qi, ti) {
            return '<tr class="tema-row">' +
                '<td><input type="text" name="quinzenas[' + qi + '][temas][' + ti + '][codigo]" class="form-control form-control-sm" placeholder="2.1"></td>' +
                '<td><input type="text" name="quinzenas[' + qi + '][temas][' + ti + '][titulo]" class="form-control form-control-sm" required placeholder="Título do tema"></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-tema">&times;</button></td>' +
                '</tr>';
        }

        function buildDiaRow(qi, di) {
            let opts = temposOpts.map(o => '<option value="' + o.v + '">' + o.v + 'T</option>').join('');
            return '<tr class="dia-row">' +
                '<td><input type="date" name="quinzenas[' + qi + '][dias][' + di + '][data]" class="form-control form-control-sm" required></td>' +
                '<td><input type="text" name="quinzenas[' + qi + '][dias][' + di + '][tema]" class="form-control form-control-sm" required></td>' +
                '<td><input type="text" name="quinzenas[' + qi + '][dias][' + di + '][objetivos_geral]" class="form-control form-control-sm"></td>' +
                '<td><input type="text" name="quinzenas[' + qi + '][dias][' + di + '][objetivos_especificos]" class="form-control form-control-sm"></td>' +
                '<td><select name="quinzenas[' + qi + '][dias][' + di + '][tempos]" class="form-select form-select-sm">' + opts + '</select></td>' +
                '<td><input type="time" name="quinzenas[' + qi + '][dias][' + di + '][hora_inicio]" class="form-control form-control-sm"></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-dia">&times;</button></td>' +
                '</tr>';
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('.btn-add-tema')) {
                const qi = e.target.closest('.btn-add-tema').dataset.qindex;
                const tbody = document.querySelector('.temas-table[data-qindex="' + qi + '"] tbody');
                const ti = tbody.querySelectorAll('.tema-row').length;
                tbody.insertAdjacentHTML('beforeend', buildTemaRow(qi, ti));
            }
            if (e.target.closest('.btn-remove-tema')) {
                const row = e.target.closest('.tema-row');
                const tbody = row.closest('tbody');
                if (tbody.querySelectorAll('.tema-row').length > 1) row.remove();
            }
            if (e.target.closest('.btn-add-dia')) {
                const qi = e.target.closest('.btn-add-dia').dataset.qindex;
                const tbody = document.querySelector('.dias-table[data-qindex="' + qi + '"] tbody');
                const di = tbody.querySelectorAll('.dia-row').length;
                tbody.insertAdjacentHTML('beforeend', buildDiaRow(qi, di));
            }
            if (e.target.closest('.btn-remove-dia')) {
                const row = e.target.closest('.dia-row');
                const tbody = row.closest('tbody');
                if (tbody.querySelectorAll('.dia-row').length > 1) row.remove();
            }
        });

        document.getElementById('tipo_periodo')?.addEventListener('change', function () {
            const mensal = this.value === 'mensal';
            const singular = mensal ? 'Mês' : 'Quinzena';
            const plural = mensal ? 'Meses' : 'Quinzenas';
            document.querySelectorAll('.label-periodo').forEach(el => {
                const n = el.textContent.replace(/\D/g, '') || '1';
                el.textContent = singular + ' ' + n;
            });
            document.querySelectorAll('.label-periodo-singular').forEach(el => el.textContent = singular);
        });
    })();
    </script>
    @endpush
</x-app-layout>
