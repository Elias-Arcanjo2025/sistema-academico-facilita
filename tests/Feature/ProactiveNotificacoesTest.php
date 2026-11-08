<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\ProactiveNotificacaoDispatcher;
use App\Services\StaffNotificacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Support\TransicaoAnoScenarioBuilder;
use Tests\TestCase;

class ProactiveNotificacoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'professor']);
        Role::firstOrCreate(['name' => 'aluno']);
        Role::firstOrCreate(['name' => 'admin_full']);
    }

    public function test_professor_recebe_alerta_cobertura_baixa(): void
    {
        Notification::fake();
        Cache::flush();

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $professor = $scenario->users->professor;
        $professor->assignRole('professor');

        Configuracao::set('trimestre_actual', '1', 'test');

        app(StaffNotificacaoService::class)->verificarEnotificarProfessor($professor);

        Notification::assertSentTo($professor, StaffAlertaAcademicoNotification::class);
    }

    public function test_aluno_recebe_alerta_negativas(): void
    {
        Notification::fake();
        Cache::flush();

        $scenario = (new TransicaoAnoScenarioBuilder)->build();
        $aluno = $scenario->alunos->aprovado;

        Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $scenario->disciplina->id,
            'turma_id' => $scenario->turmas->origem_10->id,
            'professor_id' => $scenario->users->professor->id,
            'tipo' => Nota::TIPO_MT,
            'valor' => 8,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $scenario->anos->origem->id,
            'trimestre' => 1,
        ]);
        Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $scenario->disciplina->id,
            'turma_id' => $scenario->turmas->origem_10->id,
            'professor_id' => $scenario->users->professor->id,
            'tipo' => Nota::TIPO_MT,
            'valor' => 7,
            'ano_lectivo' => TransicaoAnoScenarioBuilder::ANO_ORIGEM,
            'ano_lectivo_id' => $scenario->anos->origem->id,
            'trimestre' => 2,
        ]);

        $alunoUser = User::factory()->create([
            'estado' => 'activo',
            'aluno_id' => $aluno->id,
        ]);
        $alunoUser->assignRole('aluno');

        app(ProactiveNotificacaoDispatcher::class)->aoVisitarArea($alunoUser);

        Notification::assertSentTo($alunoUser, \App\Notifications\AlunoAlertaAcademicoNotification::class);
    }
}
