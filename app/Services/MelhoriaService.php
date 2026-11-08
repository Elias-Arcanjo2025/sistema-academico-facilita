<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use Illuminate\Support\Collection;

/**
 * Serviço de Exame de Melhoria de Nota.
 *
 * Base legal:
 *   Decreto Executivo n.º 424/25 — Regulamento da Avaliação das Aprendizagens,
 *   Capítulo V, Artigo 34.º (Exame de Melhoria de Nota).
 *
 * Diferenças em relação ao Exame de Recurso:
 *   - Alvo: alunos APROVADOS na disciplina (CFD ≥ 10) que querem melhorar a nota.
 *     Faixa elegível (configurável): [melhoria_nota_min, melhoria_nota_max], default 10..16.
 *   - Regra de aplicação: a nota final é o MAX(original, melhoria) — n.º 5 do Art.º 34.
 *   - Realizado em época de Exame de Recurso (n.º 4 do Art.º 34) — não bloqueado por época.
 *   - Solicitação por escrito do aluno/EE; aqui registamos apenas o resultado da nota.
 */
class MelhoriaService
{
    public function configuracoes(): array
    {
        return [
            'nota_min'        => (int) (Configuracao::get('melhoria_nota_min', 10)),
            'nota_max'        => (int) (Configuracao::get('melhoria_nota_max', 16)),
            'max_disciplinas' => (int) (Configuracao::get('melhoria_max_disciplinas', 0)),
        ];
    }

    /**
     * Calcula a elegibilidade ao exame de melhoria por aluno.
     * Aplica-se à pauta final (T3); em técnico só 12.ª faz sentido para fecho do ciclo,
     * mas o decreto não restringe — também serve 6.ª e 9.ª.
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

        $disciplinasCurso = $turma->curso?->disciplinas ?? collect();
        $disciplinasIds = $disciplinasCurso->pluck('id')->all();
        $nomesPorId = $disciplinasCurso->keyBy('id')->map(fn ($d) => $d->codigo ?: $d->nome)->all();

        $alunos = $turma->alunos()->where('activo', true)->orderBy('numero_ordem')->get();

        $cfds = Nota::whereIn('aluno_id', $alunos->pluck('id'))
            ->whereIn('disciplina_id', $disciplinasIds)
            ->where('tipo', Nota::TIPO_CFD)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get()
            ->groupBy('aluno_id');

        return $alunos->map(function ($aluno) use ($cfds, $cfg, $nomesPorId) {
            $notas = $cfds->get($aluno->id, collect());

            $cfdsPorDisc = [];
            foreach ($notas as $n) {
                $cfdsPorDisc[$n->disciplina_id] = (float) $n->valor;
            }

            // Filtrar apenas as disciplinas em que o aluno está aprovado (≥10)
            // e dentro da faixa elegível para melhoria.
            $elegiveis = [];
            foreach ($cfdsPorDisc as $discId => $cfd) {
                if ($cfd >= RecursoService::APROVACAO_MINIMA
                    && $cfd >= $cfg['nota_min']
                    && $cfd <= $cfg['nota_max']) {
                    $elegiveis[] = [
                        'disciplina_id' => $discId,
                        'disciplina_nome' => $nomesPorId[$discId] ?? "#$discId",
                        'cfd' => $cfd,
                    ];
                }
            }

            $elegivel = !empty($elegiveis);
            $motivo = null;
            if (empty($cfdsPorDisc)) {
                $motivo = 'Sem CFDs lançadas para este aluno.';
            } elseif (!$elegivel) {
                $motivo = "Nenhuma disciplina com CFD na faixa [{$cfg['nota_min']}..{$cfg['nota_max']}].";
            } elseif ($cfg['max_disciplinas'] > 0 && count($elegiveis) > $cfg['max_disciplinas']) {
                // Aviso visual — não bloqueia, mas a escola que decida.
                $motivo = "Atenção: aluno tem " . count($elegiveis) . " disciplinas elegíveis (limite configurado: {$cfg['max_disciplinas']}).";
            }

            return [
                'aluno_id' => $aluno->id,
                'numero_ordem' => $aluno->numero_ordem,
                'nome' => $aluno->nome,
                'elegivel' => $elegivel,
                'motivo' => $motivo,
                'disciplinas_elegiveis' => $elegiveis,
            ];
        })->values();
    }

    /**
     * CFD efectiva após melhoria: devolve MAX(cfd_original, melhoria).
     * Se não houver melhoria, devolve a CFD original.
     */
    public static function cfdEfectiva(?float $cfd, ?float $melhoria): ?float
    {
        if ($melhoria === null) {
            return $cfd;
        }
        if ($cfd === null) {
            return $melhoria;
        }
        return max($cfd, $melhoria);
    }

    /**
     * Grava (cria/actualiza/apaga) uma nota tipo Melhoria.
     */
    public function gravarMelhoria(int $alunoId, int $disciplinaId, int $turmaId, ?float $valor, ?string $anoLectivo = null): void
    {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        $existente = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('tipo', Nota::TIPO_MELHORIA)
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
            'tipo' => Nota::TIPO_MELHORIA,
            'valor' => (float) $valor,
            'professor_id' => auth()->id(),
        ]);
    }
}
