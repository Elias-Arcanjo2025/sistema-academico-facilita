<?php

namespace Tests\Unit;

use App\Support\AlunoImportTempFile;
use Tests\TestCase;

class AlunoImportTempFileTest extends TestCase
{
    public function test_rejeita_path_fora_do_diretorio_temporario(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        AlunoImportTempFile::validateOwnedPath('C:\\Windows\\System32\\drivers\\etc\\hosts', 1);
    }

    public function test_rejeita_ficheiro_com_nome_invalido(): void
    {
        $dir = AlunoImportTempFile::tempDir();
        $fakePath = $dir.DIRECTORY_SEPARATOR.'import-temp-999-evil.xlsx';
        touch($fakePath);

        try {
            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            AlunoImportTempFile::validateOwnedPath($fakePath, 1);
        } finally {
            @unlink($fakePath);
        }
    }

    public function test_aceita_ficheiro_valido_do_utilizador(): void
    {
        $userId = 42;
        $dir = AlunoImportTempFile::tempDir();
        $validName = "import-temp-{$userId}-1234567890-abc12345.xlsx";
        $path = $dir.DIRECTORY_SEPARATOR.$validName;
        touch($path);

        try {
            $resolved = AlunoImportTempFile::validateOwnedPath($path, $userId);
            $this->assertSame(realpath($path), $resolved);
        } finally {
            @unlink($path);
        }
    }
}
