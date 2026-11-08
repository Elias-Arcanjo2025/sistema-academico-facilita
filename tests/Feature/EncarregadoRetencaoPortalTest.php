<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Encarregado;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\Portal\EncarregadoNotificacaoProactivaService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EncarregadoRetencaoPortalTest extends TestCase
{
    use RefreshDatabase;

    private AnoLectivo $ano;

    private Turma $turma;

    private Aluno $aluno;

    private User $encarregadoUser;

    private User $professor;

    private Disciplina $disciplina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00', 'Africa/Luanda')); // segunda

        $this->ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'E10'], ['nome' => '10ª', 'activo' => true]);
        $this->turma = Turma::create([
            'codigo' => 'TR'.uniqid(),
            'nome' => 'TR',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $this->ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->disciplina = Disciplina::create([
            'nome' => 'Matemática',
            'codigo' => 'MAT'.uniqid(),
            'curso_id' => $curso->id,
            'activo' => true,
        ]);

        $this->aluno = Aluno::create([
            'numero_processo' => 'RET-1',
            'nome' => 'Filho Retencao',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $encarregado = Encarregado::create([
            'nome' => 'Pai Retencao',
            'telefone' => '900111222',
            'email' => 'pai.retencao@exemplo.ao',
            'activo' => true,
        ]);
        $encarregado->alunos()->attach($this->aluno->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $this->encarregadoUser = User::factory()->create([
            'estado' => 'activo',
            'encarregado_id' => $encarregado->id,
            'name' => 'Pai Portal Retencao',
        ]);
        $this->encarregadoUser->assignRole('encarregado');

        $this->professor = User::factory()->create(['estado' => 'activo']);
        $this->professor->assignRole('professor');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_digest_semanal_notifica_e_pagina_mostra_faltas(): void
    {
        Notification::fake();
        Cache::flush();

        // Falta na semana anterior (13–19 Jul 2026)
        $this->criarFalta('2026-07-14', '08:00:00');

        $this->artisan('portal:encarregado-resumo-semanal')
            ->assertSuccessful();

        Notification::assertSentTo(
            $this->encarregadoUser,
            StaffAlertaAcademicoNotification::class,
            function (StaffAlertaAcademicoNotification $n) {
                $data = $n->toArray($this->encarregadoUser);

                return ($data['tipo'] ?? null) === 'encarregado_digest_semanal'
                    && str_contains((string) ($data['mensagem'] ?? ''), 'falta');
            }
        );

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.resumo-semanal'))
            ->assertOk()
            ->assertSee('Esta semana', false)
            ->assertSee('Filho Retencao', false);

        // Segunda execução no mesmo ciclo não reenvia
        Notification::fake();
        $this->artisan('portal:encarregado-resumo-semanal')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_alerta_proactivo_faltas_e_nao_duplica_no_mesmo_dia(): void
    {
        Notification::fake();
        Cache::flush();

        $this->criarFalta('2026-07-13', '08:00:00');
        $this->criarFalta('2026-07-14', '08:00:00');
        $this->criarFalta('2026-07-15', '08:00:00');

        app(EncarregadoNotificacaoProactivaService::class)
            ->verificarEnotificar($this->encarregadoUser);

        Notification::assertSentTo(
            $this->encarregadoUser,
            StaffAlertaAcademicoNotification::class,
            function (StaffAlertaAcademicoNotification $n) {
                $data = $n->toArray($this->encarregadoUser);

                return ($data['tipo'] ?? null) === 'encarregado_alerta_faltas';
            }
        );

        Notification::fake();
        app(EncarregadoNotificacaoProactivaService::class)
            ->verificarEnotificar($this->encarregadoUser);
        Notification::assertNothingSent();
    }

    public function test_resumo_semanal_exige_role_encarregado(): void
    {
        $outro = User::factory()->create(['estado' => 'activo']);
        $outro->assignRole('professor');

        $this->actingAs($outro)
            ->get(route('portal.encarregado.resumo-semanal'))
            ->assertForbidden();
    }

    private function criarFalta(string $data, string $horaInicio): void
    {
        $aula = PresencaAula::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'professor_id' => $this->professor->id,
            'ano_lectivo_id' => $this->ano->id,
            'data' => $data,
            'hora_inicio' => $horaInicio,
            'hora_fim' => '08:45:00',
            'trimestre' => 1,
            'registado_por' => $this->professor->id,
        ]);

        PresencaRegisto::create([
            'presenca_aula_id' => $aula->id,
            'aluno_id' => $this->aluno->id,
            'estado' => PresencaRegisto::FALTA_INJUSTIFICADA,
        ]);
    }
}
