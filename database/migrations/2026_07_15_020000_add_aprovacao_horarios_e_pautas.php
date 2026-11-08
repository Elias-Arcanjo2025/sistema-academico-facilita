<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turma_horario_config', function (Blueprint $table) {
            $table->string('status', 20)->default('rascunho')->after('intervalo_texto');
            $table->foreignId('aprovado_por')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable()->after('aprovado_por');
        });

        Schema::create('pauta_aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre');
            $table->string('status', 20)->default('aprovada');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['turma_id', 'ano_lectivo_id', 'trimestre'], 'pauta_aprovacao_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pauta_aprovacoes');

        Schema::table('turma_horario_config', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprovado_por');
            $table->dropColumn(['status', 'aprovado_em']);
        });
    }
};
