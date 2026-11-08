<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de Exame de Recurso.
 *
 * Base legal:
 *   Decreto Executivo n.º 424/25 — Regulamento da Avaliação das Aprendizagens,
 *   Capítulo V, Artigo 31.º (Exame de Recurso).
 *
 * Regras chave para a 12.ª classe (configuráveis):
 *   - Aluno elegível se tiver entre 1 e N notas negativas, com CFD ∈ [min, max]
 *     onde N = recurso_max_disciplinas, [min, max] = [recurso_nota_min, recurso_nota_max].
 *   - NÃO é elegível se tiver CFD < recurso_nota_min em qualquer disciplina.
 *   - NÃO é elegível se TODAS as disciplinas "blindadas" do curso (curso_disciplina.blindada)
 *     estiverem com CFD negativa simultaneamente.
 *   - A nota do Recurso SUBSTITUI a CFD anterior (Art.º 31.º, n.º 3).
 *   - Aluno aprovado em disciplina via recurso se nota ≥ 10 valores.
 */
class RecursoService
{
    public const APROVACAO_MINIMA = 10;

    /**
     * Devolve as configurações de recurso (com defaults seguros).
     */
    public function configuracoes(): array
    {
        return [
            'nota_min'        => (int) (Configuracao::get('recurso_nota_min', 6)),
            'nota_max'        => (int) (Configuracao::get('recurso_nota_max', 9)),
            'max_disciplinas' => (int) (Configuracao::get('recurso_max_disciplinas', 3)),
        ];
    }

