{{--
    Tabela dinâmica da mini-pauta (colunas por trimestre/regime).

    Variáveis:
    - $colunas: array<int, array{key: string, header: string}>
    - $miniPauta: Collection|array de linhas
    - $chaveResultado: string (opcional, coluna destacada)
--}}
@php
    $chaveResultado = $chaveResultado ?? 'mt';
    $numColunasNotas = count($colunas ?? []);
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th class="text-center" style="width: 60px;">N.º</th>
                <th>Nome do Aluno</th>
                @foreach($colunas ?? [] as $coluna)
                    <th class="text-center {{ ($coluna['key'] ?? '') === $chaveResultado ? 'mp-col-resultado' : '' }}">
                        {{ $coluna['header'] }}
                    </th>
                @endforeach
                <th class="text-center d-print-none" style="width: 100px;">Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($miniPauta as $linha)
                @php $desist = ($linha['situacao'] ?? null) === 'desistente'; @endphp
                <tr class="{{ $desist ? 'table-warning text-muted' : '' }}">
                    <td class="text-center">{{ $linha['numero_ordem'] }}</td>
                    <td>{{ $linha['nome'] }}</td>
                    @if($desist)
                        <td colspan="{{ $numColunasNotas }}" class="text-center fst-italic text-warning-emphasis fw-bold text-uppercase">
                            — Desistente —
                        </td>
                    @else
                        @foreach($colunas ?? [] as $coluna)
                            @php
                                $valor = $linha[$coluna['key']] ?? null;
                                $destaque = ($coluna['key'] ?? '') === $chaveResultado;
                            @endphp
                            <td class="text-center {{ $destaque ? 'fw-bold' : '' }}">
                                @if($valor !== null)
                                    <span class="{{ $valor >= 10 ? 'text-success' : 'text-danger' }} {{ $destaque ? 'fs-5' : 'fw-bold' }}">
                                        {{ number_format($valor, 0) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endforeach
                    @endif
                    <td class="text-center d-print-none">
                        @if($desist)
                            <span class="badge bg-warning text-dark">Desistente</span>
                        @elseif($linha['situacao'] === 'aprovado')
                            <span class="badge bg-success">Positiva</span>
                        @elseif($linha['situacao'] === 'reprovado')
                            <span class="badge bg-danger">Negativa</span>
                        @else
                            <span class="badge bg-secondary">Pendente</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + $numColunasNotas }}" class="text-center text-muted py-4">
                        Nenhum aluno encontrado nesta turma.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
