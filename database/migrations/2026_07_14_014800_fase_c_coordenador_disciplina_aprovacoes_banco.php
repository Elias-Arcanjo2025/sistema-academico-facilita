<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordenador_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->string('ano_lectivo', 20)->nullable();
            $table->foreignId('ano_lectivo_id')->nullable()->constrained('anos_lectivos')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['user_id', 'disciplina_id', 'ano_lectivo_id'],
                'coord_disc_user_disc_ano_unique'
            );
            $table->index(['disciplina_id', 'activo']);
        });

        Schema::create('ped_banco_questoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->text('enunciado');
            $table->json('opcoes_json')->nullable();
            $table->json('resposta_json')->nullable();
            $table->decimal('cotacao', 5, 2)->default(1);
            $table->string('dificuldade', 16)->default('media');
            $table->string('tags', 255)->nullable();
            $table->string('estado', 16)->default('activa');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disciplina_id', 'estado']);
            $table->index(['criado_por']);
        });

        Schema::table('ped_planificacoes', function (Blueprint $table) {
            $table->timestamp('submetido_em')->nullable()->after('estado');
            $table->foreignId('aprovado_por')->nullable()->after('submetido_em')->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable()->after('aprovado_por');
            $table->string('motivo_rejeicao', 500)->nullable()->after('aprovado_em');
        });

        Schema::table('ped_provas', function (Blueprint $table) {
            $table->timestamp('submetido_em')->nullable()->after('estado');
            $table->foreignId('aprovado_por')->nullable()->after('submetido_em')->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable()->after('aprovado_por');
            $table->string('motivo_rejeicao', 500)->nullable()->after('aprovado_em');
        });
    }

    public function down(): void
    {
        Schema::table('ped_provas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprovado_por');
            $table->dropColumn(['submetido_em', 'aprovado_em', 'motivo_rejeicao']);
        });

        Schema::table('ped_planificacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprovado_por');
            $table->dropColumn(['submetido_em', 'aprovado_em', 'motivo_rejeicao']);
        });

        Schema::dropIfExists('ped_banco_questoes');
        Schema::dropIfExists('coordenador_disciplina');
    }
};
