<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plano de Aula</title>
    @include('pedagogico.pdf.partials.head-styles')
    <style>
        h1 { font-size: calc(var(--pdf-font-header) + 2px); margin-bottom: 4px; }
        h2 { font-size: calc(var(--pdf-font-body) + 1px); margin-top: 14px; background: var(--pdf-table-header); padding: 4px 6px; }
        .meta { font-size: calc(var(--pdf-font-body) - 1px); color: #444; margin-bottom: 12px; }
        ul, ol { margin: 4px 0; padding-left: 18px; }
        .item { margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #ddd; }
        .badge { font-size: 9px; text-transform: uppercase; color: #666; }
    </style>
</head>
<body>
    <h1>Plano de Aula — {{ $planoAula->planificacao->disciplina->nome ?? '' }}</h1>
    <p class="meta">
        <strong>Turma:</strong> {{ $planoAula->planificacao->turma->nome ?? '' }} |
        <strong>Tema:</strong> {{ ($planoAula->conteudo_json['tema'] ?? $planoAula->quinzena->tema ?? '-') }} |
        <strong>Duração:</strong> {{ $planoAula->tempos ?? 1 }} tempo(s) ({{ ($planoAula->tempos ?? 1) * 45 }} min) |
        <strong>Versão:</strong> {{ $planoAula->versao }}
    </p>
    @php $c = $planoAula->conteudo_json ?? []; @endphp

    <h2>1. Definição de conceitos</h2>
    @forelse($c['conceitos'] ?? [] as $conceito)
        @php
            $termo = is_array($conceito) ? ($conceito['termo'] ?? $conceito['nome'] ?? '') : '';
            $definicao = is_array($conceito) ? ($conceito['definicao'] ?? $conceito['descricao'] ?? '') : (string) $conceito;
        @endphp
        @if($termo || $definicao)
        <div class="item">
            @if($termo)<p><strong>{{ $termo }}</strong></p>@endif
            @if($definicao)<p>{{ $definicao }}</p>@endif
        </div>
        @endif
    @empty
        <p>—</p>
    @endforelse

    <h2>2. Conteúdo a ditar na sala de aula</h2>
    <p>{{ $c['conteudo_ditar'] ?? '—' }}</p>

    <h2>3. Exercícios, perguntas e casos práticos</h2>
    @forelse($c['exercicios_sala'] ?? [] as $item)
        @php
            $tipo = is_array($item) ? ($item['tipo'] ?? 'exercicio') : 'exercicio';
            $enunciado = is_array($item) ? ($item['enunciado'] ?? '') : $item;
            $resolucao = is_array($item) ? ($item['resolucao_orientadora'] ?? '') : '';
        @endphp
        <div class="item">
            <span class="badge">{{ $tipo }}</span>
            <p>{{ $enunciado }}</p>
            @if($resolucao)<p><em>Orientação: {{ $resolucao }}</em></p>@endif
        </div>
    @empty
        <p>—</p>
    @endforelse

    <h2>4. Tarefas para casa</h2>
    @if(is_array($c['tarefa_casa'] ?? null))
        <ol>@foreach($c['tarefa_casa'] as $t)<li>{{ is_array($t) ? ($t['enunciado'] ?? json_encode($t)) : $t }}</li>@endforeach</ol>
    @else
        <p>{{ $c['tarefa_casa'] ?? '—' }}</p>
    @endif
</body>
</html>
