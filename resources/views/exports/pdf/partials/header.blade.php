{{--
    Cabeçalho institucional centralizado (DomPDF-friendly).
    Aceita opcionalmente $logoDataUri e $pdfConfig.
--}}
@php
    $pdfConfig = $pdfConfig ?? app(\App\Services\ConfiguracaoService::class)->getPdfConfig();
    $logoWidth = (int) ($pdfConfig['logo']['width'] ?? 60);
    $logoSrc = $logoDataUri ?? null;

    if (! $logoSrc && ! empty($instituicao['logo'])) {
        $candidates = [
            storage_path('app/public/'.$instituicao['logo']),
            public_path('storage/'.$instituicao['logo']),
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) {
                $mime = mime_content_type($full) ?: 'image/png';
                $logoSrc = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
                break;
            }
        }
    }
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px 0; border:none;">
    <tr>
        <td align="center" style="text-align:center; border:none;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logótipo"
                     style="width:{{ $logoWidth }}px; max-width:{{ $logoWidth }}px; height:auto; display:block; margin:0 auto 4px auto;">
            @endif
            <div style="text-align:center; font-size:9px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin:0;">
                República de Angola
            </div>
            <div style="text-align:center; font-size:8px; color:#333; margin:1px 0 3px 0;">
                Ministério da Educação
            </div>
            <div style="text-align:center; font-size:12px; font-weight:bold; text-transform:uppercase; color:{{ $pdfConfig['colors']['primary'] ?? '#1a1a1a' }}; margin:0 8px; line-height:1.25;">
                {{ $instituicao['nome'] ?? 'Instituição de Ensino' }}
            </div>
            @if(!empty($instituicao['endereco']) || !empty($instituicao['telefone']) || !empty($instituicao['email']))
                <div style="text-align:center; font-size:8px; color:#555; margin-top:3px; line-height:1.3;">
                    @if(!empty($instituicao['endereco'])){{ $instituicao['endereco'] }}@endif
                    @if(!empty($instituicao['telefone']))
                        @if(!empty($instituicao['endereco'])) · @endif
                        Tel: {{ $instituicao['telefone'] }}
                    @endif
                    @if(!empty($instituicao['email']))
                        @if(!empty($instituicao['endereco']) || !empty($instituicao['telefone'])) · @endif
                        {{ $instituicao['email'] }}
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>
<div style="border-top:3px double #000; margin:6px 0 12px 0;"></div>
