<?php

namespace App\Services;

use App\Models\ConselhoTrimestre;
use App\Models\AjusteConselho;
use App\Models\Nota;
use App\Models\User;
use App\Models\Configuracao;
use App\Events\NotaAlterada;
use App\Support\ConselhoAjusteRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ConselhoService
{
    public function __construct(
        protected PautaService $pautaService
    ) {}

    /**
     * Criar ou recuperar conselho para uma turma/trimestre
     */
    public function obterOuCriarConselho(
        int $turmaId,
        int $trimestre,
        ?string $anoLectivo = null
    ): ConselhoTrimestre {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        return ConselhoTrimestre::firstOrCreate(
            [
                'turma_id' => $turmaId,
                'trimestre' => $trimestre,
                'ano_lectivo_id' => $anoLectivoId,
            ],
            [
                'ano_lectivo' => $anoLectivo,
                'estado' => ConselhoTrimestre::ESTADO_PENDENTE,
            ]
        );
    }

    /**
     * Iniciar conselho (pendente → em_curso)
     */
    public function iniciarConselho(
        ConselhoTrimestre $conselho,
        User $user
    ): ConselhoTrimestre {
        if (Configuracao::isModoConsultaById($conselho->ano_lectivo_id, $user)) {
            throw new \Exception('Ano lectivo em modo consulta — não é possível iniciar conselhos.');
        }

        if (!$conselho->podeIniciar()) {
            throw new \Exception('Este conselho já foi iniciado ou concluído.');
        }

        $anoActualId = Configuracao::getAnoLectivoActualId();
        if ((int) $conselho->ano_lectivo_id === $anoActualId
            && ! $user->podeIgnorarTrimestreFechado()) {
            $trimestreActual = Configuracao::getTrimestreActual();
            if ($trimestreActual && (int) $conselho->trimestre !== $trimestreActual) {
                throw new \Exception(
                    "Só pode iniciar o conselho do {$trimestreActual}.º trimestre (trimestre actual do sistema)."
                );
            }
        }

        $conselho->update([
            'estado' => ConselhoTrimestre::ESTADO_EM_CURSO,
            'data_inicio' => now(),
            'iniciado_por' => $user->id,
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($conselho)
            ->withProperties([
                'turma_id' => $conselho->turma_id,
                'trimestre' => $conselho->trimestre,
            ])
            ->log('Conselho de classe iniciado');

        return $conselho->fresh();
    }

    /**
     * Ajustar uma nota durante o conselho
     */
    public function ajustarNota(
        ConselhoTrimestre $conselho,
        Nota $notaPre,
        float $novoValor,
        string $justificacao,
        User $user,
        ?string $motivoReducao = null,
    ): array {
        // Validações
        if (Configuracao::isModoConsultaById($conselho->ano_lectivo_id, $user)) {
            throw new \Exception('Ano lectivo em modo consulta — não é possível ajustar notas no conselho.');
        }

        if (!$conselho->podeAjustar()) {
            throw new \Exception('O conselho não está em estado de ajuste.');
        }

        if (!$notaPre->isPreConselho()) {
            throw new \Exception('Apenas notas pré-conselho podem ser ajustadas.');
        }

        if (! in_array($notaPre->tipo, [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_PG], true)) {
            throw new \Exception('Apenas notas de avaliação (MAC, PP, PT ou PG) podem ser ajustadas.');
        }

        if (empty(trim($justificacao)) || strlen(trim($justificacao)) < 10) {
            throw new \Exception('A justificação é obrigatória e deve ter pelo menos 10 caracteres.');
        }

        ConselhoAjusteRules::validarAjuste(
            (float) $notaPre->valor,
            $novoValor,
            $motivoReducao,
            $justificacao,
        );

        // Verificar se já foi ajustada
        if ($notaPre->foiAjustada()) {
            throw new \Exception('Esta nota já foi ajustada neste conselho.');
        }

        $motivoReducao = ConselhoAjusteRules::isReducao((float) $notaPre->valor, $novoValor)
            ? $motivoReducao
            : null;

        DB::beginTransaction();
        try {
            // Criar nota pós-conselho (duplicata com novo valor)
            $notaPos = Nota::create([
                'aluno_id' => $notaPre->aluno_id,
                'disciplina_id' => $notaPre->disciplina_id,
                'turma_id' => $notaPre->turma_id,
                'professor_id' => $user->id,
                'tipo' => $notaPre->tipo,
                'valor' => $novoValor,
                'ano_lectivo' => $notaPre->ano_lectivo,
                'ano_lectivo_id' => $notaPre->ano_lectivo_id,
                'trimestre' => $notaPre->trimestre,
                'observacoes' => "Ajuste no conselho: {$justificacao}",
                'estado_conselho' => Nota::ESTADO_POS_CONSELHO,
                'nota_origem_id' => $notaPre->id,
            ]);

            // Registrar o ajuste
            $ajuste = AjusteConselho::create([
                'conselho_id' => $conselho->id,
                'nota_pre_id' => $notaPre->id,
                'nota_pos_id' => $notaPos->id,
                'valor_anterior' => $notaPre->valor,
                'valor_novo' => $novoValor,
                'justificacao' => $justificacao,
                'motivo_reducao' => $motivoReducao,
                'ajustado_por' => $user->id,
            ]);

            // Log de auditoria
            activity()
                ->causedBy($user)
                ->performedOn($notaPos)
                ->withProperties([
                    'conselho_id' => $conselho->id,
                    'aluno_id' => $notaPre->aluno_id,
                    'disciplina_id' => $notaPre->disciplina_id,
                    'tipo' => $notaPre->tipo,
                    'valor_anterior' => $notaPre->valor,
                    'valor_novo' => $novoValor,
                    'justificacao' => $justificacao,
                    'motivo_reducao' => $motivoReducao,
                ])
                ->log('Nota ajustada no conselho');

            // Disparar evento para recalcular MT automaticamente
            NotaAlterada::dispatch($notaPos, $user->id);

            DB::commit();

            return [
                'nota_pos' => $notaPos,
                'ajuste' => $ajuste,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Concluir conselho (em_curso → concluido)
     */
    public function concluirConselho(
        ConselhoTrimestre $conselho,
        User $user,
        ?string $observacoes = null
    ): ConselhoTrimestre {
        if (Configuracao::isModoConsultaById($conselho->ano_lectivo_id, $user)) {
            throw new \Exception('Ano lectivo em modo consulta — não é possível concluir conselhos.');
        }

        if (!$conselho->podeConcluir()) {
            throw new \Exception('O conselho não pode ser concluído no estado atual.');
        }

        $conselho->update([
            'estado' => ConselhoTrimestre::ESTADO_CONCLUIDO,
            'data_conclusao' => now(),
            'concluido_por' => $user->id,
            'observacoes' => $observacoes,
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($conselho)
            ->withProperties([
                'turma_id' => $conselho->turma_id,
                'trimestre' => $conselho->trimestre,
                'total_ajustes' => $conselho->ajustes()->count(),
            ])
            ->log('Conselho de classe concluído');

        return $conselho->fresh();
    }

    /**
     * Colunas de avaliação ajustáveis no conselho (por trimestre/regime).
     *
     * @return array<int, array{key: string, header: string, tipo: string, substitui_pt?: bool}>
     */
    public function colunasAvaliacaoConselho(int $turmaId, int $trimestre): array
    {
        $turma = \App\Models\Turma::with('curso')->findOrFail($turmaId);
        $regime = $turma->curso->regime();
        $sufixo = $trimestre;

        if ($trimestre === 3 && ! $regime->usaProvaTrimestral(3)) {
            return [
                ['key' => 'mac', 'header' => 'MAC3', 'tipo' => Nota::TIPO_MAC],
                ['key' => 'pp', 'header' => 'PP3', 'tipo' => Nota::TIPO_PP],
                ['key' => 'pg', 'header' => 'PG', 'tipo' => Nota::TIPO_PG, 'substitui_pt' => true],
            ];
        }

        return [
            ['key' => 'mac', 'header' => "MAC{$sufixo}", 'tipo' => Nota::TIPO_MAC],
            ['key' => 'pp', 'header' => "PP{$sufixo}", 'tipo' => Nota::TIPO_PP],
            ['key' => 'pt', 'header' => "PT{$sufixo}", 'tipo' => Nota::TIPO_PT],
        ];
    }

    /**
     * Obter todas as notas para o conselho (apenas pré-conselho)
     */
    public function obterNotasParaConselho(
        int $turmaId,
        int $trimestre,
        ?string $anoLectivo = null
    ): Collection {
        $anoLectivo = $anoLectivo ?? Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $colunas = $this->colunasAvaliacaoConselho($turmaId, $trimestre);
        $tiposTrimestre = collect($colunas)
            ->reject(fn ($c) => ($c['tipo'] ?? '') === Nota::TIPO_PG)
            ->pluck('tipo')
            ->all();

        $notas = Nota::with(['aluno.turma', 'disciplina', 'notaAjustada.ajusteConselhoComoPos'])
            ->where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('estado_conselho', Nota::ESTADO_PRE_CONSELHO)
            ->whereIn('tipo', $tiposTrimestre)
            ->orderBy('aluno_id')
            ->orderBy('disciplina_id')
            ->orderBy('tipo')
            ->get();

        $usaPg = collect($colunas)->contains(fn ($c) => ($c['tipo'] ?? '') === Nota::TIPO_PG);
        if ($usaPg) {
            $notasPg = Nota::with(['aluno.turma', 'disciplina', 'notaAjustada.ajusteConselhoComoPos'])
                ->where('turma_id', $turmaId)
                ->whereNull('trimestre')
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('estado_conselho', Nota::ESTADO_PRE_CONSELHO)
                ->where('tipo', Nota::TIPO_PG)
                ->orderBy('aluno_id')
                ->orderBy('disciplina_id')
                ->get();

            $notas = $notas->merge($notasPg);
        }

        return $notas;
    }

    /**
     * Obter estatísticas do conselho
     */
    public function obterEstatisticasConselho(ConselhoTrimestre $conselho): array
    {
        $ajustes = $conselho->ajustes()->with('notaPre.aluno', 'notaPre.disciplina')->get();

        return [
            'total_ajustes' => $ajustes->count(),
            'aumentos' => $ajustes->filter(fn($a) => $a->foiAumentada())->count(),
            'diminuicoes' => $ajustes->filter(fn ($a) => $a->isCorrecaoExcepcional())->count(),
            'diferenca_media' => $ajustes->avg(fn($a) => $a->getDiferenca()),
            'alunos_afetados' => $ajustes->pluck('notaPre.aluno_id')->unique()->count(),
            'disciplinas_afetadas' => $ajustes->pluck('notaPre.disciplina_id')->unique()->count(),
        ];
    }

    /**
     * Reverter um ajuste específico (apenas se conselho ainda em_curso)
     */
    public function reverterAjuste(
        AjusteConselho $ajuste,
        User $user
    ): void {
        $conselho = $ajuste->conselho;

        if (Configuracao::isModoConsultaById($conselho->ano_lectivo_id, $user)) {
            throw new \Exception('Ano lectivo em modo consulta — não é possível reverter ajustes.');
        }

        if (!$conselho->isEmCurso()) {
            throw new \Exception('Não é possível reverter ajustes de um conselho concluído.');
        }

        DB::beginTransaction();
        try {
            $notaPos = $ajuste->notaPos;
            $notaPre = $ajuste->notaPre;

            // Deletar nota pós-conselho
            $notaPos->delete();

            // Deletar registro de ajuste
            $ajuste->delete();

            // Recalcular MT usando notas pré-conselho
            $this->pautaService->calcularEGuardarMediaTrimestral(
                $notaPre->aluno_id,
                $notaPre->disciplina_id,
                $notaPre->turma_id,
                $notaPre->trimestre,
                $user->id,
                $notaPre->ano_lectivo
            );

            activity()
                ->causedBy($user)
                ->withProperties([
                    'conselho_id' => $conselho->id,
                    'aluno_id' => $notaPre->aluno_id,
                    'disciplina_id' => $notaPre->disciplina_id,
                ])
                ->log('Ajuste de conselho revertido');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
