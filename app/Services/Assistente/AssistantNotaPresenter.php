<?php

namespace App\Services\Assistente;

use App\Models\Nota;
use App\Models\Turma;
use Illuminate\Support\Collection;

/**
 * Apresentação de notas para o assistente IA (rótulos alinhados às regras do Facilita).
 */
class AssistantNotaPresenter
{
    /**
     * @param  Collection<int, Nota>  $notas
     * @return array<int, array{rotulo: string, tipo: string, valor: float, trimestre: ?int}>
     */
    public static function paraDisciplina(Collection $notas, ?Turma $turma = null): array
    {
        if ($notas->isEmpty()) {
            return [];
        }

        $regime = $turma?->curso?->regime();
        $usaPtNoT3 = $regime?->usaProvaTrimestral(3) ?? true;

        $primeira = $notas->first();
        $alunoId = $primeira->aluno_id;
        $disciplinaId = $primeira->disciplina_id;
        $turmaId = $primeira->turma_id;
        $anoLectivoId = $primeira->ano_lectivo_id;

        $linhas = [];

        foreach ($notas->sortBy(['trimestre', 'tipo']) as $nota) {
            if ($nota->tipo === Nota::TIPO_PT && (int) $nota->trimestre === 3 && ! $usaPtNoT3) {
                continue;
            }

            if ($nota->tipo === Nota::TIPO_PG) {
                continue;
            }

            $linhas[] = [
                'rotulo' => self::rotulo($nota->tipo, $nota->trimestre),
                'tipo' => $nota->tipo,
                'valor' => (float) $nota->valor,
                'trimestre' => $nota->trimestre,
            ];
        }

        if (! $usaPtNoT3 && $alunoId && $disciplinaId && $turmaId && $anoLectivoId) {
            $pg = Nota::valorProvaGlobal($alunoId, $disciplinaId, $turmaId, $anoLectivoId);
            if ($pg !== null) {
                $linhas[] = [
                    'rotulo' => 'PG',
                    'tipo' => Nota::TIPO_PG,
                    'valor' => $pg,
                    'trimestre' => 3,
                ];
            }
        }

        usort($linhas, function ($a, $b) {
            $ta = ($a['trimestre'] ?? 0) * 100 + self::ordemTipo($a['tipo']);
            $tb = ($b['trimestre'] ?? 0) * 100 + self::ordemTipo($b['tipo']);

            return $ta <=> $tb;
        });

        return array_values($linhas);
    }

    public static function rotulo(string $tipo, ?int $trimestre): string
    {
        if (in_array($tipo, [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT], true) && $trimestre) {
            return $tipo.$trimestre;
        }

        return $tipo;
    }

    protected static function ordemTipo(string $tipo): int
    {
        return match ($tipo) {
            Nota::TIPO_MAC => 1,
            Nota::TIPO_PP => 2,
            Nota::TIPO_PT => 3,
            Nota::TIPO_MT => 4,
            Nota::TIPO_PG => 5,
            default => 9,
        };
    }
}
