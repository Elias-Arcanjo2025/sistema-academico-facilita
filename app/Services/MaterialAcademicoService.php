<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\MaterialAcademico;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaterialAcademicoService
{
    public function __construct(
        private AgendaService $agenda,
    ) {}

    public function podeGerir(User $user, ?MaterialAcademico $material = null): bool
    {
        if (! $user->can('materiais.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($material === null) {
            return true;
        }

        if ((int) $material->criado_por === (int) $user->id) {
            return true;
        }

        $anoId = $material->ano_lectivo_id;

        if ($material->turma_id && $this->agenda->turmaIdsEscopo($user, $anoId)->contains((int) $material->turma_id)) {
            return $user->hasAnyRole(['director_turma', 'coordenador_curso']);
        }

        if ($material->disciplina_id && $this->disciplinaIdsCoord($user, $anoId)->contains((int) $material->disciplina_id)) {
            return true;
        }

        return false;
    }

    public function podeDescarregar(User $user, MaterialAcademico $material): bool
    {
        if (! $material->publicado && ! $this->podeGerir($user, $material)) {
            return false;
        }

        if ($user->can('materiais.download') || $user->can('materiais.ver') || $user->temVisaoInstitucional()) {
            return $this->queryVisivelPara($user, $material->ano_lectivo_id)
                ->where('materiais_academicos.id', $material->id)
                ->exists()
                || $this->podeGerir($user, $material);
        }

        return false;
    }

    public function podeDescarregarAluno(Aluno $aluno, MaterialAcademico $material): bool
    {
        if (! $material->publicado) {
            return false;
        }

        return $this->queryParaAluno($aluno, $material->ano_lectivo_id)
            ->where('materiais_academicos.id', $material->id)
            ->exists();
    }

    /**
     * @return Builder<MaterialAcademico>
     */
    public function queryVisivelPara(User $user, ?int $anoLectivoId = null): Builder
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $query = MaterialAcademico::query()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with(['disciplina', 'turma', 'criador']);

        if ($user->temVisaoInstitucional() || $user->hasRole('secretaria')) {
            return $query;
        }

        $turmaIds = $this->agenda->turmaIdsEscopo($user, $anoLectivoId);
        $discIds = $this->disciplinaIdsLeccionaOuCoordena($user, $anoLectivoId);

        return $query->where(function (Builder $q) use ($user, $turmaIds, $discIds) {
            $q->where('criado_por', $user->id);

            if ($turmaIds->isNotEmpty()) {
                $q->orWhereIn('turma_id', $turmaIds);
            }

            if ($discIds->isNotEmpty()) {
                $q->orWhereIn('disciplina_id', $discIds);
            }

            // Materiais gerais (sem turma/disciplina) — visíveis a quem tem materiais.ver
            $q->orWhere(function (Builder $g) {
                $g->whereNull('turma_id')->whereNull('disciplina_id')->where('publicado', true);
            });
        });
    }

    /**
     * @return Builder<MaterialAcademico>
     */
    public function queryParaAluno(Aluno $aluno, ?int $anoLectivoId = null): Builder
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $aluno->loadMissing('turma');

        $disciplinaIds = collect();
        if ($aluno->turma) {
            $disciplinaIds = \App\Models\Disciplina::query()
                ->paraTurma($aluno->turma)
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return MaterialAcademico::query()
            ->publicados()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where(function (Builder $q) use ($aluno, $disciplinaIds) {
                // Gerais (sem turma e sem disciplina)
                $q->where(function (Builder $g) {
                    $g->whereNull('turma_id')->whereNull('disciplina_id');
                });

                // Só disciplina (sem turma) — apenas se a disciplina faz parte do plano da turma
                if ($disciplinaIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $d) use ($disciplinaIds) {
                        $d->whereNull('turma_id')
                            ->whereIn('disciplina_id', $disciplinaIds);
                    });
                }

                if ($aluno->turma_id) {
                    $q->orWhere('turma_id', $aluno->turma_id);
                }
            })
            ->with(['disciplina', 'turma'])
            ->latest();
    }

    public function criar(User $user, array $dados, UploadedFile $ficheiro): MaterialAcademico
    {
        if (! $user->can('materiais.gerir')) {
            abort(403);
        }

        $anoId = (int) ($dados['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId());
        $path = $ficheiro->store("materiais/{$anoId}", 'local');

        return MaterialAcademico::create([
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'] ?? null,
            'ficheiro_path' => $path,
            'mime' => $ficheiro->getMimeType(),
            'tamanho' => $ficheiro->getSize() ?: 0,
            'disciplina_id' => $dados['disciplina_id'] ?? null,
            'turma_id' => $dados['turma_id'] ?? null,
            'ano_lectivo_id' => $anoId,
            'publicado' => (bool) ($dados['publicado'] ?? true),
            'criado_por' => $user->id,
        ]);
    }

    public function eliminar(User $user, MaterialAcademico $material): void
    {
        if (! $this->podeGerir($user, $material)) {
            abort(403);
        }

        // Soft-delete: manter ficheiro em disco (permite restore). Apagar binário só em forceDelete.
        $material->delete();
    }

    public function forceEliminar(User $user, MaterialAcademico $material): void
    {
        if (! $this->podeGerir($user, $material)) {
            abort(403);
        }

        $path = $material->ficheiro_path;
        $material->forceDelete();

        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function caminhoAbsoluto(MaterialAcademico $material): string
    {
        return Storage::disk('local')->path($material->ficheiro_path);
    }

    public function nomeDownload(MaterialAcademico $material): string
    {
        $ext = pathinfo($material->ficheiro_path, PATHINFO_EXTENSION);
        $base = Str::slug($material->titulo) ?: 'material';

        return $ext ? "{$base}.{$ext}" : $base;
    }

    /**
     * @return Collection<int, int>
     */
    private function disciplinaIdsCoord(User $user, ?int $anoLectivoId): Collection
    {
        if (! $user->hasRole('coordenador_disciplina')) {
            return collect();
        }

        return CoordenadorDisciplina::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->pluck('disciplina_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function disciplinaIdsLeccionaOuCoordena(User $user, ?int $anoLectivoId): Collection
    {
        $ids = $this->disciplinaIdsCoord($user, $anoLectivoId);

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
}
