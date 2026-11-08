<?php

/**
 * Activa o ano destino (fecho de ano).
 *
 * Uso: php scripts/activar-ano-lectivo.php 2025-2026 2026-2027
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Services\AlunoTransicaoService;
use App\Services\FechoAnoValidationService;
use App\Services\FechoAnoWizardService;

$anoOrigem = $argv[1] ?? '2025-2026';
$anoDestino = $argv[2] ?? '2026-2027';

echo "=== Activar ano lectivo ===\n";
echo "Origem:  {$anoOrigem}\n";
echo "Destino: {$anoDestino}\n\n";

$checklist = app(FechoAnoValidationService::class)->validateActivar($anoOrigem, $anoDestino);
if (! ($checklist['ok'] ?? false)) {
    foreach ($checklist['erros'] ?? [] as $e) {
        echo "[ERRO] {$e}\n";
    }
    foreach ($checklist['avisos'] ?? [] as $a) {
        echo "[AVISO] {$a}\n";
    }
    exit(1);
}

foreach ($checklist['avisos'] ?? [] as $a) {
    echo "[AVISO] {$a}\n";
}

$destino = AnoLectivo::where('nome', $anoDestino)->first();
if (! $destino) {
    fwrite(STDERR, "Ano destino não encontrado.\n");
    exit(1);
}
if ($destino->isActual()) {
    echo "Já está activo.\n";
    exit(0);
}

$notasArquivadas = app(AlunoTransicaoService::class)->arquivarNotasAnoLectivo($anoOrigem);
Configuracao::fecharTrimestre(3);
AnoLectivo::setActual($anoDestino);

$actual = Configuracao::getAnoLectivoActual();
$estado = app(FechoAnoWizardService::class)->obterEstado($anoOrigem, $anoDestino);

echo "Notas arquivadas (origem): {$notasArquivadas}\n";
echo "Ano actual (config): {$actual}\n";
echo "Estado destino: ".($destino->fresh()->estado)." (1=actual)\n\n";

foreach ($estado['passos'] as $passo) {
    $mark = $passo['concluido'] ? '[OK]' : '[--]';
    echo "  {$mark} {$passo['numero']}. {$passo['titulo']}: {$passo['detalhe']}\n";
}

echo "\n=== Ano {$anoDestino} activado ===\n";
