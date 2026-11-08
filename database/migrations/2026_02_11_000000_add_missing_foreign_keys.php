<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTA: Esta migration adiciona apenas foreign keys que realmente faltam.
     * Muitas foreign keys já foram adicionadas nas migrations de criação das tabelas.
     */
    public function up(): void
    {
        // A maioria das foreign keys já existem. Esta migration serve para garantir
        // integridade referencial em tabelas pivot e relacionamentos que possam estar faltando.

        // Apenas executar para MySQL - SQLite não suporta information_schema
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return; // Pular para SQLite
        }

        // Verificar e adicionar foreign keys faltantes em classe_disciplina (se a tabela existir)
        if (Schema::hasTable('classe_disciplina')) {
            Schema::table('classe_disciplina', function (Blueprint $table) {
                // Apenas adicionar se não existir
                if (!$this->foreignKeyExists('classe_disciplina', 'classe_disciplina_classe_id_foreign')) {
                    $table->foreign('classe_id', 'classe_disciplina_classe_id_foreign')
                        ->references('id')->on('classes')->onDelete('cascade');
                }
                if (!$this->foreignKeyExists('classe_disciplina', 'classe_disciplina_disciplina_id_foreign')) {
                    $table->foreign('disciplina_id', 'classe_disciplina_disciplina_id_foreign')
                        ->references('id')->on('disciplinas')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover apenas as foreign keys que foram adicionadas
        if (Schema::hasTable('classe_disciplina')) {
            try {
                Schema::table('classe_disciplina', function (Blueprint $table) {
                    $table->dropForeign('classe_disciplina_classe_id_foreign');
                    $table->dropForeign('classe_disciplina_disciplina_id_foreign');
                });
            } catch (\Exception $e) {
                // Ignorar se não existir
            }
        }
    }

    /**
     * Verifica se uma foreign key já existe na tabela
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $databaseName = Schema::getConnection()->getDatabaseName();

        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        return $exists;
    }
};
