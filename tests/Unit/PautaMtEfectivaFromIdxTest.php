<?php

namespace Tests\Unit;

use App\Models\Nota;
use App\Services\PautaService;
use App\Services\RegimePauta\RegimePolitecnico;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PautaMtEfectivaFromIdxTest extends TestCase
{
    public function test_usa_mt_persistida_quando_existe(): void
    {
        $notasIdx = [
            1 => [
                10 => [
                    1 => [
                        Nota::TIPO_MT => 12.0,
                        Nota::TIPO_MAC => 10.0,
                    ],
                ],
            ],
        ];

        $this->assertSame(12.0, $this->resolverMt($notasIdx, 1, 10, 1));
    }

    public function test_calcula_mt_a_partir_de_mac_pp_pt(): void
    {
        $notasIdx = [
            1 => [
                10 => [
                    1 => [
                        Nota::TIPO_MAC => 10.0,
                        Nota::TIPO_PP => 14.0,
                        Nota::TIPO_PT => 10.0,
                    ],
                ],
            ],
        ];

        $this->assertSame(11.0, $this->resolverMt($notasIdx, 1, 10, 1));
    }

    public function test_retorna_null_sem_componentes(): void
    {
        $this->assertNull($this->resolverMt([], 1, 10, 1));
    }

    private function resolverMt(array $notasIdx, int $alunoId, int $disciplinaId, int $trimestre): ?float
    {
        $service = new PautaService();
        $method = new ReflectionMethod(PautaService::class, 'mtEfectivaFromIdx');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            $notasIdx,
            $alunoId,
            $disciplinaId,
            $trimestre,
            new RegimePolitecnico()
        );
    }
}
