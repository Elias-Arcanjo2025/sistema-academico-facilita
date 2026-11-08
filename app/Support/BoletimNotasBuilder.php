<?php

namespace App\Support;

use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Services\RegimePauta\RegimePautaInterface;
use Illuminate\Support\Collection;

class BoletimNotasBuilder
{
    /** @var array<string, float>|null */
    protected ?array $cache = null;

    public function __construct(
        protected int $alunoId,
        protected int $turmaId,
        protected int $anoLectivoId,
        protected string $anoLectivo,
    ) {}

    /**
     * Prefetch em memória — evita N+1 em mini-pautas / boletins.
     * Preferência: nota pós-conselho sobre pré-conselho.
     *
     * @param  Collection<int, Nota>|iterable<Nota>  $notas
     */
    public function comNotas(iterable $notas): self
    {
        $cache = [];
        $scores = [];
        $incluirArquivadas = NotaLancamentoRules::anoLectivoEhHistorico($this->anoLectivoId);

        foreach ($notas as $nota) {
            if ((int) $nota->aluno_id !== $this->alunoId) {
                continue;
            }
            if ($nota->archived_at !== null && ! $incluirArquivadas) {
                continue;
            }

            $trimestre = $nota->trimestre === null ? 'anual' : (string) (int) $nota->trimestre;
            $key = $nota->disciplina_id.'|'.$trimestre.'|'.$nota->tipo;
            $isPos = $nota->estado_conselho === Nota::ESTADO_POS_CONSELHO;
            $score = ($nota->archived_at === null ? 2 : 0) + ($isPos ? 1 : 0);

            if (! isset($scores[$key]) || $score >= $scores[$key]) {
                $cache[$key] = (float) $nota->valor;
                $scores[$key] = $score;
            }
        }

        $this->cache = $cache;

        return $this;
    }

    /**
     * @return array<string, ?float>
     */
    public function linhaTrimestre(
        Disciplina $disciplina,
        int $trimestre,
        RegimePautaInterface $regime,
        int $classeNumero,
    ): array {
        $mac = $this->valor($disciplina->id, $trimestre, Nota::TIPO_MAC);
        $pp = $this->valor($disciplina->id, $trimestre, Nota::TIPO_PP);
        $pt = $regime->usaProvaTrimestral($trimestre)
            ? $this->valor($disciplina->id, $trimestre, Nota::TIPO_PT)
            : null;

        // Preferir MT recalculada a partir das componentes (como o Excel),
        // para não mostrar CF/CA desalinhados por MT persistida desactualizada.
        $mt = $this->calcularMT($mac, $pp, $pt, $regime, $trimestre)
            ?? $this->valor($disciplina->id, $trimestre, Nota::TIPO_MT);

        $mt1 = $this->mtTrimestre($disciplina->id, 1, $regime);
        $mt2 = $trimestre >= 2
            ? ($trimestre === 2 ? $mt : $this->mtTrimestre($disciplina->id, 2, $regime))
            : null;

        $linha = [
            'mac' => $mac,
            'pp' => $pp,
            'pt' => $pt,
            'mt' => $mt,
            'mt1' => null,
            'mft2' => null,
            'cf' => null,
            'pg' => null,
            'ca' => null,
            'cfd' => null,
            'recuperacao' => null,
            'recurso' => null,
            'nota_final' => null,
        ];

        if ($trimestre >= 2 && $regime->precisaMFT2()) {
            $linha['mt1'] = $mt1;
            $linha['mft2'] = $this->calcularMFT2($mt1, $mt2);
        }

        if ($trimestre === 3) {
            $linha['pg'] = $this->valorPg($disciplina->id, $regime);

            // Preferir CF/CA recalculados (CF arredondado → CA), alinhado ao Excel ROUND
            $cfCalc = $regime->calcularCF(null, null, $mt, $linha['mft2']);
            $linha['cf'] = $cfCalc ?? $this->valorAnual($disciplina->id, Nota::TIPO_CF);
            if ($linha['cf'] !== null) {
                $linha['cf'] = NotaArredondamento::media($linha['cf']);
            }

            $tipoCa = BoletimColunas::tipoCa($classeNumero);
            $caCalc = $this->calcularCA($linha['cf'], $linha['pg']);
            $linha['ca'] = $caCalc ?? $this->valorAnual($disciplina->id, $tipoCa);
            $linha['cfd'] = $this->valorAnual($disciplina->id, Nota::TIPO_CFD);
            $linha['recuperacao'] = $this->valorRecuperacaoAnual($disciplina->id);
            $linha['recurso'] = $this->valorRecurso($disciplina->id);
            $linha['nota_final'] = $this->notaFinalEfectiva($linha, $classeNumero);
        }

        return $linha;
    }

