<?php

namespace Tests\Unit;

use App\Support\PublicStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_usa_asset_quando_ficheiro_existe_em_public_storage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pagina-publica/carousel/test.jpg', 'conteudo');

        $publicDir = public_path('storage/pagina-publica/carousel');
        if (! is_dir($publicDir)) {
            mkdir($publicDir, 0777, true);
        }
        file_put_contents(public_path('storage/pagina-publica/carousel/test.jpg'), 'conteudo');

        try {
            $url = PublicStorage::url('pagina-publica/carousel/test.jpg');

            $this->assertSame('/storage/pagina-publica/carousel/test.jpg', $url);
        } finally {
            @unlink(public_path('storage/pagina-publica/carousel/test.jpg'));
            @rmdir(public_path('storage/pagina-publica/carousel'));
        }
    }

    public function test_url_usa_rota_fallback_quando_symlink_indisponivel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pagina-publica/carousel/fallback.jpg', 'conteudo');

        $url = PublicStorage::url('pagina-publica/carousel/fallback.jpg');

        $this->assertNotNull($url);
        $this->assertSame('/arquivos/pagina-publica/carousel/fallback.jpg', $url);
    }

    public function test_url_retorna_null_para_caminho_inexistente(): void
    {
        Storage::fake('public');

        $this->assertNull(PublicStorage::url('inexistente.jpg'));
        $this->assertNull(PublicStorage::url(''));
    }

    public function test_url_retorna_null_para_pasta_nao_permitida(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('backups/segredo.zip', 'x');

        $this->assertNull(PublicStorage::url('backups/segredo.zip'));
        $this->assertFalse(PublicStorage::isPathAllowed('backups/segredo.zip'));
    }
}
