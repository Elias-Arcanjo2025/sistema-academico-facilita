<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado local da licença (modo school — uma linha)
        Schema::create('control_license_states', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('activo'); // activo|em_cobranca|em_atraso|bloqueado|desconhecido
            $table->string('message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('hub_reported_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Hub: escolas registadas
        Schema::create('control_escolas', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->string('nome');
            $table->string('slug')->nullable()->unique();
            $table->string('secret_hash');
            $table->string('app_url')->nullable();
            $table->string('contacto_email')->nullable();
            $table->string('contacto_telefone')->nullable();
            $table->string('status', 32)->default('activo'); // activo|em_cobranca|em_atraso|bloqueado
            $table->string('ano_lectivo')->nullable();
            $table->string('app_version')->nullable();
            $table->unsignedInteger('total_alunos')->default(0);
            $table->unsignedInteger('total_professores')->default(0);
            $table->unsignedInteger('total_cursos')->default(0);
            $table->unsignedInteger('total_turmas')->default(0);
            $table->unsignedInteger('total_salas')->default(0);
            $table->unsignedInteger('total_disciplinas')->default(0);
            $table->unsignedInteger('total_utilizadores')->default(0);
            $table->decimal('preco_anual_aluno', 12, 2)->default(0);
            $table->string('bloqueio_motivo')->nullable();
            $table->foreignId('bloqueado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bloqueado_em')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metricas')->nullable();
            $table->json('saude')->nullable();
            $table->text('notas_internas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Hub: alunos sincronizados (dados de faturação)
        Schema::create('control_alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_escola_id')->constrained('control_escolas')->cascadeOnDelete();
            $table->uuid('aluno_uuid');
            $table->unsignedBigInteger('aluno_local_id')->nullable();
            $table->string('numero_processo')->nullable();
            $table->string('nome');
            $table->string('genero', 20)->nullable();
            $table->string('ano_lectivo')->nullable();
            $table->string('turma_codigo')->nullable();
            $table->string('turma_nome')->nullable();
            $table->string('curso_nome')->nullable();
            $table->string('classe_nome')->nullable();
            $table->string('situacao_academica', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('pagamento_estado', 32)->default('pendente'); // pendente|pago|isento|anulado
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['control_escola_id', 'aluno_uuid']);
            $table->index(['control_escola_id', 'activo']);
            $table->index(['control_escola_id', 'ano_lectivo']);
        });

        // Hub: histórico de acções de licença
        Schema::create('control_license_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_escola_id')->constrained('control_escolas')->cascadeOnDelete();
            $table->string('accao', 40); // bloquear|desbloquear|em_cobranca|em_atraso|activar|registo|heartbeat
            $table->string('status_anterior', 32)->nullable();
            $table->string('status_novo', 32)->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_license_events');
        Schema::dropIfExists('control_alunos');
        Schema::dropIfExists('control_escolas');
        Schema::dropIfExists('control_license_states');
    }
};
