<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cfg = [
            'chave' => 'resultado_max_negativas_recuperacao',
            'valor' => '2',
            'tipo' => 'integer',
            'descricao' => '10.ª/11.ª (T3): nº máximo de disciplinas negativas para resultado RECUPERAÇÃO / TRANSITA/C DEF. '
                .'0 negativas → TRANSITA; 1 até este valor → RECUPERAÇÃO (terminais) ou TRANSITA/C DEF (só contínuas); '
                .'mais do que este valor → N/TRANSITA. Também: 3+ contínuas → N/TRANSITA.',
        ];

        $existing = DB::table('configuracoes')->where('chave', $cfg['chave'])->first();

        if ($existing) {
            DB::table('configuracoes')
                ->where('chave', $cfg['chave'])
                ->update([
                    'tipo' => $cfg['tipo'],
                    'descricao' => $cfg['descricao'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('configuracoes')->insert([
                ...$cfg,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('configuracoes')->where('chave', 'resultado_max_negativas_recuperacao')->delete();
    }
};
