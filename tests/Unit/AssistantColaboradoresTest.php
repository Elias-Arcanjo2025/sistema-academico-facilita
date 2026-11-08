<?php

namespace Tests\Unit;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\Assistente\AssistantDataService;
use App\Services\Assistente\AssistantToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantColaboradoresTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected User $professor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $ano = AnoLectivo::create(['nome' => '2025-2026', 'estado' => AnoLectivo::ESTADO_ACTUAL]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');

        $curso = Curso::create(['nome' => 'Técnico de Informática', 'codigo' => 'TI', 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T10'], ['nome' => '10ª Classe', 'activo' => true]);
        $disciplina = Disciplina::create(['nome' => 'SEAC', 'codigo' => 'SEAC', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'TI10AT',
            'nome' => 'TI10AT',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->professor = User::factory()->create([
            'name' => 'Edrici Sérgio Sachombela',
            'estado' => 'activo',
        ]);
        $this->professor->assignRole('professor');

        ProfessorDisciplinaTurma::create([
            'user_id' => $this->professor->id,
            'disciplina_id' => $disciplina->id,
            'turma_id' => $turma->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->staff = User::factory()->create(['estado' => 'activo']);
        $this->staff->assignRole('professor');
    }

    public function test_search_colaboradores_encontra_professor_por_nome(): void
    {
        $resultados = app(AssistantDataService::class)->searchColaboradores($this->staff, 'edrici');

        $this->assertCount(1, $resultados);
        $this->assertSame('Edrici Sérgio Sachombela', $resultados[0]['nome']);
        $this->assertContains('professor', $resultados[0]['cargos']);
        $this->assertSame('SEAC', $resultados[0]['lecciona'][0]['disciplina']);
        $this->assertSame('TI10AT', $resultados[0]['lecciona'][0]['turma']);
    }

    public function test_search_colaboradores_nao_disponivel_para_alunos(): void
    {
        $aluno = User::factory()->create(['estado' => 'activo']);
        $aluno->assignRole('aluno');

        $this->assertSame([], app(AssistantDataService::class)->searchColaboradores($aluno, 'edrici'));
    }

    public function test_resolver_tools_inclui_buscar_colaboradores_em_pergunta_por_nome(): void
    {
        $tools = app(AssistantToolRegistry::class)->resolverTools($this->staff, 'Quem é o Edrici?');

        $this->assertContains('buscar_colaboradores', $tools);
        $this->assertContains('buscar_alunos', $tools);
    }

    public function test_executar_busca_colaboradores_com_fallback_ao_historico(): void
    {
        $registry = app(AssistantToolRegistry::class);
        $historico = [
            ['papel' => 'user', 'conteudo' => 'Quem é o Edrici?'],
            ['papel' => 'assistant', 'conteudo' => 'Não consta na lista de alunos.'],
        ];

        $resultados = $registry->executar(
            $this->staff,
            $registry->resolverTools($this->staff, 'E nos professores?', $historico),
            'E nos professores?',
            $historico
        );

        $colaboradores = $resultados['buscar_colaboradores']['colaboradores'] ?? [];
        $this->assertCount(1, $colaboradores);
        $this->assertSame('Edrici Sérgio Sachombela', $colaboradores[0]['nome']);
    }

    public function test_plurais_de_cargos_sao_stop_words(): void
    {
        $service = app(AssistantDataService::class);

        $this->assertSame([], $service->extrairTermosBusca('E nos professores?'));
        $this->assertSame([], $service->extrairTermosBusca('E nos alunos?'));
        $this->assertSame([], $service->extrairTermosBusca('Já lançou as notas?'));
    }

    public function test_gestao_ve_resumo_de_lancamentos_do_professor(): void
    {
        Role::firstOrCreate(['name' => 'admin_full', 'guard_name' => 'web']);
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole('admin_full');

        $turma = Turma::where('codigo', 'TI10AT')->firstOrFail();
        $disciplina = Disciplina::where('codigo', 'SEAC')->firstOrFail();
        $ano = AnoLectivo::where('nome', '2025-2026')->firstOrFail();

        $aluno = \App\Models\Aluno::create([
            'nome' => 'Aluno Teste',
            'numero_processo' => '2025001',
            'turma_id' => $turma->id,
            'activo' => true,
        ]);

        \App\Models\Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $disciplina->id,
            'turma_id' => $turma->id,
            'professor_id' => $this->professor->id,
            'tipo' => 'mac',
            'valor' => 14,
            'trimestre' => 1,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $ano->id,
        ]);

        $resultados = app(AssistantDataService::class)->searchColaboradores($admin, 'edrici');

        $this->assertCount(1, $resultados);
        $lecciona = $resultados[0]['lecciona'][0];
        $this->assertSame(1, $lecciona['total_alunos']);
        $this->assertSame(1, $lecciona['notas_lancadas']['1º trimestre']['MAC']);
    }

    public function test_professor_nao_ve_lancamentos_de_colegas(): void
    {
        $resultados = app(AssistantDataService::class)->searchColaboradores($this->staff, 'edrici');

        $this->assertCount(1, $resultados);
        $this->assertArrayNotHasKey('notas_lancadas', $resultados[0]['lecciona'][0]);
    }

    public function test_busca_colaboradores_recupera_nome_de_mensagens_anteriores(): void
    {
        $registry = app(AssistantToolRegistry::class);
        $historico = [
            ['papel' => 'user', 'conteudo' => 'Quem é o Edrici?'],
            ['papel' => 'assistant', 'conteudo' => 'É professor de SEAC na TI10AT.'],
            ['papel' => 'user', 'conteudo' => 'Já lançou as notas?'],
            ['papel' => 'assistant', 'conteudo' => 'Para que turma?'],
        ];

        $resultados = $registry->executar(
            $this->staff,
            ['buscar_colaboradores'],
            'TI10AT',
            $historico
        );

        $colaboradores = $resultados['buscar_colaboradores']['colaboradores'] ?? [];
        $this->assertCount(1, $colaboradores);
        $this->assertSame('Edrici Sérgio Sachombela', $colaboradores[0]['nome']);
    }
}
