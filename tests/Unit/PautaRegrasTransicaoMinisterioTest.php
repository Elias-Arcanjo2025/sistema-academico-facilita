<?php

namespace Tests\Unit;

use App\Support\PautaRegrasTransicaoMinisterio;
use App\Support\PautaResultadoTransicao;
use PHPUnit\Framework\TestCase;

class PautaRegrasTransicaoMinisterioTest extends TestCase
{
    public function test_regra_dos_7_bloqueia_media_cfd(): void
    {
        $this->assertFalse(PautaRegrasTransicaoMinisterio::podeCalcularCfdPorMedia(6.0));
        $this->assertTrue(PautaRegrasTransicaoMinisterio::podeCalcularCfdPorMedia(7.0));
        $this->assertNull(PautaRegrasTransicaoMinisterio::calcularCfdPorMedia(6.0, 18.0));
        $this->assertEquals(13, PautaRegrasTransicaoMinisterio::calcularCfdPorMedia(8.0, 18.0));
        $this->assertEquals(12, PautaRegrasTransicaoMinisterio::calcularCfdPorMedia(12.0, 10.0, 14.0));
        $this->assertNull(PautaRegrasTransicaoMinisterio::calcularCfdPorMedia(12.0, null, 14.0));
    }

    public function test_limite_cinco_exames_terminais(): void
    {
        $terminais = array_map(fn ($i) => ['disciplina_id' => $i], range(1, 7));

        $this->assertCount(5, PautaRegrasTransicaoMinisterio::limitarExamesTerminais($terminais, 11));
        $this->assertCount(7, PautaRegrasTransicaoMinisterio::limitarExamesTerminais($terminais, 10));
    }

    public function test_terminal_com_ca_baixa_usa_ca_quando_sem_cfd(): void
    {
        $nota = PautaResultadoTransicao::notaOficialDisciplina(
            ['ca' => 5, 'cfd' => null, 'tem_coluna_cfd' => true],
            ['classe_fim' => 11, 'terminal' => true, 'usa_cfd' => true],
            11,
        );

        $this->assertSame(5.0, $nota);
    }
}
