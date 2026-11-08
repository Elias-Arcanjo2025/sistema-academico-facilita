<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $cabecalho['titulo'] ?? 'Prova' }}</title>
    @php
        $m = $pdfConfig['margins'] ?? ['top' => 10, 'right' => 12, 'bottom' => 10, 'left' => 14];
        $fontBody = (int) ($pdfConfig['fonts']['sizes']['body'] ?? 8);
        $fontHeader = (int) ($pdfConfig['fonts']['sizes']['header'] ?? 9);
        $logoW = (int) ($pdfConfig['logo']['width'] ?? 40);
        $compacto = $compacto ?? true;
    @endphp
    <style>
        @page {
            size: {{ $pdfConfig['paper']['size'] ?? 'A4' }} {{ $pdfConfig['paper']['orientation'] ?? 'portrait' }};
            margin: {{ $m['top'] }}mm {{ $m['right'] }}mm {{ $m['bottom'] }}mm {{ $m['left'] }}mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: '{{ $pdfConfig['fonts']['family'] ?? 'DejaVu Sans' }}', DejaVu Sans, sans-serif;
            font-size: {{ $fontBody }}px;
            line-height: 1.22;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .prova-document { width: 100%; }

        .header-logo { text-align: center; margin-bottom: 2px; }
        .header-logo img { max-width: {{ $logoW }}px; max-height: {{ $logoW }}px; }

        .header-institucional { text-align: center; margin-bottom: 2px; line-height: 1.15; }
        .republica { font-size: 7px; text-transform: uppercase; font-weight: bold; }
        .ministerio { font-size: 6.5px; color: #333; }
        .instituicao-nome {
            font-weight: bold;
            text-transform: uppercase;
            font-size: {{ $fontHeader }}px;
            color: #1a1a1a;
            margin-top: 1px;
        }

        .separador-duplo {
            border-top: 2px double #000;
            margin: 3px 0 4px 0;
        }

        .prova-notas {
            margin: 0 0 4px 0;
            font-size: {{ max(6.5, $fontBody - 0.5) }}px;
            line-height: 1.2;
        }
        .prova-notas strong { font-size: {{ $fontBody }}px; }
        .prova-notas ol {
            margin: 1px 0 0 0;
            padding-left: 14px;
        }
        .prova-notas li { margin-bottom: 0; }

        .prova-titulo {
            text-align: center;
            font-weight: bold;
            font-size: {{ $fontBody + 1.5 }}px;
            text-transform: uppercase;
            margin: 3px 0 3px 0;
            letter-spacing: 0.3px;
        }

        .prova-meta {
            text-align: center;
            font-size: {{ $fontBody }}px;
            margin-bottom: 2px;
            line-height: 1.25;
        }

        .prova-instrucao {
            font-size: {{ $fontBody }}px;
            margin: 3px 0 5px 0;
            text-align: justify;
            line-height: 1.22;
        }

        .questao-grupo {
            margin-bottom: {{ $compacto ? '4px' : '10px' }};
            page-break-inside: avoid;
        }
        .questao-titulo {
            font-weight: bold;
            margin-bottom: 1px;
            text-align: justify;
            line-height: 1.2;
        }
        .questao-intro {
            margin: 0 0 2px 0;
            text-align: justify;
            line-height: 1.2;
        }
        .questao-subitens {
            margin: 0;
            padding-left: 10px;
            list-style: none;
        }
        .questao-subitens li {
            margin-bottom: 1px;
            text-align: justify;
            line-height: 1.2;
        }
        .questao-sub-letra { font-weight: bold; }
        .questao-cotacao { font-weight: normal; white-space: nowrap; }
        .questao-opcoes {
            margin: 1px 0 0 12px;
            padding: 0;
            list-style: none;
        }
        .questao-opcoes li {
            margin-bottom: 1px;
            text-align: justify;
            line-height: 1.2;
        }
    </style>
</head>
<body>
<div class="prova-document">
    @if(!empty($logoBase64))
        <div class="header-logo">
            <img src="{{ $logoBase64 }}" alt="Logo">
        </div>
    @elseif(!empty($instituicao['logo']))
        <div class="header-logo">
            <img src="{{ storage_path('app/public/'.$instituicao['logo']) }}" alt="Logo">
        </div>
    @endif

    <div class="header-institucional">
        <div class="republica">República de Angola</div>
        <div class="ministerio">Ministério da Educação</div>
        <div class="instituicao-nome">{{ $instituicao['nome'] ?? 'Instituto Politécnico' }}</div>
    </div>
    <div class="separador-duplo"></div>

    @if(!empty($notasPadrao))
        <div class="prova-notas">
            <strong>Notas:</strong>
            <ol>
                @foreach($notasPadrao as $nota)
                    <li>{{ $nota }}</li>
                @endforeach
            </ol>
        </div>
    @endif

    <div class="prova-titulo">{{ $cabecalho['titulo'] ?? ('Prova '.$prova->tipo) }}</div>

    <div class="prova-meta">
        @php
            $metaPartes = array_filter([
                $cabecalho['classe_turno'] ?? null,
                'Duração: '.($cabecalho['duracao'] ?? $prova->tempo_minutos).' min.',
                'Data: '.($cabecalho['data_linha'] ?? '____/____/________'),
                ($cabecalho['curso'] ?? false) ? 'Curso: '.$cabecalho['curso'] : null,
                ($cabecalho['professor'] ?? false) ? 'Professor: '.$cabecalho['professor'] : null,
                ($cabecalho['turma'] ?? false) ? 'Turma: '.$cabecalho['turma'] : null,
            ]);
        @endphp
        {{ implode(' &nbsp;|&nbsp; ', $metaPartes) }}
    </div>

    @php $c = $prova->conteudo_json ?? []; @endphp

    @php
        $instrucao = trim(($c['instrucoes'] ?? '').' '.($cabecalho['instrucao_resolucao'] ?? 'Resolve acertadamente as questões, depois de lida e compreendida:'));
    @endphp
    <div class="prova-instrucao">{{ $instrucao }}</div>

    @if(!empty($c['questoes']))
        @foreach($c['questoes'] as $q)
            @include('pedagogico.pdf._questao-prova', ['q' => $q])
        @endforeach
    @endif
</div>
</body>
</html>
