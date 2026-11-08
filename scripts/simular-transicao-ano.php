<?php

/**
 * Simulação (dry-run) da transição de ano lectivo.
 *
 * Uso:
 *   php scripts/simular-transicao-ano.php
 *   php scripts/simular-transicao-ano.php 2024-2025 2025-2026
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Services\AlunoTransicaoService;
use App\Services\AnoLectivoTransferService;
use App\Services\FechoAnoWizardService;

$anoOrigem = $argv[1] ?? Configuracao::getAnoLectivoActual();
$anoDestino = $argv[2] ?? null;

if (! $anoDestino) {
    $parts = explode('-', $anoOrigem);
    if (count($parts) === 2) {
        $anoDestino = ((int) $parts[0] + 1).'-'.((int) $parts[1] + 1);
    }
}

echo "=== Simulação transição de ano lectivo ===\n";
echo "Origem:  {$anoOrigem}\n";
echo "Destino: ".($anoDestino ?: '(não definido)')."\n\n";

$transfer = app(AnoLectivoTransferService::class);
$transicao = app(AlunoTransicaoService::class);
$wizard = app(FechoAnoWizardService::class);

// 1. Validação transferência
echo "--- Validação transferência ---\n";
$errosTransfer = $transfer->validateTransfer($anoOrigem, $anoDestino ?? '');
if ($errosTransfer) {
    foreach ($errosTransfer as $e) {
        echo "  [ERRO] {$e}\n";
    }
} else {
    echo "  [OK] Transferência pode ser executada.\n";
}

// 2. Preview
if ($anoDestino) {
    echo "\n--- Preview transferência ---\n";
    $preview = $transfer->previewTransfer($anoOrigem, $anoDestino);
    foreach ($preview['stats'] as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
    foreach ($preview['warnings'] as $w) {
        echo "  [AVISO] {$w}\n";
    }
    foreach ($preview['conflicts'] as $c) {
        echo "  [CONFLITO] {$c}\n";
    }
}

// 3. Validação transição alunos
echo "\n--- Validação transição alunos ---\n";
$errosTransicao = $transicao->validateTransicao($anoOrigem, $anoDestino ?? '');
if ($errosTransicao) {
    foreach ($errosTransicao as $e) {
        echo "  [ERRO] {$e}\n";
    }
} else {
    echo "  [OK] Transição de alunos pode ser executada.\n";
}

// 4. Mapeamento promoção / repetência
$anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
$anoDestinoId = $anoDestino ? AnoLectivo::getIdByNome($anoDestino) : null;

if ($anoOrigemId && $anoDestinoId) {
    echo "\n--- Mapeamento turmas ---\n";
    $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->get();
    $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $anoDestinoId)->get();

    $mapPromocao = $transicao->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
    $mapRepetencia = $transicao->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

    $semPromocao = [];
    $semRepetencia = [];
    foreach ($turmasOrigem as $t) {
        if (empty($mapPromocao[$t->id])) {
            $semPromocao[] = $t->codigo;
        }
        if (empty($mapRepetencia[$t->id])) {
            $semRepetencia[] = $t->codigo;
        }
    }

    echo '  Turmas origem: '.$turmasOrigem->count()."\n";
    echo '  Turmas destino: '.$turmasDestino->count()."\n";
    echo '  Com promoção: '.(count($turmasOrigem) - count($semPromocao))."\n";
    echo '  Sem turma promoção: '.count($semPromocao).($semPromocao ? ' ('.implode(', ', $semPromocao).')' : '')."\n";
    echo '  Sem turma repetência: '.count($semRepetencia).($semRepetencia ? ' ('.implode(', ', $semRepetencia).')' : '')."\n";

    $alunosOrigem = Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoOrigemId))->count();
    echo "\n  Alunos na origem: {$alunosOrigem}\n";
}

// 5. Estado wizard
if ($anoDestino) {
    echo "\n--- Estado wizard ---\n";
    $estado = $wizard->obterEstado($anoOrigem, $anoDestino);
    foreach ($estado['passos'] as $passo) {
        $icon = $passo['concluido'] ? '[OK]' : '[--]';
        echo "  {$icon} {$passo['numero']}. {$passo['titulo']}: {$passo['detalhe']}\n";
    }
    echo '  Pode activar: '.($estado['pode_activar'] ? 'sim' : 'não')."\n";
}

echo "\n=== Fim (nenhuma alteração foi feita na base de dados) ===\n";
