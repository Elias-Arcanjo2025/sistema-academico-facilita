<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Services\ConfiguracaoService;
use App\Support\NotaLancamentoRules;
use Illuminate\Support\Collection;

/**
 * Supervisão de lançamentos MAC/PP/PT — só leitura, âmbito do coordenador de disciplina.
 */
class SupervisaoLancamentosService
{
    public function __construct(
        protected DisciplinaCoordEscopoService $escopo,
        protected ConfiguracaoService $configuracaoService,
    ) {}

    public function podeSupervisionar(User $user): bool
    {
        return PedagogicoAccess::podeAcederCoordenacaoDisciplina($user, 'notas.supervisionar');
    }

    /**
     * @return array{trimestre: int, linhas: Collection<int, array<string, mixed>>, resumo: array<string, int>}
     */
    public function checklist(User $user, ?int $trimestre = null, ?int $anoLectivoId = null): array
    {
        abort_unless($this->podeSupervisionar($user), 403);

        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $trimestre = $trimestre ?? (int) $this->configuracaoService->getTrimestreActual();

        $associacoes = $this->escopo->queryAssociacoes($user, $anoLectivoId)
            ->with(['professor:id,name', 'disciplina:id,nome,codigo', 'turma:id,nome,classe_id'])
            ->get()
            ->unique(fn ($a) => $a->turma_id.':'.$a->disciplina_id.':'.$a->user_id)
            ->values();

        $tipos = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT];
        $linhas = collect();
        $resumo = ['completo' => 0, 'parcial' => 0, 'em_falta' => 0, 'sem_alunos' => 0];

        foreach ($associacoes as $assoc) {
            $totalAlunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas(
                (int) $assoc->turma_id,
                $anoLectivoId,
            )->count();

            $contagens = [];
            $estados = [];

            foreach ($tipos as $tipo) {
                if ($totalAlunos === 0) {
                    $lancados = 0;
                } else {
                    $notaQuery = Nota::query()
                        ->where('turma_id', $assoc->turma_id)
                        ->where('disciplina_id', $assoc->disciplina_id)
                        ->where('ano_lectivo_id', $anoLectivoId)
                        ->where('trimestre', $trimestre)
                        ->where('tipo', $tipo)
                        ->whereNotNull('valor');
                    NotaLancamentoRules::aplicarVisibilidadeArquivo($notaQuery, $anoLectivoId);
                    $lancados = $notaQuery->distinct()->count('aluno_id');
                }

                $contagens[$tipo] = $lancados;

                if ($totalAlunos === 0) {
                    $estados[$tipo] = 'sem_alunos';
                } elseif ($lancados >= $totalAlunos) {
                    $estados[$tipo] = 'completo';
                } elseif ($lancados > 0) {
                    $estados[$tipo] = 'parcial';
                } else {
                    $estados[$tipo] = 'em_falta';
                }
            }

            $estadoGeral = $this->estadoGeral($estados, $totalAlunos);
            $resumo[$estadoGeral] = ($resumo[$estadoGeral] ?? 0) + 1;

            $linhas->push([
                'turma_id' => (int) $assoc->turma_id,
                'turma' => $assoc->turma?->nome,
                'disciplina_id' => (int) $assoc->disciplina_id,
                'disciplina' => $assoc->disciplina?->nome,
                'professor_id' => (int) $assoc->user_id,
                'professor' => $assoc->professor?->name,
                'total_alunos' => $totalAlunos,
                'contagens' => $contagens,
                'estados' => $estados,
                'estado_geral' => $estadoGeral,
            ]);
        }

        return [
            'trimestre' => $trimestre,
            'ano_lectivo_id' => $anoLectivoId,
            'linhas' => $linhas->sortBy(['disciplina', 'turma'])->values(),
            'resumo' => $resumo,
        ];
    }

    /**
     * @param  array<string, string>  $estados
     */
    private function estadoGeral(array $estados, int $totalAlunos): string
    {
        if ($totalAlunos === 0) {
            return 'sem_alunos';
        }

        $vals = array_values($estados);
        if (collect($vals)->every(fn ($e) => $e === 'completo')) {
            return 'completo';
        }
        if (collect($vals)->every(fn ($e) => $e === 'em_falta')) {
            return 'em_falta';
        }

        return 'parcial';
    }
}
