<?php

namespace App\Services\Pedagogico;

use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Avisos in-app só aos professores das disciplinas/classes coordenadas.
 */
class AvisoDisciplinaService
{
    public function __construct(
        protected DisciplinaCoordEscopoService $escopo,
    ) {}

    public function podeAvisar(User $user): bool
    {
        return PedagogicoAccess::podeAcederCoordenacaoDisciplina($user, 'disciplina.avisar');
    }

    /**
     * @return array{enviados: int, destinatarios: list<int>}
     */
    public function enviar(
        User $remetente,
        int $disciplinaId,
        string $titulo,
        string $mensagem,
        ?int $classeId = null,
    ): array {
        abort_unless($this->podeAvisar($remetente), 403);

        if (! $remetente->temVisaoInstitucional()) {
            if ($classeId) {
                if (! $this->escopo->coordena($remetente, $disciplinaId, $classeId)) {
                    abort(403, 'Não coordena esta disciplina/classe.');
                }
            } else {
                $pares = $this->escopo->paresDisciplinaClasse($remetente)
                    ->where('disciplina_id', $disciplinaId);
                if ($pares->isEmpty()) {
                    abort(403, 'Não coordena esta disciplina.');
                }
            }
        }

        $titulo = trim($titulo);
        $mensagem = trim($mensagem);

        if ($titulo === '' || $mensagem === '') {
            throw new InvalidArgumentException('Título e mensagem são obrigatórios.');
        }

        $query = $this->escopo->queryAssociacoes($remetente)
            ->where('disciplina_id', $disciplinaId);

        if ($classeId) {
            $query->whereHas('turma', fn ($t) => $t->where('classe_id', $classeId));
        }

        $destinatarioIds = $query->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $remetente->id)
            ->values();

        $disciplina = Disciplina::find($disciplinaId);
        $professores = User::query()->whereIn('id', $destinatarioIds)->get();
        $enviados = 0;

        foreach ($professores as $professor) {
            try {
                $professor->notify(new StaffAlertaAcademicoNotification('aviso_disciplina', [
                    'titulo' => $titulo,
                    'mensagem' => ($disciplina?->nome ? '['.$disciplina->nome.'] ' : '').$mensagem,
                    'url' => route('notificacoes.index'),
                ]));
                $enviados++;
            } catch (\Throwable $e) {
                Log::warning('Falha a enviar aviso de disciplina', [
                    'user_id' => $professor->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return [
            'enviados' => $enviados,
            'destinatarios' => $destinatarioIds->all(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{disciplina_id: int, classe_id: int, disciplina: string|null, classe: string|null}>
     */
    public function opcoesAmbito(User $user)
    {
        if ($user->temVisaoInstitucional()) {
            $anoId = Configuracao::getAnoLectivoActualId();

            $pares = ProfessorDisciplinaTurma::query()
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->with(['disciplina:id,nome', 'turma:id,classe_id', 'turma.classe:id,nome'])
                ->get()
                ->map(fn (ProfessorDisciplinaTurma $a) => [
                    'disciplina_id' => (int) $a->disciplina_id,
                    'classe_id' => (int) ($a->turma?->classe_id ?? 0),
                    'disciplina' => $a->disciplina?->nome,
                    'classe' => $a->turma?->classe?->nome,
                ])
                ->filter(fn (array $p) => $p['classe_id'] > 0)
                ->unique(fn (array $p) => $p['disciplina_id'].':'.$p['classe_id'])
                ->values();

            return $pares;
        }

        $pares = $this->escopo->paresDisciplinaClasse($user);
        $disciplinaIds = $pares->pluck('disciplina_id')->unique();
        $classeIds = $pares->pluck('classe_id')->unique();

        $disciplinas = Disciplina::query()->whereIn('id', $disciplinaIds)->pluck('nome', 'id');
        $classes = Classe::query()->whereIn('id', $classeIds)->pluck('nome', 'id');

        return $pares->map(fn (array $p) => [
            'disciplina_id' => $p['disciplina_id'],
            'classe_id' => $p['classe_id'],
            'disciplina' => $disciplinas[$p['disciplina_id']] ?? null,
            'classe' => $classes[$p['classe_id']] ?? null,
        ]);
    }
}
