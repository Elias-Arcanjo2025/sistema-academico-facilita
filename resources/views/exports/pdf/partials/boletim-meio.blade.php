{{--
    Partial reutilizável: 1 boletim ocupando metade de uma folha A4.
    Usado por exports.pdf.boletins-turma-duplo (2 boletins por folha).

    Variáveis esperadas:
    - $b           : array com dados do boletim (aluno, turma, disciplinas, trimestre, situacao_final, ...)
    - $logoBase64  : data URI do logo (string vazia se não existir)
    - $instituicao : array com nome, endereco, telefone, email
--}}

{{-- CABEÇALHO --}}
<table style="width:100%; border:0; border-collapse:collapse; margin-bottom:6px; border-bottom:1.5px solid #1e3a8a; padding-bottom:4px;">
    <tr>
        <td style="text-align:center; padding:0 0 4px 0;">
            @if(!empty($logoBase64))
                <img src="{{ $logoBase64 }}" class="logo-instituicao" alt="Logo">
            @endif
            <div class="instituicao">{{ $instituicao['nome'] ?? 'Instituição de Ensino' }}</div>
            <div class="info-instituicao">
                {{ $instituicao['endereco'] ?? '' }}
                @if(!empty($instituicao['telefone'])) | Tel: {{ $instituicao['telefone'] }} @endif
                @if(!empty($instituicao['email'])) | {{ $instituicao['email'] }} @endif
            </div>
            <div class="subtitulo">
                @if(!empty($b['trimestre']))
                    BOLETIM — {{ $b['trimestre'] }}.º TRIMESTRE | {{ $b['ano_lectivo'] }}
                @else
                    BOLETIM ESCOLAR ANUAL | {{ $b['ano_lectivo'] }}
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- INFO ALUNO --}}
<div class="info-card">
    <strong>Aluno:</strong> {{ mb_strtoupper($b['aluno']->nome, 'UTF-8') }}
    &nbsp;|&nbsp; <strong>Nº Proc.:</strong> {{ $b['aluno']->numero_processo }}
    <br>
    <strong>Turma:</strong> {{ $b['turma']->nome }}
    &nbsp;|&nbsp; <strong>Classe:</strong> {{ $b['turma']->classe->nome ?? '-' }}
    &nbsp;|&nbsp; <strong>Curso:</strong> {{ $b['turma']->curso->nome ?? '-' }}
</div>

{{-- TABELA DE NOTAS --}}
<table class="data-table">
<thead>
@if(empty($b['trimestre']))
    {{-- ANUAL --}}
    <tr>
        <th style="width:20px;">Nº</th>
        <th>Disciplina</th>
        <th style="width:32px;">1ºT</th>
        <th style="width:32px;">2ºT</th>
        <th style="width:32px;">3ºT</th>
        <th style="width:36px;">NF</th>
        <th style="width:60px;">Resultado</th>
    </tr>
@else
    {{-- TRIMESTRAL --}}
    <tr>
        <th style="width:20px;">Nº</th>
        <th>Disciplina</th>
        @foreach($b['colunas'] ?? [] as $coluna)
            <th style="width:32px;">{{ $coluna['header'] }}</th>
        @endforeach
        <th style="width:60px;">Resultado</th>
    </tr>
