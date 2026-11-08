<?php

namespace App\Services;

use App\Models\AgendaEvento;
use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use App\Services\Portal\PortalNotificacaoService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AgendaService
{
    public function podeGerir(User $user, ?AgendaEvento $evento = null): bool
    {
        if (! $user->can('agenda.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $user->hasRole('secretaria')) {
            return true;
        }

        if ($evento === null) {
            return true;
        }

        if ((int) $evento->criado_por === (int) $user->id) {
            return true;
        }

        $anoId = $evento->ano_lectivo_id;
        $turmaIds = $this->turmaIdsEscopo($user, $anoId);
        $cursoIds = $this->cursoIdsEscopo($user, $anoId);

        if ($evento->visibilidade === AgendaEvento::VIS_TURMA && $evento->turma_id) {
            return $turmaIds->contains((int) $evento->turma_id);
        }

        if ($evento->visibilidade === AgendaEvento::VIS_CURSO && $evento->curso_id) {
            return $cursoIds->contains((int) $evento->curso_id);
        }

        if ($evento->visibilidade === AgendaEvento::VIS_DISCIPLINA && $evento->disciplina_id) {
            return $this->disciplinaIdsEscopo($user, $anoId)->contains((int) $evento->disciplina_id);
        }

        // Eventos de escola: só institucional/secretaria (já retornaram true acima)
        // coords/DT só se forem criadores
        return false;
    }

    public function podeCriarVisibilidade(
        User $user,
        string $visibilidade,
        ?int $turmaId,
        ?int $cursoId,
        ?int $disciplinaId = null,
    ): bool {
        if (! $user->can('agenda.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $user->hasRole('secretaria')) {
            return true;
        }

        $anoId = Configuracao::getAnoLectivoActualId();

        if ($visibilidade === AgendaEvento::VIS_TURMA) {
            return $turmaId && $this->turmaIdsEscopo($user, $anoId)->contains((int) $turmaId);
        }

        if ($visibilidade === AgendaEvento::VIS_CURSO) {
            return $cursoId && $this->cursoIdsEscopo($user, $anoId)->contains((int) $cursoId);
        }

        if ($visibilidade === AgendaEvento::VIS_DISCIPLINA) {
            return $disciplinaId && $this->disciplinaIdsEscopo($user, $anoId)->contains((int) $disciplinaId);
        }

        // escola: só institucional/secretaria
        return false;
    }

    /**
     * @return Builder<AgendaEvento>
     */
    public function queryVisivelPara(User $user, ?int $anoLectivoId = null): Builder
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $query = AgendaEvento::query()
            ->activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with(['turma', 'curso', 'criador']);

        if ($user->temVisaoInstitucional() || $user->hasAnyRole(['secretaria', 'admin_full', 'gestor_academico'])) {
            return $query;
        }

        $turmaIds = $this->turmaIdsEscopo($user, $anoLectivoId);
        $cursoIds = $this->cursoIdsEscopo($user, $anoLectivoId);
        $disciplinaIds = $this->disciplinaIdsEscopo($user, $anoLectivoId);

        return $query->where(function (Builder $q) use ($turmaIds, $cursoIds, $disciplinaIds) {
            $q->where('visibilidade', AgendaEvento::VIS_ESCOLA);

            if ($cursoIds->isNotEmpty()) {
                $q->orWhere(function (Builder $c) use ($cursoIds) {
                    $c->where('visibilidade', AgendaEvento::VIS_CURSO)
                        ->whereIn('curso_id', $cursoIds);
                });
            }

            if ($turmaIds->isNotEmpty()) {
                $q->orWhere(function (Builder $t) use ($turmaIds) {
                    $t->where('visibilidade', AgendaEvento::VIS_TURMA)
                        ->whereIn('turma_id', $turmaIds);
                });
            }

            if ($disciplinaIds->isNotEmpty()) {
                $q->orWhere(function (Builder $d) use ($disciplinaIds) {
                    $d->where('visibilidade', AgendaEvento::VIS_DISCIPLINA)
                        ->whereIn('disciplina_id', $disciplinaIds);
                });
            }
        });
    }

    /**
     * @return Collection<int, Collection<int, AgendaEvento>>
     */
    public function listarPorMes(User $user, int $ano, int $mes, ?int $anoLectivoId = null): Collection
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        return $this->queryVisivelPara($user, $anoLectivoId)
            ->whereBetween('inicia_em', [$inicio, $fim])
            ->orderBy('inicia_em')
            ->get()
            ->groupBy(fn (AgendaEvento $e) => $e->inicia_em->format('Y-m-d'));
    }

    public function criar(User $user, array $dados): AgendaEvento
    {
        $vis = $dados['visibilidade'] ?? AgendaEvento::VIS_ESCOLA;
        $turmaId = isset($dados['turma_id']) ? (int) $dados['turma_id'] : null;
        $cursoId = isset($dados['curso_id']) ? (int) $dados['curso_id'] : null;
        $disciplinaId = isset($dados['disciplina_id']) ? (int) $dados['disciplina_id'] : null;

        if (! $this->podeCriarVisibilidade($user, $vis, $turmaId, $cursoId, $disciplinaId)) {
            abort(403, 'Não pode criar eventos com esta visibilidade/escopo.');
        }

        if ($vis === AgendaEvento::VIS_ESCOLA) {
            $turmaId = null;
            $cursoId = null;
            $disciplinaId = null;
        } elseif ($vis === AgendaEvento::VIS_CURSO) {
            $turmaId = null;
            $disciplinaId = null;
        } elseif ($vis === AgendaEvento::VIS_TURMA) {
            $cursoId = null;
            $disciplinaId = null;
        } elseif ($vis === AgendaEvento::VIS_DISCIPLINA) {
            $turmaId = null;
            $cursoId = null;
        }

        $evento = AgendaEvento::create([
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'] ?? null,
            'tipo' => $dados['tipo'] ?? AgendaEvento::TIPO_OUTRO,
            'inicia_em' => $dados['inicia_em'],
            'termina_em' => $dados['termina_em'] ?? null,
            'ano_lectivo_id' => $dados['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId(),
            'turma_id' => $turmaId,
            'curso_id' => $cursoId,
            'disciplina_id' => $disciplinaId,
            'visibilidade' => $vis,
            'criado_por' => $user->id,
            'activo' => $dados['activo'] ?? true,
        ]);

        $this->notificarPublicacao($evento);

        return $evento;
    }

    public function eliminar(User $user, AgendaEvento $evento): void
    {
        if (! $this->podeGerir($user, $evento)) {
            abort(403);
        }
        $evento->update(['activo' => false]);
    }

    /**
     * Eventos visíveis para um aluno (turma + escola + curso da turma).
     *
     * @return Collection<int, AgendaEvento>
     */
    public function paraAluno(Aluno $aluno, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $aluno->loadMissing('turma');

        return AgendaEvento::query()
            ->activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where(function (Builder $q) use ($aluno) {
                $q->where('visibilidade', AgendaEvento::VIS_ESCOLA);

                if ($aluno->turma?->curso_id) {
                    $q->orWhere(function (Builder $c) use ($aluno) {
                        $c->where('visibilidade', AgendaEvento::VIS_CURSO)
                            ->where('curso_id', $aluno->turma->curso_id);
                    });
                }

                if ($aluno->turma_id) {
                    $q->orWhere(function (Builder $t) use ($aluno) {
                        $t->where('visibilidade', AgendaEvento::VIS_TURMA)
                            ->where('turma_id', $aluno->turma_id);
                    });
                }
            })
            ->orderBy('inicia_em')
            ->with(['turma', 'curso'])
            ->get();
    }

    private function notificarPublicacao(AgendaEvento $evento): void
    {
        try {
            app(PortalNotificacaoService::class)->notificarAgendaEvento($evento);
        } catch (\Throwable $e) {
            Log::warning('Falha notificação agenda: '.$e->getMessage(), ['evento_id' => $evento->id]);
        }

        if ($evento->visibilidade !== AgendaEvento::VIS_DISCIPLINA || ! $evento->disciplina_id) {
            return;
        }

        $anoId = $evento->ano_lectivo_id;
        $userIds = ProfessorDisciplinaTurma::query()
            ->where('disciplina_id', $evento->disciplina_id)
            ->where('activo', true)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->pluck('user_id')
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $evento->criado_por);

        $professores = User::query()->whereIn('id', $userIds)->get();
        $evento->loadMissing('disciplina');

        foreach ($professores as $professor) {
            try {
                $professor->notify(new StaffAlertaAcademicoNotification('agenda_disciplina', [
                    'titulo' => $evento->tipo === AgendaEvento::TIPO_REUNIAO
                        ? 'Reunião da disciplina'
                        : 'Evento da disciplina',
                    'mensagem' => ($evento->disciplina?->nome ? '['.$evento->disciplina->nome.'] ' : '').$evento->titulo,
                    'url' => route('agenda.index'),
                ]));
            } catch (\Throwable $e) {
                Log::warning('Falha notificação agenda disciplina: '.$e->getMessage());
            }
        }
    }

    /**
     * @return Collection<int, int>
     */
    public function disciplinaIdsEscopo(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $ids = collect();

        if ($user->hasRole('coordenador_disciplina')) {
            $ids = $ids->merge(
                app(DisciplinaCoordEscopoService::class)->disciplinaIds($user, $anoLectivoId)
            );
        }

        if ($user->hasRole('professor')) {
            $ids = $ids->merge(
                ProfessorDisciplinaTurma::query()
                    ->where('user_id', $user->id)
                    ->where('activo', true)
                    ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                    ->pluck('disciplina_id')
            );
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function turmaIdsEscopo(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $ids = collect();

        if ($user->hasRole('director_turma')) {
            $ids = $ids->merge(
                $user->turmasComoDirector()
                    ->wherePivot('activo', true)
                    ->when($anoLectivoId, fn ($q) => $q->where('turmas.ano_lectivo_id', $anoLectivoId))
                    ->pluck('turmas.id')
            );
        }

        if ($user->hasRole('professor')) {
            $ids = $ids->merge(
                $user->turmasComoProfessor()
                    ->wherePivot('activo', true)
                    ->when($anoLectivoId, fn ($q) => $q->where('turmas.ano_lectivo_id', $anoLectivoId))
                    ->pluck('turmas.id')
            );
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursoIds = $this->cursoIdsEscopo($user, $anoLectivoId);
            if ($cursoIds->isNotEmpty()) {
                $ids = $ids->merge(
                    \App\Models\Turma::query()
                        ->whereIn('curso_id', $cursoIds)
                        ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                        ->pluck('id')
                );
            }
        }

        if ($user->hasRole('coordenador_disciplina')) {
            $ids = $ids->merge(
                app(\App\Services\Pedagogico\DisciplinaCoordEscopoService::class)
                    ->turmaIds($user, $anoLectivoId)
            );
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function cursoIdsEscopo(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if (! $user->hasRole('coordenador_curso')) {
            return collect();
        }

        return CoordenadorCurso::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->pluck('curso_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
