<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Support\PaginaPublicaConfig;
use App\Support\TemaInstitucional;

class ConfiguracaoService
{
    /**
     * Obter configuração (cache no model Configuracao).
     */
    public function get(string $chave, $default = null)
    {
        return Configuracao::get($chave, $default);
    }

    /**
     * Definir configuração e invalidar cache
     */
    public function set(string $chave, $valor, ?string $descricao = null): Configuracao
    {
        $config = Configuracao::set($chave, $valor, $descricao);

        // Registar alteracao no log de auditoria
        activity()
            ->performedOn($config)
            ->causedBy(auth()->user())
            ->withProperties([
                'chave' => $chave,
                'valor' => $valor,
            ])
            ->log("Configuração '{$chave}' actualizada");

        return $config;
    }

    /**
     * Obter ano lectivo actual
     */
    public function getAnoLectivoActual(): int
    {
        return (int) $this->get('ano_lectivo_actual', date('Y'));
    }

    /**
     * Obter trimestre actual
     */
    public function getTrimestreActual(): int
    {
        return (int) $this->get('trimestre_actual', 1);
    }

    /**
     * Obter nota mínima de aprovação
     */
    public function getNotaMinimaAprovacao(): float
    {
        return (float) $this->get('nota_minima_aprovacao', 10.0);
    }

    /**
     * Obter pesos dos trimestres para cálculo da média final
     */
    public function getPesosTrimestres(): array
    {
        $pesos = $this->get('pesos_trimestres', '1,1,1');
        $valores = explode(',', $pesos);
        return [
            1 => (float) ($valores[0] ?? 1),
            2 => (float) ($valores[1] ?? 1),
            3 => (float) ($valores[2] ?? 1),
        ];
    }

    /**
     * Obter tipo de cálculo de média
     */
    public function getTipoCalculoMedia(): string
    {
        return $this->get('tipo_calculo_media', 'aritmetica');
    }

    /**
     * Verificar se lançamento de notas está aberto
     */
    public function isLancamentoAberto(int $trimestre): bool
    {
        $dataInicio = $this->get("lancamento_t{$trimestre}_inicio");
        $dataFim = $this->get("lancamento_t{$trimestre}_fim");

        if (!$dataInicio || !$dataFim) {
            return true; // Se não configurado, permite sempre
        }

        $hoje = now()->format('Y-m-d');
        return $hoje >= $dataInicio && $hoje <= $dataFim;
    }

    /**
     * Obter dados da instituição
     */
    public function getInstituicao(): array
    {
        return [
            'nome' => $this->get('instituicao_nome', 'Sistema Facilita'),
            'logo' => $this->get('instituicao_logo'),
            'endereco' => $this->get('instituicao_endereco'),
            'telefone' => $this->get('instituicao_telefone'),
            'email' => $this->get('instituicao_email'),
            'website' => $this->get('instituicao_website'),
            'director_geral_nome' => $this->get('director_geral_nome'),
        ];
    }

    /**
     * Configuração da página pública (cores, textos, opções de apresentação).
     */
    public function getPaginaPublica(?string $anoLectivo = null): array
    {
        $raw = [];
        foreach (PaginaPublicaConfig::todasChaves() as $chave) {
            $raw[$chave] = $this->get($chave);
        }

        return PaginaPublicaConfig::resolve($raw, $anoLectivo);
    }

    /**
     * Tema visual global (sidebar, botões, alertas, dashboard, etc.).
     */
    public function getTemaInstitucional(?string $anoLectivo = null): array
    {
        return TemaInstitucional::fromPaginaPublica($this->getPaginaPublica($anoLectivo));
    }

    /**
     * Obter todas as configurações de PDF estruturadas
     */
    public function getPdfConfig(): array
    {
        return [
            'margins' => [
                'top'    => (int) $this->get('pdf_margin_top',    15),
                'right'  => (int) $this->get('pdf_margin_right',  15),
                'bottom' => (int) $this->get('pdf_margin_bottom', 15),
                'left'   => (int) $this->get('pdf_margin_left',   15),
            ],
            'fonts' => [
                'family' => $this->get('pdf_font_family', 'DejaVu Sans'),
                'sizes' => [
                    'body' => (int) $this->get('pdf_font_size_body', 10),
                    'header' => (int) $this->get('pdf_font_size_header', 13),
                    'table' => $this->get('pdf_font_size_table', '9.5'),
                ],
            ],
            'colors' => [
                'primary' => $this->get('pdf_color_primary', '#0066cc'),
                'table_header' => $this->get('pdf_color_table_header', '#d0d0d0'),
            ],
            'paper' => [
                'size' => $this->get('pdf_paper_size', 'A4'),
                'orientation' => $this->get('pdf_paper_orientation', 'portrait'),
            ],
            'logo' => [
                'width' => (int) $this->get('pdf_logo_width', 60),
            ],
        ];
    }

