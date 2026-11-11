<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Mini-Pauta Anual - {{ $turma->nome }} - {{ $disciplina->nome }}</title>

@php
    $totalAlunos = is_countable($linhas ?? null) ? count($linhas) : 0;
    $baseTable = (float) ($pdfConfig['fonts']['sizes']['table'] ?? 8);

    if ($totalAlunos <= 25) {
        $rowPad = '2px 2px';
        $rowFont = '7.5px';
        $headFont = '7px';
        $groupFont = '8px';
    } elseif ($totalAlunos <= 35) {
        $rowPad = '1.5px 1px';
        $rowFont = '7px';
        $headFont = '6.5px';
        $groupFont = '7.5px';
    } else {
        $rowPad = '1px 1px';
        $rowFont = '6.5px';
        $headFont = '6px';
        $groupFont = '7px';
    }

    $colunasAnuais = $colunas_anuais ?? [];
    $linhas = $linhas ?? [];
    $classeLabel = $turma->classe->nome ?? $turma->classe->codigo ?? '—';
    $salaLabel = $turma->sala->nome ?? $turma->sala->codigo ?? '—';
    $periodoLabel = method_exists($turma, 'turnoNomeResolvido')
        ? $turma->turnoNomeResolvido()
        : ($turma->turno->nome ?? '—');
@endphp

@include('exports.pdf.partials.dynamic-styles')

<style>
@page { margin: 8mm 8mm 12mm 8mm; }

