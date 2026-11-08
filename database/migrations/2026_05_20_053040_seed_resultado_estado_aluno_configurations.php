<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Insere os valores default das configurações que controlam o resultado/
     * estado do aluno (reprovação por nº de disciplinas com negativa, e
     * desistência automática por nº de disciplinas sem qualquer nota).
     *
     * Estas chaves podem ser editadas na página Configurações > Avaliação.
     */
    public function up(): void
    {
        $defaults = [
            [
                'chave' => 'max_disciplinas_reprovacao',
                'valor' => '2',
                'tipo' => 'integer',
                'descricao' => 'Nº máximo de disciplinas com nota negativa (< nota_minima_aprovacao) tolerado para o aluno ser considerado APROVADO. Reprovado se tiver MAIS do que este número.',
            ],
            [
                'chave' => 'max_disciplinas_sem_nota_desistente',
                'valor' => '3',
                'tipo' => 'integer',
                'descricao' => 'Nº máximo de disciplinas sem qualquer nota lançada (MAC/PP/PT) tolerado antes de o aluno passar a DESISTENTE automaticamente em mini-pautas, pautas, estatísticas e relatórios.',
            ],
        ];

        foreach ($defaults as $cfg) {
            $existing = DB::table('configuracoes')->where('chave', $cfg['chave'])->first();

            if ($existing) {
                // Mantemos o valor já configurado, só actualizamos descrição/tipo
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
    }

    public function down(): void
    {
        DB::table('configuracoes')
            ->whereIn('chave', [
                'max_disciplinas_reprovacao',
                'max_disciplinas_sem_nota_desistente',
            ])
            ->delete();
    }
};
