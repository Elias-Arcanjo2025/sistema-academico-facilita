<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Cartões de Estudante</title>
    <style>
        @page { margin: 7mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        .grid { width: 100%; text-align: center; }
        .card {
            width: 86mm;
            height: 54mm;
            display: inline-block;
            vertical-align: top;
            margin: 2.5mm 3mm;
            border: 1px solid #94a3b8;
            border-radius: 3.2mm;
            overflow: hidden;
            text-align: left;
            background: {{ $cartao['cor_primaria'] ?? '#0f766e' }};
            color: {{ $cartao['cor_texto'] ?? '#ffffff' }};
        }
        .barra { height: 3mm; background: {{ $cartao['cor_secundaria'] ?? '#134e4a' }}; }
        .inner { padding: 2mm 2.5mm; }
        .cabeca { width: 100%; border-collapse: collapse; }
        .cabeca td { vertical-align: middle; border: none; padding: 0; }
        .logo { width: 9mm; height: 9mm; object-fit: contain; display: block; margin: 0 auto; }
        .titulo { text-align: center; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .escola { text-align: center; font-size: 5.4pt; line-height: 1.1; margin-top: 0.4mm; opacity: 0.95; }
        .corpo { width: 100%; border-collapse: collapse; margin-top: 1.2mm; }
        .corpo td { vertical-align: top; border: none; padding: 0; }
        .foto {
            width: 15mm; height: 19mm;
            background: rgba(255,255,255,0.2);
            border: 0.35mm solid rgba(255,255,255,0.5);
        }
        .foto img { width: 15mm; height: 19mm; object-fit: cover; display: block; }
        .campos { padding-left: 2mm; }
        .nome { font-size: 7.2pt; font-weight: bold; margin-bottom: 0.5mm; }
        .campo { margin: 0.35mm 0; font-size: 6.4pt; line-height: 1.2; }
        .rodape {
            margin-top: 1mm;
            padding-top: 0.8mm;
            border-top: 0.25mm solid rgba(255,255,255,0.3);
            font-size: 5.5pt;
            text-align: center;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
<div class="grid">
@foreach($alunos as $aluno)
    @php
        $ano = $aluno->turma->anoLectivo->nome ?? ($aluno->turma->ano_lectivo ?? '—');
        $fotoDataUri = $fotos[$aluno->id] ?? null;
    @endphp
    <div class="card">
        <div class="barra"></div>
        <div class="inner">
            <table class="cabeca">
                <tr>
                    @if(!empty($cartao['mostrar_logo']) && !empty($logoDataUri))
                        <td style="width:10mm;" align="center"><img class="logo" src="{{ $logoDataUri }}" alt=""></td>
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
                        <td style="width:16mm;">
                            <div class="foto">
                                @if(!empty($fotoDataUri))
                                    <img src="{{ $fotoDataUri }}" alt="">
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
                <div class="rodape">Validade: {{ $cartao['validade_texto'] ?? '' }}</div>
            @endif
        </div>
    </div>
    @if(($loop->iteration % 8) === 0 && ! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</div>
</body>
</html>
