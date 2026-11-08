<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Etiquetas - {{ $turma->nome }}</title>
<style>
@page { margin: 8mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
.grid { width: 100%; }
.etiqueta {
    display: inline-block;
    width: 48%;
    border: 1px solid #333;
    padding: 8px 10px;
    margin: 0 1% 8px 0;
    box-sizing: border-box;
    vertical-align: top;
    min-height: 72px;
}
.nome { font-weight: bold; font-size: 12px; margin-bottom: 4px; }
.meta { color: #333; line-height: 1.35; }
</style>
</head>
<body>
@foreach($alunos as $aluno)
    <div class="etiqueta">
        <div class="nome">{{ $aluno->nome }}</div>
        <div class="meta">
            Processo: {{ $aluno->numero_processo }}<br>
            Turma: {{ $turma->nome }}
            @if($aluno->numero_ordem) · N.º {{ $aluno->numero_ordem }} @endif
        </div>
    </div>
@endforeach
</body>
</html>
