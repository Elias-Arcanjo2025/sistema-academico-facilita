<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exercício</title>
    @include('pedagogico.pdf.partials.head-styles')
    <style>h1{font-size:calc(var(--pdf-font-header) + 3px);}</style>
</head>
<body>
    <h1>Exercício — {{ $exercicio->planificacao->disciplina->nome ?? '' }}</h1>
    <p>{{ $exercicio->tipo }} | {{ $exercicio->dificuldade }}</p>
    @php $c = $exercicio->conteudo_json ?? []; @endphp
    @if(!empty($c['enunciado']))<p>{!! nl2br(e($c['enunciado'])) !!}</p>@endif
    @if(!empty($c['questoes']))
    <ol>@foreach($c['questoes'] as $q)<li>{{ is_array($q) ? ($q['texto'] ?? json_encode($q)) : $q }}</li>@endforeach</ol>
    @endif
</body>
</html>
