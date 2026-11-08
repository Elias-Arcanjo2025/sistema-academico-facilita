<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Encarregado;
use App\Models\MatriculaHistorico;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoAuthService;
use App\Services\EncarregadoAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MatriculaService
{
    public function __construct(
        protected AlunoAuthService $alunoAuthService,
        protected EncarregadoAuthService $encarregadoAuthService,
    ) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function matricular(array $dados, ?User $actor = null): Aluno
    {
        return DB::transaction(function () use ($dados, $actor) {
            $turma = Turma::findOrFail($dados['turma_id']);

            $motivo = $dados['motivo'] ?? 'Matrícula inicial';

            $filiacao = [
                'pai' => [
                    'nome' => $dados['pai_nome'] ?? null,
                    'telefone' => $dados['pai_telefone'] ?? null,
                ],
                'mae' => [
                    'nome' => $dados['mae_nome'] ?? null,
                    'telefone' => $dados['mae_telefone'] ?? null,
                ],
                'encarregado' => [
                    'nome' => $dados['encarregado_nome'] ?? null,
                    'telefone' => $dados['encarregado_telefone'] ?? null,
                    'email' => $dados['encarregado_email'] ?? null,
                    'documento' => $dados['encarregado_documento'] ?? null,
                    'parentesco' => $dados['encarregado_parentesco'] ?? 'encarregado',
                    'criar_conta' => (bool) ($dados['encarregado_criar_conta'] ?? false),
                ],
            ];

            unset(
                $dados['pai_nome'], $dados['pai_telefone'],
                $dados['mae_nome'], $dados['mae_telefone'],
                $dados['encarregado_nome'], $dados['encarregado_telefone'], $dados['encarregado_email'],
                $dados['encarregado_documento'], $dados['encarregado_parentesco'], $dados['encarregado_criar_conta'],
                $dados['motivo'], $dados['foto']
            );

            $dados['numero_ordem'] = null;
            $dados['situacao_academica'] = $dados['situacao_academica'] ?? Aluno::SITUACAO_MATRICULADO;
            $dados['activo'] = $dados['activo'] ?? true;

            $aluno = Aluno::create($dados);
            $this->recalcularNumerosOrdem((int) $aluno->turma_id);
            $this->vincularFiliacao($aluno, $filiacao);

            try {
                $this->alunoAuthService->criarOuActualizarConta($aluno);
            } catch (\Throwable $e) {
                Log::warning('Falha ao criar conta de aluno na matrícula: '.$e->getMessage(), [
                    'aluno_id' => $aluno->id,
                ]);
            }

            $this->registarHistorico($aluno, MatriculaHistorico::TIPO_MATRICULA, [
                'turma_id' => $turma->id,
                'para_turma_id' => $turma->id,
                'ano_lectivo_id' => $turma->ano_lectivo_id,
                'motivo' => $motivo,
                'actor' => $actor,
                'dados' => [
                    'numero_processo' => $aluno->numero_processo,
                    'bi_numero' => $aluno->bi_numero,
                ],
            ]);

            return $aluno->fresh(['turma.curso', 'encarregados']);
        });
    }

    /**
     * @param  array{
     *   pai: array{nome?: ?string, telefone?: ?string},
     *   mae: array{nome?: ?string, telefone?: ?string},
     *   encarregado: array{nome?: ?string, telefone?: ?string, email?: ?string, documento?: ?string, parentesco?: string, criar_conta?: bool}
     * }  $filiacao
     */
    public function vincularFiliacao(Aluno $aluno, array $filiacao): void
    {
        foreach (['pai', 'mae'] as $parentesco) {
            $pessoa = $filiacao[$parentesco] ?? [];
            $nome = trim((string) ($pessoa['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }

            $encarregado = Encarregado::create([
                'nome' => $nome,
                'telefone' => $pessoa['telefone'] ?? null,
                'activo' => true,
            ]);

            $aluno->encarregados()->attach($encarregado->id, [
                'parentesco' => $parentesco,
                'principal' => false,
                'pode_ver_notas' => true,
                'pode_assinar_boletim' => false,
            ]);
        }

        $enc = $filiacao['encarregado'] ?? [];
        $nomeEnc = trim((string) ($enc['nome'] ?? ''));
        if ($nomeEnc === '') {
            return;
        }

        $parentesco = $enc['parentesco'] ?? 'encarregado';
        $existente = null;

        // Se o encarregado é o próprio pai/mãe e o nome coincide, reutiliza o registo.
        if (in_array($parentesco, ['pai', 'mae'], true)) {
            $existente = $aluno->encarregados()
                ->wherePivot('parentesco', $parentesco)
                ->where('nome', $nomeEnc)
                ->first();
        }

        if ($existente) {
            $aluno->encarregados()->updateExistingPivot($existente->id, [
                'principal' => true,
                'pode_ver_notas' => true,
                'pode_assinar_boletim' => true,
            ]);
            $encarregado = $existente;
        } else {
            $encarregado = Encarregado::create([
                'nome' => $nomeEnc,
                'telefone' => $enc['telefone'] ?? null,
                'email' => $enc['email'] ?? null,
                'documento' => $enc['documento'] ?? null,
                'activo' => true,
            ]);

            $aluno->encarregados()->attach($encarregado->id, [
                'parentesco' => $parentesco,
                'principal' => true,
                'pode_ver_notas' => true,
                'pode_assinar_boletim' => true,
            ]);
        }

        if (! empty($enc['criar_conta'])) {
            try {
                $this->encarregadoAuthService->criarOuActualizarConta($encarregado);
            } catch (\Throwable $e) {
                Log::warning('Falha ao criar conta de encarregado na matrícula: '.$e->getMessage(), [
                    'encarregado_id' => $encarregado->id,
                ]);
            }
        }
    }

    /**
     * Renova matrícula: move o aluno para uma turma do ano lectivo actual (ou indicado).
     *
     * @param  array{turma_id: int, motivo?: string}  $dados
     */
    public function renovar(Aluno $aluno, array $dados, ?User $actor = null): Aluno
    {
        return DB::transaction(function () use ($aluno, $dados, $actor) {
            $novaTurma = Turma::findOrFail($dados['turma_id']);
            $turmaAnterior = $aluno->turma;
            $anoActualId = Configuracao::getAnoLectivoActualId();

            if ((int) $novaTurma->ano_lectivo_id !== (int) $anoActualId) {
                throw ValidationException::withMessages([
                    'turma_id' => 'A renovação deve ser para uma turma do ano lectivo actual.',
                ]);
            }

            if ($turmaAnterior && (int) $turmaAnterior->ano_lectivo_id === (int) $novaTurma->ano_lectivo_id
                && (int) $turmaAnterior->id === (int) $novaTurma->id) {
                throw ValidationException::withMessages([
                    'turma_id' => 'O aluno já está nesta turma do ano lectivo actual.',
                ]);
            }

            $deTurmaId = $aluno->turma_id;
            $aluno->update([
                'turma_id' => $novaTurma->id,
                'numero_ordem' => null,
                'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
                'activo' => true,
            ]);

            if ($deTurmaId) {
                $this->recalcularNumerosOrdem((int) $deTurmaId);
            }
            $this->recalcularNumerosOrdem((int) $novaTurma->id);

            $this->registarHistorico($aluno->fresh(), MatriculaHistorico::TIPO_RENOVACAO, [
                'turma_id' => $novaTurma->id,
                'de_turma_id' => $deTurmaId,
                'para_turma_id' => $novaTurma->id,
                'ano_lectivo_id' => $novaTurma->ano_lectivo_id,
                'motivo' => $dados['motivo'] ?? 'Renovação de matrícula',
                'actor' => $actor,
            ]);

            return $aluno->fresh(['turma.curso']);
        });
    }

    /**
     * @param  array{
     *   turma_id?: int|null,
     *   de_turma_id?: int|null,
     *   para_turma_id?: int|null,
     *   ano_lectivo_id?: int|null,
     *   motivo?: string|null,
     *   dados?: array<string, mixed>|null,
     *   actor?: User|null
     * }  $opcoes
     */
    public function registarHistorico(Aluno $aluno, string $tipo, array $opcoes = []): MatriculaHistorico
    {
        $turmaId = $opcoes['turma_id'] ?? $aluno->turma_id;
        $anoLectivoId = $opcoes['ano_lectivo_id']
            ?? ($turmaId ? Turma::find($turmaId)?->ano_lectivo_id : null)
            ?? Configuracao::getAnoLectivoActualId();

        return MatriculaHistorico::create([
            'aluno_id' => $aluno->id,
            'ano_lectivo_id' => $anoLectivoId,
            'turma_id' => $turmaId,
            'tipo' => $tipo,
            'de_turma_id' => $opcoes['de_turma_id'] ?? null,
            'para_turma_id' => $opcoes['para_turma_id'] ?? null,
            'motivo' => $opcoes['motivo'] ?? null,
            'dados' => $opcoes['dados'] ?? null,
            'registado_por' => $opcoes['actor']?->id ?? auth()->id(),
        ]);
    }

    public function recalcularNumerosOrdem(int $turmaId): void
    {
        Aluno::recalcularNumerosOrdemTurma($turmaId);
    }
}
