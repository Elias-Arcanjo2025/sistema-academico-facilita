<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ano = '2025-2026';
$anoId = \App\Models\AnoLectivo::getIdByNome($ano);
$svc = app(\App\Services\RelatorioAcademicoService::class);
$configSvc = app(\App\Services\ConfiguracaoService::class);

$rel = $svc->getRelatorio($anoId, $ano, 2);
$instituicao = $configSvc->getInstituicao();

$html = view('exports.pdf.relatorio-academico', array_merge($rel, [
    'anoLectivoId' => $anoId,
    'anoLectivo' => $ano,
    'trimestre' => 2,
    'periodo' => 'trimestral',
    'trimestreNome' => 'II Trimestre',
    'cursoNome' => 'Todos os cursos',
    'turmaNome' => '',
    'instituicao' => $instituicao,
    'logoBase64' => '',
    'dataGeracao' => now()->format('d/m/Y H:i'),
]))->render();

$out = storage_path('app/test-relatorio-academico.pdf');
\Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($out);
echo "Saved: {$out}\n";
