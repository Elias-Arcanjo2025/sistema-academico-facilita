<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BoletimLayoutService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoletimLayoutSecretariaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_secretaria_guarda_modelo_e_cores_do_boletim(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('secretaria');

        $this->actingAs($user)
            ->get(route('secretaria.boletim.configurar'))
            ->assertOk()
            ->assertSee('Escolher modelo', false)
            ->assertSee('Moderno', false);

        $this->actingAs($user)
            ->post(route('secretaria.boletim.guardar'), [
                'modelo' => BoletimLayoutService::MODELO_MODERNO,
                'cor_primaria' => '#0d9488',
                'cor_secundaria' => '#134e4a',
                'cor_tabela' => '#99f6e4',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $cfg = app(BoletimLayoutService::class)->getConfig();
        $this->assertSame(BoletimLayoutService::MODELO_MODERNO, $cfg['modelo']);
        $this->assertSame('#0d9488', $cfg['cor_primaria']);
        $this->assertSame('#134e4a', $cfg['cor_secundaria']);
        $this->assertSame('#99f6e4', $cfg['cor_tabela']);

        $viewData = app(BoletimLayoutService::class)->dadosParaView();
        $this->assertSame('moderno', $viewData['boletim_modelo']);
        $this->assertSame('#0d9488', $viewData['pdfConfig']['colors']['primary']);
    }

    public function test_encarregado_nao_acede_config_boletim(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole('encarregado');

        $this->actingAs($user)
            ->get(route('secretaria.boletim.configurar'))
            ->assertForbidden();
    }
}
