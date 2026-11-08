<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Altera o formato do ano lectivo de YEAR (2025) para STRING (2025-2026)
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Alterar tipo de coluna (apenas MySQL - SQLite não suporta MODIFY COLUMN)
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE turmas MODIFY COLUMN ano_lectivo VARCHAR(9) NOT NULL');
            DB::statement('ALTER TABLE notas MODIFY COLUMN ano_lectivo VARCHAR(9) NOT NULL');
            DB::statement('ALTER TABLE professor_disciplina_turma MODIFY COLUMN ano_lectivo VARCHAR(9) NOT NULL');
            DB::statement('ALTER TABLE director_turma MODIFY COLUMN ano_lectivo VARCHAR(9) NOT NULL');
            DB::statement('ALTER TABLE coordenador_curso MODIFY COLUMN ano_lectivo VARCHAR(9) NOT NULL');
        }

        // Converter dados existentes (2025 -> 2025-2026)
        if ($driver === 'sqlite') {
            // SQLite usa CAST(x AS INTEGER) e ||  para concatenação
            DB::statement("UPDATE turmas SET ano_lectivo = ano_lectivo || '-' || (CAST(ano_lectivo AS INTEGER) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE notas SET ano_lectivo = ano_lectivo || '-' || (CAST(ano_lectivo AS INTEGER) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE professor_disciplina_turma SET ano_lectivo = ano_lectivo || '-' || (CAST(ano_lectivo AS INTEGER) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE director_turma SET ano_lectivo = ano_lectivo || '-' || (CAST(ano_lectivo AS INTEGER) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE coordenador_curso SET ano_lectivo = ano_lectivo || '-' || (CAST(ano_lectivo AS INTEGER) + 1) WHERE LENGTH(ano_lectivo) = 4");
        } else {
            // MySQL usa CONCAT e CAST AS UNSIGNED
            DB::statement("UPDATE turmas SET ano_lectivo = CONCAT(ano_lectivo, '-', CAST(ano_lectivo AS UNSIGNED) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE notas SET ano_lectivo = CONCAT(ano_lectivo, '-', CAST(ano_lectivo AS UNSIGNED) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE professor_disciplina_turma SET ano_lectivo = CONCAT(ano_lectivo, '-', CAST(ano_lectivo AS UNSIGNED) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE director_turma SET ano_lectivo = CONCAT(ano_lectivo, '-', CAST(ano_lectivo AS UNSIGNED) + 1) WHERE LENGTH(ano_lectivo) = 4");
            DB::statement("UPDATE coordenador_curso SET ano_lectivo = CONCAT(ano_lectivo, '-', CAST(ano_lectivo AS UNSIGNED) + 1) WHERE LENGTH(ano_lectivo) = 4");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Converter dados de volta (2025-2026 -> 2025)
        // SUBSTR funciona em ambos MySQL e SQLite
        if ($driver === 'sqlite') {
            DB::statement("UPDATE turmas SET ano_lectivo = SUBSTR(ano_lectivo, 1, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE notas SET ano_lectivo = SUBSTR(ano_lectivo, 1, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE professor_disciplina_turma SET ano_lectivo = SUBSTR(ano_lectivo, 1, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE director_turma SET ano_lectivo = SUBSTR(ano_lectivo, 1, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE coordenador_curso SET ano_lectivo = SUBSTR(ano_lectivo, 1, 4) WHERE LENGTH(ano_lectivo) = 9");
        } else {
            DB::statement("UPDATE turmas SET ano_lectivo = LEFT(ano_lectivo, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE notas SET ano_lectivo = LEFT(ano_lectivo, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE professor_disciplina_turma SET ano_lectivo = LEFT(ano_lectivo, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE director_turma SET ano_lectivo = LEFT(ano_lectivo, 4) WHERE LENGTH(ano_lectivo) = 9");
            DB::statement("UPDATE coordenador_curso SET ano_lectivo = LEFT(ano_lectivo, 4) WHERE LENGTH(ano_lectivo) = 9");
        }

        // Reverter tipo para YEAR (apenas MySQL)
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE turmas MODIFY COLUMN ano_lectivo YEAR NOT NULL');
            DB::statement('ALTER TABLE notas MODIFY COLUMN ano_lectivo YEAR NOT NULL');
            DB::statement('ALTER TABLE professor_disciplina_turma MODIFY COLUMN ano_lectivo YEAR NOT NULL');
            DB::statement('ALTER TABLE director_turma MODIFY COLUMN ano_lectivo YEAR NOT NULL');
            DB::statement('ALTER TABLE coordenador_curso MODIFY COLUMN ano_lectivo YEAR NOT NULL');
        }
    }
};
