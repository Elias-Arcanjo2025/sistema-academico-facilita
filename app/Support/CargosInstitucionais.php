<?php

namespace App\Support;

/**
 * Cargos com visão ou controlo sobre todos os cursos/turmas da instituição.
 */
final class CargosInstitucionais
{
    public const ADMIN = 'admin_full';

    public const DIRECTORA = 'directora_geral';

    public const SUBDIRECTOR = 'subdiretor_pedagogico';

    /** Alias funcional: Director Pedagógico = subdiretor_pedagogico. */
    public const DIRECTOR_PEDAGOGICO = self::SUBDIRECTOR;

    public const SECRETARIA = 'secretaria';

    public const INSPETOR = 'inspetor';

    public const COORDENADOR_DISCIPLINA = 'coordenador_disciplina';

    /** Controlo académico total em todos os cursos (notas, pautas, mini-pautas, etc.). */
    public const GESTOR_ACADEMICO = 'gestor_academico';

    /**
     * Visão transversal de assiduidade e disciplina (sem lançar notas).
     *
     * @return array<int, string>
     */
    public static function visaoAssiduidade(): array
    {
        return [...self::visaoInstitucional(), self::INSPETOR];
    }

    /**
     * Pode lançar/editar notas e gerir pautas em qualquer turma/curso.
     *
     * @return array<int, string>
     */
    public static function controloAcademicoTotal(): array
    {
        return [self::ADMIN, self::DIRECTORA, self::GESTOR_ACADEMICO];
    }

    /**
     * Pode consultar qualquer turma/curso (inclui subdirector — só leitura).
     *
     * @return array<int, string>
     */
    public static function visaoInstitucional(): array
    {
        return [...self::controloAcademicoTotal(), self::SUBDIRECTOR];
    }

    /**
     * Pode lançar notas mesmo com trimestre fechado (além do admin técnico).
     *
     * @return array<int, string>
     */
    public static function ignoraTrimestreFechado(): array
    {
        return [self::ADMIN, self::DIRECTORA, self::GESTOR_ACADEMICO];
    }

    /**
     * Vê avisos de cobrança/licença Facilita (coordenador de curso e hierarquia acima).
     *
     * @return array<int, string>
     */
    public static function veAvisoCobrancaFacilita(): array
    {
        return [
            self::ADMIN,
            self::DIRECTORA,
            self::SUBDIRECTOR,
            self::GESTOR_ACADEMICO,
            'coordenador_curso',
        ];
    }

    /**
     * @return array<string, string>
     */
    /**
     * Secretaria e cargos de gestão académica com acesso transversal a alunos.
     *
     * @return array<int, string>
     */
    public static function secretariaOperacional(): array
    {
        return [self::ADMIN, self::DIRECTORA, self::GESTOR_ACADEMICO, self::SECRETARIA];
    }

    public static function rotulos(): array
    {
        return [
            self::ADMIN => 'Administrador (sistema)',
            self::DIRECTORA => 'Directora Geral',
            self::SUBDIRECTOR => 'Director Pedagógico',
            self::GESTOR_ACADEMICO => 'Gestor Académico',
            self::SECRETARIA => 'Secretária',
            self::INSPETOR => 'Inspetor',
            self::COORDENADOR_DISCIPLINA => 'Coordenador de Disciplina',
            'professor' => 'Professor',
            'director_turma' => 'Director de Turma',
            'coordenador_curso' => 'Coordenador de Curso',
            'encarregado' => 'Encarregado de Educação',
            'aluno' => 'Aluno',
        ];
    }

    public static function rotulo(string $cargo): string
    {
        return self::rotulos()[$cargo] ?? str_replace('_', ' ', ucfirst($cargo));
    }
}
