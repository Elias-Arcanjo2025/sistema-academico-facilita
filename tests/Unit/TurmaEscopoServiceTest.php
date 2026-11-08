<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TurmaEscopoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TurmaEscopoServiceTest extends TestCase
{
    use RefreshDatabase;

    private TurmaEscopoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TurmaEscopoService::class);

        Role::firstOrCreate(['name' => 'gestor_academico']);
        Role::firstOrCreate(['name' => 'professor']);
    }

    public function test_gestor_academico_tem_acesso_institucional(): void
    {
        $user = User::factory()->create();
        $user->assignRole('gestor_academico');

        $this->assertTrue($this->service->podeAcederTurma($user, 999));
    }

    public function test_professor_sem_associacao_nao_acede_turma(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professor');

        $this->assertFalse($this->service->podeAcederTurma($user, 999));
    }
}
