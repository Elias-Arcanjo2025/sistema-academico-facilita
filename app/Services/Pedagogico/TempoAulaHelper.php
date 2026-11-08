<?php

namespace App\Services\Pedagogico;

class TempoAulaHelper
{
    public static function minutosPorTempo(): int
    {
        return (int) config('pedagogico.minutos_por_tempo', 45);
    }

    public static function duracaoMinutos(int $tempos): int
    {
        return max(1, $tempos) * self::minutosPorTempo();
    }

    public static function calcularHoraFim(string $horaInicio, int $tempos): string
    {
        $minutos = self::duracaoMinutos($tempos);
        $partes = explode(':', substr($horaInicio, 0, 5));
        $h = (int) ($partes[0] ?? 0);
        $m = (int) ($partes[1] ?? 0);
        $total = $h * 60 + $m + $minutos;

        return sprintf('%02d:%02d:00', intdiv($total, 60) % 24, $total % 60);
    }

    /**
     * Escala proporcional ao número de tempos (45 min cada) e temas do período.
     *
     * @return array{
     *     exercicios: int,
     *     tarefas: int,
     *     paragrafos: int,
     *     palavras_ditar: int,
     *     conceitos: int,
     *     blocos_aula: int,
     *     minutos_por_bloco: int
     * }
     */
    public static function escalaConteudo(int $tempos, int $numTemas = 1): array
    {
        $temposMax = (int) config('pedagogico.tempos_max', 8);
        $tempos = max(1, min($temposMax, $tempos));
        $numTemas = max(1, $numTemas);

        $fatorTemas = 1 + (max(0, $numTemas - 1) * 0.35);

        $paragrafos = (int) round(($tempos * 1.5 + max(0, $numTemas - 1)) * $fatorTemas);
        $exercicios = (int) round(($tempos * 2.5 + max(0, $numTemas - 1) * 1.5) * $fatorTemas);
        $tarefas = (int) round(max(1, $tempos * 1.0 + max(0, $numTemas - 1) * 0.5) * min($fatorTemas, 1.5));
        $palavrasDitar = (int) round(
            ($tempos * self::palavrasPorTempo() + max(0, $numTemas - 1) * 150) * $fatorTemas
        );

        return [
            'exercicios' => max(3, min(18, $exercicios)),
            'tarefas' => max(1, min(12, $tarefas)),
            'paragrafos' => max(2, min(14, $paragrafos)),
            'palavras_ditar' => max(280, min(8000, $palavrasDitar)),
            'conceitos' => max(4, min(16, (int) round(($tempos * 2 + max(0, $numTemas - 1) * 2) * min($fatorTemas, 1.4)))),
            'blocos_aula' => $tempos,
            'minutos_por_bloco' => self::minutosPorTempo(),
        ];
    }

    public static function palavrasPorTempo(): int
    {
        return (int) config('pedagogico.palavras_por_tempo', 380);
    }

    public static function maxTokensGeracao(int $tempos, int $numTemas = 1): int
    {
        $base = (int) config('pedagogico.plano_aula_max_tokens', 2048);
        $tempos = max(1, min((int) config('pedagogico.tempos_max', 8), $tempos));
        $numTemas = max(1, $numTemas);

        $extra = ($tempos * 650) + (max(0, $numTemas - 1) * 450);

        return min((int) config('pedagogico.plano_aula_max_tokens_cap', 12288), $base + $extra);
    }

    /**
     * Referência rápida para UI (1T vs 6T).
     *
     * @return array<int, array{tempos: int, minutos: int, palavras: int, exercicios: int}>
     */
    public static function tabelaReferencia(): array
    {
        return collect(range(1, (int) config('pedagogico.tempos_max', 8)))
            ->mapWithKeys(function (int $t) {
                $e = self::escalaConteudo($t, 1);

                return [$t => [
                    'tempos' => $t,
                    'minutos' => self::duracaoMinutos($t),
                    'palavras' => $e['palavras_ditar'],
                    'exercicios' => $e['exercicios'],
                ]];
            })
            ->all();
    }
}
