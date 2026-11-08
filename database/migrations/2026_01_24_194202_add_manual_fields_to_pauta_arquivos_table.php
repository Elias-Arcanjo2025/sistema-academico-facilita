<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Só executar se a tabela pauta_arquivos já existir
        // (evita erro de ordem de migrations)
        if (Schema::hasTable('pauta_arquivos')) {
            Schema::table('pauta_arquivos', function (Blueprint $table) {
                $table->string('turma_manual', 255)->nullable()->after('turma_id')->comment('Nome da turma digitado manualmente (dados antigos)');
                $table->string('sala_manual', 255)->nullable()->after('sala_id')->comment('Nome da sala digitado manualmente (dados antigos)');
                $table->string('disciplina_manual', 255)->nullable()->after('disciplina_id')->comment('Nome da disciplina digitado manualmente (dados antigos)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Só executar se a tabela pauta_arquivos existir
        if (Schema::hasTable('pauta_arquivos')) {
            Schema::table('pauta_arquivos', function (Blueprint $table) {
                $table->dropColumn(['turma_manual', 'sala_manual', 'disciplina_manual']);
            });
        }
    }
};
