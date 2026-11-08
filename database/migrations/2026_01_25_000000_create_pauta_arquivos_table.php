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
        Schema::create('pauta_arquivos', function (Blueprint $table) {
            $table->id();

            // Relações com outras tabelas
            $table->foreignId('curso_id')
                ->constrained('cursos')
                ->onDelete('cascade')
                ->comment('Curso da pauta (obrigatório)');

            $table->foreignId('turma_id')
                ->nullable()
                ->constrained('turmas')
                ->onDelete('cascade')
                ->comment('Turma específica (opcional - para pautas externas)');

            $table->foreignId('sala_id')
                ->nullable()
                ->constrained('salas')
                ->onDelete('set null')
                ->comment('Sala onde ocorreu (opcional)');

            $table->foreignId('disciplina_id')
                ->nullable()
                ->constrained('disciplinas')
                ->onDelete('cascade')
                ->comment('Disciplina específica (opcional - null para pauta completa)');

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Utilizador que fez upload');

            // Dados do período académico
            $table->string('ano_lectivo', 9)->comment('Ex: 2025-2026');
            $table->tinyInteger('trimestre')->default(0)->comment('0=Anual, 1=1º Trim, 2=2º Trim, 3=3º Trim');
            $table->unsignedInteger('versao')->default(1)->comment('Número da versão (auto-incremento por pauta)');

            // Metadata da pauta
            $table->enum('tipo_pauta', [
                'mini_pauta',
                'final_disciplina',
                'final_turma',
                'completa',
                'outra'
            ])->default('outra')->comment('Tipo de pauta');

            $table->enum('origem', ['sistema', 'externa'])
                ->default('externa')
                ->comment('Origem do ficheiro: sistema (exportado) ou externa (upload manual)');

            // Informações do ficheiro
            $table->string('nome_original', 255)->comment('Nome original do ficheiro');
            $table->string('nome_armazenado', 255)->comment('Nome armazenado no storage');
            $table->string('caminho_arquivo', 500)->comment('Caminho completo no storage');
            $table->unsignedInteger('tamanho_bytes')->comment('Tamanho do ficheiro em bytes');
            $table->string('mime_type', 100)->comment('Tipo MIME do ficheiro');
            $table->string('hash_arquivo', 64)->comment('SHA-256 hash para verificação de integridade');

            // Descrição opcional
            $table->text('descricao')->nullable()->comment('Observações sobre o ficheiro');

            // Status
            $table->boolean('activo')->default(true)->comment('Se o ficheiro está activo');

            $table->timestamps();

            // Índices para performance
            $table->index(['curso_id', 'ano_lectivo', 'trimestre', 'versao'], 'idx_pauta_lookup');
            $table->index(['turma_id', 'activo'], 'idx_turma_activo');
            $table->index(['tipo_pauta', 'activo'], 'idx_tipo_activo');
            $table->index('uploaded_by');
            $table->index('hash_arquivo');
            $table->index('created_at');

            // NÃO criar unique constraint para permitir múltiplas versões
            // Versionamento manual via incremento do campo 'versao'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pauta_arquivos');
    }
};
