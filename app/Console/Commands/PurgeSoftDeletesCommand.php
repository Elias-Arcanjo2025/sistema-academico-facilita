<?php

namespace App\Console\Commands;

use App\Models\MaterialAcademico;
use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Models\Pedagogico\PedCorrecao;
use App\Models\Pedagogico\PedExercicio;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedPlanoAula;
use App\Models\Pedagogico\PedProva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeSoftDeletesCommand extends Command
{
    protected $signature = 'facilita:purge-soft-deletes
                            {--days=90 : Dias após soft-delete para eliminação definitiva}
                            {--dry-run : Apenas listar o que seria eliminado}';

    protected $description = 'Elimina definitivamente registos soft-deleted antigos (materiais e pedagógico)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');
        $corte = now()->subDays($days);

        $this->info(($dry ? '[DRY-RUN] ' : '')."A limpar soft-deletes anteriores a {$corte->toDateString()} ({$days} dias).");

        $modelos = [
            MaterialAcademico::class,
            PedPlanificacao::class,
            PedPlanoAula::class,
            PedAula::class,
            PedProva::class,
            PedExercicio::class,
            PedCorrecao::class,
            PedBancoQuestao::class,
        ];

        $total = 0;

        foreach ($modelos as $modelo) {
            if (! class_exists($modelo) || ! method_exists($modelo, 'onlyTrashed')) {
                continue;
            }

            $query = $modelo::onlyTrashed()->where('deleted_at', '<', $corte);
            $count = $query->count();
            $this->line(class_basename($modelo).": {$count}");

            if ($dry || $count === 0) {
                $total += $count;
                continue;
            }

            $query->chunkById(50, function ($rows) use ($modelo, &$total) {
                foreach ($rows as $row) {
                    if ($modelo === MaterialAcademico::class && ! empty($row->ficheiro_path)) {
                        try {
                            Storage::disk('local')->delete($row->ficheiro_path);
                        } catch (\Throwable) {
                            // continua com forceDelete
                        }
                    }
                    $row->forceDelete();
                    $total++;
                }
            });
        }

        $this->info($dry ? "Seriam eliminados: {$total}" : "Eliminados: {$total}");

        return self::SUCCESS;
    }
}
