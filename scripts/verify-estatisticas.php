<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ano = '2025-2026';
$svc = app(\App\Services\EstatisticasService::class);

foreach ([1, 2, 3, 0] as $tri) {
    $label = $tri === 0 ? 'Anual' : "T{$tri}";
    $s = $svc->getEstatisticasGeraisPorNotas($ano, $tri);
    $c = $svc->getEstatisticasPorClassePorNotas($ano, $tri);
    echo "{$label} ({$s['metrica']}): total={$s['total_mf']} aval={$s['avaliados_mf']} com={$s['com_mf']} ({$s['com_pct']}%) sem={$s['sem_mf']} des={$s['desistentes_mf']}\n";
    echo "  classes: " . collect($c['classes'])->map(fn($x)=>"{$x['nome']}:{$x['com_mf']}/{$x['avaliados']}")->join(', ') . "\n";
}
