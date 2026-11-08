<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\ConteudoMinimo;
use App\Models\ConteudoMinimoItem;
use App\Models\CoordenadorDisciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConteudoMinimoService
{
    public function __construct(
        private PedagogicoAprovacaoService $aprovacao,
    ) {}

    public function podeVer(User $user, ?ConteudoMinimo $conteudo = null): bool
    {
        if (! $user->can('conteudos.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($conteudo === null) {
            return true;
        }

        if ($conteudo->isPublicado()) {
            return $this->disciplinaNoEscopoLeitura($user, (int) $conteudo->disciplina_id);
        }

        return $this->podeGerirAmbito($user, (int) $conteudo->disciplina_id, (int) $conteudo->classe_id);
    }

    public function podeGerir(User $user, ?ConteudoMinimo $conteudo = null): bool
    {
        if (! $user->can('conteudos.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return $conteudo === null || $conteudo->isRascunho();
        }

        if ($conteudo === null) {
            return $user->hasRole('coordenador_disciplina');
        }

        if (! $conteudo->isRascunho()) {
            return false;
        }

        return $this->aprovacao->coordenaDisciplina(
            $user,
            (int) $conteudo->disciplina_id,
            $conteudo->ano_lectivo_id ? (int) $conteudo->ano_lectivo_id : Configuracao::getAnoLectivoActualId(),
            (int) $conteudo->classe_id,
        );
    }

    public function podePublicar(User $user, ?ConteudoMinimo $conteudo = null): bool
    {
        if (! $user->can('conteudos.publicar')) {
            return false;
        }

        if ($conteudo === null) {
            return true;
        }

        if (! ($conteudo->isRascunho() || $conteudo->isPublicado())) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador: só no par disciplina+classe que coordena
        return $this->aprovacao->coordenaDisciplina(
            $user,
            (int) $conteudo->disciplina_id,
            $conteudo->ano_lectivo_id ? (int) $conteudo->ano_lectivo_id : Configuracao::getAnoLectivoActualId(),
            (int) $conteudo->classe_id,
        );
    }

    public function podeCriarAmbito(User $user, int $disciplinaId, int $classeId, ?int $anoLectivoId = null): bool
    {
        if (! $user->can('conteudos.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        return $this->aprovacao->coordenaDisciplina(
            $user,
            $disciplinaId,
            $anoLectivoId ?? Configuracao::getAnoLectivoActualId(),
            $classeId,
        );
    }

    /**
     * @return Builder<ConteudoMinimo>
     */
    public function queryVisivelPara(User $user, array $filtros = []): Builder
    {
        $query = ConteudoMinimo::query()
            ->with(['disciplina', 'classe', 'curso', 'criador'])
            ->latest('updated_at');

        if ($user->temVisaoInstitucional()) {
            // vê tudo
        } elseif ($user->hasRole('coordenador_disciplina') && $user->can('conteudos.gerir')) {
            $pares = $this->paresDisciplinaClasseCoord($user);
            $discLeitura = $this->disciplinaIdsLeitura($user);

            $query->where(function (Builder $q) use ($pares, $discLeitura) {
                foreach ($pares as $par) {
                    $q->orWhere(function (Builder $p) use ($par) {
                        $p->where('disciplina_id', $par['disciplina_id'])
                            ->where('classe_id', $par['classe_id']);
                    });
                }

                if ($discLeitura->isNotEmpty()) {
                    $q->orWhere(function (Builder $pub) use ($discLeitura) {
                        $pub->where('estado', ConteudoMinimo::ESTADO_PUBLICADO)
                            ->whereIn('disciplina_id', $discLeitura);
                    });
                }

                if ($pares->isEmpty() && $discLeitura->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });
        } else {
            $query->where('estado', ConteudoMinimo::ESTADO_PUBLICADO);
            $discIds = $this->disciplinaIdsLeitura($user);
            if ($discIds->isNotEmpty()) {
                $query->whereIn('disciplina_id', $discIds);
            } elseif ($user->hasRole('coordenador_curso')) {
                // Coord. curso com conteudos.ver: todos os publicados
            } else {
                // Professor sem PDT: nada
                $query->whereRaw('1 = 0');
            }
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

    public function criar(User $user, array $dados): ConteudoMinimo
    {
        $disciplinaId = (int) $dados['disciplina_id'];
        $classeId = (int) $dados['classe_id'];
        $anoId = isset($dados['ano_lectivo_id']) && $dados['ano_lectivo_id'] !== ''
            ? (int) $dados['ano_lectivo_id']
            : null;

        if (! $this->podeCriarAmbito($user, $disciplinaId, $classeId, $anoId)) {
            abort(403, 'Não pode criar conteúdos mínimos neste âmbito.');
        }

        return ConteudoMinimo::create([
            'disciplina_id' => $disciplinaId,
            'classe_id' => $classeId,
            'curso_id' => ! empty($dados['curso_id']) ? $dados['curso_id'] : null,
            'ano_lectivo_id' => $anoId,
            'trimestre' => ! empty($dados['trimestre']) ? $dados['trimestre'] : null,
            'titulo' => $dados['titulo'],
            'codigo_oficial' => $dados['codigo_oficial'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 1,
            'criado_por' => $user->id,
        ]);
    }

    public function actualizar(User $user, ConteudoMinimo $conteudo, array $dados): ConteudoMinimo
    {
        if (! $this->podeGerir($user, $conteudo)) {
            abort(403);
        }

        $conteudo->update([
            'titulo' => $dados['titulo'] ?? $conteudo->titulo,
            'codigo_oficial' => array_key_exists('codigo_oficial', $dados) ? $dados['codigo_oficial'] : $conteudo->codigo_oficial,
            'descricao' => array_key_exists('descricao', $dados) ? $dados['descricao'] : $conteudo->descricao,
            'curso_id' => array_key_exists('curso_id', $dados) ? ($dados['curso_id'] ?: null) : $conteudo->curso_id,
            'ano_lectivo_id' => array_key_exists('ano_lectivo_id', $dados) ? ($dados['ano_lectivo_id'] ?: null) : $conteudo->ano_lectivo_id,
            'trimestre' => array_key_exists('trimestre', $dados) ? ($dados['trimestre'] ?: null) : $conteudo->trimestre,
        ]);

        return $conteudo->fresh();
    }

    public function adicionarItem(User $user, ConteudoMinimo $conteudo, array $dados): ConteudoMinimoItem
    {
        if (! $this->podeGerir($user, $conteudo)) {
            abort(403);
        }

        $parentId = $dados['parent_id'] ?? null;
        $maxOrdem = (int) $conteudo->itens()->where('parent_id', $parentId)->max('ordem');

        return ConteudoMinimoItem::create([
            'conteudo_minimo_id' => $conteudo->id,
            'parent_id' => $parentId,
            'codigo' => $dados['codigo'] ?? null,
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'] ?? null,
            'ordem' => $dados['ordem'] ?? ($maxOrdem + 1),
            'obrigatorio' => array_key_exists('obrigatorio', $dados) ? (bool) $dados['obrigatorio'] : true,
            'carga_horaria_sugerida' => $dados['carga_horaria_sugerida'] ?? null,
            'trimestre_sugerido' => $dados['trimestre_sugerido'] ?? null,
        ]);
    }

    public function actualizarItem(User $user, ConteudoMinimoItem $item, array $dados): ConteudoMinimoItem
    {
        if (! $this->podeGerir($user, $item->conteudoMinimo)) {
            abort(403);
        }

        $item->update([
            'codigo' => array_key_exists('codigo', $dados) ? $dados['codigo'] : $item->codigo,
            'titulo' => $dados['titulo'] ?? $item->titulo,
            'descricao' => array_key_exists('descricao', $dados) ? $dados['descricao'] : $item->descricao,
            'ordem' => $dados['ordem'] ?? $item->ordem,
            'obrigatorio' => array_key_exists('obrigatorio', $dados) ? (bool) $dados['obrigatorio'] : $item->obrigatorio,
            'carga_horaria_sugerida' => array_key_exists('carga_horaria_sugerida', $dados) ? $dados['carga_horaria_sugerida'] : $item->carga_horaria_sugerida,
            'trimestre_sugerido' => array_key_exists('trimestre_sugerido', $dados) ? $dados['trimestre_sugerido'] : $item->trimestre_sugerido,
            'parent_id' => array_key_exists('parent_id', $dados) ? $dados['parent_id'] : $item->parent_id,
        ]);

        return $item->fresh();
    }

    public function eliminarItem(User $user, ConteudoMinimoItem $item): void
    {
        if (! $this->podeGerir($user, $item->conteudoMinimo)) {
            abort(403);
        }
        $item->delete();
    }

    public function publicar(User $user, ConteudoMinimo $conteudo): ConteudoMinimo
    {
        if (! $this->podePublicar($user, $conteudo) || ! $conteudo->isRascunho()) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $conteudo) {
            $this->arquivarPublicadosMesmoAmbito($conteudo);

            $conteudo->update([
                'estado' => ConteudoMinimo::ESTADO_PUBLICADO,
                'publicado_por' => $user->id,
                'publicado_em' => now(),
            ]);

            return $conteudo->fresh();
        });
    }

    public function arquivar(User $user, ConteudoMinimo $conteudo): ConteudoMinimo
    {
        if (! $this->podePublicar($user, $conteudo) || ! $conteudo->isPublicado()) {
            abort(403);
        }

        $conteudo->update(['estado' => ConteudoMinimo::ESTADO_ARQUIVADO]);

        return $conteudo->fresh();
    }

    public function novaVersao(User $user, ConteudoMinimo $conteudo): ConteudoMinimo
    {
        if (! $conteudo->isPublicado() && $conteudo->estado !== ConteudoMinimo::ESTADO_ARQUIVADO) {
            abort(422, 'Só a partir de publicado ou arquivado.');
        }

        $anoId = $conteudo->ano_lectivo_id ? (int) $conteudo->ano_lectivo_id : null;
        $pode = $user->temVisaoInstitucional()
            || $user->can('conteudos.publicar')
            || $this->podeCriarAmbito($user, (int) $conteudo->disciplina_id, (int) $conteudo->classe_id, $anoId);

        if (! $pode) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $conteudo) {
            $novo = ConteudoMinimo::create([
                'disciplina_id' => $conteudo->disciplina_id,
                'classe_id' => $conteudo->classe_id,
                'curso_id' => $conteudo->curso_id,
                'ano_lectivo_id' => $conteudo->ano_lectivo_id,
                'trimestre' => $conteudo->trimestre,
                'titulo' => $conteudo->titulo,
                'codigo_oficial' => $conteudo->codigo_oficial,
                'descricao' => $conteudo->descricao,
                'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
                'versao' => (int) $conteudo->versao + 1,
                'criado_por' => $user->id,
            ]);

            $mapaIds = [];
            $itens = $conteudo->itens()->orderBy('ordem')->get();

            foreach ($itens->whereNull('parent_id') as $item) {
                $copia = ConteudoMinimoItem::create([
                    'conteudo_minimo_id' => $novo->id,
                    'parent_id' => null,
                    'codigo' => $item->codigo,
                    'titulo' => $item->titulo,
                    'descricao' => $item->descricao,
                    'ordem' => $item->ordem,
                    'obrigatorio' => $item->obrigatorio,
                    'carga_horaria_sugerida' => $item->carga_horaria_sugerida,
                    'trimestre_sugerido' => $item->trimestre_sugerido,
                ]);
                $mapaIds[$item->id] = $copia->id;
            }

            foreach ($itens->whereNotNull('parent_id') as $item) {
                ConteudoMinimoItem::create([
                    'conteudo_minimo_id' => $novo->id,
                    'parent_id' => $mapaIds[$item->parent_id] ?? null,
                    'codigo' => $item->codigo,
                    'titulo' => $item->titulo,
                    'descricao' => $item->descricao,
                    'ordem' => $item->ordem,
                    'obrigatorio' => $item->obrigatorio,
                    'carga_horaria_sugerida' => $item->carga_horaria_sugerida,
                    'trimestre_sugerido' => $item->trimestre_sugerido,
                ]);
            }

            return $novo->fresh(['itensRaiz.filhos']);
        });
    }

    public function publicadoPara(
        int $disciplinaId,
        int $classeId,
        ?int $cursoId = null,
        ?int $anoLectivoId = null,
        ?int $trimestre = null,
    ): ?ConteudoMinimo {
        $candidatos = ConteudoMinimo::query()
            ->publicados()
            ->where('disciplina_id', $disciplinaId)
            ->where('classe_id', $classeId)
            ->with(['itensRaiz.filhos'])
            ->orderByDesc('versao')
            ->get();

        $melhor = null;
        $melhorScore = -1;

        foreach ($candidatos as $c) {
            $s = 0;
            if ($cursoId && (int) $c->curso_id === (int) $cursoId) {
                $s += 4;
            } elseif ($c->curso_id === null) {
                $s += 1;
            } else {
                continue;
            }
            if ($anoLectivoId && (int) $c->ano_lectivo_id === (int) $anoLectivoId) {
                $s += 2;
            } elseif ($c->ano_lectivo_id === null) {
                $s += 1;
            } else {
                continue;
            }
            if ($trimestre && (int) $c->trimestre === (int) $trimestre) {
                $s += 2;
            } elseif ($c->trimestre === null) {
                $s += 1;
            } else {
                continue;
            }
            if ($s > $melhorScore) {
                $melhorScore = $s;
                $melhor = $c;
            }
        }

        return $melhor;
    }

    private function arquivarPublicadosMesmoAmbito(ConteudoMinimo $conteudo): void
    {
        ConteudoMinimo::query()
            ->where('disciplina_id', $conteudo->disciplina_id)
            ->where('classe_id', $conteudo->classe_id)
            ->where('estado', ConteudoMinimo::ESTADO_PUBLICADO)
            ->where('id', '!=', $conteudo->id)
            ->where(function (Builder $q) use ($conteudo) {
                $conteudo->curso_id === null
                    ? $q->whereNull('curso_id')
                    : $q->where('curso_id', $conteudo->curso_id);
            })
            ->where(function (Builder $q) use ($conteudo) {
                $conteudo->ano_lectivo_id === null
                    ? $q->whereNull('ano_lectivo_id')
                    : $q->where('ano_lectivo_id', $conteudo->ano_lectivo_id);
            })
            ->where(function (Builder $q) use ($conteudo) {
                $conteudo->trimestre === null
                    ? $q->whereNull('trimestre')
                    : $q->where('trimestre', $conteudo->trimestre);
            })
            ->update(['estado' => ConteudoMinimo::ESTADO_ARQUIVADO]);
    }

    private function podeGerirAmbito(User $user, int $disciplinaId, int $classeId): bool
    {
        if (! $user->can('conteudos.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        return $this->aprovacao->coordenaDisciplina(
            $user,
            $disciplinaId,
            Configuracao::getAnoLectivoActualId(),
            $classeId,
        );
    }

    private function disciplinaNoEscopoLeitura(User $user, int $disciplinaId): bool
    {
        if ($user->temVisaoInstitucional() || $user->hasRole('coordenador_curso')) {
            return true;
        }

        return $this->disciplinaIdsLeitura($user)->contains($disciplinaId)
            || $this->aprovacao->coordenaDisciplina($user, $disciplinaId);
    }

    /**
     * @return Collection<int, int>
     */
    private function disciplinaIdsLeitura(User $user): Collection
    {
        $ids = collect();
        $anoId = Configuracao::getAnoLectivoActualId();

        if ($user->hasRole('coordenador_disciplina')) {
            $ids = $ids->merge(
                CoordenadorDisciplina::query()
                    ->where('user_id', $user->id)
                    ->where('activo', true)
                    ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                    ->pluck('disciplina_id')
            );
        }

        $ids = $ids->merge(
            ProfessorDisciplinaTurma::query()
                ->where('user_id', $user->id)
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->pluck('disciplina_id')
        );

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * @return Collection<int, array{disciplina_id:int, classe_id:int}>
     */
    private function paresDisciplinaClasseCoord(User $user): Collection
    {
        $anoId = Configuracao::getAnoLectivoActualId();

        return CoordenadorDisciplina::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->with('classes:id')
            ->get()
            ->flatMap(function (CoordenadorDisciplina $cd) {
                return $cd->classes->map(fn ($c) => [
                    'disciplina_id' => (int) $cd->disciplina_id,
                    'classe_id' => (int) $c->id,
                ]);
            })
            ->values();
    }
}
