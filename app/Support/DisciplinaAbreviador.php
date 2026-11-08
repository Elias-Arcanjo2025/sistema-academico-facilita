<?php

namespace App\Support;

class DisciplinaAbreviador
{
    /** Boletim individual (coluna mais larga). */
    public const LIMITE_BOLETIM = 18;

    /** Boletim turma — 2 por folha (coluna estreita). */
    public const LIMITE_COMPACTO = 12;

    /**
     * Abreviações oficiais (chaves normalizadas em maiúsculas sem acentos extras).
     *
     * @var array<string, string>
     */
    private const MAPA = [
        'EMPREENDEDORISMO' => 'EMPREEND.',
        'PROJECTO TECNOLOGICO' => 'PROJ. TEC.',
        'PROJETO TECNOLOGICO' => 'PROJ. TEC.',
        'INGLES TECNICO' => 'ING. TEC.',
        'TECNOLOGIAS DE PROGRAMACAO DE COMPUTADORES' => 'TLP',
        'TECNOLOGIA DE PROGRAMACAO DE COMPUTADORES' => 'TLP',
        'TECNICAS DE REPARACAO DE EQUIPAMENTOS INFORMATICOS' => 'TREI',
        'ORGANIZACAO GESTAO DE INFORMATICAS' => 'OGI',
        'ORGANIZACAO E GESTAO DE INFORMATICAS' => 'OGI',
        'SISTEMA DE ARQUIVO EDITORACAO GRAFICA' => 'SEAC',
        'SISTEMAS DE ARQUIVO EDITORACAO GRAFICA' => 'SEAC',
        'TECNOLOGIAS DE INFORMACAO E COMUNICACAO' => 'TIC',
        'TECNOLOGIA DE INFORMACAO E COMUNICACAO' => 'TIC',
        'LINGUA PORTUGUESA' => 'L. PORT.',
        'LINGUA INGLESA' => 'L. INGL.',
        'LINGUA FRANCESA' => 'L. FRANC.',
        'EDUCACAO FISICA' => 'ED. FIS.',
        'EDUCACAO VISUAL' => 'ED. VIS.',
        'EDUCACAO MUSICAL' => 'ED. MUS.',
        'EDUCACAO MORAL E CIVICA' => 'ED. MOR./CIV.',
        'EDUCACAO PARA A CIDADANIA' => 'ED. CID.',
        'CIENCIAS NATURAIS' => 'CIEN. NAT.',
        'FISICO-QUIMICA' => 'FIS-QUIM.',
        'INFORMATICA' => 'INFORM.',
        'DESENHO TECNICO' => 'DES. TEC.',
        'GESTAO ADMINISTRATIVA' => 'GEST. ADM.',
        'CONTABILIDADE E FINANCAS' => 'CONT./FIN.',
        'ORGANIZACAO E GESTAO DE EMPRESAS' => 'OGE',
        'PSICOLOGIA ORGANIZACIONAL' => 'PSIC. ORG.',
    ];

    public static function paraBoletim(string $nome, ?string $codigo = null): string
    {
        return self::abreviar($nome, $codigo, self::LIMITE_BOLETIM);
    }

    public static function paraBoletimCompacto(string $nome, ?string $codigo = null): string
    {
        return self::abreviar($nome, $codigo, self::LIMITE_COMPACTO);
    }

    public static function abreviar(string $nome, ?string $codigo = null, int $maxLen = self::LIMITE_BOLETIM): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return '';
        }

        if ($codigo !== null && ($codigo = trim($codigo)) !== '' && mb_strlen($codigo) <= 6) {
            return mb_strtoupper($codigo, 'UTF-8');
        }

        $chave = self::normalizar($nome);
        $upper = mb_strtoupper($nome, 'UTF-8');

        if (mb_strlen($upper) <= $maxLen) {
            return $upper;
        }

        if (isset(self::MAPA[$chave])) {
            return self::MAPA[$chave];
        }

        foreach (self::padroes() as $pattern => $abrev) {
            if (preg_match($pattern, $chave)) {
                return $abrev;
            }
        }

        return self::encurtar($upper, $maxLen);
    }

    protected static function normalizar(string $nome): string
    {
        $nome = mb_strtoupper(trim($nome), 'UTF-8');
        $nome = str_replace(['Á', 'À', 'Â', 'Ã', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'], [
            'A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C',
        ], $nome);
        $nome = preg_replace('/\s+/', ' ', $nome) ?? $nome;

        return trim($nome);
    }

    /**
     * @return array<string, string>
     */
    protected static function padroes(): array
    {
        return [
            '/PROJECTO\s+TECNOLOGICO|PROJETO\s+TECNOLOGICO/' => 'PROJ. TEC.',
            '/INGLES\s+TECNICO/' => 'ING. TEC.',
            '/EMPREENDEDORISMO/' => 'EMPREEND.',
            '/TECNICAS\s+DE\s+REPARACAO/' => 'TREI',
            '/ORGANIZACAO.*GESTAO.*INFORMATIC/' => 'OGI',
            '/ARQUIVO.*EDITORACAO.*GRAFIC/' => 'SEAC',
            '/PROGRAMACAO\s+DE\s+COMPUTADOR/' => 'TLP',
            '/TECNOLOGIAS?\s+DE\s+INFORMACAO\s+E\s+COMUNICACAO/' => 'TIC',
            '/DESENHO\s+TECNICO/' => 'DES. TEC.',
        ];
    }

    protected static function encurtar(string $upper, int $maxLen): string
    {
        $palavras = preg_split('/\s+/', $upper) ?: [$upper];

        if (count($palavras) >= 2) {
            $sigla = implode('', array_map(fn (string $p) => mb_substr($p, 0, 1), $palavras));
            if (mb_strlen($sigla) <= $maxLen && mb_strlen($sigla) >= 3) {
                return $sigla;
            }

            $primeira = $palavras[0];
            $resto = implode(' ', array_slice($palavras, 1));
            $abrev = $primeira . ' ' . mb_substr($resto, 0, max(1, $maxLen - mb_strlen($primeira) - 2)) . '.';
            if (mb_strlen($abrev) <= $maxLen + 1) {
                return rtrim($abrev, '. ') . (mb_strlen($abrev) > $maxLen ? '' : '.');
            }
        }

        return mb_substr($upper, 0, max(1, $maxLen - 1)) . '.';
    }
}
