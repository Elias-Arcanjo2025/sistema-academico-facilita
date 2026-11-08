<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\OrientacaoMetodologica;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrientacaoMetodologicaService
{
    public function __construct(
        protected DisciplinaCoordEscopoService $escopo,
    ) {}

    public function podeVer(User $user, ?OrientacaoMetodologica $orientacao = null): bool
    {
        if (! $user->can('orientacoes.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($orientacao === null) {
            return true;
        }

        if ($orientacao->estado === OrientacaoMetodologica::ESTADO_PUBLICADO) {
            if ($this->escopo->coordena($user, (int) $orientacao->disciplina_id, (int) $orientacao->classe_id)) {
                return true;
            }

            return ProfessorDisciplinaTurma::query()
                ->where('user_id', $user->id)
                ->where('disciplina_id', $orientacao->disciplina_id)
                ->where('activo', true)
                ->whereHas('turma', fn ($t) => $t->where('classe_id', $orientacao->classe_id))
                ->exists();
        }

        return $this->podeGerir($user, $orientacao);
    }

    public function podeGerir(User $user, ?OrientacaoMetodologica $orientacao = null): bool
    {
        if (! $user->can('orientacoes.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($orientacao === null) {
            return $this->escopo->ehCoordenadorActivo($user);
        }

        return $this->escopo->coordena(
            $user,
            (int) $orientacao->disciplina_id,
            (int) $orientacao->classe_id,
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<OrientacaoMetodologica>
     */
    public function queryVisivelPara(User $user, array $filtros = []): Builder
    {
        $query = OrientacaoMetodologica::query()
            ->with(['disciplina:id,nome', 'classe:id,nome', 'autor:id,name'])
            ->orderByDesc('updated_at');

        if ($user->temVisaoInstitucional()) {
            // vê tudo
        } elseif ($user->hasRole('coordenador_disciplina') && $this->escopo->ehCoordenadorActivo($user)) {
            $pares = $this->escopo->paresDisciplinaClasse($user);
            $query->where(function (Builder $q) use ($pares) {
                foreach ($pares as $par) {
                    $q->orWhere(function (Builder $q2) use ($par) {
                        $q2->where('disciplina_id', $par['disciplina_id'])
                            ->where('classe_id', $par['classe_id']);
                    });
                }
            });
        } elseif ($user->hasRole('professor')) {
            $anoId = Configuracao::getAnoLectivoActualId();
            $pares = ProfessorDisciplinaTurma::query()
                ->where('user_id', $user->id)
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->with('turma:id,classe_id')
                ->get()
                ->map(fn ($a) => [
                    'disciplina_id' => (int) $a->disciplina_id,
                    'classe_id' => (int) ($a->turma?->classe_id ?? 0),
                ])
                ->filter(fn ($p) => $p['classe_id'] > 0)
                ->unique(fn ($p) => $p['disciplina_id'].':'.$p['classe_id'])
                ->values();

            $query->where('estado', OrientacaoMetodologica::ESTADO_PUBLICADO)
                ->where(function (Builder $q) use ($pares) {
                    if ($pares->isEmpty()) {
                        $q->whereRaw('0 = 1');

                        return;
                    }
                    foreach ($pares as $par) {
                        $q->orWhere(function (Builder $q2) use ($par) {
                            $q2->where('disciplina_id', $par['disciplina_id'])
                                ->where('classe_id', $par['classe_id']);
                        });
                    }
                });
        } else {
            $query->whereRaw('0 = 1');
        }

        if (! empty($filtros['disciplina_id'])) {
            $query->where('disciplina_id', $filtros['disciplina_id']);
        }
        if (! empty($filtros['classe_id'])) {
            $query->where('classe_id', $filtros['classe_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function criar(User $user, array $data): OrientacaoMetodologica
    {
        abort_unless($this->podeGerir($user), 403);

        if (! $user->temVisaoInstitucional()
            && ! $this->escopo->coordena($user, (int) $data['disciplina_id'], (int) $data['classe_id'])) {
            abort(403, 'Só pode criar orientações nas disciplinas e classes que coordena.');
        }

        return OrientacaoMetodologica::create([
            ...$data,
            'estado' => OrientacaoMetodologica::ESTADO_RASCUNHO,
            'criado_por' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizar(User $user, OrientacaoMetodologica $orientacao, array $data): OrientacaoMetodologica
    {
        abort_unless($this->podeGerir($user, $orientacao), 403);

        if ($orientacao->estado === OrientacaoMetodologica::ESTADO_PUBLICADO) {
            throw new InvalidArgumentException('Orientações publicadas não podem ser editadas. Arquive e crie uma nova.');
        }

        $orientacao->update(collect($data)->only(['titulo', 'conteudo', 'trimestre'])->all());

        return $orientacao->fresh();
    }

    public function publicar(User $user, OrientacaoMetodologica $orientacao): OrientacaoMetodologica
    {
        abort_unless($this->podeGerir($user, $orientacao), 403);

        if ($orientacao->estado !== OrientacaoMetodologica::ESTADO_RASCUNHO) {
            throw new InvalidArgumentException('Só rascunhos podem ser publicados.');
        }

        DB::transaction(function () use ($user, $orientacao) {
            $orientacao->update([
                'estado' => OrientacaoMetodologica::ESTADO_PUBLICADO,
                'publicado_por' => $user->id,
                'publicado_em' => now(),
            ]);
        });

        $this->notificarProfessores($orientacao->fresh());

        return $orientacao->fresh();
    }

    public function arquivar(User $user, OrientacaoMetodologica $orientacao): OrientacaoMetodologica
    {
        abort_unless($this->podeGerir($user, $orientacao), 403);

        $orientacao->update(['estado' => OrientacaoMetodologica::ESTADO_ARQUIVADO]);

        return $orientacao->fresh();
    }

    private function notificarProfessores(OrientacaoMetodologica $orientacao): void
    {
        $orientacao->loadMissing('disciplina');
        $anoId = Configuracao::getAnoLectivoActualId();

        $userIds = ProfessorDisciplinaTurma::query()
            ->where('disciplina_id', $orientacao->disciplina_id)
            ->where('activo', true)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->whereHas('turma', fn ($t) => $t->where('classe_id', $orientacao->classe_id))
            ->pluck('user_id')
            ->unique()
            ->filter(fn ($id) => (int) $id !== (int) $orientacao->criado_por);

        $professores = User::query()->whereIn('id', $userIds)->get();

        foreach ($professores as $professor) {
            try {
                $professor->notify(new StaffAlertaAcademicoNotification('orientacao_metodologica', [
                    'titulo' => 'Nova orientação metodológica',
                    'mensagem' => ($orientacao->disciplina?->nome ?? 'Disciplina').': '.$orientacao->titulo,
                    'url' => route('pedagogico.orientacoes.show', $orientacao),
                ]));
            } catch (\Throwable $e) {
                Log::warning('Falha a notificar professor sobre orientação', [
                    'user_id' => $professor->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
    }
}
