<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Criar tabela anos_lectivos
        Schema::create('anos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 9)->unique()->comment('Formato: YYYY-YYYY');
            $table->boolean('estado')->default(false)->comment('1 = actual, 0 = anterior');
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            // Apenas um ano pode estar activo
            $table->index('estado');
        });

        // Validação de formato (apenas MySQL - SQLite não suporta REGEXP em CHECK constraints)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE anos_lectivos ADD CONSTRAINT chk_nome_format CHECK (nome REGEXP \'^[0-9]{4}-[0-9]{4}$\')');
        }

        // 2. Popular com dados existentes
        $anosExistentes = DB::table('turmas')
            ->select('ano_lectivo')
            ->distinct()
            ->whereNotNull('ano_lectivo')
            ->pluck('ano_lectivo');

        foreach ($anosExistentes as $ano) {
            DB::table('anos_lectivos')->insert([
                'nome' => $ano,
                'estado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Definir ano actual baseado em Configuracao
        $anoActual = DB::table('configuracoes')
            ->where('chave', 'ano_lectivo_actual')
            ->value('valor');

        if ($anoActual) {
            DB::table('anos_lectivos')
                ->where('nome', $anoActual)
                ->update(['estado' => true]);
        }

        // 4. Adicionar coluna ano_lectivo_id nas tabelas dependentes
        $tabelas = ['turmas', 'notas', 'professor_disciplina_turma', 'director_turma', 'coordenador_curso'];

        foreach ($tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('ano_lectivo_id')
                    ->nullable()
                    ->after('ano_lectivo')
                    ->constrained('anos_lectivos')
                    ->onDelete('restrict');
            });

            // Popular ano_lectivo_id baseado em ano_lectivo
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                // SQLite usa sintaxe diferente para UPDATE com JOIN
                DB::statement("
                    UPDATE {$tabela}
                    SET ano_lectivo_id = (
                        SELECT al.id
                        FROM anos_lectivos al
                        WHERE {$tabela}.ano_lectivo = al.nome
                    )
                ");
            } else {
                // MySQL/PostgreSQL
                DB::statement("
                    UPDATE {$tabela} t
                    INNER JOIN anos_lectivos al ON t.ano_lectivo = al.nome
                    SET t.ano_lectivo_id = al.id
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover foreign keys primeiro
        $tabelas = ['turmas', 'notas', 'professor_disciplina_turma', 'director_turma', 'coordenador_curso'];

        foreach ($tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropForeign(['ano_lectivo_id']);
                $table->dropColumn('ano_lectivo_id');
            });
        }

        Schema::dropIfExists('anos_lectivos');
    }
};
