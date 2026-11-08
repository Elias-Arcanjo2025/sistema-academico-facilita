<?php

namespace Tests\Unit;

use App\Support\ProvaQuestaoHelper;
use Tests\TestCase;

class ProvaQuestaoHelperTest extends TestCase
{
    public function test_ref_subitem_e_item_aninhado(): void
    {
        $questao = ['numero' => 2];

        $ref = ProvaQuestaoHelper::refSubitem($questao, ['letra' => 'a)'], 0);
        $this->assertSame('2a', $ref);

        $refAninhado = ProvaQuestaoHelper::refItemAninhado($ref, ['letra' => 'ii)'], 1);
        $this->assertSame('2aii', $refAninhado);
    }

    public function test_texto_respostas_aninhadas(): void
    {
        $texto = ProvaQuestaoHelper::textoRespostasAninhadas([
            ['letra' => 'i)', 'texto' => 'LAN', 'resposta_sugerida' => 'Rede local.'],
            ['letra' => 'ii)', 'texto' => 'WAN', 'resposta_sugerida' => 'Rede alargada.'],
        ]);

        $this->assertStringContainsString('i) LAN: Rede local.', $texto);
        $this->assertStringContainsString('ii) WAN: Rede alargada.', $texto);
    }

    public function test_formatar_resposta_chave_sem_json_bruto(): void
    {
        $texto = ProvaQuestaoHelper::formatarRespostaChave([
            'tipo' => 'dissertativa',
            'cotacao' => 4,
            'resposta' => null,
        ]);

        $this->assertStringContainsString('dissertativa', strtolower($texto));
        $this->assertStringNotContainsString('{', $texto);
    }
}
