<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->boolean('consulta_notas_portal')->default(true)->after('activo');
            $table->string('motivo_bloqueio_portal', 500)->nullable()->after('consulta_notas_portal');
        });

        if (! DB::table('configuracoes')->where('chave', 'portal_aluno_notas_activo')->exists()) {
            DB::table('configuracoes')->insert([
                'chave' => 'portal_aluno_notas_activo',
                'valor' => '1',
                'descricao' => 'Permite consulta de notas e boletim no portal do aluno (global)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('configuracoes')->where('chave', 'portal_aluno_mensagem_bloqueio')->exists()) {
            DB::table('configuracoes')->insert([
                'chave' => 'portal_aluno_mensagem_bloqueio',
                'valor' => 'A consulta de notas e o download do boletim estão indisponíveis. Regularize o pagamento do boletim na secretaria da escola.',
                'descricao' => 'Mensagem exibida ao aluno quando o acesso ao portal de notas está bloqueado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn(['consulta_notas_portal', 'motivo_bloqueio_portal']);
        });

        DB::table('configuracoes')->whereIn('chave', [
            'portal_aluno_notas_activo',
            'portal_aluno_mensagem_bloqueio',
        ])->delete();
    }
};
