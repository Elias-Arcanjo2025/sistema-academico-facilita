<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ano = '2025-2026';
$anoId = \App\Models\AnoLectivo::getIdByNome($ano);
$notaMin = 10;

$rows = DB::table('notas')
    ->join('users', 'notas.professor_id', '=', 'users.id')
    ->join('turmas', 'notas.turma_id', '=', 'turmas.id')
    ->where('turmas.ano_lectivo_id', $anoId)
    ->where('turmas.activo', true)
    ->where('notas.ano_lectivo', $ano)
    ->whereIn('notas.tipo', ['MAC', 'PP', 'PT'])
    ->whereNotNull('notas.professor_id')
    ->whereNull('notas.archived_at')
    ->select(
        'users.id as professor_id',
        'users.name',
        'notas.aluno_id',
        'notas.disciplina_id',
        'notas.trimestre',
        'notas.tipo',
        'notas.valor',
        'notas.estado_conselho',
    )
    ->get();

echo "Raw rows: {$rows->count()}\n";

$deduped = $rows
    ->groupBy(fn ($n) => "{$n->professor_id}|{$n->aluno_id}|{$n->disciplina_id}|{$n->trimestre}|{$n->tipo}")
    ->map(fn ($g) => $g->firstWhere('estado_conselho', 'pos_conselho') ?? $g->first())
    ->values();

echo "After dedup: {$deduped->count()}\n";
echo "Null trimestre: {$deduped->whereNull('trimestre')->count()}\n";
echo "By trimestre: T1={$deduped->where('trimestre', 1)->count()} T2={$deduped->where('trimestre', 2)->count()} T3={$deduped->where('trimestre', 3)->count()}\n";

$top = $deduped->groupBy('professor_id')->map(function ($g) use ($notaMin) {
    $neg = $g->filter(fn ($n) => (float) $n->valor < $notaMin);

    return [
        'name' => $g->first()->name,
        'neg' => $neg->count(),
        'total' => $g->count(),
        'pct' => $g->count() > 0 ? round($neg->count() / $g->count() * 100, 1) : 0,
        'media' => $neg->count() > 0 ? round($neg->avg('valor'), 1) : null,
    ];
})->sortByDesc('neg')->take(5);

echo "\nTop 5 (anual, deduped):\n";
foreach ($top as $t) {
    echo "{$t['name']} | {$t['neg']} neg | {$t['total']} total | {$t['pct']}% | média neg {$t['media']}\n";
}

// Compare with service
$svc = app(\App\Services\RelatorioAcademicoService::class);
$rel = $svc->getRelatorio($anoId, $ano, null);
echo "\nService top 1: {$rel['top_professores_negativos']->first()->professor_nome} = {$rel['top_professores_negativos']->first()->total_negativas}\n";

// Check Administrador role
$admin = DB::table('users')->where('name', 'Administrador')->first();
if ($admin) {
    $adminNotes = $deduped->where('professor_id', $admin->id);
    echo "\nAdministrador: {$adminNotes->count()} lançamentos, {$adminNotes->filter(fn($n)=>(float)$n->valor<10)->count()} negativas\n";
    echo "Roles: ".DB::table('model_has_roles')->join('roles','roles.id','=','model_has_roles.role_id')->where('model_id',$admin->id)->pluck('roles.name')->join(', ')."\n";
}
