<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Regras do Conselho de Notas conforme prática angolana (MED):
 * o conselho beneficia ou mantém a nota — reduções só em casos excepcionais documentados.
 */
class ConselhoAjusteRules
{
    public const MOTIVO_ERRO_CALCULO = 'erro_calculo';

    public const MOTIVO_FRAUDE = 'fraude_academica';

    public const MOTIVO_DISCIPLINAR = 'processo_disciplinar';

    /** @return array<string, string> */
    public static function motivosReducaoPermitidos(): array
    {
        return [
            self::MOTIVO_ERRO_CALCULO => 'Erro material de cálculo comprovado',
            self::MOTIVO_FRAUDE => 'Fraude académica (ex.: cábula) detectada antes da homologação',
            self::MOTIVO_DISCIPLINAR => 'Processo disciplinar formal concluído',
        ];
    }

    public static function isReducao(float $valorAnterior, float $novoValor): bool
    {
        return round($novoValor, 2) < round($valorAnterior, 2);
    }

    public static function isManutencao(float $valorAnterior, float $novoValor): bool
    {
        return round($novoValor, 2) === round($valorAnterior, 2);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validarAjuste(
        float $valorAnterior,
        float $novoValor,
        ?string $motivoReducao = null,
        ?string $justificacao = null,
    ): void {
        if ($novoValor < 0 || $novoValor > 20) {
            throw new InvalidArgumentException('A nota deve estar entre 0 e 20.');
        }

        if (self::isManutencao($valorAnterior, $novoValor)) {
            throw new InvalidArgumentException(
                'O novo valor é igual ao valor actual. O conselho só regista ajustes que beneficiem o estudante (aumento) ou correcções excepcionais.'
            );
        }

        if (! self::isReducao($valorAnterior, $novoValor)) {
            return;
        }

        if ($motivoReducao === null || $motivoReducao === '') {
            throw new InvalidArgumentException(
                'No Conselho de Notas não é permitido reduzir a nota de forma punitiva ou arbitrária. '
                .'Só pode diminuir em casos excepcionais: erro material de cálculo, fraude académica comprovada ou processo disciplinar formal.'
            );
        }

        if (! array_key_exists($motivoReducao, self::motivosReducaoPermitidos())) {
            throw new InvalidArgumentException('O motivo de redução seleccionado é inválido.');
        }

        $justificacao = trim((string) $justificacao);
        if (strlen($justificacao) < 20) {
            throw new InvalidArgumentException(
                'Reduções excepcionais exigem justificação detalhada com pelo menos 20 caracteres, incluindo referência ao erro, fraude ou processo disciplinar.'
            );
        }
    }

    public static function rotuloMotivoReducao(?string $motivo): ?string
    {
        if ($motivo === null || $motivo === '') {
            return null;
        }

        return self::motivosReducaoPermitidos()[$motivo] ?? $motivo;
    }
}
