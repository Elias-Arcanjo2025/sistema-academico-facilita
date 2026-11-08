<div class="ca-lancamento-scroll mt-2">
    <table class="table table-bordered table-sm mb-0 ca-lancamento-table">
        <thead>
            <tr class="ca-lancamento-grupo text-center align-middle">
                <th rowspan="2" class="ca-col-num">N.º</th>
                <th rowspan="2" class="ca-col-nome">Nome do Aluno</th>
                <th colspan="{{ $disciplinas->count() }}" class="ca-grupo-titulo" style="background: linear-gradient(180deg, #92D050 0%, #C6EFCE 100%); color: #1b4332;">
                    <i class="bi bi-award me-1"></i>{{ $tituloGrupo }}
                </th>
            </tr>
            <tr class="text-center align-middle">
                @foreach($disciplinas as $disc)
                    <th class="ca-col-disc" title="{{ $disc->nome }}">
                        <span class="ca-disc-codigo">{{ $disc->codigo ?: \Illuminate\Support\Str::limit($disc->nome, 10, '') }}</span>
                        <span class="ca-disc-meta">{{ $disc->classe_inicio_curso ?? '?' }}ª–{{ $disc->classe_fim_curso ?? '?' }}ª</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($alunos as $aluno)
                <tr>
                    <td class="text-center fw-bold ca-col-num ca-sticky">{{ $aluno->numero_ordem }}</td>
                    <td class="ca-col-nome ca-sticky ca-sticky-nome">{{ $aluno->nome }}</td>
                    @foreach($disciplinas as $disc)
                        @php
                            $valor = $cfdsAnteriores[$aluno->id][$disc->id] ?? null;
                            $neg = is_numeric($valor) && (float) $valor < 10;
                            $pos = is_numeric($valor) && (float) $valor >= 10;
                        @endphp
                        <td class="text-center ca-celula-nota {{ $pos ? 'ca-nota-pos' : ($neg ? 'ca-nota-neg' : '') }}"
                            style="--ca-fundo: {{ $corFundo }};">
                            <input type="number"
                                   name="cfds_terminais[{{ $classeTerminal }}][{{ $aluno->id }}][{{ $disc->id }}]"
                                   class="form-control form-control-sm text-center ca-input-nota"
                                   step="1" min="0" max="20"
                                   value="{{ $valor !== null ? (int) round($valor) : '' }}"
                                   placeholder="—"
                                   @disabled(!($podeEditar ?? true))
                                   aria-label="CFD — {{ $disc->nome }} — {{ $aluno->nome }}">
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + $disciplinas->count() }}" class="text-center text-muted py-4">
                        Sem alunos para apresentar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
