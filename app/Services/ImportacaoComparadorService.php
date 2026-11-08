<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Nota;
use Illuminate\Support\Collection;

/**
 * Compara valores do Excel com o estado actual no Facilita antes da importação.
 */
class ImportacaoComparadorService
{
    /**
     * @param  Collection<int, \Illuminate\Support\Collection<string, mixed>>  $rows
     * @param  iterable<int, object{id:int, codigo:string}>  $disciplinas
     * @return array{total:int, divergencias:int, linhas:array<int, array<string, mixed>>}
     */
    public function comparar(
        Collection $rows,
        int $turmaId,
        iterable $disciplinas,
        int $trimestre,
        int $anoLectivoId,
    ): array {
        $disciplinaMap = [];
        foreach ($disciplinas as $disciplina) {
            $disciplinaMap[$disciplina->codigo] = $disciplina->id;
        }

        $alunos = Aluno::where('turma_id', $turmaId)->get()->keyBy('numero_ordem');
        $notasExistentes = Nota::where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
            ->get()
            ->groupBy(fn (Nota $n) => "{$n->aluno_id}.{$n->disciplina_id}.{$n->tipo}");

        $linhas = [];
        $divergencias = 0;

        foreach ($rows as $index => $row) {
            $linha = $index + 2;
            $numeroOrdem = $row['no'] ?? $row['numero'] ?? null;
            if (! $numeroOrdem) {
                continue;
            }

            $aluno = $alunos->get((int) $numeroOrdem);
            if (! $aluno) {
                continue;
            }

            foreach ($disciplinaMap as $codigo => $discId) {
                $colMac = strtolower($codigo).'_mac';
                $colPp = strtolower($codigo).'_pp';
                $colPt = strtolower($codigo).'_pt';

                foreach ([
                    Nota::TIPO_MAC => $row[$colMac] ?? $row[strtoupper($codigo).'_MAC'] ?? null,
                    Nota::TIPO_PP => $row[$colPp] ?? $row[strtoupper($codigo).'_PP'] ?? null,
                    Nota::TIPO_PT => $row[$colPt] ?? $row[strtoupper($codigo).'_PT'] ?? null,
                ] as $tipo => $valorExcel) {
                    if ($valorExcel === null || $valorExcel === '') {
                        continue;
                    }

                    $chave = "{$aluno->id}.{$discId}.{$tipo}";
                    $existente = $notasExistentes->get($chave)?->first();
                    $valorFacilita = $existente ? (float) $existente->valor : null;
                    $valorExcel = (float) $valorExcel;

                    if ($valorFacilita !== null && abs($valorFacilita - $valorExcel) > 0.01) {
                        $divergencias++;
                        $linhas[] = [
                            'linha' => $linha,
                            'aluno' => $aluno->nome,
                            'disciplina' => $codigo,
                            'tipo' => $tipo,
                            'facilita' => $valorFacilita,
                            'excel' => $valorExcel,
                        ];
                    }
                }
            }
        }

        return [
            'total' => count($linhas),
            'divergencias' => $divergencias,
            'linhas' => array_slice($linhas, 0, 100),
        ];
    }
}
