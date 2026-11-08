<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Insere os valores default das configurações de Exame de Recurso.
     * Pode ser editado depois na página de Configurações.
     *
     * Base legal: Decreto Executivo 424/25 — Art.º 31.º (Exame de Recurso)
     */
    public function up(): void
    {
        $defaults = [
            [
                'chave' => 'recurso_nota_min',
                'valor' => '6',
                'tipo' => 'integer',
                'descricao' => 'Nota mínima (inclusive) para um aluno ser elegível a recurso numa disciplina. Default MED: 6.',
            ],
            [
                'chave' => 'recurso_nota_max',
                'valor' => '9',
                'tipo' => 'integer',
                'descricao' => 'Nota máxima (inclusive) para um aluno ser elegível a recurso numa disciplina. Default MED: 9.',
            ],
            [
                'chave' => 'recurso_max_disciplinas',
                'valor' => '3',
                'tipo' => 'integer',
                'descricao' => 'Número máximo de disciplinas em que um aluno pode fazer recurso. Default MED: 3.',
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
            ->whereIn('chave', ['recurso_nota_min', 'recurso_nota_max', 'recurso_max_disciplinas'])
            ->delete();
    }
};
