<?php

/**
 * Executa a transição de alunos entre anos lectivos.
 * NÃO activa o ano destino.
 *
 * Uso:
 *   php scripts/executar-transicao-alunos.php 2025-2026 2026-2027
 *   php scripts/executar-transicao-alunos.php 2025-2026 2026-2027 --dry-run
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Turma;
use App\Services\AlunoTransicaoService;
use App\Services\FechoAnoValidationService;
use App\Services\FechoAnoWizardService;

$anoOrigem = $argv[1] ?? '2025-2026';
$anoDestino = $argv[2] ?? '2026-2027';
$dryRun = in_array('--dry-run', $argv, true);

echo "=== Transição de alunos ===\n";
echo "Origem:  {$anoOrigem}\n";
echo "Destino: {$anoDestino}\n";
echo 'Modo:    '.($dryRun ? 'DRY-RUN' : 'EXECUÇÃO')."\n";
echo "Reprovados: SIM (mesma classe)\n\n";

$destino = AnoLectivo::where('nome', $anoDestino)->first();
if (! $destino || ! $destino->isNovo()) {
    fwrite(STDERR, "Ano destino deve existir e estar NOVO.\n");
    exit(1);
}

$service = app(AlunoTransicaoService::class);
$erros = $service->validateTransicao($anoOrigem, $anoDestino);
if ($erros) {
    foreach ($erros as $e) {
        echo "[ERRO] {$e}\n";
    }
    exit(1);
}

echo "--- Preview ---\n";
$preview = $service->previewTransicao($anoOrigem, $anoDestino);
$stats = $preview['stats'] ?? [];
foreach (['total', 'aprovados', 'reprovados', 'pendentes', 'desistentes'] as $k) {
    if (isset($stats[$k])) {
        echo "  {$k}: {$stats[$k]}\n";
    }
}
foreach ($preview['warnings'] ?? [] as $w) {
    echo "  [AVISO] {$w}\n";
}

if ($dryRun) {
    echo "\n=== Dry-run: nenhuma alteração feita ===\n";
    exit(0);
}

$anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
$anoDestinoId = AnoLectivo::getIdByNome($anoDestino);
$turmasOrigem = Turma::with(['curso', 'classe', 'turno'])->where('ano_lectivo_id', $anoOrigemId)->get();
$turmasDestino = Turma::with(['curso', 'classe', 'turno'])->where('ano_lectivo_id', $anoDestinoId)->get();

$mapP = $service->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
$mapR = $service->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

echo "\n--- A executar ---\n";
$started = microtime(true);
$result = $service->executeTransicao($anoOrigem, $anoDestino, $mapP, $mapR, true, null);
$elapsed = round(microtime(true) - $started, 1);

if (! ($result['success'] ?? false)) {
    echo '[ERRO] '.($result['message'] ?? $result['error'] ?? 'falhou')."\n";
    if (! empty($result['stats']['erros'])) {
        foreach (array_slice($result['stats']['erros'], 0, 20) as $e) {
            echo "  - {$e}\n";
        }
    }
    exit(1);
}

app(FechoAnoValidationService::class)->marcarTransicaoExecutada($anoOrigem, $anoDestino);

$s = $result['stats'];
echo '  '.$result['message']."\n";
echo "  Tempo: {$elapsed}s\n";
echo '  Transitados: '.($s['alunos_transitados'] ?? 0)."\n";
echo '  Aprovados: '.($s['alunos_aprovados'] ?? 0)."\n";
echo '  Reprovados: '.($s['alunos_reprovados'] ?? 0)."\n";
echo '  Ignorados: '.($s['alunos_ignorados'] ?? 0)."\n";
echo '  Encarregados: '.($s['vinculos_encarregado'] ?? 0)."\n";
echo '  Portais aluno: '.($s['contas_portal_actualizadas'] ?? 0)."\n";
if (! empty($s['erros'])) {
    echo '  Erros ('.count($s['erros'])."):\n";
    foreach (array_slice($s['erros'], 0, 15) as $e) {
        echo "    - {$e}\n";
    }
}

$activosOrigem = Aluno::where('activo', true)
    ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoOrigemId))
    ->count();
$alunosDestino = Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoDestinoId))->count();

echo "\n--- Pós-transição ---\n";
echo "  Activos na origem: {$activosOrigem}\n";
echo "  Alunos no destino: {$alunosDestino}\n";

$estado = app(FechoAnoWizardService::class)->obterEstado($anoOrigem, $anoDestino);
echo "\n--- Wizard ---\n";
foreach ($estado['passos'] as $passo) {
    $mark = $passo['concluido'] ? '[OK]' : '[--]';
    echo "  {$mark} {$passo['numero']}. {$passo['titulo']}: {$passo['detalhe']}\n";
}
echo '  Pode activar: '.($estado['pode_activar'] ? 'sim' : 'não')."\n";

echo "\n=== Concluído (ano NÃO activado) ===\n";
