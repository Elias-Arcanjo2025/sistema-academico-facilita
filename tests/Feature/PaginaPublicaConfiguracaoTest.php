<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaginaPublicaConfiguracaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin_full']);
        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->assignRole('admin_full');
    }

    public function test_admin_pode_guardar_cores_na_area_dedicada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('configuracoes.pagina-publica.identidade.update'), [
                'pagina_publica_cor_primaria' => '#123456',
                'pagina_publica_cor_primaria_escura' => '#654321',
                'pagina_publica_cor_secundaria' => '#abcdef',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('#123456', Configuracao::get('pagina_publica_cor_primaria'));
    }

    public function test_pagina_publica_reflecte_configuracao(): void
    {
        Configuracao::set('pagina_publica_titulo', 'Instituto Técnico', 'test');
        Configuracao::set('pagina_publica_destaque', 'Formação de Qualidade', 'test');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Instituto Técnico', false)
            ->assertSee('Formação de Qualidade', false);
    }

    public function test_area_pagina_publica_tem_menu_lateral(): void
    {
        $this->actingAs($this->admin)
            ->get(route('configuracoes.pagina-publica.identidade'))
            ->assertOk()
            ->assertSee('Identidade visual')
            ->assertSee('Carousel (hero)');
    }

    public function test_carousel_requer_tres_imagens_quando_activo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('configuracoes.pagina-publica.carousel.update'), [
                'pagina_publica_carousel_activo' => '1',
                'pagina_publica_carousel_intervalo' => 5,
                'carousel_imagem_1' => UploadedFile::fake()->image('slide1.jpg', 800, 500),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
