<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Chave de Correção</title>
    @include('pedagogico.pdf.partials.head-styles')
    <style>
        table{width:100%;border-collapse:collapse;}
        th,td{border:1px solid #333;padding:4px;vertical-align:top;}
        th{background:var(--pdf-table-header);}
        td.resposta{white-space:pre-line;}
    </style>
</head>
<body>
    @php
        use App\Support\ProvaQuestaoHelper;
        $respostas = $prova->chaveCorrecao->respostas_json ?? [];
        uksort($respostas, 'strnatcasecmp');
    @endphp
    <h2>Chave de Correção — {{ $prova->disciplina->nome ?? '' }} ({{ $prova->tipo }})</h2>
    <table>
        <thead><tr><th>Questão</th><th>Resposta</th><th>Cotação</th></tr></thead>
        <tbody>
            @foreach($respostas as $ref => $entrada)
            <tr>
                <td>{{ $ref }}</td>
                <td class="resposta">{{ ProvaQuestaoHelper::formatarRespostaChave($entrada) }}</td>
                <td>{{ is_array($entrada) ? ($entrada['cotacao'] ?? '') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
