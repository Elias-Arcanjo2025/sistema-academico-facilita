<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Livro de Turma - {{ $turma->nome }}</title>
@php $pdfPageOrientation = 'portrait'; @endphp
@include('exports.pdf.partials.dynamic-styles')
<style>
@page { margin: 12mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h1 { font-size: 16px; margin: 0 0 4px; }
h2 { font-size: 13px; margin: 0 0 12px; color: #444; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #333; padding: 4px 6px; }
th { background: #eee; text-align: left; }
.meta { margin-bottom: 8px; color: #555; }
.assinaturas { margin-top: 28px; display: table; width: 100%; }
.assinaturas div { display: table-cell; width: 50%; text-align: center; padding-top: 40px; }
</style>
</head>
<body>
    <h1>{{ $instituicao['nome'] ?? 'Instituição' }}</h1>
    <h2>Livro de Turma — {{ $turma->nome }}</h2>
    <div class="meta">
        Curso: {{ $turma->curso->nome ?? '—' }}
        · Classe: {{ $turma->classe->nome ?? '—' }}
        · Ano lectivo: {{ $turma->ano_lectivo ?? ($turma->anoLectivo->nome ?? '—') }}
        · Alunos: {{ $alunos->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40px;">N.º</th>
                <th>Nome</th>
                <th style="width:110px;">Processo</th>
                <th style="width:40px;">Sex.</th>
                <th style="width:90px;">Nascimento</th>
                <th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($alunos as $aluno)
                <tr>
                    <td>{{ $aluno->numero_ordem ?? $loop->iteration }}</td>
                    <td>{{ $aluno->nome }}</td>
                    <td>{{ $aluno->numero_processo }}</td>
                    <td>{{ $aluno->genero ?? '—' }}</td>
                    <td>{{ $aluno->data_nascimento?->format('d/m/Y') ?? '—' }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="assinaturas">
        <div>O Director de Turma<br>________________________</div>
        <div>A Secretaria<br>________________________</div>
    </div>
</body>
</html>
