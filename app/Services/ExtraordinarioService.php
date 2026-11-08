<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use Illuminate\Support\Collection;

/**
 * Serviço de Exame Extraordinário.
 *
 * Base legal:
 *   Decreto Executivo n.º 424/25 — Capítulo V, Artigo 32.º (Exame Extraordinário).
 *
 * Finalidade: estimular a conclusão do ciclo. Aplica-se a alunos que estejam em
 * pelo menos uma das condições:
 *  a) Reprovados em LP+Matemática simultaneamente (9.ª) ou LP/Mat + 1 específica
 *     da área de formação (12.ª);
 *  b) Finalistas que faltaram à época normal por motivo justificado
 *     (alínea b — flag aluno.faltou_exame_normal);
 *  c) Alunos que realizaram extraordinários em anos anteriores com aprovação em
 *     pelo menos uma disciplina (carry-over histórico);
 *  d) 12.ª: obtiveram <10 em 2 dos 3 exames de recurso realizados;
 *  e) Atletas de alta competição / desporto de alto rendimento / selecções
 *     (alínea e — flag aluno.atleta_alta_competicao).
 *
 * Regra de aprovação: ≥ 10 valores (n.º 4). A nota substitui a anterior (n.º 5).
 */
class ExtraordinarioService
{
    public const APROVACAO_MINIMA = 10;

    public function configuracoes(): array
    {
        return [
            'min_aprovacoes_anteriores' => (int) (Configuracao::get('extraordinario_min_aprovacoes_anteriores', 1)),
            'max_negativas_recurso'     => (int) (Configuracao::get('extraordinario_max_negativas_recurso', 2)),
        ];
    }

