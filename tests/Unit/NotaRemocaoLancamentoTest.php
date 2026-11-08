<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\NotaService;
use App\Support\NotaLancamentoRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotaRemocaoLancamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $professor;

    private Aluno $aluno;

    private Turma $turma;

    private Disciplina $disciplina;

    private int $anoLectivoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->anoLectivoId = AnoLectivo::create([
            'nome' => '2025-2026',
            'estado' => AnoLectivo::ESTADO_ACTUAL,
        ])->id;

        Configuracao::set('ano_lectivo_actual', '2025-2026');
        Configuracao::set('trimestre_actual', '3');

        $curso = Curso::create(['nome' => 'Curso Teste', 'codigo' => 'CT', 'activo' => true]);
        $classe = Classe::firstOrCreate(
            ['codigo' => 'T11'],
            ['nome' => '11ª Classe Teste', 'activo' => true],
        );
        $this->disciplina = Disciplina::create([
            'nome' => 'Matemática',
            'codigo' => 'MAT',
            'tipo' => 'geral',
            'activo' => true,
        ]);

        $this->turma = Turma::create([
            'codigo' => 'T11A',
            'nome' => 'T11A',
            'curso_id' => $curso->id,
            'classe_id' => $classe->id,
            'ano_lectivo_id' => $this->anoLectivoId,
            'ano_lectivo' => '2025-2026',
            'activo' => true,
        ]);

        $this->aluno = Aluno::create([
            'numero_processo' => '2025001',
            'nome' => 'Aluno Desistente',
            'numero_ordem' => 1,
            'turma_id' => $this->turma->id,
            'situacao_academica' => 'desistente',
            'activo' => true,
        ]);

        $this->professor = User::factory()->create(['estado' => 'activo']);
        $this->professor->assignRole('professor');
    }

    /**
     * @return array<string, array{0: string, 1: int|null}>
     */
    public static function tiposLancamentoProvider(): array
    {
        return [
            'MAC1' => [Nota::TIPO_MAC, 1],
            'PP1' => [Nota::TIPO_PP, 1],
            'PT1' => [Nota::TIPO_PT, 1],
            'MAC2' => [Nota::TIPO_MAC, 2],
            'PP2' => [Nota::TIPO_PP, 2],
            'PT2' => [Nota::TIPO_PT, 2],
            'MAC3' => [Nota::TIPO_MAC, 3],
            'PP3' => [Nota::TIPO_PP, 3],
            'PG' => [Nota::TIPO_PG, null],
        ];
    }

    /**
     * @dataProvider tiposLancamentoProvider
     */
    public function test_campo_vazio_remove_nota_pre_conselho(string $tipo, ?int $trimestre): void
    {
        $nota = Nota::create([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'professor_id' => $this->professor->id,
            'tipo' => $tipo,
            'valor' => 12,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->anoLectivoId,
            'trimestre' => $trimestre,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);

        $service = app(NotaService::class);
        $resultado = $service->gravarOuRemoverLancamento(
            '',
            $this->aluno->id,
            $this->disciplina->id,
            $this->turma->id,
            $tipo,
            $trimestre,
            $this->professor,
            $this->anoLectivoId,
        );

        $this->assertSame('removida', $resultado);
        $nota->refresh();
        $this->assertNotNull($nota->archived_at);

        $this->assertNull(
            Nota::query()
                ->where('id', $nota->id)
                ->whereNull('archived_at')
                ->value('id')
        );
    }

    public function test_director_turma_nao_lanca_notas_so_acompanha(): void
    {
        $director = User::factory()->create(['estado' => 'activo']);
        $director->assignRole('director_turma');

        $director->turmasComoDirector()->attach($this->turma->id, [
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->anoLectivoId,
            'activo' => true,
        ]);

        $this->assertFalse(
            NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina(
                $director,
                $this->turma->id,
                $this->disciplina->id,
            )
        );
        $this->assertTrue($director->can('notas.ver'));
        $this->assertFalse($director->can('notas.lancar'));
    }

    public function test_lancar_em_lote_remove_quando_chave_ausente_no_post(): void
    {
        $nota = Nota::create([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'professor_id' => $this->professor->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 2,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->anoLectivoId,
            'trimestre' => 3,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);

        $service = app(NotaService::class);
        // Simula POST de input number vazio: aluno omitido do array
        $resultado = $service->lancarEmLote(
            [],
            $this->disciplina->id,
            $this->turma->id,
            Nota::TIPO_MAC,
            3,
            $this->professor,
        );

        $this->assertSame(1, $resultado['removidas']);
        $nota->refresh();
        $this->assertNotNull($nota->archived_at);
    }

    public function test_nota_pos_conselho_nao_e_removida_com_campo_vazio(): void
    {
        Nota::create([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'professor_id' => $this->professor->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 14,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->anoLectivoId,
            'trimestre' => 3,
            'estado_conselho' => Nota::ESTADO_POS_CONSELHO,
        ]);

        $service = app(NotaService::class);
        $resultado = $service->gravarOuRemoverLancamento(
            '',
            $this->aluno->id,
            $this->disciplina->id,
            $this->turma->id,
            Nota::TIPO_MAC,
            3,
            $this->professor,
            $this->anoLectivoId,
        );

        $this->assertSame('bloqueada', $resultado);
        $this->assertSame(
            1,
            Nota::query()
                ->where('aluno_id', $this->aluno->id)
                ->where('tipo', Nota::TIPO_MAC)
                ->where('trimestre', 3)
                ->whereNull('archived_at')
                ->count()
        );
    }

    public function test_remove_nota_legada_com_estado_conselho_vazio(): void
    {
        $nota = Nota::create([
            'aluno_id' => $this->aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turma->id,
            'professor_id' => $this->professor->id,
            'tipo' => Nota::TIPO_MAC,
            'valor' => 2,
            'ano_lectivo' => '2025-2026',
            'ano_lectivo_id' => $this->anoLectivoId,
            'trimestre' => 3,
            'estado_conselho' => Nota::ESTADO_PRE_CONSELHO,
        ]);

        \Illuminate\Support\Facades\DB::table('notas')
            ->where('id', $nota->id)
            ->update(['estado_conselho' => '']);

        $service = app(NotaService::class);
        $resultado = $service->lancarEmLote(
            [(string) $this->aluno->id => ''],
            $this->disciplina->id,
            $this->turma->id,
            Nota::TIPO_MAC,
            3,
            $this->professor,
        );

        $this->assertSame(1, $resultado['removidas']);
        $nota->refresh();
        $this->assertNotNull($nota->archived_at);
    }

    public function test_lancar_em_lote_aceita_virgula_decimal_nos_valores(): void
    {
        $service = app(NotaService::class);
        $resultado = $service->lancarEmLote(
            [$this->aluno->id => '12,5'],
            $this->disciplina->id,
            $this->turma->id,
            Nota::TIPO_MAC,
            3,
            $this->professor,
        );

        $this->assertSame(1, $resultado['sucesso']);
        $this->assertSame(
            12.5,
            (float) Nota::query()
                ->where('aluno_id', $this->aluno->id)
                ->where('tipo', Nota::TIPO_MAC)
                ->where('trimestre', 3)
                ->whereNull('archived_at')
                ->value('valor')
        );
    }
}
