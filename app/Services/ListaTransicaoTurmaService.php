<?php

namespace App\Services;

use App\Models\Turma;
use App\Support\PautaResultadoTransicao;

/**
 * Agrupa alunos da pauta T3 por resultado de transição (DE 424/25), com disciplinas negativas.
 */
class ListaTransicaoTurmaService
{
    /** @deprecated Use gruposOrdem($classeNumero) */
    public const GRUPOS_ORDEM = [
        'TRANSITA',
        'TRANSITA/C DEF',
        'RECUPERAÇÃO',
        'N/TRANSITA',
        'Pendente',
        'DESISTENTE',
    ];

    /**
     * @return array<int, string>
     */
    public static function gruposOrdem(int $classeNumero = 10): array
    {
        if ($classeNumero === 12) {
            return [
                PautaResultadoTransicao::RESULTADO_APTO,
                PautaResultadoTransicao::RESULTADO_RECURSO,
                PautaResultadoTransicao::RESULTADO_N_APTO,
                'Pendente',
                'DESISTENTE',
            ];
        }

        return [
            PautaResultadoTransicao::RESULTADO_TRANSITA,
            PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA,
            PautaResultadoTransicao::RESULTADO_RECUPERACAO,
            PautaResultadoTransicao::RESULTADO_N_TRANSITA,
            'Pendente',
            'DESISTENTE',
        ];
    }

    public function __construct(
        protected PautaService $pautaService,
        protected RecuperacaoTransicaoService $transicaoService,
    ) {}

    /**
     * @return array{
     *   turma: Turma,
     *   trimestre: int,
     *   ano_lectivo: string,
     *   classe_numero: int,
     *   grupos: array<string, array<int, array<string, mixed>>>,
     *   resumo: array<string, int>,
     * }
     */
    public function gerar(int $turmaId, int $trimestre, ?string $anoLectivo = null): array
    {
        $pauta = $this->pautaService->gerarPautaTrimestralCompleta(
            $turmaId,
            $trimestre,
            $anoLectivo,
            \App\Support\PautaMomentoConselho::POS,
        );
        $turma = $pauta['turma'];
        $classeNumero = (int) ($pauta['classe_numero'] ?? 10);

        if ($trimestre !== 3 || ! in_array($classeNumero, [10, 11, 12], true)) {
            throw new \InvalidArgumentException('Listas de transição só estão disponíveis na pauta T3 (10.ª–12.ª).');
        }

        $analises = $this->transicaoService
            ->resumoPorTurma($turma, $pauta['ano_lectivo'] ?? $anoLectivo)
            ->keyBy('aluno_id');

        $ordem = self::gruposOrdem($classeNumero);
        $grupos = array_fill_keys($ordem, []);

        foreach ($pauta['alunos'] as $aluno) {
            $alunoId = (int) ($aluno['aluno']->id ?? 0);
            $analise = $analises->get($alunoId, []);
            $linha = $this->montarLinha($aluno, $analise);
            $chave = $this->chaveGrupo($aluno, $analise, $classeNumero);
            if (! array_key_exists($chave, $grupos)) {
                $grupos[$chave] = [];
            }
            $grupos[$chave][] = $linha;
        }

        foreach ($grupos as $chave => $linhas) {
            usort($linhas, fn ($a, $b) => ($a['numero_ordem'] ?? 0) <=> ($b['numero_ordem'] ?? 0));
            $grupos[$chave] = $linhas;
        }

        $resumo = collect($grupos)->map(fn (array $linhas) => count($linhas))->all();

        return [
            'turma' => $turma,
            'trimestre' => $trimestre,
            'ano_lectivo' => $pauta['ano_lectivo'] ?? $anoLectivo ?? '',
            'classe_numero' => $classeNumero,
            'grupos' => $grupos,
            'resumo' => $resumo,
        ];
    }

