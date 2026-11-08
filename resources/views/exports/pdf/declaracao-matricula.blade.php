<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $docConfig['titulo_matricula'] ?? 'Declaração de Matrícula' }}</title>
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
            margin: 18px 0 16px;
            color: {{ $docConfig['cor_destaque'] ?? '#111' }};
        }
        .corpo { line-height: 1.8; text-align: justify; margin: 0 12px; font-size: 12px; }
        .dados {
            margin: 16px 12px;
            border: 1px solid #9ca3af;
            padding: 10px 12px;
            background: #f9fafb;
        }
        .dados p { margin: 3px 0; font-size: 11px; }
        .assinatura { margin-top: 48px; text-align: center; font-size: 11px; }
        .assinatura .linha { width: 220px; border-top: 1px solid #111; margin: 0 auto 6px; }
        .meta { margin-top: 24px; text-align: center; font-size: 10px; color: #444; }
        .local { margin: 20px 12px 0; font-size: 11px; }
    </style>
</head>
<body>
@php
    $docConfig = $docConfig ?? [];
    $pai = $aluno->nomePai();
    $mae = $aluno->nomeMae();
    $encarregado = $aluno->encarregadoPrincipal();
    $ano = $aluno->turma->anoLectivo->nome ?? ($aluno->turma->ano_lectivo ?? '—');
    $local = $docConfig['local_emissao'] ?? ($instituicao['endereco'] ?? 'Luanda');
@endphp
@if($docConfig['mostrar_logo'] ?? true)
    @include('exports.pdf.partials.header', ['instituicao' => $instituicao, 'logoDataUri' => $logoDataUri ?? null])
@endif

<div class="doc-titulo">{{ $docConfig['titulo_matricula'] ?? 'Declaração de Matrícula' }}</div>

<div class="corpo">
    <p>
        Declaramos que o(a) estudante <strong>{{ $aluno->nome }}</strong>,
        nº de processo <strong>{{ $aluno->numero_processo }}</strong>
        @if($docConfig['mostrar_bi'] ?? true)
            , Bilhete de Identidade nº <strong>{{ $aluno->bi_numero ?: '—' }}</strong>
        @endif
        @if(($docConfig['mostrar_filiacao'] ?? true) && ($pai || $mae))
            , filho(a) de <strong>{{ $pai ?: '—' }}</strong> e de <strong>{{ $mae ?: '—' }}</strong>
        @endif,
        se encontra devidamente matriculado(a) nesta instituição,
        na turma <strong>{{ $aluno->turma->nome ?? '—' }}</strong>
        do curso <strong>{{ $aluno->turma->curso->nome ?? '—' }}</strong>,
        ano lectivo <strong>{{ $ano }}</strong>,
        com situação académica
        <strong>{{ \App\Models\Aluno::getSituacoesAcademicas()[$aluno->situacao_academica] ?? $aluno->situacao_academica }}</strong>.
    </p>
</div>

@if(($docConfig['mostrar_nascimento'] ?? true) || ($docConfig['mostrar_genero'] ?? true) || ($docConfig['mostrar_encarregado'] ?? true))
<div class="dados">
    @if($docConfig['mostrar_nascimento'] ?? true)
        <p><strong>Data de nascimento:</strong> {{ optional($aluno->data_nascimento)->format('d/m/Y') ?: '—' }}</p>
    @endif
    @if($docConfig['mostrar_genero'] ?? true)
        <p><strong>Género:</strong> {{ $aluno->genero === 'M' ? 'Masculino' : ($aluno->genero === 'F' ? 'Feminino' : '—') }}</p>
    @endif
    <p><strong>Classe / Nº ordem:</strong> {{ $aluno->turma->classe->nome ?? '—' }} / {{ $aluno->numero_ordem ?? '—' }}</p>
    @if($docConfig['mostrar_encarregado'] ?? true)
        <p><strong>Encarregado de educação:</strong>
            @if($encarregado)
                {{ $encarregado->nome }}
                @if($encarregado->telefone) — Tel. {{ $encarregado->telefone }}@endif
                @if($encarregado->documento) — BI {{ $encarregado->documento }}@endif
            @else
                —
            @endif
        </p>
    @endif
</div>
@endif

<div class="corpo">
    <p>{{ $docConfig['texto_fecho_matricula'] ?? 'A presente declaração é emitida a pedido do interessado para os fins que se fizerem necessários.' }}</p>
</div>

<div class="local">{{ $local }}, aos {{ $emitidoEm->format('d') }} de {{ $emitidoEm->translatedFormat('F') }} de {{ $emitidoEm->format('Y') }}.</div>

@if($docConfig['mostrar_assinatura'] ?? true)
<div class="assinatura">
    <div class="linha"></div>
    <div>{{ $docConfig['cargo_assinatura'] ?? 'A Secretária / Direcção' }}</div>
</div>
@endif

<div class="meta">{{ $docConfig['texto_rodape'] ?? 'Documento emitido pelo sistema académico da instituição' }}</div>
</body>
</html>
