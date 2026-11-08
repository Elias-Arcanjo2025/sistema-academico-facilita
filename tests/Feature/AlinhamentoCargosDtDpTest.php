<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\PautaAprovacao;
use App\Models\Turma;
use App\Models\TurmaHorarioConfig;
use App\Models\User;
use App\Services\HorarioEscolarAccess;
use App\Services\PautaAprovacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlinhamentoCargosDtDpTest extends TestCase
{
    use RefreshDatabase;

    protected AnoLectivo $ano;

    protected Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'Curso', 'codigo' => 'CX'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'C10'], ['nome' => '10ª', 'activo' => true]);
        $this->turma = Turma::create([
            'codigo' => 'TX'.uniqid(),
            'nome' => 'TX',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $this->ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);
    }

    public function test_diretor_pedagogico_aprova_horario_e_pauta(): void
    {
        $dp = User::factory()->create(['estado' => 'activo']);
        $dp->assignRole('subdiretor_pedagogico');

        $this->assertTrue(HorarioEscolarAccess::podeAprovar($dp));
        $this->assertTrue(HorarioEscolarAccess::turmaAcessivel($dp, $this->turma));
        $this->assertTrue($dp->can('pautas.aprovar'));
        $this->assertTrue($dp->can('notas.publicar'));

        $this->actingAs($dp)
            ->post(route('horarios.aprovar', $this->turma))
            ->assertRedirect();

        $config = TurmaHorarioConfig::paraTurma($this->turma->id, $this->ano->id);
        $this->assertTrue($config->estaAprovado());

        $this->actingAs($dp)
            ->post(route('pautas.aprovar'), [
                'turma_id' => $this->turma->id,
                'trimestre' => 1,
                'ano_lectivo' => '2025-2026',
            ])
            ->assertRedirect();

        $aprovacao = PautaAprovacao::para($this->turma->id, $this->ano->id, 1);
        $this->assertNotNull($aprovacao);
        $this->assertTrue($aprovacao->estaAprovada());
    }

    public function test_diretor_pedagogico_autoriza_portal_notas(): void
    {
        $dp = User::factory()->create(['estado' => 'activo']);
        $dp->assignRole('subdiretor_pedagogico');

        $this->actingAs($dp)
            ->post(route('configuracoes.update-portal'), [
                'portal_aluno_notas_activo' => '0',
                'portal_aluno_mensagem_bloqueio' => 'Bloqueado pelo DP',
            ])
            ->assertRedirect();

        $this->assertSame('0', Configuracao::get('portal_aluno_notas_activo'));
    }

    public function test_dt_comunica_com_encarregado_da_sua_turma(): void
    {
        Mail::fake();

        $dt = User::factory()->create(['estado' => 'activo']);
        $dt->assignRole('director_turma');
        $dt->turmasComoDirector()->attach($this->turma->id, [
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
            'activo' => true,
        ]);

        $aluno = Aluno::create([
            'numero_processo' => 'DT-1',
            'nome' => 'Aluno DT',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->assertTrue($dt->can('turma.comunicar'));
        $this->assertFalse($dt->can('notas.lancar'));

        $this->actingAs($dt)
            ->post(route('alunos.enviar-email', $aluno), [
                'destinatario' => 'encarregado@exemplo.ao',
                'assunto' => 'Reunião',
                'mensagem' => 'Compareça amanhã.',
                'nome_destinatario' => 'Encarregado',
            ])
            ->assertRedirect();

        Mail::assertSent(\App\Mail\SecretariaMensagemMail::class);
    }

    public function test_dt_nao_aprova_pauta(): void
    {
        $dt = User::factory()->create(['estado' => 'activo']);
        $dt->assignRole('director_turma');

        $this->assertFalse(app(PautaAprovacaoService::class)->podeAprovar($dt));

        $this->actingAs($dt)
            ->post(route('pautas.aprovar'), [
                'turma_id' => $this->turma->id,
                'trimestre' => 1,
            ])
            ->assertForbidden();
    }
}
