<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Support\EnsinoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsinoPerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_ii_ciclo_classes(): void
    {
        config(['ensino.perfis' => ['ii_ciclo']]);

        $this->assertSame([10, 11, 12, 13], EnsinoPerfil::classesPermitidas());
        $this->assertTrue(EnsinoPerfil::permiteClasse(10));
        $this->assertFalse(EnsinoPerfil::permiteClasse(6));
        $this->assertSame('env', EnsinoPerfil::fonte());
    }

    public function test_combinacao_primario_e_ciclos(): void
    {
        config(['ensino.perfis' => ['primario', 'i_ciclo', 'ii_ciclo']]);

        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
            EnsinoPerfil::classesPermitidas()
        );
    }

    public function test_perfil_invalido_cai_em_ii_ciclo(): void
    {
        config(['ensino.perfis' => ['desconhecido']]);

        $this->assertSame([EnsinoPerfil::II_CICLO], EnsinoPerfil::perfisActivos());
    }

    public function test_bd_tem_prioridade_sobre_env(): void
    {
        config(['ensino.perfis' => ['ii_ciclo']]);

        Configuracao::set('ensino_perfis', 'primario,i_ciclo');

        $this->assertSame(['primario', 'i_ciclo'], EnsinoPerfil::perfisActivos());
        $this->assertSame('bd', EnsinoPerfil::fonte());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], EnsinoPerfil::classesPermitidas());
    }
}
