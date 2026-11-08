<?php

namespace App\Jobs;

use App\Models\Turma;
use App\Models\User;
use App\Services\PautaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessarPautaJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número de tentativas
     */
    public int $tries = 2;

    /**
     * Tempo máximo de execução em segundos
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $turmaId,
        public int $trimestre,
        public string $tipoCalculo, // 'medias_trimestre', 'notas_finais', 'mft2', 'cf', 'ca', 'cfd'
        public ?int $userId = null
    ) {
        // Define a fila como 'pauta' para separar jobs de cálculo
        $this->onQueue('pauta');
    }

    /**
     * Execute the job.
     */
    public function handle(PautaService $pautaService): void
    {
        try {
            $turma = Turma::with(['curso', 'classe', 'alunos' => fn($q) => $q->where('activo', true)])->findOrFail($this->turmaId);

            Log::info('Iniciando processamento de pauta', [
                'turma_id' => $this->turmaId,
                'trimestre' => $this->trimestre,
                'tipo' => $this->tipoCalculo,
            ]);

            $resultado = $this->executarCalculo($pautaService, $turma);

            activity()
                ->performedOn($turma)
                ->causedBy($this->userId)
                ->withProperties([
                    'trimestre' => $this->trimestre,
                    'tipo_calculo' => $this->tipoCalculo,
                    'resultado' => $resultado,
                ])
                ->log('Pauta processada via fila');

            if ($this->userId) {
                $user = User::find($this->userId);
                if ($user) {
                    $user->notify(new \App\Notifications\PautaProcessadaNotification(
                        $turma,
                        $this->trimestre,
                        $this->tipoCalculo,
                        $resultado
                    ));
                }
            }

            Log::info('Pauta processada com sucesso', [
                'turma_id' => $this->turmaId,
                'tipo' => $this->tipoCalculo,
                'resultado' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao processar pauta', [
                'turma_id' => $this->turmaId,
                'trimestre' => $this->trimestre,
                'tipo' => $this->tipoCalculo,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Executa o cálculo adequado para cada tipo, iterando sobre alunos e disciplinas da turma
     */
    private function executarCalculo(PautaService $pautaService, Turma $turma): array
    {
        $disciplinas = $turma->curso->disciplinas ?? collect();
        $alunos = $turma->alunos;
        $professorId = $this->userId;
        $classe = (int) ($turma->classe->codigo ?? 10);

        $resultado = ['calculadas' => 0, 'pendentes' => 0, 'erros' => 0];

        return match($this->tipoCalculo) {
            'medias_trimestre' => $this->calcularMediasTrimestre($pautaService, $turma, $disciplinas),
            'notas_finais' => $this->calcularNotasFinais($pautaService, $turma, $disciplinas),
            'mft2' => $this->calcularPorAlunoEDisciplina($pautaService, $alunos, $disciplinas, $turma, 'calcularEGuardarMFT2'),
            'cf' => $this->calcularPorAlunoEDisciplina($pautaService, $alunos, $disciplinas, $turma, 'calcularEGuardarCF'),
            'ca' => $this->calcularCATurma($pautaService, $alunos, $disciplinas, $turma, $classe),
            'cfd' => $this->calcularPorAlunoEDisciplina($pautaService, $alunos, $disciplinas, $turma, 'calcularEGuardarCFD'),
            default => throw new \InvalidArgumentException("Tipo de cálculo inválido: {$this->tipoCalculo}"),
        };
    }

    private function calcularMediasTrimestre(PautaService $pautaService, Turma $turma, $disciplinas): array
    {
        $resultado = ['calculadas' => 0, 'pendentes' => 0];

        foreach ($disciplinas as $disciplina) {
            $parcial = $pautaService->calcularMediasTrimestraisTurma(
                $turma->id,
                $disciplina->id,
                $this->trimestre,
                $this->userId ?? 0
            );
            $resultado['calculadas'] += $parcial['calculadas'];
            $resultado['pendentes'] += $parcial['pendentes'];
        }

        return $resultado;
    }

    private function calcularNotasFinais(PautaService $pautaService, Turma $turma, $disciplinas): array
    {
        $resultado = ['calculadas' => 0, 'pendentes' => 0];

        foreach ($disciplinas as $disciplina) {
            $parcial = $pautaService->calcularNotasFinaisTurma(
                $turma->id,
                $disciplina->id,
                $this->userId ?? 0
            );
            $resultado['calculadas'] += $parcial['calculadas'];
            $resultado['pendentes'] += $parcial['pendentes'];
        }

        return $resultado;
    }

    private function calcularPorAlunoEDisciplina(PautaService $pautaService, $alunos, $disciplinas, Turma $turma, string $metodo): array
    {
        $resultado = ['calculadas' => 0, 'pendentes' => 0];

        foreach ($alunos as $aluno) {
            foreach ($disciplinas as $disciplina) {
                $nota = $pautaService->$metodo(
                    $aluno->id,
                    $disciplina->id,
                    $turma->id,
                    $this->userId ?? 0
                );
                $nota ? $resultado['calculadas']++ : $resultado['pendentes']++;
            }
        }

        return $resultado;
    }

    private function calcularCATurma(PautaService $pautaService, $alunos, $disciplinas, Turma $turma, int $classe): array
    {
        $resultado = ['calculadas' => 0, 'pendentes' => 0];

        foreach ($alunos as $aluno) {
            foreach ($disciplinas as $disciplina) {
                $nota = $pautaService->calcularEGuardarCA(
                    $aluno->id,
                    $disciplina->id,
                    $turma->id,
                    $classe,
                    $this->userId ?? 0
                );
                $nota ? $resultado['calculadas']++ : $resultado['pendentes']++;
            }
        }

        return $resultado;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de processamento de pauta falhou definitivamente', [
            'turma_id' => $this->turmaId,
            'trimestre' => $this->trimestre,
            'tipo' => $this->tipoCalculo,
            'erro' => $exception->getMessage(),
        ]);

        // Notificar usuário sobre a falha
        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new \App\Notifications\JobFalhadoNotification(
                    'Processamento de Pauta',
                    $this->turmaId,
                    $exception->getMessage()
                ));
            }
        }
    }
}
