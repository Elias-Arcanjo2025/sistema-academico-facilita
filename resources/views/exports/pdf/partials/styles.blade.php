{{--
    Estilos CSS Compartilhados para PDFs
    Template Moderno e Profissional
    Facilita - Sistema de Gestão Escolar
--}}
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        line-height: 1.4;
        color: #000;
        padding: 0;
    }

    .container {
        width: 100%;
        max-width: 100%;
    }

    /* ===== CABEÇALHO ===== */

    /* Logotipo Centralizado */
    .header-logo {
        text-align: center;
        margin-bottom: 5px;
    }
    .header-logo img {
    }

    /* Informações Institucionais */
    .header-institucional {
        text-align: center;
        margin-bottom: 6px;
        padding: 0;
    }
    .republica {
        font-size: 8px;
        text-transform: uppercase;
        margin-bottom: 1px;
        font-weight: bold;
    }
    .ministerio {
        font-size: 7px;
        margin-bottom: 2px;
        color: #333;
    }
    .instituicao-nome {
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 2px;
        color: #1a1a1a;
    }
    .instituicao-contacto {
        font-size: 7px;
        color: #555;
        line-height: 1.2;
    }

    /* Separadores */
    .separador {
        border-top: 1px solid #333;
        margin: 4px 0;
    }
    .separador-duplo {
        border-top: 3px double #000;
        margin: 6px 0 8px 0;
    }

    /* ===== TÍTULO DO DOCUMENTO ===== */

    .titulo-documento {
        text-align: center;
        margin: 8px 0; /* Removida margem lateral */
        background: #f5f5f5;
        padding: 8px;
        border-radius: 4px;
        page-break-inside: avoid;
    }
    .titulo-documento h1 {
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 3px;
        color: #1a1a1a;
    }
    .titulo-documento h2 {
        font-size: 10px;
        font-weight: normal;
        color: #444;
    }

    /* ===== CAIXAS DE INFORMAÇÃO ===== */

    .info-box {
        border: 2px solid #333;
        padding: 8px 12px;
        margin: 0 0 8px 0; /* Removida margem lateral */
        background: #fafafa;
        border-radius: 3px;
        page-break-inside: avoid;
    }
    .info-row {
        display: table;
        width: 100%;
        margin-bottom: 4px;
    }
    .info-row:last-child {
        margin-bottom: 0;
    }
    .info-cell {
        display: table-cell;
        width: 50%;
        font-size: 9px;
        padding: 1px 5px;
    }
    .info-label {
        font-weight: bold;
        display: inline-block;
        min-width: 75px;
        color: #333;
    }
    .info-value {
        color: #000;
    }

    /* ===== TABELAS ===== */

    .table-wrapper {
        margin: 0 0 10px 0; /* Removida margem lateral */
        page-break-inside: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    .data-table th,
    .data-table td {
        border: 1px solid #333;
        padding: 6px 4px;
        text-align: center;
        vertical-align: middle;
    }
    .data-table thead {
        page-break-inside: avoid;
        page-break-after: avoid;
    }
    .data-table thead th {
        font-weight: bold;
        font-size: 8.5px;
        text-transform: uppercase;
        color: #000;
        padding: 8px 4px;
    }
    .data-table tbody tr {
        page-break-inside: avoid;
    }
    .data-table tbody td {
    }
    .data-table td.numero,
    .data-table td.col-ordem {
        font-weight: bold;
        background: #f8f8f8;
    }
    .data-table td.nome-aluno,
    .data-table td.col-nome {
        text-align: left;
        padding-left: 10px;
        font-size: 9px;
    }
    .data-table tr:nth-child(even) {
        background: #fcfcfc;
    }

    /* Notas com Cores */
    .nota-positiva {
        font-weight: bold;
        color: #000;
    }
    .nota-negativa {
        font-weight: bold;
        color: #d00000;
    }
    .nota-destaque {
        font-size: 12px;
        font-weight: bold;
        background: #f0f0f0;
    }

    /* ===== ESTATÍSTICAS ===== */

    .estatisticas-box,
    .resumo-box {
        border: 2px solid #333;
        padding: 8px 12px;
        margin: 0 0 10px 0; /* Removida margem lateral */
        background: #fafafa;
        border-radius: 3px;
        page-break-inside: avoid;
    }
    .estatisticas-box.alerta {
        background: #fff3cd;
    }
    .estatisticas-titulo {
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 9px;
        border-bottom: 1px solid #999;
        padding-bottom: 4px;
        text-transform: uppercase;
    }
    .estatisticas-grid {
        display: table;
        width: 100%;
    }
    .estatisticas-item {
        display: table-cell;
        text-align: center;
        padding: 6px 4px;
    }
    .estatisticas-valor {
        font-size: 13px;
        font-weight: bold;
        display: block;
        color: #1a1a1a;
    }
    .estatisticas-label {
        font-size: 7px;
        color: #666;
        text-transform: uppercase;
        margin-top: 2px;
    }

    /* ===== RODAPÉ ===== */

    .footer-section {
        margin: 15px 0 0 0; /* Removida margem lateral */
        page-break-inside: avoid;
    }

    /* Assinaturas */
    .assinaturas-container {
        display: table;
        width: 100%;
        margin-top: 25px;
    }
    .assinatura-box {
        display: table-cell;
        width: 50%;
        text-align: center;
        padding: 0 15px;
    }
    .assinatura-linha {
        border-top: 1.5px solid #000;
        padding-top: 5px;
        margin-top: 25px;
    }
    .assinatura-cargo {
        font-size: 9px;
        font-weight: bold;
    }

    /* Informações de Contacto */
    .footer-contacto {
        margin-top: 15px;
        padding-top: 8px;
        border-top: 1px solid #ccc;
        text-align: center;
        font-size: 7px;
        color: #666;
        line-height: 1.4;
    }
    .footer-contacto-linha {
        margin-bottom: 2px;
    }

    /* Data e Info do Documento */
    .documento-info {
        margin-top: 12px;
        font-size: 7px;
        color: #888;
    }
    .documento-info-row {
        display: table;
        width: 100%;
    }
    .documento-info-left {
        display: table-cell;
        text-align: left;
    }
    .documento-info-right {
        display: table-cell;
        text-align: right;
    }

    /* ===== LEGENDA ===== */

    .legenda {
        margin: 0 0 8px 0; /* Removida margem lateral */
        font-size: 7px;
        color: #666;
        border-top: 1px dashed #999;
        padding-top: 5px;
        text-align: justify;
        page-break-inside: avoid;
    }

    /* ===== UTILITÁRIOS ===== */

    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .text-right { text-align: right; }
    .fw-bold { font-weight: bold; }
    .text-uppercase { text-transform: uppercase; }
    .mb-small { margin-bottom: 5px; }
    .mb-medium { margin-bottom: 10px; }
    .mb-large { margin-bottom: 15px; }
</style>

@include('exports.pdf.partials.dynamic-styles')
