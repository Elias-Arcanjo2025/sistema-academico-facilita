<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Matriz de Prova</title>
    @include('pedagogico.pdf.partials.head-styles')
    <style>
        table{width:100%;border-collapse:collapse;}
        th,td{border:1px solid #333;padding:4px;}
        th{background:var(--pdf-table-header);}
    </style>
</head>
<body>
    <h2>Matriz — {{ $prova->disciplina->nome ?? '' }} ({{ $prova->tipo }})</h2>
    @php $linhas = $prova->matriz->linhas_json ?? []; @endphp
    <table>
        <thead>
            <tr>
                <th>Questão</th>
                <th>Conteúdo</th>
                <th>Competência</th>
                <th>Cotação</th>
            </tr>
        </thead>
        <tbody>
            @foreach($linhas as $linha)
            <tr>
                <td>{{ $linha['questao_ref'] ?? $linha['ref'] ?? $linha['numero'] ?? '' }}</td>
                <td>{{ $linha['conteudo'] ?? $linha['texto'] ?? $linha['tema'] ?? '' }}</td>
                <td>{{ $linha['competencia'] ?? $linha['tema'] ?? '' }}</td>
                <td>{{ isset($linha['cotacao']) ? number_format((float) $linha['cotacao'], 1, ',', '.') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