body { color: #111; font-family: DejaVu Sans, sans-serif; }

.footer {
    position: fixed;
    bottom: -6mm;
    left: 0;
    right: 0;
    height: 18px;
    font-size: 6.5px;
    color: #555;
    border-top: 1px solid #bbb;
    padding-top: 2px;
}
.footer-left   { width: 40%; float: left; }
.footer-center { width: 40%; float: left; text-align: center; }
.footer-right  { width: 20%; float: right; text-align: right; }

.meta-top {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
}
.meta-top td {
    font-size: 8px;
    padding: 1px 3px;
    vertical-align: middle;
}
.institution-header {
    text-align: center;
    margin-bottom: 4px;
}
.institution-logo {
    height: 92px;
    max-width: 160px;
    width: auto;
    display: block;
    margin: 0 auto 2px;
}
.institution-name {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.document-title {
    margin-top: 2px;
    font-size: 16pt;
    font-weight: bold;
    text-transform: uppercase;
    text-align: center;
}
.document-subtitle {
    font-size: 14pt;
    font-weight: normal;
}
.meta-right {
    text-align: right;
    white-space: nowrap;
    font-size: 8px;
}
.meta-right strong { font-size: 9px; }

.folha {
    width: 100%;
    border-collapse: collapse;
    border: 1.4px solid #000;
}
.folha th, .folha td {
    border: 1px solid #000;
    padding: {{ $rowPad }};
    text-align: center;
    font-size: {{ $rowFont }};
    vertical-align: middle;
}
.folha thead th {
    background: #fff;
    font-weight: bold;
    font-size: {{ $headFont }};
}
.folha .grupo {
    font-size: {{ $groupFont }};
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: #f3f3f3;
}
.folha .subcol {
    width: 22px;
    font-size: {{ $headFont }};
    padding: 3px 1px;
}
.folha th.gen-head {
    width: 16px;
    font-size: 6px;
}
.folha td.n {
    width: 18px;
    font-weight: bold;
}
.folha td.nome {
    text-align: left;
    padding-left: 4px;
    font-size: {{ $rowFont }};
    white-space: nowrap;
}
.folha td.gen {
    width: 14px;
    font-size: 6.5px;
    text-transform: uppercase;
}
.nota-azul { color: #0047AB; font-weight: bold; }
.nota-vermelha { color: #C00000; font-weight: bold; }
.mt-cell { font-weight: bold; }
.desistente-row td { background: #f0f0f0 !important; color: #666; font-style: italic; }

.assinaturas {
    width: 100%;
    margin-top: 10px;
    border-collapse: collapse;
}
.assinaturas td {
    width: 50%;
    text-align: center;
    font-size: 7.5px;
    padding: 0 12px;
}
.assinatura-linha {
    border-top: 1px solid #000;
    margin: 16px 10px 2px 10px;
}
</style>
</head>
<body>

<div class="footer">
    <div class="footer-left">Gerado em {{ $gerado_em->format('d/m/Y H:i') }} — Mini-pauta anual</div>
    <div class="footer-center">Sistema Facilita – Gestão Escolar</div>
    <div class="footer-right">
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->text($pdf->get_width() - 70, $pdf->get_height() - 24, "Pág. ".$PAGE_NUM."/".$PAGE_COUNT, $font, 6.5, array(0.33,0.33,0.33));
            }
        </script>
    </div>
    <div style="clear:both;"></div>
</div>

<div class="institution-header">
    @if(!empty($logoBase64))
        <img class="institution-logo" src="{{ $logoBase64 }}" alt="Logótipo da instituição">
    @endif
    <div class="institution-name">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
    <div class="document-title">
        Mini-Pauta Anual<br>
        <span class="document-subtitle">{{ $disciplina->nome }} · Ano Lectivo {{ $ano_lectivo }}</span>
    </div>
</div>

<table class="meta-top">
    <tr>
        <td class="meta-right" style="width:100%;">
            <strong>{{ $classeLabel }}</strong><br>
            Turma: {{ $turma->nome }}<br>
            Sala: {{ $salaLabel }} · Período: {{ $periodoLabel }}
        </td>
    </tr>
</table>

<table class="folha">
    <thead>
        <tr>
            <th rowspan="2" style="width:18px;">Nº</th>
            <th rowspan="2" style="min-width:140px;">Nome completo</th>
            <th rowspan="2" class="gen-head">Gén.</th>
            <th class="grupo" colspan="{{ count($colunasAnuais[1] ?? []) }}">1º Trimestre</th>
            <th class="grupo" colspan="{{ count($colunasAnuais[2] ?? []) }}">2º Trimestre</th>
            <th class="grupo" colspan="{{ count($colunasAnuais[3] ?? []) }}">3º Trimestre</th>
        </tr>
        <tr>
            @foreach([1, 2, 3] as $t)
                @foreach(($colunasAnuais[$t] ?? []) as $col)
                    <th class="subcol">{{ $col['header'] }}</th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($linhas as $linha)
            @php
                $isDesistente = ($linha['situacao'] ?? null) === 'desistente'
                    || ($linha['t1']['situacao'] ?? null) === 'desistente';
                $gen = $linha['genero'] ?? null;
                $genCurto = match (mb_strtolower((string) $gen)) {
                    'm', 'masculino', 'male' => 'M',
                    'f', 'feminino', 'female' => 'F',
                    default => $gen ? mb_strtoupper(mb_substr((string) $gen, 0, 1)) : '—',
                };
            @endphp
            <tr class="{{ $isDesistente ? 'desistente-row' : '' }}">
                <td class="n">{{ $linha['numero_ordem'] }}</td>
                <td class="nome">{{ $linha['nome'] }}</td>
                <td class="gen">{{ $genCurto }}</td>
                @foreach([1 => 't1', 2 => 't2', 3 => 't3'] as $t => $campo)
                    @foreach(($colunasAnuais[$t] ?? []) as $col)
                        @php
                            $dadosT = $linha[$campo] ?? null;
                            $valor = null;
                            if ($dadosT && ! $isDesistente) {
                                $raw = $dadosT[$col['key']] ?? null;
                                $valor = $raw !== null ? (int) round((float) $raw) : null;
                            }
                            $ehResultado = ! empty($col['resultado']);
                            $classeNota = '';
                            if ($valor !== null) {
                                $classeNota = $valor >= 10 ? 'nota-azul' : 'nota-vermelha';
                            }
                        @endphp
                        <td class="{{ $ehResultado ? 'mt-cell' : '' }} {{ $classeNota }}">
                            {{ $isDesistente ? '—' : ($valor ?? '') }}
                        </td>
                    @endforeach
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="20" style="padding:12px;">Sem alunos nesta turma.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="assinaturas">
    <tr>
        <td>
            <div class="assinatura-linha"></div>
            O Professor
            @if(!empty($professor_nome))
                <br><strong>{{ $professor_nome }}</strong>
            @endif
        </td>
        <td>
            <div class="assinatura-linha"></div>
            O Director de Turma
            @if(!empty($director_turma_nome))
                <br><strong>{{ $director_turma_nome }}</strong>
            @endif
        </td>
    </tr>
</table>

</body>
</html>
