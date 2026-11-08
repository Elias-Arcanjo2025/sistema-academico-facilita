<?php

namespace Tests\Unit;

use App\Services\RegimePauta\RegimePolitecnico;
use PHPUnit\Framework\TestCase;

class RegimePolitecnicoExpressoesExcelTest extends TestCase
{
    public function test_formulas_calculam_com_celulas_vazias_como_zero(): void
    {
        $regime = new RegimePolitecnico;
        $get = fn (string $k) => strtoupper($k).'15';

        $expr = $regime->expressoesExcel($get, 10);

        $this->assertStringContainsString('IF(ISNUMBER(MAC15),MAC15,0)', $expr['mt_t3']);
        $this->assertStringNotContainsString('COUNT(', $expr['mt_t3']);
        $this->assertStringContainsString('IF(ISNUMBER(MFT215),MFT215,0)', $expr['cf']);
        $this->assertStringContainsString('IF(ISNUMBER(MT115),MT115,0)', $expr['mft2']);
        $this->assertStringContainsString('0.6*', $expr['ca']);
        $this->assertStringNotContainsString('=""', $expr['cf']);
    }

    public function test_cfd_12_media_tres_cas_com_parenteses_balanceados(): void
    {
        $regime = new RegimePolitecnico;
        $get = fn (string $k) => match ($k) {
            'ca' => 'AE15',
            'ca10' => 'W15',
            'ca11' => 'X15',
            default => strtoupper($k).'15',
        };

        $expr = $regime->expressoesExcel($get, 12);
        $formula = $expr['cfd_12'];

        $this->assertSame(0, substr_count($formula, '(') - substr_count($formula, ')'));
        $this->assertStringContainsString('ROUND((', $formula);
        $this->assertStringContainsString('/3,0)', $formula);
        $this->assertStringContainsString('W15=""', $formula);
        $this->assertStringContainsString('X15=""', $formula);
        $this->assertStringNotContainsString('AND(W15="",X15="")', $formula);
    }

    public function test_cfd_12_de_11_media_duas_cas(): void
    {
        $regime = new RegimePolitecnico;
        $get = fn (string $k) => match ($k) {
            'ca' => 'BE15',
            'ca11' => 'AX15',
            default => strtoupper($k).'15',
        };

        $expr = $regime->expressoesExcel($get, 12);
        $formula = $expr['cfd_12_de_11'];

        $this->assertSame(0, substr_count($formula, '(') - substr_count($formula, ')'));
        $this->assertStringContainsString('/2,0)', $formula);
        $this->assertStringContainsString('AX15', $formula);
        $this->assertStringContainsString('BE15', $formula);
        $this->assertStringNotContainsString('CA10', $formula);
    }

    public function test_cfd_10_igual_a_ca(): void
    {
        $regime = new RegimePolitecnico;
        $get = fn (string $k) => $k === 'ca' ? 'AU15' : strtoupper($k).'15';

        $expr = $regime->expressoesExcel($get, 12);

        $this->assertSame('=AU15', $expr['cfd_10']);
    }
}
