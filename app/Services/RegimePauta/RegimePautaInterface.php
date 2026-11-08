<?php

namespace App\Services\RegimePauta;

/**
 * Contrato dos regimes de cálculo de pauta.
 *
 * Cada regime define o LAYOUT (colunas) e as FÓRMULAS específicas. O
 * PautaService orquestra mas não decide formulas — pergunta ao regime.
 *
 * Implementações actuais:
 *  - RegimePolitecnico   : Informática / técnico (com MFT2 e carry-overs)
 *  - RegimeAdministracao : Contabilidade / Gestão (trimestres independentes)
 */
interface RegimePautaInterface
{
    /**
     * Identificador do regime (constante usada no campo cursos.regime_calculo).
     */
    public function id(): string;

    /**
     * Nome legível para apresentação.
     */
    public function nome(): string;

    /**
     * Esquema de colunas POR DISCIPLINA para um dado trimestre e classe.
     *
     * Cada item: ['key' => string, 'header' => string, 'tipo' => 'nota'|'formula'|'int', 'destaque' => bool?]
     *
     * @param int $trimestre 1, 2 ou 3
     * @param int $classeNumero 10, 11 ou 12
     * @param array{
     *   recurso?:bool,
     *   recurso_disciplina?:bool,
     *   terminal_classe_atual?:bool,
     *   classe_inicio?:int,
     *   classe_fim?:int,
     *   classe_max?:int,
     *   faltas_fundidas?:bool,
     *   usa_mtp?:bool,
     *   projeto_tecnologico?:bool
     * } $flags
     * @return array<int, array<string, mixed>>
     */
    public function colunasDisciplina(int $trimestre, int $classeNumero, array $flags = []): array;

    /**
     * Tipos de Nota (constantes de App\Models\Nota::TIPO_*) necessários para o
     * trimestre/classe. Usado na query de carregamento de notas para evitar N+1.
     *
     * @return array<int, string>
     */
    public function tiposNotaUsados(int $trimestre, int $classeNumero): array;

    /**
     * Se este regime usa a MFT2 (Média Final dos 2 trimestres acumulada).
     */
    public function precisaMFT2(): bool;

    /**
     * Se este regime mostra/usa carry-over de CA das classes anteriores na 12.ª.
     */
    public function carregaCAsAnteriores(): bool;

    /**
     * Se a pauta final (T3) deste regime tem uma coluna "MÉDIA" do aluno
     * (média aritmética das CAs do ano).
     */
    public function temColunaMediaAluno(int $trimestre): bool;

    /**
     * Calcula a CF (Classificação Final do ano) a partir das MTs trimestrais
     * e/ou MFT2 (regime politécnico) — cada regime aplica a sua aritmética.
     */
    public function calcularCF(?float $mt1, ?float $mt2, ?float $mt3, ?float $mft2): ?float;

    /**
     * Tipos de nota que entram no cálculo da média trimestral (MT).
     *
     * @return array<int, string>
     */
    public function componentesMediaTrimestral(int $trimestre): array;

    /**
     * Divisor da média trimestral (ex.: 3 para T1/T2, 2 para T3 politécnico).
     */
    public function divisorMediaTrimestral(int $trimestre): int;

    /**
     * Se o trimestre permite lançamento de PT (prova trimestral).
     * No 3.º trimestre politécnico usa-se PG em vez de PT3.
     */
    public function usaProvaTrimestral(int $trimestre): bool;

    /**
     * Devolve o valor usado para decidir Aprovado/Reprovado e contar negativas
     * para uma dada disciplina × trimestre × classe.
     *
     * - Politécnico: T1/T2 = MT; T3 10/11 = CA; T3 12 = CFD (com substituições).
     * - Administração: T1/T2 = MT; T3 = CA (com substituições).
     *
     * @param array<string, mixed> $base estrutura com mac, pp, pt, mt, mft2, cf, pg, ca, ca10, ca11, cfd, recurso, melhoria, extraordinario
     */
    public function valorParaResultado(array $base, int $trimestre, int $classeNumero): ?float;

    /**
     * Chave da coluna usada na fórmula RESULTADO do Excel (por disciplina).
     *
     * @param  array<int, array<string, mixed>>  $colsDisc
     */
    public function chaveColunaResultado(int $trimestre, int $classeNumero, array $colsDisc): string;

    /**
     * Devolve as expressões de fórmula Excel para cada coluna calculada,
     * em função do mapa de colunas resolvidas.
     *
     * @param callable $get função que recebe a key da coluna (ex.: 'mac') e
     *                      devolve a coordenada Excel correspondente (ex.: 'G15')
     * @param int $classeNumero
     * @return array<string, string>  ['mt' => '=...', 'mft2' => '=...', 'cf' => '=...', 'ca' => '=...', 'cfd' => '=...']
     */
    public function expressoesExcel(callable $get, int $classeNumero): array;

    /**
     * Calcula CFD para pré-visualização web (espelha as fórmulas Excel).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $flags  classe_inicio, classe_fim, classe_max, terminal_classe_atual
     */
    public function calcularCfdPreview(array $base, int $classeNumero, array $flags = []): ?float;
}
