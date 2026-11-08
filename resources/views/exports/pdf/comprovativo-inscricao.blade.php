<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $docConfig['titulo_comprovativo'] ?? 'Comprovativo de Inscrição' }}</title>
    @include('exports.pdf.partials.dynamic-styles')
    @include('exports.pdf.partials.styles')
    <style>
        .doc-titulo {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 1px;
            margin: 18px 0 14px;
            color: {{ $docConfig['cor_destaque'] ?? '#111' }};
        }
        .intro { text-align: justify; margin: 0 12px 10px; font-size: 12px; line-height: 1.6; }
        .box {
            border: 1.5px solid #333;
            padding: 12px 14px;
            margin: 10px 12px 16px;
            background: #fafafa;
        }
        .box table { width: 100%; border-collapse: collapse; }
        .box th, .box td {
            text-align: left;
            padding: 4px 0;
            font-size: 11px;
            vertical-align: top;
            border: none;
        }
        .box th { width: 38%; color: #374151; font-weight: bold; }
        .assinatura { margin-top: 40px; text-align: center; font-size: 11px; }
        .assinatura .linha { width: 220px; border-top: 1px solid #111; margin: 0 auto 6px; }
        .meta { margin-top: 20px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>
@php
    $docConfig = $docConfig ?? [];
    $pai = $aluno->nomePai();
    $mae = $aluno->nomeMae();
    $encarregado = $aluno->encarregadoPrincipal();
@endphp
@if($docConfig['mostrar_logo'] ?? true)
    @include('exports.pdf.partials.header', ['instituicao' => $instituicao, 'logoDataUri' => $logoDataUri ?? null])
@endif

<div class="doc-titulo">{{ $docConfig['titulo_comprovativo'] ?? 'Comprovativo de Inscrição' }}</div>
<div class="intro">Comprovamos a inscrição do(a) estudante abaixo identificado(a) nesta instituição de ensino:</div>

<div class="box">
    <table>
        <tr><th>Nome completo</th><td><strong>{{ $aluno->nome }}</strong></td></tr>
        <tr><th>Nº processo</th><td>{{ $aluno->numero_processo }}</td></tr>
        @if($docConfig['mostrar_bi'] ?? true)
            <tr><th>Nº BI</th><td>{{ $aluno->bi_numero ?: '—' }}</td></tr>
        @endif
        @if($docConfig['mostrar_nascimento'] ?? true)
            <tr><th>Data de nascimento</th><td>{{ optional($aluno->data_nascimento)->format('d/m/Y') ?: '—' }}</td></tr>
        @endif
        @if($docConfig['mostrar_genero'] ?? true)
            <tr><th>Género</th><td>{{ $aluno->genero === 'M' ? 'Masculino' : ($aluno->genero === 'F' ? 'Feminino' : '—') }}</td></tr>
        @endif
        @if($docConfig['mostrar_filiacao'] ?? true)
            <tr><th>Nome do pai</th><td>{{ $pai ?: '—' }}</td></tr>
            <tr><th>Nome da mãe</th><td>{{ $mae ?: '—' }}</td></tr>
        @endif
        @if($docConfig['mostrar_encarregado'] ?? true)
            <tr><th>Encarregado</th><td>
                @if($encarregado)
                    {{ $encarregado->nome }}
                    @if($encarregado->telefone) — {{ $encarregado->telefone }}@endif
                @else
                    —
                @endif
            </td></tr>
        @endif
        <tr><th>Turma / Classe</th><td>{{ $aluno->turma->nome ?? '—' }} / {{ $aluno->turma->classe->nome ?? '—' }}</td></tr>
        <tr><th>Curso</th><td>{{ $aluno->turma->curso->nome ?? '—' }}</td></tr>
        <tr><th>Ano lectivo</th><td>{{ $aluno->turma->anoLectivo->nome ?? ($aluno->turma->ano_lectivo ?? '—') }}</td></tr>
        <tr><th>Data de emissão</th><td>{{ $emitidoEm->format('d/m/Y H:i') }}</td></tr>
    </table>
</div>

@if($docConfig['mostrar_assinatura'] ?? true)
<div class="assinatura">
    <div class="linha"></div>
    <div>{{ $docConfig['cargo_assinatura'] ?? 'Secretaria Académica' }}</div>
</div>
@endif
<div class="meta">{{ $docConfig['texto_rodape'] ?? 'Guarde este comprovativo. Em caso de dúvida, contacte a secretaria.' }}</div>
</body>
</html>
