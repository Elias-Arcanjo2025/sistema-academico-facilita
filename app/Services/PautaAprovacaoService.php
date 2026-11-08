<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\PautaAprovacao;
use App\Models\User;
use InvalidArgumentException;

class PautaAprovacaoService
{
    public function podeAprovar(User $user): bool
    {
        return $user->can('pautas.aprovar') || $user->temControloAcademicoInstitucional();
    }

    public function obter(?int $turmaId, ?int $trimestre, ?string $anoLectivoNome = null): ?PautaAprovacao
    {
        if (! $turmaId || ! $trimestre) {
            return null;
        }

        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivoNome ?: Configuracao::getAnoLectivoActual())
            ?? Configuracao::getAnoLectivoActualId();

        return PautaAprovacao::para((int) $turmaId, (int) $anoLectivoId, (int) $trimestre);
    }

    public function aprovar(User $user, int $turmaId, int $trimestre, ?string $anoLectivoNome = null, ?string $observacao = null): PautaAprovacao
    {
        abort_unless($this->podeAprovar($user), 403);

        if ($trimestre < 1 || $trimestre > 3) {
            throw new InvalidArgumentException('Trimestre inválido.');
        }

        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivoNome ?: Configuracao::getAnoLectivoActual())
            ?? Configuracao::getAnoLectivoActualId();

        return PautaAprovacao::updateOrCreate(
            [
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'trimestre' => $trimestre,
            ],
            [
                'status' => PautaAprovacao::STATUS_APROVADA,
                'aprovado_por' => $user->id,
                'aprovado_em' => now(),
                'observacao' => $observacao,
            ]
        );
    }

    public function reabrir(User $user, int $turmaId, int $trimestre, ?string $anoLectivoNome = null): PautaAprovacao
    {
        abort_unless($this->podeAprovar($user), 403);

        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivoNome ?: Configuracao::getAnoLectivoActual())
            ?? Configuracao::getAnoLectivoActualId();

        return PautaAprovacao::updateOrCreate(
            [
                'turma_id' => $turmaId,
                'ano_lectivo_id' => $anoLectivoId,
                'trimestre' => $trimestre,
            ],
            [
                'status' => PautaAprovacao::STATUS_REABERTA,
                'aprovado_por' => $user->id,
                'aprovado_em' => now(),
            ]
        );
    }
}
