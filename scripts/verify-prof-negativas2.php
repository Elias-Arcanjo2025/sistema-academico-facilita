<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ano = '2025-2026';
$anoId = \App\Models\AnoLectivo::getIdByNome($ano);

foreach ([null, 2] as $tri) {
    $q = DB::table('notas')
        ->join('users', 'notas.professor_id', '=', 'users.id')
        ->join('turmas', 'notas.turma_id', '=', 'turmas.id')
        ->where('turmas.ano_lectivo_id', $anoId)
        ->where('turmas.activo', true)
        ->where('notas.ano_lectivo', $ano)
        ->whereIn('notas.tipo', ['MAC', 'PP', 'PT'])
        ->whereNotNull('notas.professor_id')
        ->whereNull('notas.archived_at')
        ->when($tri !== null, fn ($q) => $q->where('notas.trimestre', $tri));

    $rows = $q->select('users.id as professor_id', 'users.name', 'notas.aluno_id', 'notas.disciplina_id', 'notas.trimestre', 'notas.tipo', 'notas.valor', 'notas.estado_conselho')->get();

    $deduped = $rows->groupBy(fn ($n) => "{$n->professor_id}|{$n->aluno_id}|{$n->disciplina_id}|{$n->trimestre}|{$n->tipo}")
        ->map(fn ($g) => $g->firstWhere('estado_conselho', 'pos_conselho') ?? $g->first())->values();

    $prePos = $rows->count() - $deduped->count();
    $j = $deduped->where('name', 'Josué Fernando Wafuntuca');
    $negJ = $j->filter(fn ($n) => (float) $n->valor < 10)->count();

    $label = $tri === null ? 'ANUAL' : "T{$tri}";
    echo "{$label}: raw={$rows->count()} dedup={$deduped->count()} removed={$prePos}\n";
    echo "  Josué: {$negJ} negativas de {$j->count()} lançamentos\n";
}

// pre/post conselho pairs?
$pairs = DB::table('notas')
    ->whereIn('tipo', ['MAC', 'PP', 'PT'])
    ->whereNull('archived_at')
    ->where('ano_lectivo', $ano)
    ->select('aluno_id', 'disciplina_id', 'trimestre', 'tipo', DB::raw('count(*) as c'))
    ->groupBy('aluno_id', 'disciplina_id', 'trimestre', 'tipo')
    ->having('c', '>', 1)
    ->limit(5)
    ->get();
echo "\nAmostra chaves com >1 registo (pre+pos?): {$pairs->count()} grupos\n";
foreach ($pairs as $p) {
    echo "  aluno={$p->aluno_id} disc={$p->disciplina_id} T{$p->trimestre} {$p->tipo}: {$p->c} registos\n";
}

// Who is Administrador launching for?
$adminId = DB::table('users')->where('name', 'Administrador')->value('id');
$byTipo = DB::table('notas')
    ->where('professor_id', $adminId)
    ->where('ano_lectivo', $ano)
    ->whereIn('tipo', ['MAC', 'PP', 'PT'])
    ->whereNull('archived_at')
    ->select('tipo', DB::raw('count(*) as c'))
    ->groupBy('tipo')
    ->get();
echo "\nAdministrador por tipo:\n";
foreach ($byTipo as $b) {
    echo "  {$b->tipo}: {$b->c}\n";
}
