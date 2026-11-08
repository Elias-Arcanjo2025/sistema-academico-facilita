<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\ConselhoTrimestre;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConselhoService;
use App\Support\ConselhoAjusteRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ConselhoAjusteRulesTest extends TestCase
{
    public function test_permite_aumento_de_nota(): void
    {
        ConselhoAjusteRules::validarAjuste(10.0, 12.0, null, 'Evolução positiva do aluno no trimestre');

        $this->assertFalse(ConselhoAjusteRules::isReducao(10.0, 12.0));
    }

    public function test_rejeita_mantencao_sem_ajuste(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('igual ao valor actual');

        ConselhoAjusteRules::validarAjuste(11.0, 11.0, null, 'Sem alteração pretendida');
    }

    public function test_rejeita_reducao_sem_motivo_excepcional(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('punitiva ou arbitrária');

        ConselhoAjusteRules::validarAjuste(12.0, 10.0, null, 'Queremos baixar a nota');
    }

    public function test_permite_reducao_com_motivo_e_justificacao_detalhada(): void
    {
        ConselhoAjusteRules::validarAjuste(
            14.0,
            12.0,
            ConselhoAjusteRules::MOTIVO_ERRO_CALCULO,
            'Erro material comprovado na soma das avaliações contínuas — ficha corrigida em reunião.',
        );

        $this->assertTrue(ConselhoAjusteRules::isReducao(14.0, 12.0));
    }

    public function test_reducao_exige_justificacao_mais_longa(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('20 caracteres');

        ConselhoAjusteRules::validarAjuste(
            14.0,
            12.0,
            ConselhoAjusteRules::MOTIVO_FRAUDE,
            'Fraude',
        );
    }
}