    /**
     * Nota oficial para aprovação (substitui CA/CFD quando há recuperação/recurso).
     *
     * @param  array<string, ?float>  $linha
     */
    protected function notaFinalEfectiva(array $linha, int $classeNumero): ?float
    {
        $usaCfd = $linha['cfd'] !== null;

        return \App\Support\PautaCalculos::notaResultadoDisciplina(
            [
                'ca' => $linha['ca'],
                'cfd' => $linha['cfd'],
                'recuperacao' => $linha['recuperacao'],
                'recurso' => $linha['recurso'],
            ],
            $usaCfd,
        );
    }

    protected function valorPg(int $disciplinaId, RegimePautaInterface $regime): ?float
    {
        // Usar cache/métodos locais (evita Nota::valorProvaGlobal extra).
        $pg = $this->valorAnual($disciplinaId, Nota::TIPO_PG);
        if ($pg !== null || $regime->usaProvaTrimestral(3)) {
            return $pg;
        }

        return $this->valor($disciplinaId, 3, Nota::TIPO_PT);
    }

    protected function valorRecuperacaoAnual(int $disciplinaId): ?float
    {
        return $this->valorAnual($disciplinaId, Nota::TIPO_RECUPERACAO);
    }

    protected function valorRecurso(int $disciplinaId): ?float
    {
        return $this->valor($disciplinaId, 3, Nota::TIPO_RECURSO)
            ?? $this->valorAnual($disciplinaId, Nota::TIPO_RECURSO);
    }

    protected function mtTrimestre(int $disciplinaId, int $trimestre, RegimePautaInterface $regime): ?float
    {
        $mac = $this->valor($disciplinaId, $trimestre, Nota::TIPO_MAC);
        $pp = $this->valor($disciplinaId, $trimestre, Nota::TIPO_PP);
        $pt = $regime->usaProvaTrimestral($trimestre)
            ? $this->valor($disciplinaId, $trimestre, Nota::TIPO_PT)
            : null;

        return $this->calcularMT($mac, $pp, $pt, $regime, $trimestre)
            ?? $this->valor($disciplinaId, $trimestre, Nota::TIPO_MT);
    }

    protected function calcularMT(
        ?float $mac,
        ?float $pp,
        ?float $pt,
        RegimePautaInterface $regime,
        int $trimestre,
    ): ?float {
        $componentes = $regime->componentesMediaTrimestral($trimestre);
        $mapa = [
            Nota::TIPO_MAC => $mac,
            Nota::TIPO_PP => $pp,
            Nota::TIPO_PT => $pt,
        ];

        $valores = [];
        foreach ($componentes as $tipo) {
            $valor = $mapa[$tipo] ?? null;
            if ($valor === null) {
                return null;
            }
            $valores[] = $valor;
        }

        if ($valores === []) {
            return null;
        }

        return NotaArredondamento::media(array_sum($valores) / $regime->divisorMediaTrimestral($trimestre));
    }

    protected function calcularMFT2(?float $mt1, ?float $mt2): ?float
    {
        if ($mt1 === null || $mt2 === null) {
            return null;
        }

        return NotaArredondamento::media(($mt1 + $mt2) / 2);
    }

    protected function calcularCA(?float $cf, ?float $pg): ?float
    {
        if ($cf === null || $pg === null) {
            return null;
        }

        return \App\Support\PautaCalculos::caFromCfPg($cf, $pg);
    }

    protected function valor(int $disciplinaId, int $trimestre, string $tipo): ?float
    {
        if ($this->cache !== null) {
            $key = $disciplinaId.'|'.$trimestre.'|'.$tipo;

            return array_key_exists($key, $this->cache) ? $this->cache[$key] : null;
        }

        $query = Nota::query()
            ->where('aluno_id', $this->alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $this->turmaId)
            ->where('ano_lectivo_id', $this->anoLectivoId)
            ->where('trimestre', $trimestre)
            ->where('tipo', $tipo);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($query, $this->anoLectivoId, true);
        $nota = $query
            ->orderByRaw('CASE WHEN estado_conselho = ? THEN 0 ELSE 1 END', [Nota::ESTADO_POS_CONSELHO])
            ->first();

        return $nota !== null ? (float) $nota->valor : null;
    }

    protected function valorAnual(int $disciplinaId, string $tipo): ?float
    {
        if ($this->cache !== null) {
            $key = $disciplinaId.'|anual|'.$tipo;

            return array_key_exists($key, $this->cache) ? $this->cache[$key] : null;
        }

        $query = Nota::query()
            ->where('aluno_id', $this->alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $this->turmaId)
            ->where('ano_lectivo_id', $this->anoLectivoId)
            ->whereNull('trimestre')
            ->where('tipo', $tipo);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($query, $this->anoLectivoId, true);
        $nota = $query
            ->orderByRaw('CASE WHEN estado_conselho = ? THEN 0 ELSE 1 END', [Nota::ESTADO_POS_CONSELHO])
            ->first();

        return $nota !== null ? (float) $nota->valor : null;
    }

    public static function paraAluno(Aluno $aluno, ?string $anoLectivo = null): self
    {
        $anoLectivo = $anoLectivo ?? \App\Models\Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        return new self(
            $aluno->id,
            (int) $aluno->turma_id,
            (int) $anoLectivoId,
            $anoLectivo,
        );
    }
}
