<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Cartão de Estudante — {{ $aluno->nome }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        .page { width: 100%; text-align: center; }
        .card {
            width: 86mm;
            height: 54mm;
            display: inline-block;
            vertical-align: top;
            margin: 4mm 6mm;
            border: 1px solid #94a3b8;
            border-radius: 3.5mm;
            overflow: hidden;
            text-align: left;
            background: {{ $cartao['cor_primaria'] ?? '#0f766e' }};
            color: {{ $cartao['cor_texto'] ?? '#ffffff' }};
        }
        .card-verso {
            background: #ffffff;
            color: #1f2937;
        }
        .barra {
            height: 3.2mm;
            background: {{ $cartao['cor_secundaria'] ?? '#134e4a' }};
        }
        .barra-clara {
            height: 3.2mm;
            background: {{ $cartao['cor_primaria'] ?? '#0f766e' }};
        }
        .inner { padding: 2.2mm 3mm 2mm; }
        .cabeca { width: 100%; border-collapse: collapse; margin-bottom: 1.5mm; }
        .cabeca td { vertical-align: middle; padding: 0; border: none; }
        .logo {
            width: 11mm;
            height: 11mm;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .titulo {
            text-align: center;
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.15;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .escola {
            text-align: center;
            font-size: 5.8pt;
            line-height: 1.15;
            opacity: 0.95;
            margin-top: 0.6mm;
        }
        .corpo { width: 100%; border-collapse: collapse; margin-top: 1.5mm; }
        .corpo td { vertical-align: top; border: none; padding: 0; }
        .foto {
            width: 18mm;
            height: 22mm;
            background: rgba(255,255,255,0.2);
            border: 0.45mm solid rgba(255,255,255,0.55);
            text-align: center;
        }
        .foto-verso {
            background: #e5e7eb;
            border-color: #9ca3af;
        }
        .foto img { width: 18mm; height: 22mm; object-fit: cover; display: block; }
        .campos { padding-left: 2.8mm; }
        .nome {
            font-size: 8.2pt;
            font-weight: bold;
            margin-bottom: 1mm;
            line-height: 1.2;
        }
        .campo { margin: 0.45mm 0; font-size: 6.8pt; line-height: 1.25; }
        .campo strong { font-size: 6pt; opacity: 0.9; }
        .rodape {
            margin-top: 1.5mm;
            padding-top: 1mm;
            border-top: 0.3mm solid rgba(255,255,255,0.35);
            font-size: 6pt;
            text-align: center;
            opacity: 0.95;
        }
        .verso-inner { padding: 4mm 4mm; }
        .verso-titulo {
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            color: {{ $cartao['cor_primaria'] ?? '#0f766e' }};
            margin-bottom: 2.5mm;
            text-transform: uppercase;
        }
        .verso-texto {
            text-align: center;
            font-size: 7pt;
            line-height: 1.45;
            color: #374151;
        }
        .verso-escola {
            text-align: center;
            font-size: 6.5pt;
            margin-top: 4mm;
            color: #6b7280;
        }
        .etiqueta {
            display: block;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            margin-bottom: 1mm;
        }
    </style>
</head>
<body>
@php
    $ano = $aluno->turma->anoLectivo->nome ?? ($aluno->turma->ano_lectivo ?? '—');
    $prim = $cartao['cor_primaria'] ?? '#0f766e';
@endphp
<div class="page">
    <div class="etiqueta">Frente</div>
    <div class="card">
        <div class="barra"></div>
        <div class="inner">
            <table class="cabeca">
                <tr>
                    @if(!empty($cartao['mostrar_logo']) && !empty($logoDataUri))
                        <td style="width:12mm;" align="center">
                            <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
                        </td>
                    @endif
                    <td align="center" style="text-align:center;">
                        <div class="titulo">{{ $cartao['titulo'] ?? 'Cartão de Estudante' }}</div>
                        <div class="escola">{{ $instituicao['nome'] ?? '' }}</div>
                    </td>
                </tr>
            </table>
            <table class="corpo">
                <tr>
                    @if(!empty($cartao['mostrar_foto']))
                        <td style="width:19mm;">
                            <div class="foto">
                                @if(!empty($fotoDataUri))
                                    <img src="{{ $fotoDataUri }}" alt="Foto">
                                @endif
                            </div>
                        </td>
                    @endif
                    <td class="campos">
                        <div class="nome">{{ $aluno->nome }}</div>
                        @if(!empty($cartao['mostrar_processo']))
                            <div class="campo"><strong>Processo:</strong> {{ $aluno->numero_processo }}</div>
                        @endif
                        @if(!empty($cartao['mostrar_bi']))
                            <div class="campo"><strong>BI:</strong> {{ $aluno->bi_numero ?: '—' }}</div>
                        @endif
                        @if(!empty($cartao['mostrar_turma']))
                            <div class="campo"><strong>Turma:</strong> {{ $aluno->turma->nome ?? '—' }}</div>
                        @endif
                        @if(!empty($cartao['mostrar_curso']))
                            <div class="campo"><strong>Curso:</strong> {{ $aluno->turma->curso->nome ?? '—' }}</div>
                        @endif
                        @if(!empty($cartao['mostrar_ano']))
                            <div class="campo"><strong>Ano:</strong> {{ $ano }}</div>
                        @endif
                    </td>
                </tr>
            </table>
            @if(!empty($cartao['mostrar_validade']))
                <div class="rodape">Validade: {{ $cartao['validade_texto'] ?? 'Ano lectivo em curso' }}</div>
            @endif
        </div>
    </div>

    @if(!empty($cartao['mostrar_verso']))
        <div class="etiqueta" style="margin-top:6mm;">Verso</div>
        <div class="card card-verso">
            <div class="barra-clara"></div>
            <div class="verso-inner">
                <div class="verso-titulo">{{ $cartao['titulo'] ?? 'Cartão de Estudante' }}</div>
                <div class="verso-texto">{{ $cartao['verso_texto'] ?? '' }}</div>
                <div class="verso-escola">{{ $instituicao['nome'] ?? '' }}</div>
                @if(!empty($instituicao['telefone']) || !empty($instituicao['endereco']))
                    <div class="verso-escola" style="margin-top:1.5mm;">
                        @if(!empty($instituicao['telefone']))Tel: {{ $instituicao['telefone'] }}@endif
                        @if(!empty($instituicao['endereco']))
                            @if(!empty($instituicao['telefone'])) · @endif
                            {{ $instituicao['endereco'] }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
</body>
</html>
