<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANTE: Esta migration converte o tipo de ano_lectivo de year (integer)
     * para string(9) no formato "YYYY-YYYY" em 5 tabelas.
     *
     * Exemplo: 2025 → "2024-2025"
     */
    public function up(): void
    {
        $this->convertTable('turmas');
        $this->convertTable('notas');
        $this->convertTable('professor_disciplina_turma');
        $this->convertTable('director_turma');
        $this->convertTable('coordenador_curso');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->revertTable('turmas');
        $this->revertTable('notas');
        $this->revertTable('professor_disciplina_turma');
        $this->revertTable('director_turma');
        $this->revertTable('coordenador_curso');
    }

    /**
     * Converte uma tabela de year para string(9)
     */
    private function convertTable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1. Converter dados existentes: 2025 → "2024-2025"
        if ($driver === 'sqlite') {
            // SQLite usa || para concatenação e CAST sem AS CHAR
            DB::statement("
                UPDATE {$table}
                SET ano_lectivo = (CAST(ano_lectivo - 1 AS TEXT) || '-' || CAST(ano_lectivo AS TEXT))
                WHERE ano_lectivo IS NOT NULL
                AND ano_lectivo NOT LIKE '%-%'
            ");
        } else {
            // MySQL usa CONCAT e CAST AS CHAR
            DB::statement("
                UPDATE {$table}
                SET ano_lectivo = CONCAT(
                    CAST(ano_lectivo - 1 AS CHAR),
                    '-',
                    CAST(ano_lectivo AS CHAR)
                )
                WHERE ano_lectivo IS NOT NULL
                AND ano_lectivo NOT LIKE '%-%'
            ");
        }

        // 2. Alterar tipo de coluna de year para string(9)
        Schema::table($table, function (Blueprint $table) {
            $table->string('ano_lectivo', 9)->change();
        });

        // 3. Adicionar constraint de validação (formato YYYY-YYYY) - apenas MySQL
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE {$table}
                    ADD CONSTRAINT chk_{$table}_ano_lectivo_format
                    CHECK (ano_lectivo REGEXP '^[0-9]{4}-[0-9]{4}$')
                ");
            } catch (\Exception $e) {
                // Se falhar (versão antiga do MySQL), ignorar - validação será feita no Laravel
                \Log::warning("Could not add CHECK constraint for {$table}.ano_lectivo: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverte uma tabela de string(9) para year
     */
    private function revertTable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1. Remover constraint se existir (apenas MySQL)
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE {$table}
                    DROP CONSTRAINT IF EXISTS chk_{$table}_ano_lectivo_format
                ");
            } catch (\Exception $e) {
                // Ignorar se não existir
            }
        }

        // 2. Converter dados de volta: "2024-2025" → 2025
        if ($driver === 'sqlite') {
            // SQLite usa SUBSTR
            DB::statement("
                UPDATE {$table}
                SET ano_lectivo = CAST(SUBSTR(ano_lectivo, 6, 4) AS INTEGER)
                WHERE ano_lectivo IS NOT NULL
                AND ano_lectivo LIKE '%-%'
            ");
        } else {
            // MySQL usa SUBSTRING e CAST AS UNSIGNED
            DB::statement("
                UPDATE {$table}
                SET ano_lectivo = CAST(SUBSTRING(ano_lectivo, 6, 4) AS UNSIGNED)
                WHERE ano_lectivo IS NOT NULL
                AND ano_lectivo LIKE '%-%'
            ");
        }

        // 3. Alterar tipo de coluna de string para year
        Schema::table($table, function (Blueprint $table) {
            $table->year('ano_lectivo')->change();
        });
    }
};
