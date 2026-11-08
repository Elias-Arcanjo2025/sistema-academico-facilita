<?php

namespace App\Services;

class DocumentoAcademicoService
{
    public const CHAVES = [
        'doc_titulo_frequencia' => 'Declaração de Frequência',
        'doc_titulo_matricula' => 'Declaração de Matrícula',
        'doc_titulo_comprovativo' => 'Comprovativo de Inscrição',
        'doc_titulo_ficha' => 'Ficha do Aluno',
        'doc_cor_destaque' => '#7c2d12',
        'doc_mostrar_logo' => '1',
        'doc_mostrar_bi' => '1',
        'doc_mostrar_filiacao' => '1',
        'doc_mostrar_encarregado' => '1',
        'doc_mostrar_nascimento' => '1',
        'doc_mostrar_genero' => '1',
        'doc_mostrar_foto_ficha' => '1',
        'doc_mostrar_assinatura' => '1',
        'doc_cargo_assinatura' => 'A Secretária / Direcção',
        'doc_texto_rodape' => 'Documento emitido pelo sistema académico da instituição',
        'doc_texto_fecho_frequencia' => 'Por ser verdade, passamos a presente declaração, que vai por nós assinada e autenticada.',
        'doc_texto_fecho_matricula' => 'A presente declaração é emitida a pedido do interessado para os fins que se fizerem necessários.',
        'doc_local_emissao' => '',
    ];

    public function __construct(
        protected ConfiguracaoService $configuracao,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $cfg = [];
        foreach (self::CHAVES as $chave => $default) {
            $cfg[str_replace('doc_', '', $chave)] = $this->configuracao->get($chave, $default);
        }

        foreach ([
            'mostrar_logo', 'mostrar_bi', 'mostrar_filiacao', 'mostrar_encarregado',
            'mostrar_nascimento', 'mostrar_genero', 'mostrar_foto_ficha', 'mostrar_assinatura',
        ] as $boolKey) {
            $cfg[$boolKey] = filter_var($cfg[$boolKey] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        if (trim((string) ($cfg['local_emissao'] ?? '')) === '') {
            $inst = $this->configuracao->getInstituicao();
            $cfg['local_emissao'] = $inst['endereco'] ?? 'Luanda';
        }

        return $cfg;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function guardar(array $dados): void
    {
        $map = [
            'titulo_frequencia' => 'doc_titulo_frequencia',
            'titulo_matricula' => 'doc_titulo_matricula',
            'titulo_comprovativo' => 'doc_titulo_comprovativo',
            'titulo_ficha' => 'doc_titulo_ficha',
            'cor_destaque' => 'doc_cor_destaque',
            'mostrar_logo' => 'doc_mostrar_logo',
            'mostrar_bi' => 'doc_mostrar_bi',
            'mostrar_filiacao' => 'doc_mostrar_filiacao',
            'mostrar_encarregado' => 'doc_mostrar_encarregado',
            'mostrar_nascimento' => 'doc_mostrar_nascimento',
            'mostrar_genero' => 'doc_mostrar_genero',
            'mostrar_foto_ficha' => 'doc_mostrar_foto_ficha',
            'mostrar_assinatura' => 'doc_mostrar_assinatura',
            'cargo_assinatura' => 'doc_cargo_assinatura',
            'texto_rodape' => 'doc_texto_rodape',
            'texto_fecho_frequencia' => 'doc_texto_fecho_frequencia',
            'texto_fecho_matricula' => 'doc_texto_fecho_matricula',
            'local_emissao' => 'doc_local_emissao',
        ];

        foreach ($map as $campo => $chave) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }
            $valor = $dados[$campo];
            if (is_bool($valor)) {
                $valor = $valor ? '1' : '0';
            }
            $this->configuracao->set($chave, (string) $valor, 'Personalização de documentos académicos');
        }
    }

    public function tituloPara(string $tipo): string
    {
        $cfg = $this->getConfig();

        return match ($tipo) {
            'frequencia' => (string) $cfg['titulo_frequencia'],
            'matricula' => (string) $cfg['titulo_matricula'],
            'comprovativo' => (string) $cfg['titulo_comprovativo'],
            'ficha' => (string) $cfg['titulo_ficha'],
            default => 'Documento',
        };
    }
}
