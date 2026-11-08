<?php

namespace App\Services;

class CartaoEstudanteService
{
    public const CHAVES = [
        'cartao_titulo' => 'Cartão de Estudante',
        'cartao_cor_primaria' => '#0f766e',
        'cartao_cor_secundaria' => '#134e4a',
        'cartao_cor_texto' => '#ffffff',
        'cartao_mostrar_foto' => '1',
        'cartao_mostrar_processo' => '1',
        'cartao_mostrar_bi' => '1',
        'cartao_mostrar_turma' => '1',
        'cartao_mostrar_curso' => '1',
        'cartao_mostrar_ano' => '1',
        'cartao_mostrar_validade' => '1',
        'cartao_validade_texto' => 'Ano lectivo em curso',
        'cartao_verso_texto' => 'Este cartão é propriedade da instituição. Em caso de perda, contacte a secretaria.',
        'cartao_mostrar_verso' => '1',
        'cartao_mostrar_logo' => '1',
        'cartao_orientacao' => 'landscape',
    ];

    public function __construct(
        protected ConfiguracaoService $configuracao,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $cfg = [];
        foreach (self::CHAVES as $chave => $default) {
            $cfg[str_replace('cartao_', '', $chave)] = $this->configuracao->get($chave, $default);
        }

        foreach ([
            'mostrar_foto', 'mostrar_processo', 'mostrar_bi', 'mostrar_turma',
            'mostrar_curso', 'mostrar_ano', 'mostrar_validade', 'mostrar_verso', 'mostrar_logo',
        ] as $boolKey) {
            $cfg[$boolKey] = filter_var($cfg[$boolKey] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $cfg;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function guardar(array $dados): void
    {
        $map = [
            'titulo' => 'cartao_titulo',
            'cor_primaria' => 'cartao_cor_primaria',
            'cor_secundaria' => 'cartao_cor_secundaria',
            'cor_texto' => 'cartao_cor_texto',
            'mostrar_foto' => 'cartao_mostrar_foto',
            'mostrar_processo' => 'cartao_mostrar_processo',
            'mostrar_bi' => 'cartao_mostrar_bi',
            'mostrar_turma' => 'cartao_mostrar_turma',
            'mostrar_curso' => 'cartao_mostrar_curso',
            'mostrar_ano' => 'cartao_mostrar_ano',
            'mostrar_validade' => 'cartao_mostrar_validade',
            'validade_texto' => 'cartao_validade_texto',
            'verso_texto' => 'cartao_verso_texto',
            'mostrar_verso' => 'cartao_mostrar_verso',
            'mostrar_logo' => 'cartao_mostrar_logo',
            'orientacao' => 'cartao_orientacao',
        ];

        foreach ($map as $campo => $chave) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }
            $valor = $dados[$campo];
            if (is_bool($valor)) {
                $valor = $valor ? '1' : '0';
            }
            $this->configuracao->set($chave, (string) $valor, 'Personalização do cartão de estudante');
        }
    }
}
