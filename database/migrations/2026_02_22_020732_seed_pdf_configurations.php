<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $configs = [
            // Margens (em mm) - valores atuais do código
            ['chave' => 'pdf_margin_top', 'valor' => '10', 'tipo' => 'integer',
             'descricao' => 'Margem superior do PDF em mm'],
            ['chave' => 'pdf_margin_right', 'valor' => '15', 'tipo' => 'integer',
             'descricao' => 'Margem direita do PDF em mm'],
            ['chave' => 'pdf_margin_bottom', 'valor' => '10', 'tipo' => 'integer',
             'descricao' => 'Margem inferior do PDF em mm'],
            ['chave' => 'pdf_margin_left', 'valor' => '15', 'tipo' => 'integer',
             'descricao' => 'Margem esquerda do PDF em mm'],

            // Fontes
            ['chave' => 'pdf_font_family', 'valor' => 'DejaVu Sans', 'tipo' => 'string',
             'descricao' => 'Família de fonte para PDFs (suportadas: DejaVu Sans, Helvetica, Times-Roman, Courier)'],
            ['chave' => 'pdf_font_size_body', 'valor' => '10', 'tipo' => 'integer',
             'descricao' => 'Tamanho da fonte do corpo em px'],
            ['chave' => 'pdf_font_size_header', 'valor' => '13', 'tipo' => 'integer',
             'descricao' => 'Tamanho da fonte dos cabeçalhos em px'],
            ['chave' => 'pdf_font_size_table', 'valor' => '9.5', 'tipo' => 'string',
             'descricao' => 'Tamanho da fonte das tabelas em px'],

            // Cores (hex)
            ['chave' => 'pdf_color_primary', 'valor' => '#0066cc', 'tipo' => 'string',
             'descricao' => 'Cor primária (títulos, destaques)'],
            ['chave' => 'pdf_color_table_header', 'valor' => '#d0d0d0', 'tipo' => 'string',
             'descricao' => 'Cor de fundo dos cabeçalhos de tabela'],

            // Papel
            ['chave' => 'pdf_paper_size', 'valor' => 'A4', 'tipo' => 'string',
             'descricao' => 'Tamanho do papel (A4, Letter)'],
            ['chave' => 'pdf_paper_orientation', 'valor' => 'portrait', 'tipo' => 'string',
             'descricao' => 'Orientação padrão (portrait, landscape). Nota: pautas finais sempre usam landscape'],

            // Logo
            ['chave' => 'pdf_logo_width', 'valor' => '60', 'tipo' => 'integer',
             'descricao' => 'Largura máxima do logo em px'],
        ];

        foreach ($configs as $config) {
            \App\Models\Configuracao::updateOrCreate(
                ['chave' => $config['chave']],
                $config
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\Configuracao::whereIn('chave', [
            'pdf_margin_top', 'pdf_margin_right', 'pdf_margin_bottom', 'pdf_margin_left',
            'pdf_font_family', 'pdf_font_size_body', 'pdf_font_size_header', 'pdf_font_size_table',
            'pdf_color_primary', 'pdf_color_table_header',
            'pdf_paper_size', 'pdf_paper_orientation',
            'pdf_logo_width',
        ])->delete();
    }
};
