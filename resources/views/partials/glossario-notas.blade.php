@php
    $notaMinima = \App\Models\Configuracao::get('nota_minima_aprovacao', 10);
@endphp
<div class="offcanvas offcanvas-end" tabindex="-1" id="glossarioNotas" aria-labelledby="glossarioNotasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="glossarioNotasLabel">Como ler as notas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body small">
        <dl class="mb-0">
            <dt>MAC</dt>
            <dd class="text-muted">Média das Avaliações Contínuas (trabalhos, participação, testes).</dd>
            <dt>PP</dt>
            <dd class="text-muted">Prova Parcial do trimestre.</dd>
            <dt>PT</dt>
            <dd class="text-muted">Prova Trimestral (1.º e 2.º trimestres).</dd>
            <dt>PG</dt>
            <dd class="text-muted">Prova Global (3.º trimestre, regime politécnico).</dd>
            <dt>MT</dt>
            <dd class="text-muted">Média Trimestral — combinação de MAC, PP e PT (ou MAC+PP no 3.º trim. politécnico).</dd>
            <dt>MFT2</dt>
            <dd class="text-muted">Média dos dois primeiros trimestres ((MT1+MT2)/2).</dd>
            <dt>CF / CA / CFD</dt>
            <dd class="text-muted mb-0">Classificação final do ano / classificação anual / classificação final da disciplina (conforme a classe).</dd>
        </dl>
        <hr>
        <p class="text-muted mb-2">
            Nota mínima de aprovação: <strong>{{ $notaMinima }} valores</strong>.
            Valores abaixo indicam necessidade de recuperação ou reforço, conforme as regras da escola.
        </p>
        @if(Route::has('regras-avaliacao'))
            <a href="{{ route('regras-avaliacao') }}" class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-journal-bookmark me-1"></i> Ver regras completas de avaliação
            </a>
        @endif
    </div>
</div>
