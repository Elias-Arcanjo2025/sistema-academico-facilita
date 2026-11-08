<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\ConteudoMinimo;
use App\Models\CoordenadorDisciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class FaseEConteudosMinimosTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    private User $coord;

    private User $directorPed;

    private User $professor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'conteudos.ver', 'conteudos.gerir', 'conteudos.publicar',
            'pedagogico.ver', 'disciplinas.ver',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (['coordenador_disciplina', 'subdiretor_pedagogico', 'professor', 'admin_full'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('coordenador_disciplina')->syncPermissions([
            'conteudos.ver', 'conteudos.gerir', 'conteudos.publicar',
            'pedagogico.ver', 'disciplinas.ver',
        ]);
        Role::findByName('subdiretor_pedagogico')->syncPermissions([
            'conteudos.ver', 'conteudos.gerir', 'conteudos.publicar',
            'pedagogico.ver',
        ]);
        Role::findByName('professor')->syncPermissions([
            'conteudos.ver', 'pedagogico.ver',
        ]);
        Role::findByName('admin_full')->syncPermissions(Permission::all());

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('ano_lectivo_actual', TransicaoAnoScenarioBuilder::ANO_ORIGEM, 'test');

        $this->coord = User::factory()->create(['estado' => 'activo', 'name' => 'Coord Disc']);
        $this->coord->assignRole('coordenador_disciplina');
        $atribuicao = CoordenadorDisciplina::create([
            'user_id' => $this->coord->id,
            'disciplina_id' => $this->scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $this->scenario->anos->origem->id,
            'activo' => true,
        ]);
        $atribuicao->classes()->sync([$this->scenario->classes->c10->id]);

        $this->directorPed = User::factory()->create(['estado' => 'activo']);
        $this->directorPed->assignRole('subdiretor_pedagogico');

        $this->professor = $this->scenario->users->professor;
        $this->professor->assignRole('professor');
    }

    public function test_coord_cria_rascunho_e_itens_no_escopo(): void
    {
        $this->actingAs($this->coord)
            ->post(route('pedagogico.conteudos.store'), [
                'disciplina_id' => $this->scenario->disciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Programa MAT 10ª',
            ])
            ->assertRedirect();

        $conteudo = ConteudoMinimo::first();
        $this->assertNotNull($conteudo);
        $this->assertSame(ConteudoMinimo::ESTADO_RASCUNHO, $conteudo->estado);

        $this->actingAs($this->coord)
            ->post(route('pedagogico.conteudos.itens.store', $conteudo), [
                'titulo' => 'Unidade 1 — Álgebra',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conteudo_minimo_itens', [
            'conteudo_minimo_id' => $conteudo->id,
            'titulo' => 'Unidade 1 — Álgebra',
        ]);
    }

    public function test_coord_nao_cria_fora_do_escopo(): void
    {
        $this->actingAs($this->coord)
            ->post(route('pedagogico.conteudos.store'), [
                'disciplina_id' => $this->scenario->disciplina->id,
                'classe_id' => $this->scenario->classes->c11->id,
                'titulo' => 'Fora do escopo',
            ])
            ->assertForbidden();
    }

    public function test_coord_publica_no_escopo_e_nao_fora(): void
    {
        $conteudo = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Programa',
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
        ]);

        $this->actingAs($this->coord)
            ->post(route('pedagogico.conteudos.publicar', $conteudo))
            ->assertRedirect();

        $this->assertSame(ConteudoMinimo::ESTADO_PUBLICADO, $conteudo->fresh()->estado);

        $fora = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c11->id,
            'titulo' => 'Fora',
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
        ]);

        $this->actingAs($this->coord)
            ->post(route('pedagogico.conteudos.publicar', $fora))
            ->assertForbidden();
    }

    public function test_director_ainda_publica(): void
    {
        $conteudo = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Programa',
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
        ]);

        $this->actingAs($this->directorPed)
            ->post(route('pedagogico.conteudos.publicar', $conteudo))
            ->assertRedirect();

        $this->assertSame(ConteudoMinimo::ESTADO_PUBLICADO, $conteudo->fresh()->estado);
    }

    public function test_publicar_arquiva_versao_anterior(): void
    {
        $antigo = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Programa v1',
            'estado' => ConteudoMinimo::ESTADO_PUBLICADO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
            'publicado_por' => $this->directorPed->id,
            'publicado_em' => now(),
        ]);

        $novo = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Programa v2',
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 2,
            'criado_por' => $this->coord->id,
        ]);

        $this->actingAs($this->directorPed)
            ->post(route('pedagogico.conteudos.publicar', $novo))
            ->assertRedirect();

        $this->assertSame(ConteudoMinimo::ESTADO_ARQUIVADO, $antigo->fresh()->estado);
        $this->assertSame(ConteudoMinimo::ESTADO_PUBLICADO, $novo->fresh()->estado);
    }

    public function test_professor_ve_publicados_nao_edita(): void
    {
        $publicado = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Visível',
            'estado' => ConteudoMinimo::ESTADO_PUBLICADO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
            'publicado_por' => $this->directorPed->id,
            'publicado_em' => now(),
        ]);

        $rascunho = ConteudoMinimo::create([
            'disciplina_id' => $this->scenario->disciplina->id,
            'classe_id' => $this->scenario->classes->c10->id,
            'titulo' => 'Oculto rascunho',
            'estado' => ConteudoMinimo::ESTADO_RASCUNHO,
            'versao' => 1,
            'criado_por' => $this->coord->id,
        ]);

        $this->actingAs($this->professor)
            ->get(route('pedagogico.conteudos.index'))
            ->assertOk()
            ->assertSee('Visível')
            ->assertDontSee('Oculto rascunho');

        $this->actingAs($this->professor)
            ->get(route('pedagogico.conteudos.show', $publicado))
            ->assertOk();

        $this->actingAs($this->professor)
            ->get(route('pedagogico.conteudos.edit', $rascunho))
            ->assertForbidden();

        $this->actingAs($this->professor)
            ->post(route('pedagogico.conteudos.store'), [
                'disciplina_id' => $this->scenario->disciplina->id,
                'classe_id' => $this->scenario->classes->c10->id,
                'titulo' => 'Tentativa',
            ])
            ->assertForbidden();
    }
}
