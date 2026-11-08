<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pauta Final - {{ $turma->nome }} - {{ $disciplina->nome }}</title>
    @include('exports.pdf.partials.styles')
    <style>
        /* Estilos adicionais específicos para pauta final */
        .pauta-table td.trimestre-col {
            font-size: 9px;
        }
        .pauta-table td.nf-col {
            font-size: 11px;
            font-weight: bold;
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Cabeçalho --}}
        @include('exports.pdf.partials.header', ['instituicao' => $instituicao])

        {{-- Título --}}
        <div class="titulo-documento">
            <h1>Pauta Final de Disciplina</h1>
            <h2>Ano Lectivo {{ $ano_lectivo }}</h2>
        </div>

        {{-- Informações --}}
        <div class="info-box">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Turma:</span>
                    <span class="info-value"><strong>{{ $turma->nome }}</strong></span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Curso:</span>
                    <span class="info-value">{{ $turma->curso->nome ?? '-' }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Disciplina:</span>
                    <span class="info-value"><strong>{{ $disciplina->nome }}</strong></span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Classe:</span>
                    <span class="info-value">{{ $turma->classe->nome ?? '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="table-wrapper">
            <table class="data-table pauta-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 35px;">N.º</th>
                        <th rowspan="2">Nome Completo do Aluno</th>
                        <th colspan="3">Trimestres</th>
                        <th rowspan="2" style="width: 55px;">NF</th>
                        <th rowspan="2" style="width: 60px;">Resultado</th>
                    </tr>
                    <tr>
                        <th style="width: 50px;">1º</th>
                        <th style="width: 50px;">2º</th>
                        <th style="width: 50px;">3º</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pauta_final as $aluno)
                        <tr>
                            <td class="numero">{{ $aluno['numero_ordem'] }}</td>
                            <td class="nome-aluno">{{ $aluno['nome'] }}</td>
                            <td class="trimestre-col {{ ($aluno['mt1'] ?? 0) >= 10 ? 'nota-positiva' : 'nota-negativa' }}">
                                {{ $aluno['mt1'] !== null ? round($aluno['mt1']) : '-' }}
                            </td>
                            <td class="trimestre-col {{ ($aluno['mt2'] ?? 0) >= 10 ? 'nota-positiva' : 'nota-negativa' }}">
                                {{ $aluno['mt2'] !== null ? round($aluno['mt2']) : '-' }}
                            </td>
                            <td class="trimestre-col {{ ($aluno['mt3'] ?? 0) >= 10 ? 'nota-positiva' : 'nota-negativa' }}">
                                {{ $aluno['mt3'] !== null ? round($aluno['mt3']) : '-' }}
                            </td>
                            <td class="nf-col {{ ($aluno['nf'] ?? 0) >= 10 ? 'nota-positiva' : 'nota-negativa' }}">
                                {{ $aluno['nf'] !== null ? round($aluno['nf']) : '-' }}
                            </td>
                            <td style="font-size: 8px;">
                                @if(($aluno['desistente'] ?? false) || ($aluno['situacao'] ?? null) === 'desistente' || ($aluno['resultado'] ?? null) === 'desistente')
                                    <strong>Desistente</strong>
                                @elseif(isset($aluno['resultado']))
                                    @if($aluno['resultado'] === 'aprovado')
                                        Aprovado
                                    @elseif($aluno['resultado'] === 'reprovado')
                                        Reprovado
                                    @elseif($aluno['resultado'] === 'exame')
                                        Exame
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Estatísticas --}}
        @if(isset($estatisticas))
            <div class="estatisticas-box">
                <div class="estatisticas-titulo">Resumo Estatístico</div>
                <div class="estatisticas-grid">
                    <div class="estatisticas-item">
                        <span class="estatisticas-valor">{{ $estatisticas['total_alunos'] ?? 0 }}</span>
                        <span class="estatisticas-label">Total Alunos</span>
                    </div>
                    <div class="estatisticas-item">
                        <span class="estatisticas-valor" style="color: #006600;">{{ $estatisticas['aprovados'] ?? 0 }}</span>
                        <span class="estatisticas-label">Aprovados</span>
                    </div>
                    <div class="estatisticas-item">
                        <span class="estatisticas-valor" style="color: #d00000;">{{ $estatisticas['reprovados'] ?? 0 }}</span>
                        <span class="estatisticas-label">Reprovados</span>
                    </div>
                    <div class="estatisticas-item">
                        <span class="estatisticas-valor" style="color: #ff8c00;">{{ $estatisticas['exames'] ?? 0 }}</span>
                        <span class="estatisticas-label">Exames</span>
                    </div>
                    <div class="estatisticas-item">
                        <span class="estatisticas-valor">{{ $estatisticas['media_geral'] ?? '-' }}</span>
                        <span class="estatisticas-label">Média Geral</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Legenda --}}
        <div class="legenda">
            <strong>Legenda:</strong> N.º = Número de Ordem | 1º/2º/3º = Médias Trimestrais | NF = Nota Final
        </div>

        {{-- Rodapé --}}
        @include('exports.pdf.partials.footer', [
            'instituicao' => $instituicao,
            'gerado_em' => $gerado_em,
            'assinaturas' => ['O Professor', 'O Director de Turma']
        ])
    </div>
</body>
</html>
