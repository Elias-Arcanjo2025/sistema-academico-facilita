<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Services\EstatisticasService::class);
$cfg = app(App\Services\ConfiguracaoService::class);
$ano = App\Models\Configuracao::getAnoLectivoActual();
$cursoId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--curso=')) {
        $cursoId = (int) substr($arg, 8);
    }
}

$mapa = $svc->getMapaAproveitamento($ano, 1, $cursoId);
$inst = $cfg->getInstituicao();

$logoBase64 = null;
if (!empty($inst['logo'])) {
    $logoPath = storage_path('app/public/'.$inst['logo']);
    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
        $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
        echo 'Logo: yes'.PHP_EOL;
    }
}

$pdf = app(App\Services\PdfExportService::class)->loadView('exports.pdf.mapa-aproveitamento', [
    'anoLectivo' => $ano,
    'periodo' => 1,
    'trimestre' => 1,
    'trimestreRomano' => 'I',
    'tituloMapa' => App\Services\EstatisticasService::tituloMapaPdf(1, $ano),
    'mapa' => $mapa,
    'instituicao' => $inst,
    'gerado_em' => now(),
    'coordenador' => 'Test',
    'cursoNome' => null,
    'subdirector' => (object) ['name' => 'Test'],
    'logoBase64' => $logoBase64,
], 'landscape');

$out = storage_path('app/test-mapa.pdf');
file_put_contents($out, $pdf->output());

echo 'Pages: '.$pdf->getDomPDF()->getCanvas()->get_page_count().PHP_EOL;
echo 'Saved: '.$out.PHP_EOL;

foreach ($mapa['por_curso'] as $nome => $cd) {
    echo 'Curso '.$nome.': '.count($cd['turmas']).' turmas ('.(count($cd['turmas']) * 2 + 1).' linhas tabela)'.PHP_EOL;
}
