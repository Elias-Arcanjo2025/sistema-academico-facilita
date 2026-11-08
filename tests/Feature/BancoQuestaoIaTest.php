<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class BancoQuestaoIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_gerar_questoes_com_ia_grava_no_banco(): void
    {
        foreach ([
            'pedagogico.ver', 'pedagogico.banco.ver', 'pedagogico.banco.gerir', 'pedagogico.ia.gerar',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }
        Role::firstOrCreate(['name' => 'professor']);
        Role::findByName('professor')->syncPermissions([
            'pedagogico.ver', 'pedagogico.banco.ver', 'pedagogico.banco.gerir', 'pedagogico.ia.gerar',
        ]);

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        Configuracao::set('trimestre_actual', '1', 'test');

        $user = $scenario->users->professor;
        $user->assignRole('professor');

        CoordenadorDisciplina::create([
            'user_id' => $user->id,
            'disciplina_id' => $scenario->disciplina->id,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $scenario->anos->origem->id,
            'activo' => true,
        ])->classes()->sync([$scenario->classes->c10->id]);

        $this->mock(AIServiceInterface::class, function ($mock) {
            $mock->shouldReceive('chatJson')->once()->andReturn([
                'data' => [
                    'questoes' => [
                        [
                            'tipo' => 'escolha_multipla',
                            'enunciado' => 'Qual é a capital de Angola?',
                            'opcoes' => [
                                'A' => 'Luanda',
                                'B' => 'Benguela',
                                'C' => 'Huambo',
                                'D' => 'Lobito',
                            ],
                            'resposta' => 'A',
                            'cotacao' => 2,
                            'dificuldade' => 'facil',
                            'tags' => 'geografia',
                        ],
                        [
                            'tipo' => 'escolha_multipla',
                            'enunciado' => '2 + 2 = ?',
                            'opcoes' => ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '22'],
                            'resposta' => 'B',
                            'cotacao' => 1,
                            'dificuldade' => 'facil',
                            'tags' => 'calculo',
                        ],
                    ],
                ],
                'meta' => ['provider' => 'fake', 'model' => 'test'],
            ]);
        });

        $this->actingAs($user)
            ->post(route('pedagogico.banco.gerar'), [
                'disciplina_id' => $scenario->disciplina->id,
                'classe_id' => $scenario->classes->c10->id,
                'tipo' => PedBancoQuestao::TIPO_ESCOLHA,
                'dificuldade' => PedBancoQuestao::DIFF_FACIL,
                'quantidade' => 2,
                'tema' => 'revisão',
                'cotacao' => 1,
            ])
            ->assertRedirect(route('pedagogico.banco.index', ['disciplina_id' => $scenario->disciplina->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ped_banco_questoes', [
            'disciplina_id' => $scenario->disciplina->id,
            'criado_por' => $user->id,
            'enunciado' => 'Qual é a capital de Angola?',
            'tipo' => PedBancoQuestao::TIPO_ESCOLHA,
        ]);
        $this->assertSame(2, PedBancoQuestao::where('disciplina_id', $scenario->disciplina->id)->count());
    }
}
