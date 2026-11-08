<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistente\AssistantAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'assistente.usar']);
        Permission::firstOrCreate(['name' => 'alunos.ver']);
        Role::firstOrCreate(['name' => 'professor']);
        Role::firstOrCreate(['name' => 'aluno']);
        Role::firstOrCreate(['name' => 'admin_full']);
    }

    public function test_professor_tem_nivel_pii_limitado(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professor');

        $access = app(AssistantAccess::class);

        $this->assertSame(AssistantAccess::PII_PROFESSOR, $access->nivelPii($user));
        $this->assertFalse($access->incluirDadosPessoais($user));
        $this->assertSame('professor', $access->perfilResposta($user));
    }

    public function test_admin_tem_nivel_pii_completo(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin_full');

        $access = app(AssistantAccess::class);

        $this->assertSame(AssistantAccess::PII_COMPLETO, $access->nivelPii($user));
        $this->assertTrue($access->incluirDadosPessoais($user));
        $this->assertSame('completo', $access->perfilResposta($user));
    }

    public function test_utilizador_precisa_permissao_assistente(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professor');

        $access = app(AssistantAccess::class);

        $this->assertFalse($access->canUse($user));

        Role::findByName('professor')->givePermissionTo('assistente.usar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue($access->canUse($user));
    }

    public function test_aluno_tem_perfil_e_regras_limitadas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');
        $user->givePermissionTo('assistente.usar');

        $access = app(AssistantAccess::class);

        $this->assertSame('aluno', $access->perfilResposta($user));
        $regras = $access->regrasResposta($user);
        $this->assertSame('aluno', $regras['perfil']);
        $this->assertNotEmpty($regras['nao_pode']);
    }

    public function test_aluno_pode_perguntar_sobre_proprias_notas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $permitidas = [
            'Como estão as minhas notas este trimestre?',
            'O que significam MAC e PP?',
            'Comparar as minhas notas do 1º e 2º trimestre',
            'Comparar as minhas notas do primeiro com o segundo trimestre',
            'Como estão as notas de Física?',
            'Explica o meu CFD de Matemática',
            'Quais disciplinas tenho positivas e negativas este trimestre?',
            'Como funciona o lançamento de notas MAC e PP?',
            'Quais são os meus dados no portal?',
            'Quem são os meus professores?',
            'Comparação das notas de cada disciplina do primeiro com o segundo trimestre',
        ];

        foreach ($permitidas as $pergunta) {
            $this->assertTrue(
                $access->avaliarPergunta($user, $pergunta)['permitida'],
                "Deveria permitir: {$pergunta}"
            );
        }
    }

    public function test_aluno_nao_pode_perguntar_notas_de_colega(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $resultado = $access->avaliarPergunta($user, 'Quais são as notas do João?');

        $this->assertFalse($resultado['permitida']);
        $this->assertNotEmpty($resultado['resposta']);
    }

    public function test_aluno_nao_pode_pedir_desempenho_de_outro_aluno_pelo_nome(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $resultado = $access->avaliarPergunta(
            $user,
            'Quero saber o desempenho academico do Narciso Sardinha.'
        );

        $this->assertFalse($resultado['permitida']);
        $this->assertStringContainsString('próprio', mb_strtolower($resultado['resposta']));
    }

    public function test_aluno_pode_perguntar_desempenho_com_referencia_propria(): void
    {
        $user = User::factory()->create(['name' => 'Maria Silva']);
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $this->assertTrue($access->avaliarPergunta($user, 'Qual é o meu desempenho académico?')['permitida']);
        $this->assertTrue($access->avaliarPergunta($user, 'Como estão as minhas notas?')['permitida']);
    }

    public function test_aluno_nao_pode_pedir_alteracao_de_notas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('aluno');

        $access = app(AssistantAccess::class);

        $resultado = $access->avaliarPergunta($user, 'Como posso alterar a minha nota de Física?');

        $this->assertFalse($resultado['permitida']);

        $this->assertFalse($access->avaliarPergunta($user, 'Quero lançar as minhas notas')['permitida']);
    }

    public function test_professor_nao_e_bloqueado_por_validacao_de_aluno(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professor');

        $access = app(AssistantAccess::class);

        $this->assertTrue($access->avaliarPergunta($user, 'Quais são as notas do João na minha turma?')['permitida']);
    }
}
