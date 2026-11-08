<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Services\WhatsApp\EncarregadoWhatsAppNotifier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PresencaService
{
    public function __construct(
        private TurmaEscopoService $escopo,
        private EncarregadoWhatsAppNotifier $whatsappNotifier,
    ) {}

    public function podeVerTurma(User $user, int $turmaId, ?int $disciplinaId = null): bool
    {
        if ($user->temVisaoAssiduidade()) {
            return true;
        }

        return $this->escopo->podeAcederTurma($user, $turmaId, $disciplinaId);
    }

    public function podeLancarAula(User $user, TurmaHorario $slot): bool
    {
        if (! $user->can('presencas.lancar')) {
            return false;
        }

        if ($user->temVisaoAssiduidade()) {
            return true;
        }

        if ($user->hasRole('director_turma') && $this->escopo->podeAcederTurma($user, (int) $slot->turma_id)) {
            return true;
        }

        // Professor: só as suas aulas no horário
        if ((int) $slot->professor_id === (int) $user->id) {
            return true;
        }

        return $this->escopo->podeAcederTurma($user, (int) $slot->turma_id, (int) $slot->disciplina_id)
            && $user->hasRole('professor');
    }

    public function assertPodeLancarAula(User $user, TurmaHorario $slot): void
    {
        if (! $this->podeLancarAula($user, $slot)) {
            abort(403, 'Não tem permissão para lançar presença nesta aula.');
        }
    }

    /**
     * Slots do horário para uma data (dia da semana ISO 1–6).
     *
     * @return Collection<int, TurmaHorario>
     */
    public function aulasDoDia(User $user, Carbon|string $data, ?int $anoLectivoId = null): Collection
    {
        $data = Carbon::parse($data)->startOfDay();
        $diaSemana = (int) $data->dayOfWeekIso; // 1=Seg … 7=Dom
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if (! $anoLectivoId || $diaSemana > 6) {
            return collect();
        }

        $query = TurmaHorario::query()
            ->with(['turma.curso', 'disciplina', 'professor', 'sala'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('dia_semana', $diaSemana)
            ->orderBy('hora_inicio');

        if ($user->temVisaoAssiduidade()) {
            // todas
        } elseif ($user->hasRole('director_turma') && ! $user->hasRole('professor')) {
            $turmaIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');
            $query->whereIn('turma_id', $turmaIds);
        } elseif ($user->hasRole('professor') && ! $user->hasRole('director_turma')) {
            $query->where('professor_id', $user->id);
        } elseif ($user->hasRole('director_turma') && $user->hasRole('professor')) {
            $turmaIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');
            $query->where(function ($q) use ($user, $turmaIds) {
                $q->where('professor_id', $user->id)
                    ->orWhereIn('turma_id', $turmaIds);
            });
        } else {
            // coordenador / outros com escopo por acesso
            $query->where(function ($q) use ($user) {
                $q->where('professor_id', $user->id);
            });
        }

        return $query->get()->filter(fn (TurmaHorario $slot) => $this->podeLancarAula($user, $slot) || $this->podeVerTurma($user, (int) $slot->turma_id, (int) $slot->disciplina_id));
    }

    public function aulaExistente(TurmaHorario $slot, Carbon|string $data): ?PresencaAula
    {
        $data = Carbon::parse($data)->toDateString();

        return PresencaAula::query()
            ->where('turma_id', $slot->turma_id)
            ->where('disciplina_id', $slot->disciplina_id)
            ->whereDate('data', $data)
            ->where('hora_inicio', $slot->hora_inicio)
            ->first();
    }

    /**
     * @param  array<int, string>  $estados  aluno_id => estado
     */
    public function gravarChamada(
        User $user,
        TurmaHorario $slot,
        Carbon|string $data,
        array $estados,
        ?string $observacao = null,
    ): PresencaAula {
        $this->assertPodeLancarAula($user, $slot);

        $data = Carbon::parse($data)->startOfDay();
        $anoLectivoId = (int) ($slot->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());
        $trimestre = (int) (Configuracao::getTrimestreActual() ?? 1);
        $estadosValidos = array_keys(PresencaRegisto::estados());

        $alunosIds = Aluno::query()
            ->where('turma_id', $slot->turma_id)
            ->where('activo', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $novasFaltas = [];

        $aula = DB::transaction(function () use ($user, $slot, $data, $estados, $observacao, $anoLectivoId, $trimestre, $estadosValidos, $alunosIds, &$novasFaltas) {
            $aula = PresencaAula::query()->updateOrCreate(
                [
                    'turma_id' => $slot->turma_id,
                    'disciplina_id' => $slot->disciplina_id,
                    'data' => $data->toDateString(),
                    'hora_inicio' => $slot->hora_inicio,
                ],
                [
                    'professor_id' => $slot->professor_id,
                    'turma_horario_id' => $slot->id,
                    'ano_lectivo_id' => $anoLectivoId,
                    'hora_fim' => $slot->hora_fim,
                    'trimestre' => $trimestre,
                    'registado_por' => $user->id,
                    'observacao' => $observacao,
                ]
            );

            foreach ($alunosIds as $alunoId) {
                $estado = $estados[$alunoId] ?? PresencaRegisto::PRESENTE;
                if (! in_array($estado, $estadosValidos, true)) {
                    throw new InvalidArgumentException("Estado de presença inválido para aluno {$alunoId}.");
                }

                $registo = PresencaRegisto::query()->firstOrNew([
                    'presenca_aula_id' => $aula->id,
                    'aluno_id' => $alunoId,
                ]);

                // Não sobrescrever justificação ao regravar se já estava justificada
                // e o utilizador não pediu outro estado.
                if (
                    $registo->exists
                    && $registo->estado === PresencaRegisto::FALTA_JUSTIFICADA
                    && ($estado === PresencaRegisto::FALTA_INJUSTIFICADA || $estado === PresencaRegisto::FALTA_JUSTIFICADA)
                    && ! $user->can('presencas.justificar')
                ) {
                    continue;
                }

                $estadoAnterior = $registo->exists ? $registo->estado : null;

                $registo->estado = $estado;
                if ($estado !== PresencaRegisto::FALTA_JUSTIFICADA) {
                    $registo->justificacao = null;
                    $registo->justificado_por = null;
                    $registo->justificado_em = null;
                }
                $registo->save();

                if (
                    $estado === PresencaRegisto::FALTA_INJUSTIFICADA
                    && $estadoAnterior !== PresencaRegisto::FALTA_INJUSTIFICADA
                ) {
                    $novasFaltas[] = $alunoId;
                }
            }

            return $aula->load(['registos.aluno', 'disciplina', 'turma']);
        });

        foreach (array_unique($novasFaltas) as $alunoId) {
            $aluno = Aluno::query()->find($alunoId);
            if ($aluno) {
                $this->whatsappNotifier->faltaRegistada($aluno, $aula);
            }
        }

        return $aula;
    }

    public function justificar(User $user, PresencaRegisto $registo, string $motivo): PresencaRegisto
    {
        if (! $user->can('presencas.justificar')) {
            abort(403, 'Não tem permissão para justificar faltas.');
        }

        $aula = $registo->aula;
        if (! $aula || ! $this->podeVerTurma($user, (int) $aula->turma_id, (int) $aula->disciplina_id)) {
            abort(403);
        }

        if (! $registo->isFalta() && $registo->estado !== PresencaRegisto::ATRASO) {
            throw new InvalidArgumentException('Só é possível justificar faltas ou atrasos.');
        }

        $registo->estado = PresencaRegisto::FALTA_JUSTIFICADA;
        $registo->justificacao = $motivo;
        $registo->justificado_por = $user->id;
        $registo->justificado_em = now();
        $registo->save();

        return $registo;
    }

    /**
     * Contagens J/I por aluno e disciplina num trimestre.
     *
     * @return array{j: int, i: int}
     */
    public function contarFaltasAlunoDisciplina(
        int $alunoId,
        int $disciplinaId,
        int $trimestre,
        ?int $anoLectivoId = null,
    ): array {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $base = PresencaRegisto::query()
            ->where('aluno_id', $alunoId)
            ->whereHas('aula', function ($q) use ($disciplinaId, $trimestre, $anoLectivoId) {
                $q->where('disciplina_id', $disciplinaId)
                    ->where('trimestre', $trimestre)
                    ->when($anoLectivoId, fn ($qq) => $qq->where('ano_lectivo_id', $anoLectivoId));
            });

        $j = (clone $base)->where('estado', PresencaRegisto::FALTA_JUSTIFICADA)->count();
        $i = (clone $base)->where('estado', PresencaRegisto::FALTA_INJUSTIFICADA)->count();

        return ['j' => $j, 'i' => $i];
    }

    /**
     * Mapa aluno_id => disciplina_id => ['j'=>n,'i'=>n] para uma turma/trimestre.
     *
     * @return array<int, array<int, array{j: int, i: int}>>
     */
    public function mapaFaltasTurma(int $turmaId, int $trimestre, ?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $rows = PresencaRegisto::query()
            ->selectRaw('presenca_registos.aluno_id, presenca_aulas.disciplina_id, presenca_registos.estado, COUNT(*) as total')
            ->join('presenca_aulas', 'presenca_aulas.id', '=', 'presenca_registos.presenca_aula_id')
            ->where('presenca_aulas.turma_id', $turmaId)
            ->where('presenca_aulas.trimestre', $trimestre)
            ->when($anoLectivoId, fn ($q) => $q->where('presenca_aulas.ano_lectivo_id', $anoLectivoId))
            ->whereIn('presenca_registos.estado', [
                PresencaRegisto::FALTA_JUSTIFICADA,
                PresencaRegisto::FALTA_INJUSTIFICADA,
            ])
            ->groupBy('presenca_registos.aluno_id', 'presenca_aulas.disciplina_id', 'presenca_registos.estado')
            ->get();

        $mapa = [];
        foreach ($rows as $row) {
            $aid = (int) $row->aluno_id;
            $did = (int) $row->disciplina_id;
            $mapa[$aid][$did] ??= ['j' => 0, 'i' => 0];
            if ($row->estado === PresencaRegisto::FALTA_JUSTIFICADA) {
                $mapa[$aid][$did]['j'] = (int) $row->total;
            } else {
                $mapa[$aid][$did]['i'] = (int) $row->total;
            }
        }

        return $mapa;
    }

    /**
     * Resumo para portal do aluno.
     *
     * @return array{totais: array{presente:int,falta_injustificada:int,falta_justificada:int,atraso:int}, recentes: Collection}
     */
    public function resumoAluno(int $alunoId, ?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $totais = [
            PresencaRegisto::PRESENTE => 0,
            PresencaRegisto::FALTA_INJUSTIFICADA => 0,
            PresencaRegisto::FALTA_JUSTIFICADA => 0,
            PresencaRegisto::ATRASO => 0,
        ];

        $counts = PresencaRegisto::query()
            ->where('aluno_id', $alunoId)
            ->whereHas('aula', fn ($q) => $q->when($anoLectivoId, fn ($qq) => $qq->where('ano_lectivo_id', $anoLectivoId)))
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        foreach ($counts as $estado => $total) {
            if (array_key_exists($estado, $totais)) {
                $totais[$estado] = (int) $total;
            }
        }

        $recentes = PresencaRegisto::query()
            ->with(['aula.disciplina', 'aula.turma'])
            ->where('aluno_id', $alunoId)
            ->whereHas('aula', fn ($q) => $q->when($anoLectivoId, fn ($qq) => $qq->where('ano_lectivo_id', $anoLectivoId)))
            ->whereIn('estado', [
                PresencaRegisto::FALTA_INJUSTIFICADA,
                PresencaRegisto::FALTA_JUSTIFICADA,
                PresencaRegisto::ATRASO,
            ])
            ->latest('id')
            ->limit(30)
            ->get();

        return compact('totais', 'recentes');
    }
}
