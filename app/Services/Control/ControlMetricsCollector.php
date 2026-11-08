<?php

namespace App\Services\Control;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConfiguracaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ControlMetricsCollector
{
    public function __construct(
        private readonly ConfiguracaoService $configuracaoService,
    ) {}

    public function collect(): array
    {
        $instituicao = $this->configuracaoService->getInstituicao();
        $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();

        $professores = $this->countProfessores();

        return [
            'installation_id' => (string) config('control.installation_id'),
            'instituicao' => [
                'nome' => $instituicao['nome'] ?? 'Sistema Facilita',
                'email' => $instituicao['email'] ?? null,
                'telefone' => $instituicao['telefone'] ?? null,
                'website' => $instituicao['website'] ?? null,
            ],
            'app_url' => config('app.url'),
            'app_version' => $this->appVersion(),
            'ano_lectivo' => $anoLectivo,
            'metricas' => [
                'alunos' => Aluno::query()->where('activo', true)->count(),
                'alunos_total' => Aluno::query()->count(),
                'professores' => $professores,
                'cursos' => Curso::query()->count(),
                'turmas' => Turma::query()->when(
                    $anoLectivo,
                    fn ($q) => $q->where('ano_lectivo', $anoLectivo)
                )->count(),
                'salas' => Sala::query()->count(),
                'disciplinas' => Disciplina::query()->count(),
                'utilizadores' => User::query()->where('estado', 'activo')->count(),
            ],
            'saude' => $this->health(),
            'reported_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Payload de alunos para faturação (sem PII excessiva).
     *
     * @return list<array<string, mixed>>
     */
    public function collectAlunos(?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?: \App\Models\Configuracao::getAnoLectivoActual();

        $query = Aluno::query()
            ->with(['turma.curso', 'turma.classe', 'turma.anoLectivo'])
            ->orderBy('id');

        if ($anoLectivo) {
            $query->whereHas('turma', fn ($q) => $q->where('ano_lectivo', $anoLectivo));
        }

        return $query->get()->map(function (Aluno $aluno) use ($anoLectivo) {
            $turma = $aluno->turma;

            return [
                'aluno_uuid' => $aluno->public_uuid,
                'aluno_local_id' => $aluno->id,
                'numero_processo' => $aluno->numero_processo,
                'nome' => $aluno->nome,
                'genero' => $aluno->genero,
                'ano_lectivo' => $turma?->ano_lectivo ?? $anoLectivo,
                'turma_codigo' => $turma?->codigo,
                'turma_nome' => $turma?->nome,
                'curso_nome' => $turma?->curso?->nome,
                'classe_nome' => $turma?->classe?->nome,
                'situacao_academica' => $aluno->situacao_academica,
                'activo' => (bool) $aluno->activo,
            ];
        })->all();
    }

    private function countProfessores(): int
    {
        if (! Schema::hasTable('professor_disciplina_turma')) {
            return User::role('professor')->count();
        }

        return (int) ProfessorDisciplinaTurma::query()
            ->where('activo', true)
            ->selectRaw('count(distinct user_id) as aggregate')
            ->value('aggregate');
    }

    private function health(): array
    {
        $failedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $diskFree = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());

        return [
            'queue_failed' => $failedJobs,
            'disk_free_mb' => $diskFree !== false ? (int) round($diskFree / 1024 / 1024) : null,
            'disk_total_mb' => $diskTotal !== false ? (int) round($diskTotal / 1024 / 1024) : null,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    private function appVersion(): string
    {
        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            return trim((string) file_get_contents($versionFile)) ?: 'dev';
        }

        return 'dev';
    }
}
