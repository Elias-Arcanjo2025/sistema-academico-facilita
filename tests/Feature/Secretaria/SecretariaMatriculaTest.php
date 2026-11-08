<?php

namespace Tests\Feature\Secretaria;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\MatriculaHistorico;
use App\Models\Turma;
use App\Models\User;
use App\Services\CartaoEstudanteService;
use App\Support\CargosInstitucionais;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariaMatriculaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
        ]);
        Configuracao::set('ano_lectivo_actual', '2025-2026');
    }

    public function test_secretaria_acede_painel_e_professor_nao(): void
    {
        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $this->actingAs($secretaria)
            ->get(route('secretaria.dashboard'))
            ->assertOk();

        $professor = User::factory()->create(['estado' => 'activo']);
        $professor->assignRole('professor');

        $this->actingAs($professor)
            ->get(route('secretaria.dashboard'))
            ->assertForbidden();
    }

    public function test_matricular_cria_aluno_e_historico(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();

        $this->actingAs($secretaria)
            ->post(route('secretaria.matriculas.store'), [
                'numero_processo' => 'SEC-001',
                'nome' => 'Aluno Secretaria',
                'genero' => 'M',
                'bi_numero' => '123456789LA042',
                'turma_id' => $turma->id,
                'activo' => '1',
                'data_nascimento' => '2010-01-01',
            ])
            ->assertRedirect();

        $aluno = Aluno::where('numero_processo', 'SEC-001')->first();
        $this->assertNotNull($aluno);
        $this->assertSame('123456789LA042', $aluno->bi_numero);
        $this->assertDatabaseHas('matricula_historicos', [
            'aluno_id' => $aluno->id,
            'tipo' => MatriculaHistorico::TIPO_MATRICULA,
        ]);
    }

    public function test_ajax_alunos_por_turma(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();
        Aluno::create([
            'numero_processo' => 'SEC-AJAX',
            'nome' => 'Aluno Ajax',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->actingAs($secretaria)
            ->getJson(route('secretaria.ajax.alunos-por-turma', ['turma_id' => $turma->id]))
            ->assertOk()
            ->assertJsonFragment(['numero_processo' => 'SEC-AJAX', 'nome' => 'Aluno Ajax']);
    }

    public function test_renovar_grava_historico(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();
        $aluno = Aluno::create([
            'numero_processo' => 'SEC-REN',
            'nome' => 'Aluno Renovar',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $turmaNova = Turma::create([
            'codigo' => 'T10B',
            'nome' => 'T10B',
            'curso_id' => $turma->curso_id,
            'classe_id' => $turma->classe_id,
            'ano_lectivo_id' => $turma->ano_lectivo_id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->actingAs($secretaria)
            ->post(route('secretaria.matriculas.renovar.store', $aluno), [
                'turma_id' => $turmaNova->id,
                'motivo' => 'Renovação teste',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('matricula_historicos', [
            'aluno_id' => $aluno->id,
            'tipo' => MatriculaHistorico::TIPO_RENOVACAO,
            'para_turma_id' => $turmaNova->id,
        ]);
        $this->assertSame($turmaNova->id, $aluno->fresh()->turma_id);
    }

    public function test_upload_documento_aluno(): void
    {
        Storage::fake('public');
        [$secretaria, $turma] = $this->secretariaComTurma();
        $aluno = Aluno::create([
            'numero_processo' => 'SEC-DOC',
            'nome' => 'Aluno Docs',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $file = UploadedFile::fake()->create('bi.pdf', 100, 'application/pdf');

        $this->actingAs($secretaria)
            ->post(route('secretaria.alunos.documentos.store', $aluno), [
                'tipo' => 'bi',
                'titulo' => 'Cópia do BI',
                'ficheiro' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('aluno_documentos', [
            'aluno_id' => $aluno->id,
            'titulo' => 'Cópia do BI',
            'tipo' => 'bi',
        ]);
    }

    public function test_pdf_declaracao_frequencia(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();
        $aluno = Aluno::create([
            'numero_processo' => 'SEC-PDF',
            'nome' => 'Aluno PDF',
            'bi_numero' => '999999999LA042',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $encarregado = \App\Models\Encarregado::create([
            'nome' => 'Pai PDF',
            'telefone' => '923111222',
            'activo' => true,
        ]);
        $aluno->encarregados()->attach($encarregado->id, [
            'parentesco' => 'pai',
            'principal' => true,
            'pode_ver_notas' => true,
            'pode_assinar_boletim' => true,
        ]);

        $this->actingAs($secretaria)
            ->get(route('secretaria.documentos.emitir', [
                'aluno_id' => $aluno->id,
                'tipo' => 'frequencia',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_import_template_matriculas(): void
    {
        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $this->actingAs($secretaria)
            ->get(route('secretaria.matriculas.import.template'))
            ->assertOk();
    }

    public function test_guardar_configuracao_cartao(): void
    {
        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $this->actingAs($secretaria)
            ->post(route('secretaria.cartao.guardar'), [
                'titulo' => 'Cartão Facilita Teste',
                'cor_primaria' => '#112233',
                'cor_secundaria' => '#445566',
                'cor_texto' => '#ffffff',
                'mostrar_foto' => '1',
                'mostrar_processo' => '1',
                'mostrar_bi' => '1',
                'mostrar_turma' => '1',
                'mostrar_curso' => '0',
                'mostrar_ano' => '1',
                'mostrar_validade' => '1',
                'mostrar_verso' => '1',
                'mostrar_logo' => '1',
                'validade_texto' => '2025/2026',
                'verso_texto' => 'Texto verso teste',
                'orientacao' => 'landscape',
            ])
            ->assertRedirect();

        $cfg = app(CartaoEstudanteService::class)->getConfig();
        $this->assertSame('Cartão Facilita Teste', $cfg['titulo']);
        $this->assertSame('#112233', $cfg['cor_primaria']);
        $this->assertTrue($cfg['mostrar_foto']);
        $this->assertTrue($cfg['mostrar_bi']);
        $this->assertFalse($cfg['mostrar_curso']);
    }

    public function test_guardar_configuracao_documentos(): void
    {
        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        $this->actingAs($secretaria)
            ->post(route('secretaria.documentos.configurar.guardar'), [
                'titulo_frequencia' => 'Declaração Teste',
                'titulo_matricula' => 'Matrícula Teste',
                'titulo_comprovativo' => 'Comprovativo Teste',
                'titulo_ficha' => 'Ficha Teste',
                'cor_destaque' => '#112233',
                'mostrar_logo' => '1',
                'mostrar_bi' => '1',
                'mostrar_filiacao' => '0',
                'mostrar_encarregado' => '1',
                'mostrar_nascimento' => '1',
                'mostrar_genero' => '1',
                'mostrar_foto_ficha' => '1',
                'mostrar_assinatura' => '1',
                'cargo_assinatura' => 'A Secretária',
                'texto_rodape' => 'Rodapé teste',
                'texto_fecho_frequencia' => 'Fecho frequência',
                'texto_fecho_matricula' => 'Fecho matrícula',
                'local_emissao' => 'Luanda',
            ])
            ->assertRedirect();

        $cfg = app(\App\Services\DocumentoAcademicoService::class)->getConfig();
        $this->assertSame('Declaração Teste', $cfg['titulo_frequencia']);
        $this->assertFalse($cfg['mostrar_filiacao']);
        $this->assertSame('#112233', $cfg['cor_destaque']);
    }

    public function test_diretor_pedagogico_rotulo(): void
    {
        $this->assertSame('Director Pedagógico', CargosInstitucionais::rotulo('subdiretor_pedagogico'));
        $this->assertSame('Secretária', CargosInstitucionais::rotulo('secretaria'));
    }

    public function test_secretaria_tem_permissoes_criar_aluno_e_turma(): void
    {
        [$secretaria] = $this->secretariaComTurma();

        $this->assertTrue($secretaria->can('alunos.criar'));
        $this->assertTrue($secretaria->can('turmas.criar'));
        $this->assertTrue($secretaria->podeTransferirEntreCursos());
    }

    public function test_secretaria_arquiva_aluno(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();
        $aluno = Aluno::create([
            'numero_processo' => 'SEC-ARQ',
            'nome' => 'Aluno Arquivo',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->actingAs($secretaria)
            ->post(route('alunos.arquivar', $aluno), ['motivo' => 'Desistência formal'])
            ->assertRedirect();

        $this->assertFalse($aluno->fresh()->activo);
        $this->assertDatabaseHas('matricula_historicos', [
            'aluno_id' => $aluno->id,
            'tipo' => MatriculaHistorico::TIPO_ARQUIVO,
        ]);
    }

    public function test_secretaria_distribui_alunos_entre_turmas(): void
    {
        [$secretaria, $turmaA] = $this->secretariaComTurma();
        $turmaB = Turma::create([
            'codigo' => 'T10B'.uniqid(),
            'nome' => 'T10B',
            'curso_id' => $turmaA->curso_id,
            'classe_id' => $turmaA->classe_id,
            'ano_lectivo_id' => $turmaA->ano_lectivo_id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $aluno = Aluno::create([
            'numero_processo' => 'SEC-DIST',
            'nome' => 'Aluno Dist',
            'turma_id' => $turmaA->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->actingAs($secretaria)
            ->post(route('secretaria.distribuicao.store'), [
                'turma_origem_id' => $turmaA->id,
                'turma_destino_id' => $turmaB->id,
                'aluno_ids' => [$aluno->id],
                'motivo' => 'Reorganização de turmas',
            ])
            ->assertRedirect();

        $this->assertSame($turmaB->id, $aluno->fresh()->turma_id);
    }

    public function test_secretaria_imprime_livro_e_etiquetas(): void
    {
        [$secretaria, $turma] = $this->secretariaComTurma();
        Aluno::create([
            'numero_processo' => 'SEC-PDF',
            'nome' => 'Aluno PDF',
            'turma_id' => $turma->id,
            'activo' => true,
            'situacao_academica' => Aluno::SITUACAO_MATRICULADO,
        ]);

        $this->actingAs($secretaria)
            ->get(route('export.livro-turma.pdf', ['turma_id' => $turma->id]))
            ->assertOk();

        $this->actingAs($secretaria)
            ->get(route('export.etiquetas-alunos.pdf', ['turma_id' => $turma->id]))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Turma}
     */
    private function secretariaComTurma(): array
    {
        $ano = AnoLectivo::query()->where('nome', '2025-2026')->first()
            ?? AnoLectivo::create([
                'nome' => '2025-2026',
                'estado' => AnoLectivo::ESTADO_ACTUAL,
            ]);

        $curso = Curso::create(['nome' => 'Curso Sec', 'codigo' => 'CS'.uniqid(), 'activo' => true]);
        $classe = Classe::firstOrCreate(['codigo' => 'T10'], ['nome' => '10ª Classe', 'activo' => true]);
        $turma = Turma::create([
            'codigo' => 'T10A'.uniqid(),
            'nome' => 'T10A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $ano->id,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $secretaria = User::factory()->create(['estado' => 'activo']);
        $secretaria->assignRole('secretaria');

        return [$secretaria, $turma];
    }
}