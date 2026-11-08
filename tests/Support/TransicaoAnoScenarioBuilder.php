<?php

namespace Tests\Support;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\Curso;
use App\Models\DirectorTurma;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;

/**
 * Cenário mínimo para testar transferência / transição de ano lectivo.
 */
class TransicaoAnoScenarioBuilder
{
    public const ANO_ORIGEM = '2024-2025';

    public const ANO_DESTINO = '2025-2026';

    public object $anos;

    public object $curso;

    public object $classes;

    public object $turmas;

    public object $users;

    public ?Disciplina $disciplina = null;

    public object $alunos;

    public function build(): self
    {
        Configuracao::set('ano_lectivo_actual', self::ANO_ORIGEM, 'test');
        Configuracao::set('nota_minima_aprovacao', '10', 'test');

        $this->anos = (object) [
            'origem' => AnoLectivo::create([
                'nome' => self::ANO_ORIGEM,
                'estado' => AnoLectivo::ESTADO_ACTUAL,
            ]),
            'destino' => AnoLectivo::create([
                'nome' => self::ANO_DESTINO,
                'estado' => AnoLectivo::ESTADO_NOVO,
            ]),
        ];

        $this->curso = (object) [
            'ti' => Curso::firstOrCreate(
                ['codigo' => 'TI'],
                ['nome' => 'Técnico de Informática', 'activo' => true]
            ),
        ];

        $this->classes = (object) [
            'c10' => Classe::firstOrCreate(
                ['codigo' => '10'],
                ['nome' => '10ª Classe', 'activo' => true]
            ),
            'c11' => Classe::firstOrCreate(
                ['codigo' => '11'],
                ['nome' => '11ª Classe', 'activo' => true]
            ),
        ];

        $this->turmas = (object) [
            'origem_10' => Turma::create([
                'codigo' => 'TI10AT-O',
                'nome' => 'TI10AT',
                'curso_id' => $this->curso->ti->id,
                'classe_id' => $this->classes->c10->id,
                'ano_lectivo' => self::ANO_ORIGEM,
                'ano_lectivo_id' => $this->anos->origem->id,
                'activo' => true,
            ]),
            'destino_10' => Turma::create([
                'codigo' => 'TI10AT-D',
                'nome' => 'TI10AT',
                'curso_id' => $this->curso->ti->id,
                'classe_id' => $this->classes->c10->id,
                'ano_lectivo' => self::ANO_DESTINO,
                'ano_lectivo_id' => $this->anos->destino->id,
                'activo' => true,
            ]),
            'destino_11' => Turma::create([
                'codigo' => 'TI11AT-D',
                'nome' => 'TI11AT',
                'curso_id' => $this->curso->ti->id,
                'classe_id' => $this->classes->c11->id,
                'ano_lectivo' => self::ANO_DESTINO,
                'ano_lectivo_id' => $this->anos->destino->id,
                'activo' => true,
            ]),
        ];

        $professor = User::factory()->create(['estado' => 'activo', 'name' => 'Prof Teste']);
        $this->disciplina = Disciplina::firstOrCreate(
            ['codigo' => 'MAT-TST'],
            ['nome' => 'Matemática Teste', 'activo' => true]
        );

        ProfessorDisciplinaTurma::create([
            'user_id' => $professor->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turmas->origem_10->id,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'activo' => true,
        ]);

        DirectorTurma::create([
            'user_id' => $professor->id,
            'turma_id' => $this->turmas->origem_10->id,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'activo' => true,
        ]);

        CoordenadorCurso::create([
            'user_id' => $professor->id,
            'curso_id' => $this->curso->ti->id,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'activo' => true,
        ]);

        $aluno = Aluno::create([
            'numero_processo' => 'PROC-001',
            'nome' => 'Aluno Aprovado',
            'data_nascimento' => '2008-01-01',
            'genero' => 'M',
            'turma_id' => $this->turmas->origem_10->id,
            'numero_ordem' => 1,
            'activo' => true,
        ]);

        Nota::create([
            'aluno_id' => $aluno->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turmas->origem_10->id,
            'professor_id' => $professor->id,
            'tipo' => Nota::TIPO_CFD,
            'valor' => 14,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'trimestre' => 3,
        ]);

        $this->users = (object) ['professor' => $professor, 'aluno' => $aluno];
        $this->alunos = (object) ['aprovado' => $aluno];

        return $this;
    }

    /** Segunda turma 10ª no mesmo curso (mapeamento independente). */
    public function withSegundaTurma(): self
    {
        $this->turmas->origem_10_b = Turma::create([
            'codigo' => 'TI10BT-O',
            'nome' => 'TI10BT',
            'curso_id' => $this->curso->ti->id,
            'classe_id' => $this->classes->c10->id,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'activo' => true,
        ]);

        $this->turmas->destino_10_b = Turma::create([
            'codigo' => 'TI10BT-D',
            'nome' => 'TI10BT',
            'curso_id' => $this->curso->ti->id,
            'classe_id' => $this->classes->c10->id,
            'ano_lectivo' => self::ANO_DESTINO,
            'ano_lectivo_id' => $this->anos->destino->id,
            'activo' => true,
        ]);

        $this->turmas->destino_11_b = Turma::create([
            'codigo' => 'TI11BT-D',
            'nome' => 'TI11BT',
            'curso_id' => $this->curso->ti->id,
            'classe_id' => $this->classes->c11->id,
            'ano_lectivo' => self::ANO_DESTINO,
            'ano_lectivo_id' => $this->anos->destino->id,
            'activo' => true,
        ]);

        $alunoB = Aluno::create([
            'numero_processo' => 'PROC-002',
            'nome' => 'Aluno B Aprovado',
            'data_nascimento' => '2008-06-01',
            'genero' => 'F',
            'turma_id' => $this->turmas->origem_10_b->id,
            'numero_ordem' => 1,
            'activo' => true,
        ]);

        Nota::create([
            'aluno_id' => $alunoB->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turmas->origem_10_b->id,
            'professor_id' => $this->users->professor->id,
            'tipo' => Nota::TIPO_CFD,
            'valor' => 12,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'trimestre' => 3,
        ]);

        $this->alunos->aprovado_b = $alunoB;

        return $this;
    }

    /** Aluno reprovado na turma principal (10ª). */
    public function withAlunoReprovado(): self
    {
        Configuracao::set('max_disciplinas_reprovacao', '0', 'test');
        $reprovado = Aluno::create([
            'numero_processo' => 'PROC-REP',
            'nome' => 'Aluno Reprovado',
            'data_nascimento' => '2008-03-15',
            'genero' => 'M',
            'turma_id' => $this->turmas->origem_10->id,
            'numero_ordem' => 2,
            'activo' => true,
        ]);

        Nota::create([
            'aluno_id' => $reprovado->id,
            'disciplina_id' => $this->disciplina->id,
            'turma_id' => $this->turmas->origem_10->id,
            'professor_id' => $this->users->professor->id,
            'tipo' => Nota::TIPO_CFD,
            'valor' => 6,
            'ano_lectivo' => self::ANO_ORIGEM,
            'ano_lectivo_id' => $this->anos->origem->id,
            'trimestre' => 3,
        ]);

        $this->alunos->reprovado = $reprovado;

        return $this;
    }

    /** Turmas destino com o mesmo código que a origem (simula transferência 1:1). */
    public function withTurmasDestinoMesmoCodigo(): self
    {
        $this->turmas->destino_10->update(['codigo' => 'TI10AT-O']);
        $this->turmas->destino_11->update(['codigo' => 'TI11AT-O']);

        return $this;
    }
}
