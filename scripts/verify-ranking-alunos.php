<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ano = '2025-2026';
$anoId = \App\Models\AnoLectivo::getIdByNome($ano);
$svc = app(\App\Services\RelatorioAcademicoService::class);

foreach ([1, 2, 3] as $tri) {
    $rel = $svc->getRelatorio($anoId, $ano, $tri === 3 && false ? null : $tri);
    $comRanking = $rel['melhores_por_turma']->filter(fn ($i) => $i['ranking']->isNotEmpty());
    $totalAlunos = $comRanking->sum(fn ($i) => $i['ranking']->count());
    echo "T{$tri} ({$rel['metrica_ranking']}): {$rel['total_turmas']} turmas, {$comRanking->count()} com ranking, {$totalAlunos} alunos no top\n";
}

$relAnual = $svc->getRelatorio($anoId, $ano, null);
$comRanking = $relAnual['melhores_por_turma']->filter(fn ($i) => $i['ranking']->isNotEmpty());
echo "Anual ({$relAnual['metrica_ranking']}): {$relAnual['total_turmas']} turmas, {$comRanking->count()} com ranking\n";

// Sample T3 CG10AT
$turma = \App\Models\Turma::where('nome', 'CG10AT')->where('ano_lectivo_id', $anoId)->first();
if ($turma) {
    $item = $relAnual['melhores_por_turma']->firstWhere(fn ($i) => $i['turma']->id === $turma->id);
    if ($item) {
        echo "\nCG10AT T3 ranking ({$item['ranking']->count()}):\n";
        foreach ($item['ranking'] as $e) {
            echo "  {$e['aluno']->nome}: {$e['media']} ({$e['total_disc']} disc.)\n";
        }
    }
}
