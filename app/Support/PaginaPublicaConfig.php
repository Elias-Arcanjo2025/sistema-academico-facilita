<?php

namespace App\Support;

class PaginaPublicaConfig
{
    public const CAROUSEL_SLOTS = 5;

    public const CAROUSEL_MINIMO = 3;

    public const CAROUSEL_LARGURA_PADRAO = 800;

    public const CAROUSEL_ALTURA_PADRAO = 560;

    public const CHAVES = [
        'pagina_publica_cor_primaria',
        'pagina_publica_cor_primaria_escura',
        'pagina_publica_cor_secundaria',
        'pagina_publica_titulo',
        'pagina_publica_destaque',
        'pagina_publica_subtitulo',
        'pagina_publica_badge',
        'pagina_publica_sobre_titulo',
        'pagina_publica_sobre_texto',
        'pagina_publica_sobre_itens',
        'pagina_publica_func_titulo',
        'pagina_publica_func_subtitulo',
        'pagina_publica_alunos_titulo',
        'pagina_publica_alunos_subtitulo',
        'pagina_publica_cta_titulo',
        'pagina_publica_cta_texto',
        'pagina_publica_cta_aluno_titulo',
        'pagina_publica_cta_aluno_texto',
        'pagina_publica_cta_escola_titulo',
        'pagina_publica_cta_escola_texto',
        'pagina_publica_footer_texto',
        'pagina_publica_meta_descricao',
        'pagina_publica_mostrar_ano_lectivo',
        'pagina_publica_mostrar_contactos',
        'pagina_publica_usar_logo_instituicao',
        'pagina_publica_carousel_activo',
        'pagina_publica_carousel_intervalo',
        'pagina_publica_mostrar_registo',
        'registo_publico_activo',
    ];

    public static function defaults(): array
    {
        return [
            'cor_primaria' => '#4f46e5',
            'cor_primaria_escura' => '#3730a3',
            'cor_secundaria' => '#06b6d4',
            'titulo' => 'A vida da tua escola',
            'destaque' => 'num só lugar, com IA',
            'subtitulo' => 'Alunos consultam notas e horários, encarregados acompanham pelo portal e WhatsApp, e a escola gere tudo com o apoio de um assistente inteligente.',
            'badge' => 'Ano Lectivo {ano}',
            'sobre_titulo' => 'Desenvolvido para o Ensino em Angola',
            'sobre_texto' => 'O sistema foi concebido para responder às necessidades reais das instituições de ensino angolanas, seguindo a estrutura curricular nacional com suporte a trimestres, classes do ensino geral e técnico-profissional.',
            'sobre_itens' => "Sistema trimestral (MAC + PP + PT = MT)\nClasses da 10.ª à 13.ª (Ensino Secundário)\nCálculo automático de CFD, CA e CF\nExportação para PDF e Excel\nBackup automático diário",
            'alunos_titulo' => 'Portal do Aluno',
            'alunos_subtitulo' => 'Consulta as tuas notas, boletins, horário de aulas e calendário de provas — de forma simples e segura.',
            'func_titulo' => 'Para a tua escola',
            'func_subtitulo' => 'Ferramentas profissionais para docentes, coordenadores, secretaria e equipa de gestão académica — da matrícula à transição de ano.',
            'cta_titulo' => 'Começa agora',
            'cta_texto' => 'Escolhe como queres aceder à plataforma.',
            'cta_aluno_titulo' => 'És aluno?',
            'cta_aluno_texto' => 'Entra no portal e acompanha o teu desempenho académico em tempo real.',
            'cta_escola_titulo' => 'Representas a escola?',
            'cta_escola_texto' => 'Acede à área institucional para gerir notas, turmas, pautas e relatórios.',
            'footer_texto' => 'Plataforma académica com Inteligência Artificial para alunos, famílias, docentes e gestão escolar — notas, pautas, boletins, mensagens e relatórios.',
            'meta_descricao' => 'Portal do aluno e do encarregado com gestão académica inteligente — notas, boletins, horários, mensagens, WhatsApp e assistente de IA para a tua escola.',
            'mostrar_ano_lectivo' => true,
            'mostrar_contactos' => true,
            'usar_logo_instituicao' => false,
            'carousel_activo' => false,
            'carousel_intervalo' => 5,
            'mostrar_registo' => true,
        ];
    }

