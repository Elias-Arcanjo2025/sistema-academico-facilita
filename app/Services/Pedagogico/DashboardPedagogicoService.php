<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedCorrecao;
use App\Models\Pedagogico\PedExecucaoAnalise;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Models\User;
use App\Services\ConfiguracaoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardPedagogicoService
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService,
        protected AulaMinistradaService $aulaMinistradaService,
        protected DisciplinaCoordEscopoService $disciplinaCoordEscopo,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function obterKpis(User $user, array $filtros = []): array
    {
        $anoLectivoId = $filtros['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId();
        $trimestre = (int) ($filtros['trimestre'] ?? $this->configuracaoService->getTrimestreActual());
        $cacheKey = sprintf(
            'ped_dashboard_%d_%d_%d_%s',
            $user->id,
            $anoLectivoId,
            $trimestre,
            md5(json_encode($filtros))
        );
        $ttl = config('pedagogico.dashboard_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($user, $filtros, $anoLectivoId, $trimestre) {
            $planificacoesQuery = PedPlanificacao::query()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('trimestre', $trimestre);

            $planificacoesQuery = $this->aplicarEscopoUtilizador($planificacoesQuery, $user);

            if (! empty($filtros['turma_id'])) {
                $planificacoesQuery->where('turma_id', $filtros['turma_id']);
            }
            if (! empty($filtros['disciplina_id'])) {
                $planificacoesQuery->where('disciplina_id', $filtros['disciplina_id']);
            }

            $planificacoesIds = (clone $planificacoesQuery)->pluck('id');

            $totalPlanificacoes = $planificacoesIds->count();
            $activas = (clone $planificacoesQuery)->where('estado', PedPlanificacao::ESTADO_ACTIVO)->count();

            $aulasStats = PedAula::whereIn('planificacao_id', $planificacoesIds)
                ->select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $execucaoMedia = 0.0;
            if ($planificacoesIds->isNotEmpty()) {
                $percentagens = PedPlanificacao::whereIn('id', $planificacoesIds)
                    ->get()
                    ->map(fn (PedPlanificacao $p) => $this->aulaMinistradaService->percentagemExecucao($p));
                $execucaoMedia = round($percentagens->avg() ?? 0, 2);
            }

            $ultimaAnalise = PedExecucaoAnalise::whereIn('planificacao_id', $planificacoesIds)
                ->latest('calculado_em')
                ->first();

            $provasGeradas = PedProva::whereIn('planificacao_id', $planificacoesIds)->count();
            $correcoesPendentes = PedCorrecao::whereHas('prova', fn ($q) => $q->whereIn('planificacao_id', $planificacoesIds))
                ->where('estado', 'pendente')
                ->count();

            $mediasNotas = $this->obterMediasNotas($planificacoesIds, $anoLectivoId, $trimestre, $filtros);

            return [
                'ano_lectivo_id' => $anoLectivoId,
                'trimestre' => $trimestre,
                'total_planificacoes' => $totalPlanificacoes,
                'planificacoes_activas' => $activas,
                'aulas_por_estado' => $aulasStats->all(),
                'execucao_media_percentagem' => $execucaoMedia,
                'ultima_analise_execucao' => $ultimaAnalise?->only([
                    'percentagem_execucao', 'previsao_conclusao', 'calculado_em',
                ]),
                'provas_geradas' => $provasGeradas,
                'correcoes_pendentes' => $correcoesPendentes,
                'medias_notas' => $mediasNotas,
                'pdf_config' => $this->configuracaoService->getPdfConfig(),
            ];
        });
    }

    /**
     * Leitura apenas — nunca escreve em notas.
     *
     * @return array<string, float|null>
     */
    protected function obterMediasNotas($planificacoesIds, int $anoLectivoId, int $trimestre, array $filtros): array
    {
        if ($planificacoesIds->isEmpty()) {
            return ['MAC' => null, 'PP' => null, 'PT' => null, 'MT' => null];
        }

        $turmasIds = PedPlanificacao::whereIn('id', $planificacoesIds)->pluck('turma_id')->unique();
        $disciplinasIds = PedPlanificacao::whereIn('id', $planificacoesIds)->pluck('disciplina_id')->unique();

        $query = Nota::query()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', $trimestre)
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('disciplina_id', $disciplinasIds)
            ->whereIn('tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_MT]);

        if (! empty($filtros['turma_id'])) {
            $query->where('turma_id', $filtros['turma_id']);
        }
        if (! empty($filtros['disciplina_id'])) {
            $query->where('disciplina_id', $filtros['disciplina_id']);
        }

        $medias = $query->select('tipo', DB::raw('AVG(valor) as media'))
            ->groupBy('tipo')
            ->pluck('media', 'tipo');

        return [
            'MAC' => isset($medias[Nota::TIPO_MAC]) ? round((float) $medias[Nota::TIPO_MAC], 2) : null,
            'PP' => isset($medias[Nota::TIPO_PP]) ? round((float) $medias[Nota::TIPO_PP], 2) : null,
            'PT' => isset($medias[Nota::TIPO_PT]) ? round((float) $medias[Nota::TIPO_PT], 2) : null,
            'MT' => isset($medias[Nota::TIPO_MT]) ? round((float) $medias[Nota::TIPO_MT], 2) : null,
        ];
    }

    protected function aplicarEscopoUtilizador($query, User $user)
    {
        if (PedagogicoAccess::podeVerTudo($user)) {
            return $query;
        }

        if ($user->hasRole('coordenador_disciplina') && $this->disciplinaCoordEscopo->ehCoordenadorActivo($user)) {
            return $this->disciplinaCoordEscopo->aplicarEscopoPlanificacao($query, $user);
        }

        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = Configuracao::getAnoLectivoActualId();
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return $query->whereHas('turma', fn ($q) => $q->whereIn('curso_id', $cursosIds));
        }

        if ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');

            return $query->whereIn('turma_id', $turmasIds);
        }

        if ($user->hasRole('professor')) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('0 = 1');
    }
}
