<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encarregados', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('nome');
            $table->string('telefone', 40)->nullable()->index();
            $table->string('email')->nullable()->unique();
            $table->string('documento', 50)->nullable();
            $table->boolean('whatsapp_opt_in')->default(false);
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('aluno_encarregado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('encarregado_id')->constrained('encarregados')->cascadeOnDelete();
            $table->string('parentesco', 40)->default('encarregado'); // pai|mae|tutor|encarregado
            $table->boolean('principal')->default(true);
            $table->boolean('pode_ver_notas')->default(true);
            $table->boolean('pode_assinar_boletim')->default(true);
            $table->timestamps();

            $table->unique(['aluno_id', 'encarregado_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('encarregado_id')->nullable()->after('aluno_id')
                ->constrained('encarregados')->nullOnDelete();
        });

        Schema::create('boletim_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('encarregado_id')->constrained('encarregados')->cascadeOnDelete();
            $table->string('ano_lectivo')->nullable();
            $table->unsignedTinyInteger('trimestre')->nullable();
            $table->string('hash_documento', 64);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('assinado_em');
            $table->timestamps();

            $table->index(['aluno_id', 'ano_lectivo', 'trimestre']);
        });

        Schema::create('whatsapp_mensagens', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // in|out
            $table->string('telefone', 40)->index();
            $table->foreignId('encarregado_id')->nullable()->constrained('encarregados')->nullOnDelete();
            $table->string('tipo', 40)->default('text'); // text|template|image|status
            $table->string('template')->nullable();
            $table->text('corpo')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status', 32)->default('queued'); // queued|sent|delivered|failed|received
            $table->json('payload')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();
        });

        Schema::create('conformidade_relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->string('ano_lectivo')->nullable();
            $table->unsignedTinyInteger('trimestre')->nullable();
            $table->string('selo', 32)->default('conforme'); // conforme|alerta|nao_conforme
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->json('resultado')->nullable();
            $table->foreignId('gerado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conformidade_relatorios');
        Schema::dropIfExists('whatsapp_mensagens');
        Schema::dropIfExists('boletim_assinaturas');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encarregado_id');
        });

        Schema::dropIfExists('aluno_encarregado');
        Schema::dropIfExists('encarregados');
    }
};