    /**
     * Calcula a elegibilidade ao recurso para cada aluno de uma turma.
     * Aplica-se exclusivamente à pauta final da 12.ª classe (T3).
     *
     * @return Collection<int, array{
     *   aluno_id:int,
     *   numero_ordem:?int,
     *   nome:string,
     *   elegivel:bool,
     *   motivo:?string,
     *   disciplinas_elegiveis:array<int,array{disciplina_id:int,cfd:float}>,
     *   negativas_total:int,
     * }>
     */
    public function calcularElegibilidade(Turma $turma, ?string $anoLectivo = null): Collection
    {
        $cfg = $this->configuracoes();
        // Fonte de verdade: ano lectivo da TURMA (não o ano "actual" do sistema).
        $anoLectivo = $anoLectivo
            ?? $turma->anoLectivo?->nome
            ?? $turma->ano_lectivo
            ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $classeNumero = (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
        if ($classeNumero !== 12) {
            return collect();
        }

        // Disciplinas do curso desta turma (carrega pivot para flag "blindada")
        $disciplinasCurso = $turma->curso?->disciplinas ?? collect();
        $blindadasIds = $disciplinasCurso->filter(fn ($d) => (bool) ($d->pivot->blindada ?? false))
            ->pluck('id')->all();
        $disciplinasIds = $disciplinasCurso->pluck('id')->all();
        $nomesPorId = $disciplinasCurso->keyBy('id')->map(fn ($d) => $d->codigo ?: $d->nome)->all();

        // Carregar todas as CFDs (tipo CFD) dos alunos desta turma para o ano lectivo actual
        $alunos = $turma->alunos()->where('activo', true)->orderBy('numero_ordem')->get();

        $cfds = Nota::whereIn('aluno_id', $alunos->pluck('id'))
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('tipo', Nota::TIPO_CFD)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get()
            ->groupBy('aluno_id');

        return $alunos->map(function ($aluno) use ($cfds, $cfg, $blindadasIds, $nomesPorId) {
            $notas = $cfds->get($aluno->id, collect());

            // CFDs por disciplina (1 valor por disciplina)
            $cfdsPorDisc = [];
            foreach ($notas as $n) {
                $cfdsPorDisc[$n->disciplina_id] = (float) $n->valor;
            }

            // Categorizar
            $negativasNaFaixa = [];   // CFD ∈ [min, max]  → elegíveis a recurso
            $negativasForaFaixa = []; // CFD < min          → bloqueia recurso
            foreach ($cfdsPorDisc as $discId => $cfd) {
                if ($cfd < $cfg['nota_min']) {
                    $negativasForaFaixa[$discId] = $cfd;
                } elseif ($cfd <= $cfg['nota_max']) {
                    $negativasNaFaixa[$discId] = $cfd;
                }
            }
            $totalNegativas = count($negativasNaFaixa) + count($negativasForaFaixa);

            // Avaliar elegibilidade
            $elegivel = true;
            $motivo = null;

            if (empty($cfdsPorDisc)) {
                $elegivel = false;
                $motivo = 'Sem CFDs lançadas para este aluno.';
            } elseif (!empty($negativasForaFaixa)) {
                $elegivel = false;
                $nomes = collect(array_keys($negativasForaFaixa))->map(fn ($id) => $nomesPorId[$id] ?? "#$id")->implode(', ');
                $motivo = "Reprovado sem direito a recurso (CFD < {$cfg['nota_min']} em: {$nomes}).";
            } elseif (empty($negativasNaFaixa)) {
                $elegivel = false;
                $motivo = 'Sem disciplinas com CFD na faixa de recurso.';
            } elseif (count($negativasNaFaixa) > $cfg['max_disciplinas']) {
                $elegivel = false;
                $motivo = "Excede o máximo de {$cfg['max_disciplinas']} disciplinas para recurso (tem " . count($negativasNaFaixa) . ').';
            } elseif (!empty($blindadasIds) && !empty(array_intersect($blindadasIds, array_keys($negativasNaFaixa)))) {
                // Verifica se TODAS as blindadas estão negativas (em qualquer faixa)
                $negativasIds = array_merge(array_keys($negativasNaFaixa), array_keys($negativasForaFaixa));
                $blindadasNegativas = array_intersect($blindadasIds, $negativasIds);
                if (count($blindadasNegativas) === count($blindadasIds)) {
                    $nomesBl = collect($blindadasIds)->map(fn ($id) => $nomesPorId[$id] ?? "#$id")->implode(', ');
                    $elegivel = false;
                    $motivo = "Reprovado em todas as disciplinas blindadas simultaneamente ({$nomesBl}).";
                }
            }

            $disciplinasElegiveis = [];
            $disciplinasNaFaixa = [];
            foreach ($negativasNaFaixa as $discId => $cfd) {
                $disciplinasNaFaixa[] = [
                    'disciplina_id' => $discId,
                    'cfd' => $cfd,
                ];
                if ($elegivel) {
                    $disciplinasElegiveis[] = [
                        'disciplina_id' => $discId,
                        'cfd' => $cfd,
                    ];
                }
            }

            return [
                'aluno_id' => $aluno->id,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'elegivel' => $elegivel,
                'motivo' => $motivo,
                'disciplinas_elegiveis' => $disciplinasElegiveis,
                'disciplinas_negativas_faixa' => $disciplinasNaFaixa,
                'negativas_total' => $totalNegativas,
            ];
        })->values();
    }

    /**
     * CFD efectiva após recurso: se existe nota tipo RECURSO, devolve essa; senão devolve a CFD original.
     * Lê a partir de uma estrutura pré-carregada [aluno_id][disciplina_id] => ['cfd'=>..., 'recurso'=>...].
     */
    public static function cfdEfectiva(?float $cfd, ?float $recurso): ?float
    {
        // Regra MED: a nota do recurso SUBSTITUI a CFD anterior.
        if ($recurso !== null) {
            return $recurso;
        }
        return $cfd;
    }

    /**
     * Grava (cria/actualiza/apaga) uma nota tipo RECURSO para um aluno/disciplina.
     */
    public function gravarRecurso(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        ?float $valor,
        ?string $anoLectivo = null,
        bool $foraElegibilidade = false,
    ): void {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $existente = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('tipo', Nota::TIPO_RECURSO)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->whereNull('archived_at')
            ->first();

        if ($valor === null || $valor === '') {
            if ($existente) {
                $existente->archive('Exame de recurso removido');
            }

            return;
        }

        $valor = (float) $valor;

        if ($existente) {
            if ((float) $existente->valor === $valor) {
                return;
            }

            $existente->archive('Substituída por nova nota de exame de recurso');
        }

        $observacoes = 'Exame de recurso — '.now()->format('d/m/Y H:i');
        if ($foraElegibilidade) {
            $observacoes .= ' [Fora da elegibilidade formal — permitido pela configuração da escola]';
        }

        $nota = Nota::create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'ano_lectivo' => AnoLectivo::find($anoLectivoId)?->nome,
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => 3,
            'tipo' => Nota::TIPO_RECURSO,
            'valor' => $valor,
            'professor_id' => auth()->id(),
            'observacoes' => $observacoes,
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId,
                'turma_id' => $turmaId,
                'valor' => $valor,
                'tipo' => Nota::TIPO_RECURSO,
                'fora_elegibilidade' => $foraElegibilidade,
            ])
            ->log($foraElegibilidade
                ? 'Nota de recurso lançada (fora da elegibilidade formal)'
                : 'Nota de recurso lançada');
    }

    /**
     * Lista plana de elegibilidade ao recurso (relatório consolidado).
     */
    public function listarElegiveisPlano(?int $turmaId = null, ?string $anoLectivo = null): Collection
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $query = Turma::with(['classe', 'curso.disciplinas', 'anoLectivo'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereHas('classe', function ($q) {
                $q->where('codigo', 'like', '12%')->orWhere('nome', 'like', '12%');
            });

        if ($turmaId) {
            $query->where('id', $turmaId);
        }

        $linhas = collect();

        foreach ($query->orderBy('nome')->get() as $turma) {
            $disciplinas = $turma->curso?->disciplinas->keyBy('id') ?? collect();
            $elegibilidade = $this->calcularElegibilidade($turma, $anoLectivo);

            foreach ($elegibilidade->where('elegivel', true) as $alunoElig) {
                foreach ($alunoElig['disciplinas_elegiveis'] as $de) {
                    $disc = $disciplinas->get($de['disciplina_id']);
                    $linhas->push([
                        'tipo_exame' => 'Recurso',
                        'turma_id' => $turma->id,
                        'turma_nome' => $turma->nome,
                        'classe' => 12,
                        'trimestre' => 3,
                        'aluno_id' => $alunoElig['aluno_id'],
                        'numero_ordem' => $alunoElig['numero_ordem'],
                        'aluno_nome' => $alunoElig['nome'],
                        'disciplina_id' => $de['disciplina_id'],
                        'disciplina_nome' => $disc?->codigo ?: $disc?->nome ?? "#{$de['disciplina_id']}",
                        'nota_referencia' => $de['cfd'],
                        'detalhe' => 'CFD: ' . (int) round($de['cfd']),
                    ]);
                }
            }
        }

        return $linhas->sortBy(['turma_nome', 'numero_ordem', 'disciplina_nome'])->values();
    }
}