    /**
     * Obter margens formatadas para CSS @page
     */
    public function getPdfMarginsCss(): string
    {
        $config = $this->getPdfConfig();
        return sprintf(
            '%dmm %dmm %dmm %dmm',
            $config['margins']['top'],
            $config['margins']['right'],
            $config['margins']['bottom'],
            $config['margins']['left']
        );
    }

    /**
     * Obter todas as configurações agrupadas
     */
    public function getAllGrouped(): array
    {
        $configs = Configuracao::all()->keyBy('chave');

        return [
            'ano_lectivo' => [
                'ano_lectivo_actual' => $configs->get('ano_lectivo_actual')?->valor ?? date('Y'),
                'trimestre_actual' => $configs->get('trimestre_actual')?->valor ?? 1,
            ],
            'avaliacao' => [
                'nota_minima_aprovacao' => $configs->get('nota_minima_aprovacao')?->valor ?? 10,
                'tipo_calculo_media' => $configs->get('tipo_calculo_media')?->valor ?? 'aritmetica',
                'pesos_trimestres' => $configs->get('pesos_trimestres')?->valor ?? '1,1,1',
                'max_disciplinas_reprovacao' => (int) ($configs->get('max_disciplinas_reprovacao')?->valor ?? 2),
                'max_disciplinas_sem_nota_desistente' => (int) ($configs->get('max_disciplinas_sem_nota_desistente')?->valor ?? 3),
                'resultado_12_max_negativas_recurso' => (int) ($configs->get('resultado_12_max_negativas_recurso')?->valor ?? 3),
                'resultado_max_negativas_recuperacao' => (int) ($configs->get('resultado_max_negativas_recuperacao')?->valor ?? 2),
                'exames_permitir_lancamento_nao_elegivel' => ($configs->get('exames_permitir_lancamento_nao_elegivel')?->valor ?? '1') === '1',
            ],
            'recurso' => [
                'recurso_nota_min' => (int) ($configs->get('recurso_nota_min')?->valor ?? 6),
                'recurso_nota_max' => (int) ($configs->get('recurso_nota_max')?->valor ?? 9),
                'recurso_max_disciplinas' => (int) ($configs->get('recurso_max_disciplinas')?->valor ?? 3),
            ],
            'melhoria' => [
                'melhoria_nota_min' => (int) ($configs->get('melhoria_nota_min')?->valor ?? 10),
                'melhoria_nota_max' => (int) ($configs->get('melhoria_nota_max')?->valor ?? 16),
                'melhoria_max_disciplinas' => (int) ($configs->get('melhoria_max_disciplinas')?->valor ?? 0),
            ],
            'periodos_lancamento' => [
                'lancamento_t1_inicio' => $configs->get('lancamento_t1_inicio')?->valor,
                'lancamento_t1_fim' => $configs->get('lancamento_t1_fim')?->valor,
                'lancamento_t2_inicio' => $configs->get('lancamento_t2_inicio')?->valor,
                'lancamento_t2_fim' => $configs->get('lancamento_t2_fim')?->valor,
                'lancamento_t3_inicio' => $configs->get('lancamento_t3_inicio')?->valor,
                'lancamento_t3_fim' => $configs->get('lancamento_t3_fim')?->valor,
            ],
            'instituicao' => [
                'instituicao_nome' => $configs->get('instituicao_nome')?->valor ?? 'Sistema Facilita',
                'instituicao_logo' => $configs->get('instituicao_logo')?->valor,
                'instituicao_endereco' => $configs->get('instituicao_endereco')?->valor,
                'instituicao_telefone' => $configs->get('instituicao_telefone')?->valor,
                'instituicao_email' => $configs->get('instituicao_email')?->valor,
                'instituicao_website' => $configs->get('instituicao_website')?->valor,
                'director_geral_nome' => $configs->get('director_geral_nome')?->valor,
            ],
            'portal' => [
                'portal_aluno_notas_activo' => $configs->get('portal_aluno_notas_activo')?->valor ?? '1',
                'portal_aluno_mensagem_bloqueio' => $configs->get('portal_aluno_mensagem_bloqueio')?->valor ?? '',
            ],
            'seguranca' => [
                'bloqueio_inactividade_activo' => $configs->get('bloqueio_inactividade_activo')?->valor ?? '1',
                'bloqueio_inactividade_minutos' => (int) ($configs->get('bloqueio_inactividade_minutos')?->valor ?? 30),
            ],
            'pagina_publica' => $this->mapPaginaPublicaParaFormulario($configs),
            'pagina_publica_carousel' => $this->mapCarouselParaFormulario($configs),
            'pdf' => [
                'pdf_margin_top' => $configs->get('pdf_margin_top')?->valor ?? 10,
                'pdf_margin_right' => $configs->get('pdf_margin_right')?->valor ?? 15,
                'pdf_margin_bottom' => $configs->get('pdf_margin_bottom')?->valor ?? 10,
                'pdf_margin_left' => $configs->get('pdf_margin_left')?->valor ?? 15,
                'pdf_font_family' => $configs->get('pdf_font_family')?->valor ?? 'DejaVu Sans',
                'pdf_font_size_body' => $configs->get('pdf_font_size_body')?->valor ?? 10,
                'pdf_font_size_header' => $configs->get('pdf_font_size_header')?->valor ?? 13,
                'pdf_font_size_table' => $configs->get('pdf_font_size_table')?->valor ?? '9.5',
                'pdf_color_primary' => $configs->get('pdf_color_primary')?->valor ?? '#0066cc',
                'pdf_color_table_header' => $configs->get('pdf_color_table_header')?->valor ?? '#d0d0d0',
                'pdf_paper_size' => $configs->get('pdf_paper_size')?->valor ?? 'A4',
                'pdf_paper_orientation' => $configs->get('pdf_paper_orientation')?->valor ?? 'portrait',
                'pdf_logo_width' => $configs->get('pdf_logo_width')?->valor ?? 60,
            ],
        ];
    }

