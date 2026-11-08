{{--
    Rodapé Padrão para PDFs com Position Fixed e Paginação Automática
    Inclui: Assinaturas + Data de Geração + Sistema + Paginação

    Uso: @include('exports.pdf.partials.footer', [
        'instituicao' => $instituicao,
        'gerado_em' => $gerado_em,
        'assinaturas' => ['O Professor', 'O Director de Turma'] // Opcional
    ])
--}}

<div style="position: fixed; bottom: 0; left: 0; right: 0; width: 100%; border-top: 1px solid #bbb; padding: 8px 20px; background: white; font-size: 8px;">
    <!-- Linha de Informações -->
    <div style="width: 100%; margin-bottom: 10px;">
        <div style="width: 35%; float: left; text-align: left;">
            Gerado em {{ $gerado_em->format('d/m/Y H:i') }}
        </div>
        <div style="width: 40%; float: left; text-align: center;">
            Documento gerado pelo Sistema Facilita – Gestão Escolar
        </div>
        <div style="width: 25%; float: right; text-align: right;">
            <script type="text/php">
                if (isset($pdf)) {
                    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                    $size = 8;
                    $pageText = "Página " . $PAGE_NUM . " / " . $PAGE_COUNT;
                    $y = $pdf->get_height() - 60;
                    $x = $pdf->get_width() - 100;
                    $pdf->text($x, $y, $pageText, $font, $size, array(0, 0, 0));
                }
            </script>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Assinaturas -->
    @if(!empty($assinaturas) && count($assinaturas) > 0)
        <div style="width: 100%; margin-top: 10px; display: table;">
            @foreach($assinaturas as $assinatura)
                <div style="display: table-cell; width: {{ 100 / count($assinaturas) }}%; text-align: center; padding-top: 10px; border-top: 1px solid #333;">
                    <div style="font-size: 8px;">{{ $assinatura }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