    public static function carouselChaves(): array
    {
        $chaves = [];
        for ($i = 1; $i <= self::CAROUSEL_SLOTS; $i++) {
            $chaves[] = "pagina_publica_carousel_{$i}_imagem";
            $chaves[] = "pagina_publica_carousel_{$i}_titulo";
            $chaves[] = "pagina_publica_carousel_{$i}_texto";
            $chaves[] = "pagina_publica_carousel_{$i}_largura";
            $chaves[] = "pagina_publica_carousel_{$i}_altura";
        }

        return $chaves;
    }

    public static function todasChaves(): array
    {
        return array_merge(self::CHAVES, self::carouselChaves());
    }

    /**
     * @param  array<string, string|null>  $raw
     */
    public static function resolve(array $raw, ?string $anoLectivo = null): array
    {
        $d = self::defaults();

        $config = [
            'cor_primaria' => self::cor($raw['pagina_publica_cor_primaria'] ?? null, $d['cor_primaria']),
            'cor_primaria_escura' => self::cor($raw['pagina_publica_cor_primaria_escura'] ?? null, $d['cor_primaria_escura']),
            'cor_secundaria' => self::cor($raw['pagina_publica_cor_secundaria'] ?? null, $d['cor_secundaria']),
            'titulo' => self::texto($raw['pagina_publica_titulo'] ?? null, $d['titulo']),
            'destaque' => self::texto($raw['pagina_publica_destaque'] ?? null, $d['destaque']),
            'subtitulo' => self::texto($raw['pagina_publica_subtitulo'] ?? null, $d['subtitulo']),
            'badge' => self::texto($raw['pagina_publica_badge'] ?? null, $d['badge']),
            'sobre_titulo' => self::texto($raw['pagina_publica_sobre_titulo'] ?? null, $d['sobre_titulo']),
            'sobre_texto' => self::texto($raw['pagina_publica_sobre_texto'] ?? null, $d['sobre_texto']),
            'sobre_itens' => self::lista($raw['pagina_publica_sobre_itens'] ?? null, $d['sobre_itens']),
            'func_titulo' => self::texto($raw['pagina_publica_func_titulo'] ?? null, $d['func_titulo']),
            'func_subtitulo' => self::texto($raw['pagina_publica_func_subtitulo'] ?? null, $d['func_subtitulo']),
            'alunos_titulo' => self::texto($raw['pagina_publica_alunos_titulo'] ?? null, $d['alunos_titulo']),
            'alunos_subtitulo' => self::texto($raw['pagina_publica_alunos_subtitulo'] ?? null, $d['alunos_subtitulo']),
            'cta_titulo' => self::texto($raw['pagina_publica_cta_titulo'] ?? null, $d['cta_titulo']),
            'cta_texto' => self::texto($raw['pagina_publica_cta_texto'] ?? null, $d['cta_texto']),
            'cta_aluno_titulo' => self::texto($raw['pagina_publica_cta_aluno_titulo'] ?? null, $d['cta_aluno_titulo']),
            'cta_aluno_texto' => self::texto($raw['pagina_publica_cta_aluno_texto'] ?? null, $d['cta_aluno_texto']),
            'cta_escola_titulo' => self::texto($raw['pagina_publica_cta_escola_titulo'] ?? null, $d['cta_escola_titulo']),
            'cta_escola_texto' => self::texto($raw['pagina_publica_cta_escola_texto'] ?? null, $d['cta_escola_texto']),
            'footer_texto' => self::texto($raw['pagina_publica_footer_texto'] ?? null, $d['footer_texto']),
            'meta_descricao' => self::texto($raw['pagina_publica_meta_descricao'] ?? null, $d['meta_descricao']),
            'mostrar_ano_lectivo' => self::bool($raw['pagina_publica_mostrar_ano_lectivo'] ?? null, $d['mostrar_ano_lectivo']),
            'mostrar_contactos' => self::bool($raw['pagina_publica_mostrar_contactos'] ?? null, $d['mostrar_contactos']),
            'usar_logo_instituicao' => self::bool($raw['pagina_publica_usar_logo_instituicao'] ?? null, $d['usar_logo_instituicao']),
            'carousel_activo' => self::bool($raw['pagina_publica_carousel_activo'] ?? null, $d['carousel_activo']),
            'carousel_intervalo' => max(2, min(30, (int) ($raw['pagina_publica_carousel_intervalo'] ?? $d['carousel_intervalo']))),
            'mostrar_registo' => self::bool($raw['pagina_publica_mostrar_registo'] ?? null, $d['mostrar_registo']),
            'carousel_slides' => self::slidesCarousel($raw),
        ];

        if ($anoLectivo) {
            $config['badge'] = str_replace('{ano}', $anoLectivo, $config['badge']);
        }

        $config['hero_gradiente'] = self::heroGradiente(
            $config['cor_primaria_escura'],
            $config['cor_primaria'],
            $config['cor_secundaria']
        );

        return $config;
    }

