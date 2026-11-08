@php
    use App\Support\PautaResultadoTransicao;
    use App\Services\RecursoService;
    use App\Services\RecuperacaoService;
@endphp

<div class="regras-avaliacao-conteudo">
    <p class="text-muted">
        Referência legal: <strong>Decreto Executivo n.º 424/25</strong> — Regulamento da Avaliação das Aprendizagens.
        Ano lectivo actual no sistema: <strong>{{ $anoLectivo }}</strong> · Trimestre actual: <strong>{{ $trimestre }}.º</strong>.
        Nota mínima de aprovação configurada: <strong>{{ $notaMinima }} valores</strong>.
    </p>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-tags me-2"></i>Tipos de nota</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Sigla</th>
                        <th>Designação</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>MAC</strong></td><td>Média das Avaliações Contínuas</td><td>Média das avaliações ao longo do trimestre.</td></tr>
                    <tr><td><strong>PP</strong></td><td>Prova Parcial</td><td>Prova intercalar do trimestre.</td></tr>
                    <tr><td><strong>PT</strong></td><td>Prova Trimestral</td><td>Prova de fecho (1.º e 2.º trimestres).</td></tr>
                    <tr><td><strong>PG</strong></td><td>Prova Global</td><td>3.º trimestre (regime politécnico); entra no cálculo da CA.</td></tr>
                    <tr><td><strong>MT</strong></td><td>Média Trimestral</td><td>Resultado do trimestre por disciplina.</td></tr>
                    <tr><td><strong>MFT2</strong></td><td>Média Final do 2.º Trimestre</td><td>Média entre MT1 e MT2 (politécnico).</td></tr>
                    <tr><td><strong>CF</strong></td><td>Classificação Final</td><td>Média anual parcial (antes da CA/CFD).</td></tr>
                    <tr><td><strong>CA</strong></td><td>Classificação Anual</td><td>Nota anual da disciplina (contínuas e terminais).</td></tr>
                    <tr><td><strong>CFD</strong></td><td>Classificação Final da Disciplina</td><td>Nota final em disciplinas terminais / 12.ª classe.</td></tr>
                    <tr><td><strong>REC</strong></td><td>Recuperação trimestral</td><td>Exame de recuperação no trimestre (10.ª/11.ª); substitui a MT quando aplicável.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-calculator me-2"></i>Fórmulas de médias (regime politécnico)</h3>
        <ul>
            <li><strong>MT (1.º e 2.º trimestres):</strong> MT = (MAC + PP + PT) ÷ 3</li>
            <li><strong>MT (3.º trimestre):</strong> MT = (MAC + PP) ÷ 2 — não há PT; usa-se PG.</li>
            <li><strong>MFT2:</strong> MFT2 = (MT1 + MT2) ÷ 2</li>
            <li><strong>CF:</strong> CF = (MFT2 + MT3) ÷ 2</li>
            <li><strong>CA (com PG):</strong> CA = 0,6 × CF + 0,4 × PG — se PG estiver em falta, conta como 0.</li>
            <li><strong>CFD:</strong> calculada nas disciplinas terminais com base na CA/CF do ano terminal.</li>
        </ul>
        <p class="text-muted small mb-0">
            No regime de administração, a CF resulta da média das três MT. O sistema aplica automaticamente o regime do curso.
        </p>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-clipboard-check me-2"></i>Aprovação na pauta trimestral e final</h3>
        <ul>
            <li>Disciplina <strong>positiva</strong> quando a nota oficial (MT no trimestre, ou CA/CFD no final) é ≥ {{ $notaMinima }} valores.</li>
            <li>Aluno <strong>APTO</strong> no trimestre se tiver no máximo <strong>2 disciplinas negativas</strong>.</li>
            <li>Aluno <strong>NÃO APTO</strong> com 3 ou mais disciplinas negativas.</li>
            <li>Na pauta final, a mesma regra aplica-se com os resultados <strong>APROVADO</strong> / <strong>REPROVADO</strong>.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-arrow-right-circle me-2"></i>Transição de classe (pauta T3 — 10.ª a 12.ª)</h3>
        <p>Resultados possíveis na coluna de transição:</p>
        <ul>
            <li><strong>TRANSITA</strong> — 0 disciplinas negativas.</li>
            <li><strong>TRANS. C/DEF.</strong> — 1 a {{ PautaResultadoTransicao::MAX_NEGATIVAS_TRANSITA }} disciplinas negativas (total), com pelo menos uma disciplina terminal negativa para recuperação anual.</li>
            <li><strong>N/TRANSITA</strong> — mais de {{ PautaResultadoTransicao::MAX_NEGATIVAS_TRANSITA }} negativas no total, ou {{ PautaResultadoTransicao::MAX_NEGATIVAS_CONTINUIDADE }} ou mais negativas em disciplinas de continuidade.</li>
        </ul>
        <p class="text-muted small">
            Nota oficial por disciplina: em contínuas usa-se a <strong>CA</strong>; em terminais usa-se a <strong>CFD</strong> (ou CA quando ainda não há CFD).
            Exames especiais (recuperação, recurso, melhoria, extraordinário) substituem ou melhoram a nota base conforme a lei.
        </p>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-bandaid me-2"></i>Exame de Recuperação anual (10.ª e 11.ª — Art.º 30.º)</h3>
        <ul>
            <li>Destina-se a alunos em <strong>TRANS. C/DEF.</strong> na pauta final do ano.</li>
            <li>Uma nota por disciplina elegível; a nota de recuperação <strong>substitui a CFD</strong> (ou CA) para efeitos de aproveitamento.</li>
            <li>Aprovação na disciplina via recuperação: ≥ {{ RecuperacaoService::APROVACAO_MINIMA }} valores.</li>
            <li>Alterações ou remoções de notas mantêm <strong>histórico</strong> — a nota anterior é arquivada, nunca apagada.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-arrow-counterclockwise me-2"></i>Exame de Recurso (12.ª — Art.º 31.º)</h3>
        <ul>
            <li>Aluno elegível quando tem entre 1 e <strong>{{ $recursoCfg['max_disciplinas'] }}</strong> disciplinas com CFD na faixa <strong>{{ $recursoCfg['nota_min'] }}–{{ $recursoCfg['nota_max'] }}</strong> valores.</li>
            <li><strong>Não</strong> é elegível se tiver CFD &lt; {{ $recursoCfg['nota_min'] }} em qualquer disciplina.</li>
            <li><strong>Não</strong> é elegível se todas as disciplinas blindadas do curso estiverem negativas simultaneamente.</li>
            <li>A nota do recurso <strong>substitui a CFD</strong> anterior (Art.º 31.º, n.º 3).</li>
            <li>Aprovação na disciplina via recurso: ≥ {{ RecursoService::APROVACAO_MINIMA }} valores.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-graph-up me-2"></i>Melhoria e Exame Extraordinário</h3>
        <ul>
            <li><strong>Melhoria (Art.º 34.º):</strong> a nota efectiva é o <strong>máximo</strong> entre a nota base e a nota de melhoria (não substitui se for inferior).</li>
            <li><strong>Extraordinário (Art.º 32.º):</strong> a nota do exame <strong>substitui</strong> a nota base, tal como recurso/recuperação.</li>
            <li>Prioridade na pauta: extraordinário &gt; recurso/recuperação &gt; melhoria.</li>
        </ul>
    </section>

    <section class="mb-2">
        <h3 class="h5 border-bottom pb-2"><i class="bi bi-clock-history me-2"></i>Histórico e auditoria</h3>
        <ul class="mb-0">
            <li>Toda alteração de nota (incluindo recurso e recuperação) <strong>arquiva</strong> a nota anterior com motivo e data.</li>
            <li>Lançamentos registam o professor responsável, data/hora e tipo de nota.</li>
            <li>Conselhos de notas concluídos bloqueiam relançamentos na disciplina afectada.</li>
            <li>O registo de actividade do sistema guarda eventos relevantes para auditoria.</li>
        </ul>
    </section>
</div>
