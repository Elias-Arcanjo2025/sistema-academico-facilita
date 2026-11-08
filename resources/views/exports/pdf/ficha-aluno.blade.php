<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $docConfig['titulo_ficha'] ?? 'Ficha do Aluno' }} — {{ $aluno->nome }}</title>
    @include('exports.pdf.partials.dynamic-styles')
    @include('exports.pdf.partials.styles')
    <style>
        .ficha-titulo {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0 14px;
            padding: 8px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: {{ $docConfig['cor_destaque'] ?? '#111' }};
        }
        .ficha-topo { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .ficha-topo td { vertical-align: top; border: none; padding: 0; }
        .foto-box {
            width: 100px;
            height: 120px;
            border: 1.5px solid #333;
            text-align: center;
            background: #f9fafb;
        }
        .foto-box img { width: 100px; height: 120px; object-fit: cover; display: block; }
        .foto-placeholder {
            width: 100px;
            height: 120px;
            line-height: 120px;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
        .secao {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 14px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid {{ $docConfig['cor_destaque'] ?? ($pdfConfig['colors']['primary'] ?? '#333') }};
            color: {{ $docConfig['cor_destaque'] ?? ($pdfConfig['colors']['primary'] ?? '#111') }};
        }
        .dados { width: 100%; border-collapse: collapse; }
        .dados th, .dados td {
            border: 1px solid #9ca3af;
            padding: 5px 8px;
            text-align: left;
            font-size: 10px;
            vertical-align: top;
        }
        .dados th {
            background: {{ $pdfConfig['colors']['table_header'] ?? '#f0f0f0' }};
            width: 32%;
            font-weight: bold;
        }
        .meta { margin-top: 18px; font-size: 9px; color: #555; text-align: center; }
        .assinaturas { width: 100%; margin-top: 36px; border-collapse: collapse; }
        .assinaturas td { width: 50%; text-align: center; font-size: 10px; padding-top: 28px; }
        .assinaturas .linha { border-top: 1px solid #333; width: 70%; margin: 0 auto 4px; }
    </style>
</head>
<body>
@php
    $docConfig = $docConfig ?? [];
    $pai = $aluno->encarregadoPorParentesco('pai');
    $mae = $aluno->encarregadoPorParentesco('mae');
    $encarregado = $aluno->encarregadoPrincipal();
@endphp
@if($docConfig['mostrar_logo'] ?? true)
    @include('exports.pdf.partials.header', ['instituicao' => $instituicao, 'logoDataUri' => $logoDataUri ?? null])
@endif

<div class="ficha-titulo">{{ $docConfig['titulo_ficha'] ?? 'Ficha do Aluno' }}</div>

<table class="ficha-topo">
    <tr>
        <td style="padding-right:12px;">
            <table class="dados">
                <tr><th>Nome completo</th><td><strong>{{ $aluno->nome }}</strong></td></tr>
                <tr><th>Nº processo</th><td>{{ $aluno->numero_processo }}</td></tr>
                @if($docConfig['mostrar_bi'] ?? true)
                    <tr><th>Bilhete de Identidade</th><td>{{ $aluno->bi_numero ?: '—' }}</td></tr>
                @endif
                @if($docConfig['mostrar_nascimento'] ?? true)
                    <tr><th>Data de nascimento</th><td>{{ optional($aluno->data_nascimento)->format('d/m/Y') ?: '—' }}</td></tr>
                @endif
                @if($docConfig['mostrar_genero'] ?? true)
                    <tr><th>Género</th><td>{{ $aluno->genero === 'M' ? 'Masculino' : ($aluno->genero === 'F' ? 'Feminino' : '—') }}</td></tr>
                @endif
            </table>
        </td>
        @if($docConfig['mostrar_foto_ficha'] ?? true)
            <td style="width:110px;">
                <div class="foto-box">
                    @if(!empty($fotoDataUri))
                        <img src="{{ $fotoDataUri }}" alt="Fotografia">
                    @else
                        <div class="foto-placeholder">SEM FOTO</div>
                    @endif
                </div>
            </td>
        @endif
    </tr>
</table>

<div class="secao">Dados académicos</div>
<table class="dados">
    <tr><th>Turma</th><td>{{ $aluno->turma->nome ?? '—' }}</td></tr>
    <tr><th>Curso</th><td>{{ $aluno->turma->curso->nome ?? '—' }}</td></tr>
    <tr><th>Classe</th><td>{{ $aluno->turma->classe->nome ?? '—' }}</td></tr>
    <tr><th>Nº de ordem</th><td>{{ $aluno->numero_ordem ?? '—' }}</td></tr>
    <tr><th>Ano lectivo</th><td>{{ $aluno->turma->anoLectivo->nome ?? ($aluno->turma->ano_lectivo ?? '—') }}</td></tr>
    <tr><th>Situação académica</th><td>{{ \App\Models\Aluno::getSituacoesAcademicas()[$aluno->situacao_academica] ?? $aluno->situacao_academica }}</td></tr>
</table>

@if(($docConfig['mostrar_filiacao'] ?? true) || ($docConfig['mostrar_encarregado'] ?? true))
<div class="secao">Filiação e encarregado de educação</div>
<table class="dados">
    @if($docConfig['mostrar_filiacao'] ?? true)
        <tr><th>Nome do pai</th><td>{{ $pai?->nome ?: '—' }}@if($pai?->telefone) — Tel. {{ $pai->telefone }}@endif</td></tr>
        <tr><th>Nome da mãe</th><td>{{ $mae?->nome ?: '—' }}@if($mae?->telefone) — Tel. {{ $mae->telefone }}@endif</td></tr>
    @endif
    @if($docConfig['mostrar_encarregado'] ?? true)
        <tr><th>Encarregado</th><td>
            @if($encarregado)
                {{ $encarregado->nome }}
                @if($encarregado->pivot->parentesco) ({{ ucfirst($encarregado->pivot->parentesco) }})@endif
                @if($encarregado->documento) — BI {{ $encarregado->documento }}@endif
                @if($encarregado->telefone) — Tel. {{ $encarregado->telefone }}@endif
                @if($encarregado->email) — {{ $encarregado->email }}@endif
            @else
                —
            @endif
        </td></tr>
    @endif
</table>
@endif

@if($docConfig['mostrar_assinatura'] ?? true)
<table class="assinaturas">
    <tr>
        <td><div class="linha"></div>O(A) Encarregado(a)</td>
        <td><div class="linha"></div>{{ $docConfig['cargo_assinatura'] ?? 'A Secretária / Direcção' }}</td>
    </tr>
</table>
@endif

<div class="meta">{{ $docConfig['texto_rodape'] ?? ('Emitido em '.$emitidoEm->format('d/m/Y H:i').' · Documento oficial da instituição') }}</div>
</body>
</html>
