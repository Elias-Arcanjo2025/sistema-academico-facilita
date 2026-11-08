<?php

namespace App\Imports;

use App\Models\Aluno;
use App\Models\MatriculaHistorico;
use App\Models\User;
use App\Services\MatriculaService;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * Importação de matrículas (Secretaria): mesmos campos do cadastro de alunos + histórico.
 */
class MatriculasImport extends AlunosImport implements WithEvents
{
    /** @var list<array{numero_processo: string, turma_id: int}> */
    protected array $processados = [];

    protected ?User $actor = null;

    public function __construct(?int $turmaId = null, ?User $actor = null)
    {
        parent::__construct($turmaId);
        $this->actor = $actor;
    }

    public function model(array $row)
    {
        $aluno = parent::model($row);

        $turmaId = (int) ($row['turma_id'] ?? $this->turmaId);
        $this->processados[] = [
            'numero_processo' => (string) $row['numero_processo'],
            'turma_id' => $turmaId,
        ];

        return $aluno;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $this->processarFiliacoes();
                $this->registarHistoricos();
            },
        ];
    }

    protected function registarHistoricos(): void
    {
        if ($this->processados === []) {
            return;
        }

        /** @var MatriculaService $matriculaService */
        $matriculaService = app(MatriculaService::class);

        foreach ($this->processados as $item) {
            $aluno = Aluno::query()
                ->where('numero_processo', $item['numero_processo'])
                ->where('turma_id', $item['turma_id'])
                ->latest('id')
                ->first();

            if (! $aluno) {
                continue;
            }

            $jaTemHistorico = MatriculaHistorico::query()
                ->where('aluno_id', $aluno->id)
                ->where('tipo', MatriculaHistorico::TIPO_MATRICULA)
                ->exists();

            if ($jaTemHistorico) {
                continue;
            }

            $matriculaService->registarHistorico($aluno, MatriculaHistorico::TIPO_MATRICULA, [
                'turma_id' => $aluno->turma_id,
                'para_turma_id' => $aluno->turma_id,
                'motivo' => 'Matrícula importada via Excel',
                'actor' => $this->actor,
                'dados' => [
                    'numero_processo' => $aluno->numero_processo,
                    'bi_numero' => $aluno->bi_numero,
                    'origem' => 'excel',
                ],
            ]);
        }
    }
}
