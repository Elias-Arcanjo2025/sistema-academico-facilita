<?php

namespace Tests\Unit;

use App\Imports\AlunosImport;
use Tests\TestCase;

class AlunosImportNormalizacaoTest extends TestCase
{
    public function test_normaliza_telefones_numericos_do_excel_para_string(): void
    {
        $import = new AlunosImport();

        $data = $import->prepareForValidation([
            'pai_telefone' => 923456789,
            'mae_telefone' => 923456780.0,
        ], 1);

        $this->assertSame('923456789', $data['pai_telefone']);
        $this->assertSame('923456780', $data['mae_telefone']);
    }

    public function test_preserva_telefone_ja_formatado_como_string(): void
    {
        $import = new AlunosImport();

        $data = $import->prepareForValidation([
            'pai_telefone' => '0024423456789',
            'mae_telefone' => null,
        ], 1);

        $this->assertSame('0024423456789', $data['pai_telefone']);
        $this->assertNull($data['mae_telefone']);
    }
}