    /**
     * Determina elegibilidade ao Extraordinário por aluno.
     *
     * Aplica-se à 12.ª (foco no ensino técnico-finalista) e 9.ª. O service
     * recebe a turma e considera classe + flags do aluno + histórico de notas.
     *
     * Para a 12.ª, exige-se que TODAS as CFDs estejam lançadas para avaliar.
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

        $disciplinasCurso = $turma->curso?->disciplinas ?? collect();
        $blindadasIds = $disciplinasCurso->filter(fn ($d) => (bool) ($d->pivot->blindada ?? false))
            ->pluck('id')->all();
        $disciplinasIds = $disciplinasCurso->pluck('id')->all();
        $nomesPorId = $disciplinasCurso->keyBy('id')->map(fn ($d) => $d->codigo ?: $d->nome)->all();

        $alunos = $turma->alunos()->where('activo', true)->orderBy('numero_ordem')->get();

        $cfds = Nota::whereIn('aluno_id', $alunos->pluck('id'))
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('tipo', Nota::TIPO_CFD)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get()
            ->groupBy('aluno_id');

        $recursos = Nota::whereIn('aluno_id', $alunos->pluck('id'))
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('tipo', Nota::TIPO_RECURSO)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get()
            ->groupBy('aluno_id');

        $extrasAnteriores = Nota::whereIn('aluno_id', $alunos->pluck('id'))
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('tipo', Nota::TIPO_EXTRAORDINARIO)
            ->where('ano_lectivo_id', '!=', $anoLectivoId)
            ->where('valor', '>=', self::APROVACAO_MINIMA)
            ->get()
            ->groupBy('aluno_id');

        return $alunos->map(function ($aluno) use ($cfds, $recursos, $extrasAnteriores, $cfg, $blindadasIds, $nomesPorId, $classeNumero) {
            $cfdsAluno = $cfds->get($aluno->id, collect());
            $recursosAluno = $recursos->get($aluno->id, collect());
            $extrasAluno = $extrasAnteriores->get($aluno->id, collect());

            $cfdsPorDisc = [];
            foreach ($cfdsAluno as $n) {
                $cfdsPorDisc[$n->disciplina_id] = (float) $n->valor;
            }

            // Aplicar substituição por recurso
            $cfdsEfectivas = $cfdsPorDisc;
            foreach ($recursosAluno as $r) {
                $cfdsEfectivas[$r->disciplina_id] = (float) $r->valor;
            }

            $negativas = [];
            foreach ($cfdsEfectivas as $discId => $cfd) {
                if ($cfd < self::APROVACAO_MINIMA) {
                    $negativas[$discId] = $cfd;
                }
            }

            // Recursos com nota < 10 (alínea d, 12.ª)
            $recursosNegativos = collect($recursosAluno)->filter(fn ($r) => (float) $r->valor < self::APROVACAO_MINIMA)->count();

            $motivos = [];
            $disciplinasElegiveis = [];

            // Alínea a) Reprovado em blindadas simultaneamente (LP+Mat ou LP/Mat + área)
            $blindadasNegativas = !empty($blindadasIds) ? array_intersect($blindadasIds, array_keys($negativas)) : [];
            if (!empty($blindadasIds) && count($blindadasNegativas) === count($blindadasIds)) {
                $motivos[] = 'Reprovado em disciplinas blindadas simultaneamente (alínea a, Art.º 32).';
                foreach ($negativas as $discId => $cfd) {
                    $disciplinasElegiveis[$discId] = ['disciplina_id' => $discId, 'cfd' => $cfd];
                }
            }

            // Alínea b) Faltou ao exame nacional/recurso (flag aluno)
            if ($aluno->faltou_exame_normal) {
                $motivos[] = 'Faltou à época normal/recurso por motivo justificado (alínea b, Art.º 32).';
                foreach ($negativas as $discId => $cfd) {
                    $disciplinasElegiveis[$discId] = ['disciplina_id' => $discId, 'cfd' => $cfd];
                }
            }

            // Alínea c) Aprovou em extraordinário anterior em ≥ min_aprovacoes_anteriores disciplina(s)
            if ($extrasAluno->count() >= $cfg['min_aprovacoes_anteriores']) {
                $motivos[] = "Tem {$extrasAluno->count()} aprovação(ões) em extraordinários anteriores (alínea c, Art.º 32).";
                foreach ($negativas as $discId => $cfd) {
                    $disciplinasElegiveis[$discId] = ['disciplina_id' => $discId, 'cfd' => $cfd];
                }
            }

            // Alínea d) 12.ª: <10 em ≥ max_negativas_recurso dos exames de recurso realizados
            if ($classeNumero === 12 && $recursosAluno->count() >= 3 && $recursosNegativos >= $cfg['max_negativas_recurso']) {
                $motivos[] = "Obteve <10 em {$recursosNegativos} dos exames de recurso realizados (alínea d, Art.º 32).";
                foreach ($negativas as $discId => $cfd) {
                    $disciplinasElegiveis[$discId] = ['disciplina_id' => $discId, 'cfd' => $cfd];
                }
            }

            // Alínea e) Atleta de alta competição
            if ($aluno->atleta_alta_competicao) {
                $motivos[] = 'Atleta de alta competição / selecção (alínea e, Art.º 32).';
                foreach ($negativas as $discId => $cfd) {
                    $disciplinasElegiveis[$discId] = ['disciplina_id' => $discId, 'cfd' => $cfd];
                }
            }

            $elegivel = !empty($motivos) && !empty($disciplinasElegiveis);
            $motivoTexto = $elegivel
                ? implode(' ', $motivos)
                : (empty($cfdsPorDisc) ? 'Sem CFDs lançadas.' : 'Não cumpre nenhuma das condições do Art.º 32.');

            // Map para apresentação
            $disciplinasElegiveisArr = [];
            foreach ($disciplinasElegiveis as $discId => $dados) {
                $disciplinasElegiveisArr[] = [
                    'disciplina_id' => $discId,
                    'disciplina_nome' => $nomesPorId[$discId] ?? "#$discId",
                    'cfd' => $dados['cfd'],
                ];
            }

            return [
                'aluno_id' => $aluno->id,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'elegivel' => $elegivel,
                'motivo' => $motivoTexto,
                'disciplinas_elegiveis' => $disciplinasElegiveisArr,
            ];
        })->values();
    }

    /**
     * CFD efectiva após extraordinário: a nota substitui (n.º 5 do Art.º 32).
     */
    public static function cfdEfectiva(?float $cfd, ?float $extraordinario): ?float
    {
        if ($extraordinario !== null) {
            return $extraordinario;
        }
        return $cfd;
    }

    public function gravarExtraordinario(int $alunoId, int $disciplinaId, int $turmaId, ?float $valor, ?string $anoLectivo = null): void
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $existente = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('tipo', Nota::TIPO_EXTRAORDINARIO)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('trimestre', 3)
            ->first();

        if ($valor === null || $valor === '') {
            if ($existente) {
                $existente->delete();
            }
            return;
        }

        if ($existente) {
            $existente->valor = (float) $valor;
            $existente->save();
            return;
        }

        Nota::create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'ano_lectivo' => AnoLectivo::find($anoLectivoId)?->nome,
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => 3,
            'tipo' => Nota::TIPO_EXTRAORDINARIO,
            'valor' => (float) $valor,
            'professor_id' => auth()->id(),
        ]);
    }
}
