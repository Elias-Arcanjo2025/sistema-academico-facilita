<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\BoletimAssinatura;
use App\Models\Encarregado;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EncarregadoPortalAcessoService
{
    public function encarregadoDoUser(User $user): Encarregado
    {
        $encarregado = $user->encarregado;
        if (! $encarregado || ! $user->hasRole('encarregado')) {
            abort(403, 'Conta de encarregado inválida. Contacte a secretaria se precisar de acesso.');
        }

        return $encarregado;
    }

    public function filhos(Encarregado $encarregado): Collection
    {
        return $encarregado->alunos()
            ->with(['turma.curso', 'turma.classe'])
            ->where('alunos.activo', true)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Garante vínculo familiar activo (independente de permissões de notas).
     */
    public function assertVinculo(Encarregado $encarregado, Aluno $aluno): void
    {
        $pivot = $encarregado->alunos()->where('alunos.id', $aluno->id)->first();
        if (! $pivot) {
            abort(403, 'Este educando não está associado à sua conta de encarregado.');
        }
    }

    /**
     * Pode ver o perfil do educando (assiduidade, ocorrências, materiais, etc.).
     */
    public function assertPodeVer(Encarregado $encarregado, Aluno $aluno): void
    {
        $this->assertVinculo($encarregado, $aluno);
    }

    /**
     * Pode consultar notas / boletim do educando.
     */
    public function assertPodeVerNotas(Encarregado $encarregado, Aluno $aluno): void
    {
        $pivot = $encarregado->alunos()->where('alunos.id', $aluno->id)->first();
        if (! $pivot || ! $pivot->pivot->pode_ver_notas) {
            abort(403, 'Não tem permissão para consultar as notas deste educando. Peça à secretaria para activar o acesso.');
        }
    }

    public function assertPodeAssinar(Encarregado $encarregado, Aluno $aluno): void
    {
        $pivot = $encarregado->alunos()->where('alunos.id', $aluno->id)->first();
        if (! $pivot || ! $pivot->pivot->pode_assinar_boletim) {
            throw ValidationException::withMessages([
                'assinatura' => 'Não tem permissão para assinar o boletim deste educando.',
            ]);
        }
    }

    public function assinarBoletim(
        Encarregado $encarregado,
        Aluno $aluno,
        string $hashDocumento,
        ?string $anoLectivo,
        ?int $trimestre,
        ?string $ip,
        ?string $userAgent,
    ): BoletimAssinatura {
        $this->assertPodeAssinar($encarregado, $aluno);

        return BoletimAssinatura::query()->create([
            'aluno_id' => $aluno->id,
            'encarregado_id' => $encarregado->id,
            'ano_lectivo' => $anoLectivo,
            'trimestre' => $trimestre,
            'hash_documento' => $hashDocumento,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'assinado_em' => now(),
        ]);
    }
}
