<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporta o Art.º 29.º do Decreto Executivo n.º 424/25 (Avaliação na Modalidade de Educação Especial).
     *
     *  - educacao_especial: flag indicando se o aluno é público-alvo da Educação Especial
     *  - necessidades_especificas: texto livre que descreve o tipo de NEE e adaptações
     *    necessárias (Braille, caracteres ampliados, leitura orientada, tempo suplementar,
     *    sala separada, etc.). Documentado conforme alíneas a, b, c do n.º 5 do Art.º 29.
     */
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->boolean('educacao_especial')
                ->default(false)
                ->after('genero')
                ->comment('Aluno público-alvo da Educação Especial (Art.º 29 do DE 424/25)');

            $table->text('necessidades_especificas')
                ->nullable()
                ->after('educacao_especial')
                ->comment('Descrição das necessidades educativas e adaptações de avaliação');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn(['educacao_especial', 'necessidades_especificas']);
        });
    }
};
