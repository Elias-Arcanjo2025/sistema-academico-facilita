<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Chamada — {{ $horario->disciplina?->nome }}</h2>
            <a href="{{ route('presencas.index', ['data' => $data, 'ano_lectivo_id' => $anoLectivoId ?? null]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Assiduidade', 'url' => route('presencas.index')],
                ['label' => 'Presenças', 'url' => route('presencas.index', ['data' => $data, 'ano_lectivo_id' => $anoLectivoId ?? null])],
                ['label' => 'Chamada', 'url' => route('presencas.aula', ['horario' => $horario->id, 'data' => $data])],
            ]" />

            <x-tip-card icon="bi-clipboard-check" variant="info" title="Lançar chamada" id="tip-presencas-aula" compact>
                Marque o estado de cada aluno (presente, falta, atraso…). Guarde no final. Pode justificar faltas depois no registo da aula.
            </x-tip-card>

            @if(!($podeEditar ?? true))
                <div class="alert alert-secondary">
                    <i class="bi bi-lock-fill me-1"></i>Ano lectivo anterior — modo consulta. Só o administrador pode alterar a chamada.
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <div class="fw-semibold">{{ $horario->turma?->nome }} · {{ $horario->disciplina?->nome }}</div>
                    <div class="small text-muted">
                        {{ \Carbon\Carbon::parse($data)->translatedFormat('d/m/Y') }}
                        · {{ \Illuminate\Support\Str::substr($horario->hora_inicio, 0, 5) }}–{{ \Illuminate\Support\Str::substr($horario->hora_fim, 0, 5) }}
                        · Prof. {{ $horario->professor?->name ?? '—' }}
                    </div>
                </div>
            </div>

            @if($alunos->isEmpty())
                <div class="card">
                    <div class="card-body">
                        <x-empty-state icon="bi-people" message="Esta turma não tem alunos activos." :can-create="false" />
                    </div>
                </div>
            @else
                @if($podeEditar ?? true)
                    <form method="POST" action="{{ route('presencas.store', $horario) }}">
                        @csrf
                        <input type="hidden" name="data" value="{{ $data }}">

                        <div class="card mb-3">
                            <div class="card-body">
                                <label class="form-label">Observação (opcional)</label>
                                <input type="text" name="observacao" class="form-control" maxlength="500"
                                       value="{{ old('observacao', $aula?->observacao) }}">
                            </div>
                        </div>

                        <x-data-table
                            title="Lista de chamada"
                            icon="bi-people"
                            :badge="$alunos->count()"
                            :searchable="true">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:4rem">N.º</th>
                                        <th>Aluno</th>
                                        <th style="min-width:14rem">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($alunos as $aluno)
                                        @php
                                            $reg = $registos->get($aluno->id);
                                            $estadoActual = old('estados.'.$aluno->id, $reg?->estado ?? 'presente');
                                        @endphp
                                        <tr>
                                            <td>{{ $aluno->numero_ordem }}</td>
                                            <td class="fw-semibold">{{ $aluno->nome }}</td>
                                            <td>
                                                <select name="estados[{{ $aluno->id }}]" class="form-select form-select-sm">
                                                    @foreach($estados as $valor => $label)
                                                        <option value="{{ $valor }}" @selected($estadoActual === $valor)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if($reg?->estado === 'falta_justificada' && $reg->justificacao)
                                                    <div class="small text-muted mt-1">Justificação: {{ $reg->justificacao }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-data-table>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Guardar chamada
                            </button>
                            @if($aula)
                                <a href="{{ route('presencas.show', $aula) }}" class="btn btn-outline-secondary">Ver registo</a>
                            @endif
                        </div>
                    </form>
                @else
                    <x-data-table
                        title="Lista de chamada"
                        icon="bi-people"
                        :badge="$alunos->count()"
                        :searchable="true">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:4rem">N.º</th>
                                    <th>Aluno</th>
                                    <th style="min-width:14rem">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alunos as $aluno)
                                    @php $reg = $registos->get($aluno->id); @endphp
                                    <tr>
                                        <td>{{ $aluno->numero_ordem }}</td>
                                        <td class="fw-semibold">{{ $aluno->nome }}</td>
                                        <td>
                                            {{ $estados[$reg?->estado ?? ''] ?? ($reg?->estado ? ucfirst(str_replace('_', ' ', $reg->estado)) : '—') }}
                                            @if($reg?->estado === 'falta_justificada' && $reg->justificacao)
                                                <div class="small text-muted mt-1">Justificação: {{ $reg->justificacao }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-data-table>
                    @if($aula)
                        <div class="mt-3">
                            <a href="{{ route('presencas.show', $aula) }}" class="btn btn-outline-secondary">Ver registo</a>
                        </div>
                    @endif
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
