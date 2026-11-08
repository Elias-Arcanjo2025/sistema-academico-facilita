<?php

namespace App\Services\Radar;

use App\Models\Aluno;
use App\Models\Control\ControlEscola;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConfiguracaoService;
use App\Services\PautaService;
use App\Services\SaudeEscolaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Radar pedagógico — visão da directora + benchmark anónimo do hub.
 */
class RadarPedagogicoService
{
    public function __construct(
        private readonly SaudeEscolaService $saudeEscola,
        private readonly ConfiguracaoService $configuracao,
    ) {}

    public function gerar(?User $user = null): array
    {
        $anoId = \App\Models\Configuracao::getAnoLectivoActualId();
        $ano = $anoId ? \App\Models\Configuracao::getAnoLectivoActual() : null;
        $trimestre = (int) ($this->configuracao->getTrimestreActual() ?: 1);

        try {
            $saude = $anoId ? $this->saudeEscola->indicadores($ano) : [
                'score' => null,
                'score_label' => 'Sem ano lectivo',
            ];
        } catch (\Throwable $e) {
            $saude = ['score' => null, 'score_label' => 'Indisponível', 'erro' => $e->getMessage()];
        }

        $turmasRisco = $anoId ? $this->turmasEmRisco($anoId, $trimestre) : [];
        $macPpAtraso = $anoId ? $this->professoresMacPpEmAtraso($anoId, $trimestre) : [];
        $alunosRisco = $anoId ? $this->alunosEmRisco($anoId, $trimestre) : [];
        $abandono = $anoId ? $this->indicadoresAbandono($anoId) : ['total' => 0, 'desistentes' => 0, 'auto_desistentes' => 0, 'alunos' => []];
        $benchmark = $this->benchmarkHubAnonimo($saude);

        return [
            'ano_lectivo' => $ano,
            'trimestre' => $trimestre,
            'sem_ano_lectivo' => ! $anoId,
            'saude_escola' => $saude,
            'turmas_risco' => $turmasRisco,
            'professores_atraso' => $macPpAtraso,
            'alunos_risco' => $alunosRisco,
            'abandono' => $abandono,
            'benchmark' => $benchmark,
            'gerado_em' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function turmasEmRisco(int $anoLectivoId, int $trimestre): array
    {
        $turmas = Turma::query()
            ->with(['curso', 'classe'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->withCount(['alunos as alunos_activos' => fn ($q) => $q->where('activo', true)])
            ->get();

        $risco = [];
        foreach ($turmas as $turma) {
            if ($turma->alunos_activos < 1) {
                continue;
            }

            $negativas = Nota::query()
                ->where('turma_id', $turma->id)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', ['MT', 'mt', 'CF', 'cf', 'CA', 'ca'])
                ->where('valor', '<', 10)
                ->whereNull('archived_at')
                ->count();

            $totalNotas = Nota::query()
                ->where('turma_id', $turma->id)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', ['MT', 'mt', 'CF', 'cf', 'CA', 'ca'])
                ->whereNull('archived_at')
                ->count();

            $taxa = $totalNotas > 0 ? round(($negativas / $totalNotas) * 100, 1) : 0;
            $nivel = match (true) {
                $taxa >= 40 => 'critico',
                $taxa >= 25 => 'alto',
                $taxa >= 15 => 'moderado',
                default => null,
            };

            if (! $nivel) {
                continue;
            }

            $risco[] = [
                'turma_id' => $turma->id,
                'turma' => $turma->nome ?? $turma->codigo,
                'curso' => $turma->curso?->nome,
                'alunos' => $turma->alunos_activos,
                'taxa_negativas' => $taxa,
                'nivel' => $nivel,
            ];
        }

        usort($risco, fn ($a, $b) => $b['taxa_negativas'] <=> $a['taxa_negativas']);

        return array_slice($risco, 0, 15);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function professoresMacPpEmAtraso(int $anoLectivoId, int $trimestre): array
    {
        if (! Schema::hasTable('professor_disciplina_turma')) {
            return [];
        }

        $assocs = ProfessorDisciplinaTurma::query()
            ->with(['professor', 'turma', 'disciplina'])
            ->where('activo', true)
            ->where(function ($q) use ($anoLectivoId) {
                $q->where('ano_lectivo_id', $anoLectivoId)
                    ->orWhereHas('turma', fn ($t) => $t->where('ano_lectivo_id', $anoLectivoId));
            })
            ->get();

        $atrasos = [];
        foreach ($assocs as $assoc) {
            $turma = $assoc->turma;
            if (! $turma) {
                continue;
            }

            $alunos = $turma->alunos()->where('activo', true)->count();
            if ($alunos < 1) {
                continue;
            }

            $mac = Nota::query()
                ->where('turma_id', $turma->id)
                ->where('disciplina_id', $assoc->disciplina_id)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', ['MAC', 'mac'])
                ->whereNull('archived_at')
                ->distinct()
                ->count('aluno_id');

            $pp = Nota::query()
                ->where('turma_id', $turma->id)
                ->where('disciplina_id', $assoc->disciplina_id)
                ->where('trimestre', $trimestre)
                ->whereIn('tipo', ['PP', 'pp'])
                ->whereNull('archived_at')
                ->distinct()
                ->count('aluno_id');

            $faltaMac = $mac < (int) ceil($alunos * 0.8);
            $faltaPp = $pp < (int) ceil($alunos * 0.8);

            if (! $faltaMac && ! $faltaPp) {
                continue;
            }

            $atrasos[] = [
                'professor' => $assoc->professor?->name ?? '—',
                'user_id' => $assoc->user_id,
                'turma_id' => $turma->id,
                'disciplina_id' => $assoc->disciplina_id,
                'turma' => $turma->nome ?? $turma->codigo,
                'disciplina' => $assoc->disciplina?->nome ?? '—',
                'alunos' => $alunos,
                'mac_lancados' => $mac,
                'pp_lancados' => $pp,
                'falta_mac' => $faltaMac,
                'falta_pp' => $faltaPp,
            ];
        }

        return array_slice($atrasos, 0, 20);
    }

    /**
     * Alunos com ≥3 MTs negativas no trimestre actual.
     *
     * @return list<array<string, mixed>>
     */
    private function alunosEmRisco(int $anoLectivoId, int $trimestre): array
    {
        $rows = Nota::query()
            ->select('aluno_id', DB::raw('COUNT(*) as negativas'))
            ->where('trimestre', $trimestre)
            ->where('tipo', Nota::TIPO_MT)
            ->where('valor', '<', 10)
            ->whereNull('archived_at')
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->groupBy('aluno_id')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('negativas')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $alunos = Aluno::query()
            ->with(['turma.curso'])
            ->whereIn('id', $rows->pluck('aluno_id'))
            ->get()
            ->keyBy('id');

        $lista = [];
        foreach ($rows as $row) {
            $aluno = $alunos->get($row->aluno_id);
            if (! $aluno || ! $aluno->activo) {
                continue;
            }
            $lista[] = [
                'aluno_id' => $aluno->id,
                'nome' => $aluno->nome,
                'turma' => $aluno->turma?->nome,
                'curso' => $aluno->turma?->curso?->nome,
                'mts_negativas' => (int) $row->negativas,
                'nivel' => (int) $row->negativas >= 5 ? 'critico' : 'alto',
            ];
        }

        return $lista;
    }

    /**
     * @return array{total:int,desistentes:int,auto_desistentes:int,alunos:list<array<string,mixed>>}
     */
    private function indicadoresAbandono(int $anoLectivoId): array
    {
        $desistentes = Aluno::query()
            ->with(['turma'])
            ->where(function ($q) {
                $q->where('situacao_academica', Aluno::SITUACAO_DESISTENTE)
                    ->orWhere('activo', false);
            })
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->orderBy('nome')
            ->limit(30)
            ->get();

        $alunoIdsActivos = Aluno::query()
            ->where('activo', true)
            ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->pluck('id')
            ->all();

        $autoIds = [];
        try {
            $autoIds = array_keys(app(PautaService::class)->getAutoDesistenteIds($alunoIdsActivos, $anoLectivoId));
        } catch (\Throwable) {
            $autoIds = [];
        }

        $lista = $desistentes->map(fn (Aluno $a) => [
            'aluno_id' => $a->id,
            'nome' => $a->nome,
            'turma' => $a->turma?->nome,
            'motivo' => $a->situacao_academica === Aluno::SITUACAO_DESISTENTE
                ? 'Situação: desistente'
                : 'Inactivo',
        ])->all();

        return [
            'total' => $desistentes->count() + count($autoIds),
            'desistentes' => $desistentes->where('situacao_academica', Aluno::SITUACAO_DESISTENTE)->count(),
            'auto_desistentes' => count($autoIds),
            'alunos' => $lista,
        ];
    }

    /**
     * Comparativo anónimo: esta escola vs percentis das escolas do hub (mesma BD hub ou métricas locais).
     *
     * @param  array<string, mixed>  $saudeLocal
     * @return array<string, mixed>
     */
    private function benchmarkHubAnonimo(array $saudeLocal): array
    {
        if (config('control.mode') !== 'hub' || ! Schema::hasTable('control_escolas')) {
            return [
                'disponivel' => false,
                'motivo' => 'Benchmark de rede disponível no Hub Facilita (várias escolas sincronizadas).',
            ];
        }

        $escolas = ControlEscola::query()->where('activo', true)->get();
        if ($escolas->count() < 2) {
            return [
                'disponivel' => false,
                'motivo' => 'São necessárias pelo menos 2 escolas no hub para o comparativo anónimo.',
                'escolas_hub' => $escolas->count(),
            ];
        }

        $alunos = $escolas->pluck('total_alunos')->filter()->sort()->values();
        $mediaAlunos = round($alunos->avg() ?? 0, 1);
        $p50 = $alunos->get((int) floor(($alunos->count() - 1) * 0.5)) ?? 0;

        return [
            'disponivel' => true,
            'escolas_hub' => $escolas->count(),
            'alunos_media_rede' => $mediaAlunos,
            'alunos_mediana_rede' => $p50,
            'nota' => 'Comparativo anónimo — nenhuma escola é identificada.',
            'score_local' => $saudeLocal['score'] ?? $saudeLocal['pontuacao'] ?? null,
        ];
    }
}
