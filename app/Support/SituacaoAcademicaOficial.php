<?php

namespace App\Support;

use App\Models\Aluno;

/**
 * Mapeamento entre resultado de transição (DE 424/25) e situacao_academica do aluno.
 */
class SituacaoAcademicaOficial
{
    /**
     * @param  array<string, mixed>  $analise  Saída de RecuperacaoTransicaoService::analisarAluno / resumoPorTurma
     * @return string|null  Constante Aluno::SITUACAO_* ou null para manter situação actual (pauta incompleta)
     */
    public static function situacaoAcademicaDeAnalise(array $analise, Aluno $aluno): ?string
    {
        if (($analise['situacao'] ?? '') === 'incompleta' || trim((string) ($analise['resultado'] ?? '')) === '') {
            return null;
        }

        $chave = self::resultadoTransicaoParaChave(trim((string) $analise['resultado']), $aluno);

        return self::chaveParaSituacaoAcademica($chave);
    }

    /**
     * Para transição de alunos (chaves internas: aprovado, reprovado, pendente, desistente).
     */
    public static function resultadoTransicaoParaChave(string $resultado, Aluno $aluno): string
    {
        if ($aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE || $resultado === 'DESISTENTE') {
            return 'desistente';
        }

        if ($resultado === PautaResultadoTransicao::RESULTADO_N_TRANSITA
            || $resultado === PautaResultadoTransicao::RESULTADO_N_APTO
            || str_starts_with($resultado, 'N/')) {
            return 'reprovado';
        }

        if ($resultado === PautaResultadoTransicao::RESULTADO_APTO
            || $resultado === PautaResultadoTransicao::RESULTADO_TRANSITA
            || PautaResultadoTransicao::ehTransitaComDeficiencia($resultado)) {
            return 'aprovado';
        }

        if (PautaResultadoTransicao::ehRecurso12($resultado)
            || PautaResultadoTransicao::ehRecuperacao($resultado)) {
            return 'pendente';
        }

        return 'pendente';
    }

    /**
     * @return string|null  null quando pendente / sem decisão
     */
    public static function chaveParaSituacaoAcademica(string $chave): ?string
    {
        return match ($chave) {
            'aprovado' => Aluno::SITUACAO_APROVADO,
            'reprovado' => Aluno::SITUACAO_REPROVADO,
            'desistente' => Aluno::SITUACAO_DESISTENTE,
            default => null,
        };
    }
}
