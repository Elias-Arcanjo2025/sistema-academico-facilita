<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Encarregado;
use App\Models\Ocorrencia;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Models\Turma;
use App\Models\User;
use App\Services\ExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncarregadoEngajamentoPortalTest extends TestCase
{
    use RefreshDatabase;

    private AnoLectivo $ano;

    private Turma $turma;

    private Aluno $aluno;

    private User $encarregadoUser;

    private User $director;

    private Disciplina $disciplina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');
        Configuracao::set('trimestre_actual', '1');

        $curso = Curso::create(['nome' => 'C', 'codigo' => 'C'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'E10'], ['nome' => '10ª', 'activo' => true]);
        $this->turma = Turma::create([
            'codigo' => 'TG'.uniqid(),
            'nome' => 'TG',
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
            'numero_processo' => 'ENG-1',
            'nome' => 'Filho Engajamento',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $encarregado = Encarregado::create([
            'nome' => 'Pai Engajamento',
            'telefone' => '900333444',
            'email' => 'pai.eng@exemplo.ao',
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
            'name' => 'Pai Portal Eng',
        ]);
        $this->encarregadoUser->assignRole('encarregado');

        $this->director = User::factory()->create(['estado' => 'activo', 'name' => 'DT Eng']);
        $this->director->assignRole('director_turma');
        $this->director->turmasComoDirector()->attach($this->turma->id, [
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->ano->id,
            'activo' => true,
        ]);
    }

    public function test_dashboard_mostra_acao_por_mensagem_nao_lida(): void
    {
        $conversa = PortalConversa::create([
            'aluno_id' => $this->aluno->id,
            'encarregado_user_id' => $this->encarregadoUser->id,
            'last_message_at' => now(),
        ]);

        PortalMensagem::create([
            'conversa_id' => $conversa->id,
            'user_id' => $this->director->id,
            'corpo' => 'Precisamos falar sobre o comportamento.',
            'read_at' => null,
        ]);

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.dashboard'))
            ->assertOk()
            ->assertSee('O que fazer agora', false)
            ->assertSee('Ler mensagem da escola', false);
    }

    public function test_timeline_mostra_ocorrencia_e_exige_vinculo(): void
    {
        Ocorrencia::create([
            'aluno_id' => $this->aluno->id,
            'turma_id' => $this->turma->id,
            'ano_lectivo_id' => $this->ano->id,
            'data' => now()->toDateString(),
            'tipo' => Ocorrencia::TIPO_ADVERTENCIA,
            'severidade' => Ocorrencia::SEV_MEDIA,
            'descricao' => 'Chegou atrasado sem justificação.',
            'estado' => Ocorrencia::ESTADO_ABERTA,
            'registado_por' => $this->director->id,
        ]);

        $aula = PresencaAula::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'professor_id' => $this->director->id,
            'ano_lectivo_id' => $this->ano->id,
            'data' => now()->subDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:45:00',
            'trimestre' => 1,
            'registado_por' => $this->director->id,
        ]);

        PresencaRegisto::create([
            'presenca_aula_id' => $aula->id,
            'aluno_id' => $this->aluno->id,
            'estado' => PresencaRegisto::FALTA_INJUSTIFICADA,
        ]);

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.filho.timeline', $this->aluno))
            ->assertOk()
            ->assertSee('Linha do tempo', false)
            ->assertSee('Advertência', false)
            ->assertSee('Falta injustificada', false);

        $outro = Aluno::create([
            'numero_processo' => 'ENG-X',
            'nome' => 'Sem Vinculo',
            'turma_id' => $this->turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->actingAs($this->encarregadoUser)
            ->get(route('portal.encarregado.filho.timeline', $outro))
            ->assertForbidden();
    }

    public function test_assinatura_digital_aparece_nos_dados_do_boletim_pdf(): void
    {
        $this->aluno->load(['turma.curso', 'turma.classe']);

        $this->actingAs($this->encarregadoUser)
            ->post(route('portal.encarregado.assinar-boletim', $this->aluno), [
                'confirmacao' => '1',
                'trimestre' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('boletim_assinaturas', [
            'aluno_id' => $this->aluno->id,
            'encarregado_id' => $this->encarregadoUser->encarregado_id,
            'ano_lectivo' => '2025-2026',
            'trimestre' => 1,
        ]);

        $dados = app(ExportService::class)->gerarDadosBoletimTrimestre($this->aluno, 1, '2025-2026');

        $this->assertNotNull($dados['assinatura_encarregado']);
        $this->assertSame('Pai Engajamento', $dados['assinatura_encarregado']['nome']);
        $this->assertNotEmpty($dados['assinatura_encarregado']['hash_curto']);

        $html = view('exports.pdf.partials.assinatura-encarregado', [
            'assinatura_encarregado' => $dados['assinatura_encarregado'],
        ])->render();

        $this->assertStringContainsString('Assinado digitalmente no portal', $html);
        $this->assertStringContainsString('Pai Engajamento', $html);
    }
}
