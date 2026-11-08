<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporte a múltiplos regimes de cálculo das pautas por curso.
     *
     *  - politecnico  : actual (Informática, técnico, etc.)
     *                   - T2 calcula MFT2 = (MT1+MT2)/2
     *                   - T3 usa MFT2, PG, CF, CA, CFD
     *                   - 11.ª/12.ª: carregam CA das classes anteriores
     *                   - Resultado da 12.ª baseado em CFD
     *
     *  - administracao: Contabilidade e Gestão / Gestão Empresarial
     *                   - Trimestres totalmente independentes (sem MFT2)
     *                   - T3 final: CF = (MT1+MT2+MT3)/3
     *                   - CA = 0.6*CF + 0.4*PG (igual ao politécnico)
     *                   - Sem carry-overs de CA-10/CA-11 na 12.ª
     *                   - Pauta final mostra só CF|PG|CA(+REC) por disciplina
     *                   - Coluna MÉDIA do aluno (média aritmética das CAs)
     *                   - Resultado baseado em CA
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->enum('regime_calculo', ['politecnico', 'administracao'])
                ->default('politecnico')
                ->after('classe_max')
                ->comment('Regime de cálculo de médias da pauta. Decide as fórmulas e o layout do Excel.');
        });

        // Classificar cursos existentes — heurística pelo nome.
        // O utilizador escolheu Opção B (aplicação retroactiva), portanto:
        // - Cursos cujo nome contém "Contabilidade" ou "Gestão" → administração
        // - Restantes → politécnico (default já aplicado)
        $cursosAdmin = DB::table('cursos')
            ->where(function ($q) {
                $q->where('nome', 'like', '%Contabilidade%')
                  ->orWhere('nome', 'like', '%Gestão%')
                  ->orWhere('nome', 'like', '%Gestao%')
                  ->orWhere('nome', 'like', '%Administra%');
            })
            ->pluck('id');

        if ($cursosAdmin->isNotEmpty()) {
            DB::table('cursos')
                ->whereIn('id', $cursosAdmin)
                ->update(['regime_calculo' => 'administracao']);
        }
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('regime_calculo');
        });
    }
};
