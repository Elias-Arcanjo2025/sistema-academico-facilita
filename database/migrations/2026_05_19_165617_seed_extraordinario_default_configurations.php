<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Insere os valores default das configurações de Exame Extraordinário.
     *
     * Base legal: Decreto Executivo 424/25 — Art.º 32.º (Exame Extraordinário).
     */
    public function up(): void
    {
        $defaults = [
            [
                'chave' => 'extraordinario_min_aprovacoes_anteriores',
                'valor' => '1',
                'tipo' => 'integer',
                'descricao' => 'N.º mínimo de aprovações em exames extraordinários anteriores para nova elegibilidade (Art.º 32, n.º 2-c).',
            ],
            [
                'chave' => 'extraordinario_max_negativas_recurso',
                'valor' => '2',
                'tipo' => 'integer',
                'descricao' => '12.ª classe: máximo de notas <10 em recursos realizados que torna o aluno elegível ao Extraordinário (Art.º 32, n.º 2-d).',
            ],
        ];

        foreach ($defaults as $cfg) {
            DB::table('configuracoes')->updateOrInsert(
                ['chave' => $cfg['chave']],
                [
                    'valor' => $cfg['valor'],
                    'tipo' => $cfg['tipo'],
                    'descricao' => $cfg['descricao'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('configuracoes')
            ->whereIn('chave', ['extraordinario_min_aprovacoes_anteriores', 'extraordinario_max_negativas_recurso'])
            ->delete();
    }
};
