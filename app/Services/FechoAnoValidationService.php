<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\DirectorTurma;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Support\PautaResultadoTransicao;

class FechoAnoValidationService
{
    public function __construct(
        protected AlunoTransicaoService $transicaoService,
        protected AnoLectivoTransferService $transferService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function validateConfigurar(string $anoOrigem, string $anoDestino): array
    {
        $errors = $this->transicaoService->validateTransicao($anoOrigem, $anoDestino);

        $destino = AnoLectivo::where('nome', $anoDestino)->first();
        if ($destino && ! $destino->isNovo()) {
            $errors[] = "O ano lectivo {$anoDestino} deve estar marcado como NOVO.";
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    public function validateTransferir(string $anoOrigem, string $anoDestino): array
    {
        return $this->transferService->validateTransfer($anoOrigem, $anoDestino);
    }

    /**
     * @return array<int, string>
     */
    public function validateTransitar(string $anoOrigem, string $anoDestino): array
    {
        $errors = $this->validateConfigurar($anoOrigem, $anoDestino);

        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);

        if ($anoOrigemId && $anoDestinoId) {
            $turmasOrigem = Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->get();
            $turmasDestino = Turma::with('classe')->where('ano_lectivo_id', $anoDestinoId)->get();
            $mapPromocao = $this->transicaoService->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);

            if (collect($mapPromocao)->filter()->isEmpty()) {
                $errors[] = 'Não existem turmas de promoção (classe seguinte) no ano destino.';
            }
        }

        return $errors;
    }

    /**
     * @return array{ok: bool, erros: array<int, string>, avisos: array<int, string>}
     */
    public function validateActivar(string $anoOrigem, string $anoDestino): array
    {
        $erros = $this->validateConfigurar($anoOrigem, $anoDestino);
        $avisos = [];

        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);
        $destino = AnoLectivo::where('nome', $anoDestino)->first();

        if ($destino?->isActual()) {
            $avisos[] = 'Este ano lectivo já está activo.';
        }

        if ($anoOrigemId && $anoDestinoId) {
            $turmasOrigem = Turma::where('ano_lectivo_id', $anoOrigemId)->count();
            $turmasDestino = Turma::where('ano_lectivo_id', $anoDestinoId)->count();

            if ($turmasDestino < $turmasOrigem) {
                $erros[] = "O ano destino tem {$turmasDestino} turma(s), mas a origem tem {$turmasOrigem}. Execute a transferência de estrutura.";
            }

            $assocOrigem = ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoOrigemId)->where('activo', true)->count();
            $assocDestino = ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoDestinoId)->where('activo', true)->count();
            if ($assocOrigem > 0 && $assocDestino === 0) {
                $erros[] = 'Não existem associações professor no ano destino. Execute a transferência de estrutura.';
            }

            $alunosOrigem = Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoOrigemId))->where('activo', true)->count();
            $alunosDestino = Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoDestinoId))->count();

            if ($alunosOrigem > 0 && $alunosDestino === 0) {
                $erros[] = 'Não existem alunos no ano destino. Execute a transição de alunos.';
            }

            if ($alunosOrigem > 0 && $alunosDestino > 0 && $alunosDestino < $alunosOrigem) {
                $avisos[] = "Apenas {$alunosDestino} de {$alunosOrigem} aluno(s) estão no ano destino — confirme se é intencional.";
            }
        }

        $t3Fechado = Configuracao::get('trimestre_3_fechado');
        if ($t3Fechado !== 'true' && $t3Fechado !== '1') {
            $avisos[] = 'O trimestre 3 não está marcado como fechado. Recomenda-se fechar antes de activar o novo ano.';
        }

        return [
            'ok' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }

    /**
     * Marca transferência concluída (persistido em configuracoes).
     */
    public function marcarTransferenciaExecutada(string $anoOrigem, string $anoDestino): void
    {
        Configuracao::set(
            $this->chaveMarcador('transfer', $anoOrigem, $anoDestino),
            now()->toIso8601String(),
            'Transferência de estrutura executada via fecho de ano'
        );
    }

    public function marcarTransicaoExecutada(string $anoOrigem, string $anoDestino): void
    {
        Configuracao::set(
            $this->chaveMarcador('transicao', $anoOrigem, $anoDestino),
            now()->toIso8601String(),
            'Transição de alunos executada via fecho de ano'
        );
    }

    public function transferenciaFoiExecutada(string $anoOrigem, string $anoDestino): bool
    {
        return (bool) Configuracao::get($this->chaveMarcador('transfer', $anoOrigem, $anoDestino));
    }

    public function transicaoFoiExecutada(string $anoOrigem, string $anoDestino): bool
    {
        return (bool) Configuracao::get($this->chaveMarcador('transicao', $anoOrigem, $anoDestino));
    }

    private function chaveMarcador(string $tipo, string $origem, string $destino): string
    {
        return "fecho_ano_{$tipo}_{$origem}_{$destino}";
    }
}
