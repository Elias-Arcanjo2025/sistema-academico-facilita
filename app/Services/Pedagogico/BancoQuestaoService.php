<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Disciplina;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Models\Pedagogico\PedProva;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BancoQuestaoService
{
    public function __construct(
        private PedagogicoAprovacaoService $aprovacao,
    ) {}

    public function podeGerir(User $user, PedBancoQuestao $questao): bool
    {
        if (! $user->can('pedagogico.banco.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($this->aprovacao->coordenaDisciplina($user, (int) $questao->disciplina_id)) {
            return true;
        }

        return (int) $questao->criado_por === (int) $user->id;
    }

    public function podeCriarNaDisciplina(User $user, int $disciplinaId): bool
    {
        if (! $user->can('pedagogico.banco.gerir')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $this->aprovacao->coordenaDisciplina($user, $disciplinaId)) {
            return true;
        }

        $anoId = Configuracao::getAnoLectivoActualId();

        return ProfessorDisciplinaTurma::query()
            ->where('user_id', $user->id)
            ->where('disciplina_id', $disciplinaId)
            ->where('activo', true)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->exists();
    }

    /**
     * Disciplinas em que o utilizador pode criar/gerir questões no banco.
     *
     * @return Collection<int, Disciplina>
     */
    public function disciplinasDisponiveis(User $user): Collection
    {
        $base = Disciplina::query()->where('activo', true)->orderBy('nome');

        if ($user->temVisaoInstitucional()) {
            return $base->get(['id', 'nome']);
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $ids = collect();

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
        )->unique()->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $base->whereIn('id', $ids)->get(['id', 'nome']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $query = PedBancoQuestao::query()->with(['disciplina', 'criador'])->latest();

        if (! empty($filtros['disciplina_id'])) {
            $query->where('disciplina_id', $filtros['disciplina_id']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        } else {
            $query->where('estado', PedBancoQuestao::ESTADO_ACTIVA);
        }

        if (! $user->temVisaoInstitucional() && ! $user->hasRole('coordenador_disciplina')) {
            // Professor: vê activas da disciplina que lecciona + as suas
            $anoId = Configuracao::getAnoLectivoActualId();
            $discIds = ProfessorDisciplinaTurma::query()
                ->where('user_id', $user->id)
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->pluck('disciplina_id')
                ->unique();

            $query->where(function ($q) use ($user, $discIds) {
                $q->whereIn('disciplina_id', $discIds->isEmpty() ? [-1] : $discIds)
                    ->orWhere('criado_por', $user->id);
            });
        } elseif ($user->hasRole('coordenador_disciplina') && ! $user->temVisaoInstitucional()) {
            $anoId = Configuracao::getAnoLectivoActualId();
            $discIds = CoordenadorDisciplina::query()
                ->where('user_id', $user->id)
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->pluck('disciplina_id')
                ->unique();
            $query->whereIn('disciplina_id', $discIds->isEmpty() ? [-1] : $discIds);
        }

        return $query->paginate(20)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(User $user, array $dados): PedBancoQuestao
    {
        if (! $this->podeCriarNaDisciplina($user, (int) $dados['disciplina_id'])) {
            abort(403);
        }

        return PedBancoQuestao::create([
            ...$dados,
            'criado_por' => $user->id,
            'estado' => $dados['estado'] ?? PedBancoQuestao::ESTADO_ACTIVA,
        ]);
    }

    /**
     * @param  array<int>  $questaoIds
     */
    public function importarParaProva(User $user, PedProva $prova, array $questaoIds): PedProva
    {
        if ($prova->estado !== PedProva::ESTADO_RASCUNHO) {
            throw new InvalidArgumentException('Só é possível importar questões para provas em rascunho.');
        }

        $questoes = PedBancoQuestao::query()
            ->whereIn('id', $questaoIds)
            ->where('disciplina_id', $prova->disciplina_id)
            ->activas()
            ->get();

        if ($questoes->isEmpty()) {
            throw new InvalidArgumentException('Nenhuma questão válida seleccionada.');
        }

        $conteudo = $prova->conteudo_json ?? [];
        $lista = $conteudo['questoes'] ?? (is_array($conteudo) && array_is_list($conteudo) ? $conteudo : []);
        if (! is_array($lista)) {
            $lista = [];
        }

        $numero = count($lista) + 1;
        foreach ($questoes as $q) {
            $lista[] = $q->paraConteudoProva($numero++);
        }

        if (isset($conteudo['questoes']) || (! array_is_list($conteudo) && $conteudo !== [])) {
            $conteudo['questoes'] = $lista;
        } else {
            $conteudo = ['questoes' => $lista];
        }

        $prova->update(['conteudo_json' => $conteudo]);

        return $prova->fresh();
    }
}
