<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cfg = [
            'chave' => 'resultado_12_max_negativas_recurso',
            'valor' => '3',
            'tipo' => 'integer',
            'descricao' => '12.ª classe (T3): nº máximo de disciplinas negativas para resultado RECURSO. '
                .'0 negativas → APTO; 1 até este valor → RECURSO; mais do que este valor → N/APTO.',
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
                'chave' => $cfg['chave'],
                'valor' => $cfg['valor'],
                'tipo' => $cfg['tipo'],
                'descricao' => $cfg['descricao'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('configuracoes')
            ->where('chave', 'resultado_12_max_negativas_recurso')
            ->delete();
    }
};