    /**
     * Actualizar múltiplas configurações de uma vez
     */
    public function updateMany(array $configuracoes): void
    {
        foreach ($configuracoes as $chave => $valor) {
            if ($valor !== null) {
                $this->set($chave, $valor);
            }
        }
    }

    /**
     * Limpar todo o cache de configurações
     */
    public function clearCache(): void
    {
        Configuracao::flushCache();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \App\Models\Configuracao>  $configs
     */
    protected function mapPaginaPublicaParaFormulario($configs): array
    {
        $defaults = PaginaPublicaConfig::defaults();
        $resolved = PaginaPublicaConfig::resolve(
            collect(PaginaPublicaConfig::todasChaves())
                ->mapWithKeys(fn (string $chave) => [$chave => $configs->get($chave)?->valor])
                ->all()
        );

        return [
            'pagina_publica_cor_primaria' => $resolved['cor_primaria'],
            'pagina_publica_cor_primaria_escura' => $resolved['cor_primaria_escura'],
            'pagina_publica_cor_secundaria' => $resolved['cor_secundaria'],
            'pagina_publica_titulo' => $configs->get('pagina_publica_titulo')?->valor ?? $defaults['titulo'],
            'pagina_publica_destaque' => $configs->get('pagina_publica_destaque')?->valor ?? $defaults['destaque'],
            'pagina_publica_subtitulo' => $configs->get('pagina_publica_subtitulo')?->valor ?? $defaults['subtitulo'],
            'pagina_publica_badge' => $configs->get('pagina_publica_badge')?->valor ?? $defaults['badge'],
            'pagina_publica_sobre_titulo' => $configs->get('pagina_publica_sobre_titulo')?->valor ?? $defaults['sobre_titulo'],
            'pagina_publica_sobre_texto' => $configs->get('pagina_publica_sobre_texto')?->valor ?? $defaults['sobre_texto'],
            'pagina_publica_sobre_itens' => $configs->get('pagina_publica_sobre_itens')?->valor ?? $defaults['sobre_itens'],
            'pagina_publica_func_titulo' => $configs->get('pagina_publica_func_titulo')?->valor ?? $defaults['func_titulo'],
            'pagina_publica_func_subtitulo' => $configs->get('pagina_publica_func_subtitulo')?->valor ?? $defaults['func_subtitulo'],
            'pagina_publica_alunos_titulo' => $configs->get('pagina_publica_alunos_titulo')?->valor ?? $defaults['alunos_titulo'],
            'pagina_publica_alunos_subtitulo' => $configs->get('pagina_publica_alunos_subtitulo')?->valor ?? $defaults['alunos_subtitulo'],
            'pagina_publica_cta_titulo' => $configs->get('pagina_publica_cta_titulo')?->valor ?? $defaults['cta_titulo'],
            'pagina_publica_cta_texto' => $configs->get('pagina_publica_cta_texto')?->valor ?? $defaults['cta_texto'],
            'pagina_publica_cta_aluno_titulo' => $configs->get('pagina_publica_cta_aluno_titulo')?->valor ?? $defaults['cta_aluno_titulo'],
            'pagina_publica_cta_aluno_texto' => $configs->get('pagina_publica_cta_aluno_texto')?->valor ?? $defaults['cta_aluno_texto'],
            'pagina_publica_cta_escola_titulo' => $configs->get('pagina_publica_cta_escola_titulo')?->valor ?? $defaults['cta_escola_titulo'],
            'pagina_publica_cta_escola_texto' => $configs->get('pagina_publica_cta_escola_texto')?->valor ?? $defaults['cta_escola_texto'],
            'pagina_publica_footer_texto' => $configs->get('pagina_publica_footer_texto')?->valor ?? $defaults['footer_texto'],
            'pagina_publica_meta_descricao' => $configs->get('pagina_publica_meta_descricao')?->valor ?? $defaults['meta_descricao'],
            'pagina_publica_mostrar_ano_lectivo' => $configs->get('pagina_publica_mostrar_ano_lectivo')?->valor ?? '1',
            'pagina_publica_mostrar_contactos' => $configs->get('pagina_publica_mostrar_contactos')?->valor ?? '1',
            'pagina_publica_usar_logo_instituicao' => $configs->get('pagina_publica_usar_logo_instituicao')?->valor ?? '0',
            'pagina_publica_mostrar_registo' => $configs->get('pagina_publica_mostrar_registo')?->valor ?? '1',
            'registo_publico_activo' => $configs->get('registo_publico_activo')?->valor ?? '1',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \App\Models\Configuracao>  $configs
     */
    protected function mapCarouselParaFormulario($configs): array
    {
        $data = [
            'pagina_publica_carousel_activo' => $configs->get('pagina_publica_carousel_activo')?->valor ?? '0',
            'pagina_publica_carousel_intervalo' => $configs->get('pagina_publica_carousel_intervalo')?->valor ?? '5',
            'slides' => [],
        ];

        for ($i = 1; $i <= PaginaPublicaConfig::CAROUSEL_SLOTS; $i++) {
            $data['slides'][$i] = [
                'imagem' => $configs->get("pagina_publica_carousel_{$i}_imagem")?->valor,
                'url' => PaginaPublicaConfig::urlImagem($configs->get("pagina_publica_carousel_{$i}_imagem")?->valor),
                'titulo' => $configs->get("pagina_publica_carousel_{$i}_titulo")?->valor ?? '',
                'texto' => $configs->get("pagina_publica_carousel_{$i}_texto")?->valor ?? '',
                'largura' => PaginaPublicaConfig::dimensao(
                    $configs->get("pagina_publica_carousel_{$i}_largura")?->valor,
                    PaginaPublicaConfig::CAROUSEL_LARGURA_PADRAO
                ),
                'altura' => PaginaPublicaConfig::dimensao(
                    $configs->get("pagina_publica_carousel_{$i}_altura")?->valor,
                    PaginaPublicaConfig::CAROUSEL_ALTURA_PADRAO,
                    150,
                    900
                ),
            ];
        }

        return $data;
    }

    public function registoPublicoActivo(): bool
    {
        return filter_var(
            $this->get('registo_publico_activo', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function paginaPublicaMostrarRegisto(): bool
    {
        return filter_var(
            $this->get('pagina_publica_mostrar_registo', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function bloqueioInactividadeActivo(): bool
    {
        return filter_var(
            $this->get('bloqueio_inactividade_activo', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function bloqueioInactividadeMinutos(): int
    {
        return max(1, min(480, (int) $this->get('bloqueio_inactividade_minutos', 30)));
    }
}
