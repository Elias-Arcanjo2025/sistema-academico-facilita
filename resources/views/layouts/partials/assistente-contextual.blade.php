@php
    $rota = request()->route()?->getName() ?? '';
    $dicas = [
        'notas.lancar-pg' => 'A Prova Global (PG) substitui o PT no 3.º trimestre. Confirme turma e disciplina antes de guardar.',
        'notas.lancar' => request('modo') === 'folha'
            ? 'Folha completa: preencha MAC, PP e PT/PG na mesma grelha. Campo vazio remove a nota. A MT calcula-se automaticamente.'
            : 'Por tipo: lance um tipo de cada vez (inclui exames). Use Tab entre células. A MT calcula-se automaticamente.',
        'recuperacoes.lancar' => 'A nota de recuperação substitui a CFD. Simule o impacto na transição antes de guardar.',
        'recursos.lancar' => 'O recurso substitui a CFD na 12.ª classe. Verifique elegibilidade na coluna Estado.',
        'pautas.show' => 'Na pauta T3, o resultado de transição usa recuperação/recurso como nota efectiva.',
        'pautas.index' => 'Consulte pautas por turma e trimestre. No T3, verifique o resultado de transição.',
        'exames.elegibilidade' => 'Lista consolidada de alunos elegíveis a recuperação e recurso.',
    ];
    $dica = null;
    foreach ($dicas as $prefixo => $texto) {
        if ($rota === $prefixo || str_starts_with($rota, $prefixo.'.')) {
            $dica = $texto;
            break;
        }
    }
@endphp
@if($dica)
<div class="alert alert-light border py-2 px-3 mb-3 small d-flex align-items-start gap-2 facilita-contextual-hint" role="note">
    <i class="bi bi-lightbulb text-warning flex-shrink-0"></i>
    <span>{{ $dica }}</span>
</div>
@endif
