<?php

namespace Tests\Feature;

use App\Models\AlunoAvaliacaoDiaria;
use App\Models\AlunoPresenca;
use App\Support\SetupInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class AvaliacaoDiariaTest extends TestCase
{
    use RefreshDatabase;

    private object $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'presencas.lancar']);
        $role = Role::firstOrCreate(['name' => 'professor']);
        $role->givePermissionTo($permission);

        $this->scenario = (new TransicaoAnoScenarioBuilder)->build();
        SetupInicial::marcarConcluido();
        $this->scenario->users->professor->assignRole($role);
    }

    public function test_professor_consulta_e_guarda_presenca_e_avaliacao_diaria_sem_duplicar(): void
    {
        $professor = $this->scenario->users->professor;
        $aluno = $this->scenario->alunos->aprovado;
        $turma = $this->scenario->turmas->origem_10;
        $disciplina = $this->scenario->disciplina;
        $data = '2025-02-10';

        $this->actingAs($professor)
            ->get(route('avaliacao-diaria.index', [
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'data' => $data,
            ]))
            ->assertOk()
            ->assertSee($aluno->nome)
            ->assertSee('Guardar presenças e avaliações');

        $payload = [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => $data,
            'presencas' => [
                $aluno->id => ['estado' => 'atrasado', 'observacao' => 'Chegou após o início.'],
            ],
            'avaliacoes' => [
                $aluno->id => [
                    'participacao' => 15,
                    'empenho' => 16,
                    'comportamento' => 17,
                    'realizacao_actividades' => 18,
                    'compreensao' => 14,
                    'observacao_geral' => 'Bom desempenho.',
                ],
            ],
        ];

        $this->actingAs($professor)
            ->post(route('avaliacao-diaria.store'), $payload)
            ->assertRedirect(route('avaliacao-diaria.index', [
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'data' => $data,
            ]));

        $this->assertDatabaseHas('aluno_presencas', [
            'aluno_id' => $aluno->id,
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor->id,
            'estado' => 'atrasado',
        ]);
        $this->assertDatabaseHas('aluno_avaliacoes_diarias', [
            'aluno_id' => $aluno->id,
            'professor_id' => $professor->id,
            'participacao' => 15,
        ]);

        $payload['presencas'][$aluno->id]['estado'] = 'presente';
        $payload['avaliacoes'][$aluno->id]['participacao'] = 19;

        $this->actingAs($professor)
            ->post(route('avaliacao-diaria.store'), $payload)
            ->assertRedirect();

        $this->assertSame(1, AlunoPresenca::query()->count());
        $this->assertSame(1, AlunoAvaliacaoDiaria::query()->count());
        $this->assertDatabaseHas('aluno_presencas', ['aluno_id' => $aluno->id, 'estado' => 'presente']);
        $this->assertDatabaseHas('aluno_avaliacoes_diarias', ['aluno_id' => $aluno->id, 'participacao' => 19]);
    }

    public function test_professor_nao_pode_aceder_a_turma_nao_associada(): void
    {
        $this->actingAs($this->scenario->users->professor)
            ->get(route('avaliacao-diaria.index', [
                'turma_id' => $this->scenario->turmas->destino_10->id,
            ]))
            ->assertForbidden();
    }

    public function test_classificacao_diaria_tem_de_ficar_entre_zero_e_vinte(): void
    {
        $aluno = $this->scenario->alunos->aprovado;

        $this->actingAs($this->scenario->users->professor)
            ->post(route('avaliacao-diaria.store'), [
                'turma_id' => $this->scenario->turmas->origem_10->id,
                'disciplina_id' => $this->scenario->disciplina->id,
                'data' => '2025-02-10',
                'presencas' => [
                    $aluno->id => ['estado' => 'presente'],
                ],
                'avaliacoes' => [
                    $aluno->id => [
                        'participacao' => 21,
                        'empenho' => 10,
                        'comportamento' => 10,
                        'realizacao_actividades' => 10,
                        'compreensao' => 10,
                    ],
                ],
            ])
            ->assertSessionHasErrors("avaliacoes.{$aluno->id}.participacao");

        $this->assertDatabaseCount('aluno_presencas', 0);
        $this->assertDatabaseCount('aluno_avaliacoes_diarias', 0);
    }

    public function test_registo_parcial_e_permitido_sem_preencher_todos_os_campos(): void
    {
        $aluno = $this->scenario->alunos->aprovado;

        $this->actingAs($this->scenario->users->professor)
            ->post(route('avaliacao-diaria.store'), [
                'turma_id' => $this->scenario->turmas->origem_10->id,
                'disciplina_id' => $this->scenario->disciplina->id,
                'data' => '2025-02-10',
                'presencas' => [
                    $aluno->id => ['estado' => '', 'observacao' => 'Observação sem estado.'],
                ],
                'avaliacoes' => [
                    $aluno->id => [
                        'participacao' => 15,
                        'empenho' => '',
                        'comportamento' => '',
                        'realizacao_actividades' => '',
                        'compreensao' => '',
                        'observacao_geral' => '',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('aluno_presencas', [
            'aluno_id' => $aluno->id,
            'estado' => null,
            'observacao' => 'Observação sem estado.',
        ]);
        $this->assertDatabaseHas('aluno_avaliacoes_diarias', [
            'aluno_id' => $aluno->id,
            'participacao' => 15,
            'empenho' => null,
        ]);
    }

    public function test_pode_guardar_sem_dados_sem_criar_registos_vazios(): void
    {
        $aluno = $this->scenario->alunos->aprovado;

        $this->actingAs($this->scenario->users->professor)
            ->post(route('avaliacao-diaria.store'), [
                'turma_id' => $this->scenario->turmas->origem_10->id,
                'disciplina_id' => $this->scenario->disciplina->id,
                'data' => '2025-02-10',
                'presencas' => [
                    $aluno->id => ['estado' => '', 'observacao' => ''],
                ],
                'avaliacoes' => [
                    $aluno->id => [
                        'participacao' => '',
                        'empenho' => '',
                        'comportamento' => '',
                        'realizacao_actividades' => '',
                        'compreensao' => '',
                        'observacao_geral' => '',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('aluno_presencas', 0);
        $this->assertDatabaseCount('aluno_avaliacoes_diarias', 0);
    }
}