@endif
</thead>
<tbody>
@foreach($b['disciplinas'] as $i => $d)
<tr>
    <td>{{ $i+1 }}</td>
    <td class="disciplina">{{ \App\Support\DisciplinaAbreviador::paraBoletimCompacto($d['disciplina']->nome, $d['disciplina']->codigo ?? null) }}</td>
    @if(empty($b['trimestre']))
        <td class="{{ isset($d['mt1']) && round($d['mt1']) >= 10 ? 'aprovado' : (isset($d['mt1']) ? 'reprovado' : 'pendente') }}">{{ isset($d['mt1']) ? round($d['mt1']) : '-' }}</td>
        <td class="{{ isset($d['mt2']) && round($d['mt2']) >= 10 ? 'aprovado' : (isset($d['mt2']) ? 'reprovado' : 'pendente') }}">{{ isset($d['mt2']) ? round($d['mt2']) : '-' }}</td>
        <td class="{{ isset($d['mt3']) && round($d['mt3']) >= 10 ? 'aprovado' : (isset($d['mt3']) ? 'reprovado' : 'pendente') }}">{{ isset($d['mt3']) ? round($d['mt3']) : '-' }}</td>
        <td class="nota-final {{ isset($d['nota_final']) && round($d['nota_final']) >= 10 ? 'aprovado' : (isset($d['nota_final']) ? 'reprovado' : 'pendente') }}">
            {{ isset($d['nota_final']) ? round($d['nota_final']) : '-' }}
        </td>
    @else
        @foreach($b['colunas'] ?? [] as $coluna)
            @php $valor = $d[$coluna['key']] ?? null; @endphp
            <td class="{{ $valor !== null && round($valor) >= 10 ? 'aprovado' : ($valor !== null ? 'reprovado' : 'pendente') }}">
                {{ $valor !== null ? round($valor) : '-' }}
            </td>
        @endforeach
    @endif
    <td class="{{ ($d['situacao'] ?? '') === 'aprovado' ? 'aprovado' : (($d['situacao'] ?? '') === 'reprovado' ? 'reprovado' : 'pendente') }}">
        {{ ($d['situacao'] ?? 'pendente') === 'aprovado' ? 'Aprov.' : ((($d['situacao'] ?? '')) === 'reprovado' ? 'Reprov.' : 'Pend.') }}
    </td>
</tr>
@endforeach
</tbody>
</table>

{{-- RESUMO ESTATÍSTICAS (igual ao boletim individual) --}}
@if(isset($b['resumo']))
<div class="resumo-box">
    <span class="label">Disciplinas:</span>
    <span class="val">{{ $b['resumo']['total'] ?? count($b['disciplinas'] ?? []) }}</span>
    &nbsp;|&nbsp;
    <span class="label">Aprovadas:</span>
    <span class="val ok">{{ $b['resumo']['aprovadas'] ?? 0 }}</span>
    &nbsp;|&nbsp;
    <span class="label">Reprovadas:</span>
    <span class="val fail">{{ $b['resumo']['reprovadas'] ?? 0 }}</span>
    @if(($b['resumo']['pendentes'] ?? 0) > 0)
        &nbsp;|&nbsp;
        <span class="label">Pendentes:</span>
        <span class="val warn">{{ $b['resumo']['pendentes'] }}</span>
    @endif
    &nbsp;|&nbsp;
    <span class="label">Média Geral:</span>
    <span class="val">{{ isset($b['resumo']['media_geral']) && $b['resumo']['media_geral'] !== null ? number_format($b['resumo']['media_geral'], 0) : '-' }}</span>
</div>
@endif

{{-- RESULTADO FINAL (apenas no anual) --}}
@if(empty($b['trimestre']) && isset($b['situacao_final']))
<div class="resultado-final">
    RESULTADO FINAL:
    @if($b['situacao_final'] === 'aprovado')
        <span class="aprovado">&#10003; APROVADO</span>
    @elseif($b['situacao_final'] === 'reprovado')
        <span class="reprovado">&#10007; REPROVADO</span>
    @else
        <span class="pendente">PENDENTE</span>
    @endif
</div>
@endif

{{-- ASSINATURAS --}}
<table class="assinaturas">
<tr>
    <td style="width:50%">
        <div class="linha-assinatura"></div>
        Director de Turma
    </td>
    <td style="width:50%">
        @include('exports.pdf.partials.assinatura-encarregado', ['assinatura_encarregado' => $b['assinatura_encarregado'] ?? ($assinatura_encarregado ?? null)])
    </td>
</tr>
</table>