    /**
     * @param  array<string, mixed>  $aluno
     * @param  array<string, mixed>  $analise
     * @return array<string, mixed>
     */
    protected function montarLinha(array $aluno, array $analise): array
    {
        $continuas = $analise['continuas_deficientes'] ?? [];
        $todasNeg = $analise['disciplinas_recuperacao'] ?? [];
        $contIds = collect($continuas)->pluck('disciplina_id')->map(fn ($id) => (int) $id)->all();

        $terminais = collect($todasNeg)
            ->filter(fn ($t) => ! in_array((int) ($t['disciplina_id'] ?? 0), $contIds, true))
            ->values()
            ->all();

        $fmt = fn (array $items) => collect($items)
            ->map(fn ($t) => trim(($t['disciplina_nome'] ?? '').' ('.(int) round((float) ($t['ca'] ?? 0)).')'))
            ->filter()
            ->implode(', ');

        $resultado = ! empty($aluno['desistente'])
            ? 'DESISTENTE'
            : trim((string) ($analise['resultado'] ?? $aluno['resultado'] ?? ''));

        return [
            'numero_ordem' => $aluno['numero_ordem'] ?? null,
            'numero_processo' => $aluno['numero_processo'] ?? '',
            'nome' => $aluno['nome'] ?? '',
            'resultado' => $resultado !== '' ? $resultado : 'Pendente',
            'observacao' => ! empty($aluno['desistente'])
                ? 'DESISTENTE'
                : ($analise['observacao'] ?? $aluno['observacao'] ?? ''),
            'deficiencias' => $fmt($continuas),
            'recuperacao' => $fmt($terminais),
            'todas_negativas' => $fmt($todasNeg),
            'total_negativas' => count($todasNeg),
        ];
    }

    /**
     * @param  array<string, mixed>  $aluno
     * @param  array<string, mixed>  $analise
     */
    protected function chaveGrupo(array $aluno, array $analise, int $classeNumero = 10): string
    {
        if (! empty($aluno['desistente'])) {
            return 'DESISTENTE';
        }

        $resultado = trim((string) ($analise['resultado'] ?? $aluno['resultado'] ?? ''));
        if ($resultado === '') {
            return 'Pendente';
        }

        $ordem = self::gruposOrdem($classeNumero);

        if ($classeNumero === 12) {
            if (in_array($resultado, $ordem, true)) {
                return $resultado;
            }
            if (PautaResultadoTransicao::ehNApto($resultado)) {
                return PautaResultadoTransicao::RESULTADO_N_APTO;
            }

            return $resultado;
        }

        if (PautaResultadoTransicao::ehTransitaComDeficiencia($resultado)) {
            return PautaResultadoTransicao::RESULTADO_TRANSITA_COM_DEFICIENCIA;
        }

        if (PautaResultadoTransicao::ehRecuperacao($resultado)) {
            return PautaResultadoTransicao::RESULTADO_RECUPERACAO;
        }

        if (in_array($resultado, $ordem, true)) {
            return $resultado;
        }

        if (str_starts_with($resultado, 'N/')) {
            return 'N/TRANSITA';
        }

        return $resultado;
    }

    /**
     * @return array{dados: array<string, mixed>, gruposExibir: array<string, array<int, array<string, mixed>>>, filtro: ?string}
     */
    public function prepararExportacao(int $turmaId, int $trimestre, ?string $anoLectivo, ?string $resultado = null): array
    {
        $dados = $this->gerar($turmaId, $trimestre, $anoLectivo);
        $ordem = self::gruposOrdem((int) ($dados['classe_numero'] ?? 10));

        if ($resultado !== null && $resultado !== '') {
            if (! in_array($resultado, $ordem, true)) {
                throw new \InvalidArgumentException('Resultado inválido para exportação.');
            }

            return [
                'dados' => $dados,
                'gruposExibir' => [$resultado => $dados['grupos'][$resultado] ?? []],
                'filtro' => $resultado,
            ];
        }

        $gruposExibir = [];
        foreach ($ordem as $grupo) {
            if (! empty($dados['grupos'][$grupo])) {
                $gruposExibir[$grupo] = $dados['grupos'][$grupo];
            }
        }

        return [
            'dados' => $dados,
            'gruposExibir' => $gruposExibir,
            'filtro' => null,
        ];
    }
}
