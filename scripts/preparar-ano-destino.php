<?php

/**
 * Prepara o ano destino: transferir estrutura + criar turmas de promoção.
 * NÃO transita alunos e NÃO activa o ano.
 *
 * Uso: php scripts/preparar-ano-destino.php 2025-2026 2026-2027
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Turma;
use App\Services\AlunoTransicaoService;
use App\Services\AnoLectivoTransferService;
use App\Services\FechoAnoValidationService;
use App\Services\FechoAnoWizardService;
use Illuminate\Support\Facades\DB;

$anoOrigem = $argv[1] ?? '2025-2026';
$anoDestino = $argv[2] ?? '2026-2027';

echo "=== Preparar ano destino (sem alunos / sem activar) ===\n";
echo "Origem:  {$anoOrigem}\n";
echo "Destino: {$anoDestino}\n\n";

$destino = AnoLectivo::where('nome', $anoDestino)->first();
if (! $destino) {
    fwrite(STDERR, "Ano destino {$anoDestino} não existe.\n");
    exit(1);
}
if (! $destino->isNovo()) {
    fwrite(STDERR, "Ano destino deve estar NOVO (estado actual: {$destino->estado}).\n");
    exit(1);
}

$transfer = app(AnoLectivoTransferService::class);
$erros = $transfer->validateTransfer($anoOrigem, $anoDestino);
if ($erros) {
    foreach ($erros as $e) {
        echo "[ERRO] {$e}\n";
    }
    exit(1);
}

echo "--- 1) Transferir estrutura (1:1) ---\n";
$result = $transfer->executeTransfer($anoOrigem, $anoDestino, true, true, true, false, false);
if (! ($result['success'] ?? false)) {
    echo "[ERRO] ".($result['message'] ?? 'falhou')."\n";
    exit(1);
}

$stats = $result['stats'];
echo "  Turmas criadas/mapeadas: ".($stats['turmas'] ?? 0)." (skipped: ".($stats['skipped'] ?? 0).")\n";
echo "  Associações: ".($stats['associacoes'] ?? 0)."\n";
echo "  Directores: ".($stats['directores'] ?? 0)."\n";
echo "  Coordenadores: ".($stats['coordenadores'] ?? 0)."\n";

app(FechoAnoValidationService::class)->marcarTransferenciaExecutada($anoOrigem, $anoDestino);
echo "  Marcador de transferência gravado.\n";

echo "\n--- 2) Criar turmas de promoção (classe+1) ---\n";

$anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
$anoDestinoId = (int) $destino->id;
$classes = Classe::query()->get()->keyBy(fn ($c) => (string) $c->codigo);

$turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->get();
$criadas = 0;
$jaExistiam = 0;
$ignoradas = 0;

DB::transaction(function () use ($turmasOrigem, $classes, $anoDestino, $anoDestinoId, &$criadas, &$jaExistiam, &$ignoradas) {
    foreach ($turmasOrigem as $origem) {
        $classeActual = (int) ($origem->classe->codigo ?? 0);
        if ($classeActual <= 0 || $classeActual >= 13) {
            $ignoradas++;
            echo "  [SKIP] {$origem->codigo} — classe {$classeActual} sem promoção\n";
            continue;
        }

        $classeAlvo = $classeActual + 1;
        $classe = $classes->get((string) $classeAlvo);
        if (! $classe) {
            $ignoradas++;
            echo "  [SKIP] {$origem->codigo} — classe {$classeAlvo} não cadastrada\n";
            continue;
        }

        $codigoNovo = preg_replace('/(\d{2})/', sprintf('%02d', $classeAlvo), $origem->codigo, 1);
        $nomeNovo = preg_replace('/(\d{2})/', sprintf('%02d', $classeAlvo), $origem->nome, 1);

        $existe = Turma::query()
            ->where('ano_lectivo_id', $anoDestinoId)
            ->where('codigo', $codigoNovo)
            ->first();

        if (! $existe) {
            $existe = Turma::query()
                ->where('ano_lectivo_id', $anoDestinoId)
                ->where('curso_id', $origem->curso_id)
                ->where('classe_id', $classe->id)
                ->when($origem->turno_id, fn ($q) => $q->where('turno_id', $origem->turno_id))
                ->where('nome', $nomeNovo)
                ->first();
        }

        if ($existe) {
            $jaExistiam++;
            echo "  [OK] Já existe {$existe->codigo}\n";
            continue;
        }

        // Evitar colisão de código se a turma 1:1 já usou o mesmo código noutro contexto
        $codigoFinal = $codigoNovo;
        $n = 1;
        while (Turma::where('ano_lectivo_id', $anoDestinoId)->where('codigo', $codigoFinal)->exists()) {
            $codigoFinal = $codigoNovo.'-P'.$n;
            $n++;
        }

        Turma::create([
            'codigo' => $codigoFinal,
            'nome' => $nomeNovo,
            'curso_id' => $origem->curso_id,
            'classe_id' => $classe->id,
            'sala_id' => $origem->sala_id,
            'turno_id' => $origem->turno_id,
            'ano_lectivo' => $anoDestino,
            'ano_lectivo_id' => $anoDestinoId,
            'activo' => true,
        ]);

        $criadas++;
        echo "  [+] {$origem->codigo} → {$codigoFinal} (classe {$classeAlvo})\n";
    }
});

echo "\nResumo promoção: criadas={$criadas}, já existiam={$jaExistiam}, ignoradas={$ignoradas}\n";

echo "\n--- 3) Estado wizard ---\n";
$estado = app(FechoAnoWizardService::class)->obterEstado($anoOrigem, $anoDestino);
foreach ($estado['passos'] as $passo) {
    $mark = $passo['concluido'] ? '[OK]' : '[--]';
    echo "  {$mark} {$passo['numero']}. {$passo['titulo']}: {$passo['detalhe']}\n";
}

$transicao = app(AlunoTransicaoService::class);
$origem = Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->get();
$destinoTurmas = Turma::with('classe')->where('ano_lectivo_id', $anoDestinoId)->get();
$mapP = $transicao->criarMapeamentoPromocao($origem, $destinoTurmas);
$mapR = $transicao->criarMapeamentoRepetencia($origem, $destinoTurmas);
$comP = collect($mapP)->filter()->count();
$comR = collect($mapR)->filter()->count();

echo "\nMapeamento: promoção {$comP}/{$origem->count()} · repetência {$comR}/{$origem->count()}\n";
echo "Turmas no destino: {$destinoTurmas->count()}\n";
echo "\n=== Concluído (alunos NÃO transitados; ano NÃO activado) ===\n";