    /**
     * @param  array<string, string|null>  $raw
     * @return list<array{indice: int, imagem: string|null, titulo: string, texto: string}>
     */
    public static function slidesCarousel(array $raw): array
    {
        $slides = [];
        for ($i = 1; $i <= self::CAROUSEL_SLOTS; $i++) {
            $imagem = trim((string) ($raw["pagina_publica_carousel_{$i}_imagem"] ?? ''));
            if ($imagem === '') {
                continue;
            }
            $slides[] = [
                'indice' => $i,
                'imagem' => $imagem,
                'url' => self::urlImagem($imagem),
                'titulo' => self::texto($raw["pagina_publica_carousel_{$i}_titulo"] ?? null, ''),
                'texto' => self::texto($raw["pagina_publica_carousel_{$i}_texto"] ?? null, ''),
                'largura' => self::dimensao($raw["pagina_publica_carousel_{$i}_largura"] ?? null, self::CAROUSEL_LARGURA_PADRAO),
                'altura' => self::dimensao($raw["pagina_publica_carousel_{$i}_altura"] ?? null, self::CAROUSEL_ALTURA_PADRAO),
            ];
        }

        return $slides;
    }

    public static function urlImagem(?string $path): ?string
    {
        return PublicStorage::url($path);
    }

    public static function dimensao(?string $valor, int $fallback, int $min = 200, int $max = 1400): int
    {
        $n = (int) trim((string) ($valor ?? ''));

        if ($n < $min) {
            return $fallback;
        }

        return min($max, $n);
    }

    public static function cor(?string $valor, string $fallback): string
    {
        $valor = trim((string) $valor);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $valor) ? strtolower($valor) : $fallback;
    }

    public static function texto(?string $valor, string $fallback): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $fallback;
    }

    public static function bool(?string $valor, bool $fallback): bool
    {
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        return in_array($valor, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return list<string>
     */
    public static function lista(?string $valor, string $fallback): array
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            $texto = $fallback;
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $texto) ?: [])));
    }

    /**
     * @return array{inicio: string, meio: string, fim: string, claro: string}
     */
    public static function heroGradiente(string $escura, string $primaria, string $secundaria): array
    {
        return [
            'inicio' => self::escurecer($escura, 0.15),
            'meio' => $primaria,
            'fim' => self::clarear($primaria, 0.12),
            'claro' => self::clarear($secundaria, 0.35),
        ];
    }

    public static function escurecer(string $hex, float $factor): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $factor = max(0, min(1, $factor));

        return self::hex(
            (int) round($r * (1 - $factor)),
            (int) round($g * (1 - $factor)),
            (int) round($b * (1 - $factor))
        );
    }

    public static function clarear(string $hex, float $factor): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $factor = max(0, min(1, $factor));

        return self::hex(
            (int) round($r + (255 - $r) * $factor),
            (int) round($g + (255 - $g) * $factor),
            (int) round($b + (255 - $b) * $factor)
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }
}
