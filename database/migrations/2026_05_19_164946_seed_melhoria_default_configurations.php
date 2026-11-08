<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Insere os valores default das configurações de Exame de Melhoria de Nota.
     *
     * Base legal: Decreto Executivo 424/25 — Art.º 34.º (Exame de Melhoria de Nota)
     *
     *  - Aplica-se a alunos que tenham concluído o ano lectivo com êxito (positiva).
     *  - Faixa elegível: nota positiva entre [nota_min, nota_max].
     *    O decreto refere "5 a 9 e 10 a 16 valores", interpretado como qualquer nota
     *    positiva (≥10) cuja melhoria seja útil — defaults 10..16.
     *  - A nota final = MAX(nota_original, nota_melhoria).
     */
    public function up(): void
    {
        $defaults = [
            [
                'chave' => 'melhoria_nota_min',
                'valor' => '10',
                'tipo' => 'integer',
                'descricao' => 'Nota mínima da CFD original elegível para Exame de Melhoria de Nota.',
            ],
            [
                'chave' => 'melhoria_nota_max',
                'valor' => '16',
                'tipo' => 'integer',
                'descricao' => 'Nota máxima da CFD original elegível para Exame de Melhoria de Nota.',
            ],
            [
                'chave' => 'melhoria_max_disciplinas',
                'valor' => '0',
                'tipo' => 'integer',
                'descricao' => 'Limite de disciplinas para melhoria (0 = sem limite). Configurável conforme orientação do MED.',
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
            ->whereIn('chave', ['melhoria_nota_min', 'melhoria_nota_max', 'melhoria_max_disciplinas'])
            ->delete();
    }
};
