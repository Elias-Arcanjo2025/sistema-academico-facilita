<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ped_planificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre');
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('data_inicial');
            $table->date('data_final');
            $table->string('estado', 20)->default('rascunho');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['ano_lectivo_id', 'trimestre', 'turma_id', 'disciplina_id', 'user_id'],
                'ped_planificacao_unique'
            );
            $table->index(['turma_id', 'trimestre', 'estado']);
        });

        Schema::create('ped_quinzenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->date('data_inicial');
            $table->date('data_final');
            $table->string('tema');
            $table->json('subtemas')->nullable();
            $table->json('competencias')->nullable();
            $table->json('objetivos')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['planificacao_id', 'numero']);
        });

        Schema::create('ped_horarios_semanais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['planificacao_id', 'dia_semana']);
        });

        Schema::create('ped_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->foreignId('quinzena_id')->nullable()->constrained('ped_quinzenas')->nullOnDelete();
            $table->date('data');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->string('tema_ministrado')->nullable();
            $table->text('conteudo_ministrado')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('estado', 20)->default('planeada');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['planificacao_id', 'data']);
            $table->index(['planificacao_id', 'estado']);
        });

        Schema::create('ped_geracoes_ia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50)->default('openrouter');
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('custo_estimado', 10, 6)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->string('status', 20)->default('processing');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->nullableMorphs('geravel');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['tipo', 'status']);
        });

        Schema::create('ped_planos_aula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->foreignId('quinzena_id')->nullable()->constrained('ped_quinzenas')->nullOnDelete();
            $table->foreignId('aula_id')->nullable()->constrained('ped_aulas')->nullOnDelete();
            $table->json('conteudo_json');
            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('estado', 20)->default('rascunho');
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_exercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->foreignId('quinzena_id')->nullable()->constrained('ped_quinzenas')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('dificuldade', 20)->default('medio');
            $table->json('conteudo_json');
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_provas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->json('config_json')->nullable();
            $table->json('conteudo_json')->nullable();
            $table->unsignedSmallInteger('tempo_minutos')->nullable();
            $table->decimal('cotacao_total', 5, 2)->nullable();
            $table->string('estado', 20)->default('rascunho');
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_matrizes_prova', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('ped_provas')->cascadeOnDelete();
            $table->json('linhas_json');
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_chaves_correcao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('ped_provas')->cascadeOnDelete();
            $table->json('respostas_json');
            $table->json('criterios_json')->nullable();
            $table->decimal('pontuacao_total', 5, 2)->nullable();
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_correcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('ped_provas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('professor_id')->constrained('users')->cascadeOnDelete();
            $table->string('ficheiro_path')->nullable();
            $table->string('ocr_provider', 50)->nullable();
            $table->longText('ocr_texto')->nullable();
            $table->decimal('ocr_confianca_global', 5, 4)->nullable();
            $table->decimal('sugestao_nota', 5, 2)->nullable();
            $table->decimal('nota_confirmada', 5, 2)->nullable();
            $table->string('estado', 20)->default('pendente');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ped_correcao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correcao_id')->constrained('ped_correcoes')->cascadeOnDelete();
            $table->string('questao_ref', 20);
            $table->text('resposta_extraida')->nullable();
            $table->text('resposta_esperada')->nullable();
            $table->string('confianca', 10)->default('media');
            $table->decimal('sugestao_pontos', 5, 2)->nullable();
            $table->decimal('pontos_confirmados', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ped_execucao_analises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planificacao_id')->constrained('ped_planificacoes')->cascadeOnDelete();
            $table->decimal('percentagem_execucao', 5, 2)->default(0);
            $table->json('conteudos_atraso_json')->nullable();
            $table->json('conteudos_adiantados_json')->nullable();
            $table->date('previsao_conclusao')->nullable();
            $table->json('recomendacoes_json')->nullable();
            $table->foreignId('geracao_ia_id')->nullable()->constrained('ped_geracoes_ia')->nullOnDelete();
            $table->timestamp('calculado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ped_execucao_analises');
        Schema::dropIfExists('ped_correcao_itens');
        Schema::dropIfExists('ped_correcoes');
        Schema::dropIfExists('ped_chaves_correcao');
        Schema::dropIfExists('ped_matrizes_prova');
        Schema::dropIfExists('ped_provas');
        Schema::dropIfExists('ped_exercicios');
        Schema::dropIfExists('ped_planos_aula');
        Schema::dropIfExists('ped_geracoes_ia');
        Schema::dropIfExists('ped_aulas');
        Schema::dropIfExists('ped_horarios_semanais');
        Schema::dropIfExists('ped_quinzenas');
        Schema::dropIfExists('ped_planificacoes');
    }
};
