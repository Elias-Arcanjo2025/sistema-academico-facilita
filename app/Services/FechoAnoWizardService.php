<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;

class FechoAnoWizardService
{
    public function __construct(
        protected AlunoTransicaoService $transicaoService,
        protected FechoAnoValidationService $validationService,
    ) {}

    /**
     * @return array{ano_origem: string, ano_destino: string, passos: array<int, array<string, mixed>>, pode_activar: bool, preview: ?array, checklist_activar: array}
     */
    public function obterEstado(?string $anoOrigem = null, ?string $anoDestino = null): array
    {
        $anoOrigem = $anoOrigem ?: Configuracao::getAnoLectivoActual();
        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = $anoDestino ? AnoLectivo::getIdByNome($anoDestino) : null;
        $anoDestinoModel = $anoDestino ? AnoLectivo::where('nome', $anoDestino)->first() : null;

        $turmasOrigem = $anoOrigemId
            ? Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->count()
            : 0;

        $turmasDestino = $anoDestinoId
            ? Turma::with('classe')->where('ano_lectivo_id', $anoDestinoId)->count()
            : 0;

        $alunosOrigem = $anoOrigemId
            ? Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoOrigemId))->where('activo', true)->count()
            : 0;

        $alunosDestino = $anoDestinoId
            ? Aluno::whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoDestinoId))->count()
            : 0;

        $temTurmasPromocao = false;
        $preview = null;

        if ($anoOrigemId && $anoDestinoId) {
            $origem = Turma::with('classe')->where('ano_lectivo_id', $anoOrigemId)->get();
            $destino = Turma::with('classe')->where('ano_lectivo_id', $anoDestinoId)->get();
            $mapPromocao = $this->transicaoService->criarMapeamentoPromocao($origem, $destino);
            $temTurmasPromocao = collect($mapPromocao)->filter()->isNotEmpty();

            if ($alunosOrigem > 0) {
                $preview = $this->transicaoService->previewTransicao($anoOrigem, $anoDestino);
            }
        }

        $transferExecutada = $anoDestino
            ? $this->validationService->transferenciaFoiExecutada($anoOrigem, $anoDestino)
            : false;
        $transicaoExecutada = $anoDestino
            ? $this->validationService->transicaoFoiExecutada($anoOrigem, $anoDestino)
            : false;

        $assocOrigem = $anoOrigemId
            ? ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoOrigemId)->where('activo', true)->count()
            : 0;
        $assocDestino = $anoDestinoId
            ? ProfessorDisciplinaTurma::where('ano_lectivo_id', $anoDestinoId)->where('activo', true)->count()
            : 0;

        $passos = [
            [
                'numero' => 1,
                'titulo' => 'Ano lectivo de destino',
                'descricao' => 'Criar e seleccionar o ano NOVO que receberá a estrutura e os alunos.',
                'concluido' => $anoDestinoModel?->isNovo() ?? false,
                'detalhe' => $anoDestino
                    ? ($anoDestinoModel?->isNovo() ? "Ano {$anoDestino} pronto" : 'O ano destino deve estar marcado como NOVO')
                    : 'Seleccione o ano de destino',
            ],
            [
                'numero' => 2,
                'titulo' => 'Turmas no ano destino',
                'descricao' => 'Criar turmas (mesma classe e classe seguinte para promoção).',
                'concluido' => $turmasDestino > 0 && $temTurmasPromocao,
                'detalhe' => "{$turmasDestino} turma(s) no destino · Promoção: ".($temTurmasPromocao ? 'mapeamento possível' : 'criar turmas classe+1'),
            ],
            [
                'numero' => 3,
                'titulo' => 'Transferir estrutura',
                'descricao' => 'Copiar turmas 1:1, associações, directores e coordenadores.',
                'concluido' => $transferExecutada || ($assocDestino > 0 && $assocDestino >= $assocOrigem && $turmasDestino >= $turmasOrigem),
                'detalhe' => $transferExecutada
                    ? 'Transferência executada'
                    : "{$assocDestino}/{$assocOrigem} associação(ões) no destino",
            ],
            [
                'numero' => 4,
                'titulo' => 'Transitar alunos',
                'descricao' => 'Aprovados → classe seguinte; reprovados → mesma classe (regras DE 424/25).',
                'concluido' => $transicaoExecutada || ($alunosDestino > 0 && $alunosOrigem > 0),
                'detalhe' => $transicaoExecutada
                    ? 'Transição executada'
                    : "{$alunosDestino} aluno(s) no destino · {$alunosOrigem} activos na origem",
            ],
            [
                'numero' => 5,
                'titulo' => 'Activar ano lectivo',
                'descricao' => 'Tornar o ano destino o ano lectivo actual do sistema.',
                'concluido' => $anoDestinoModel?->isActual() ?? false,
                'detalhe' => $anoDestinoModel?->isActual()
                    ? "Ano {$anoDestino} já está activo"
                    : 'Executar após confirmar turmas e alunos',
            ],
        ];

        $checklistActivar = $anoDestino
            ? $this->validationService->validateActivar($anoOrigem, $anoDestino)
            : ['ok' => false, 'erros' => [], 'avisos' => []];

        return [
            'ano_origem' => $anoOrigem,
            'ano_destino' => $anoDestino ?? '',
            'passos' => $passos,
            'pode_activar' => ($anoDestinoModel?->isNovo() ?? false) && $checklistActivar['ok'],
            'estatisticas' => compact(
                'turmasOrigem',
                'turmasDestino',
                'alunosOrigem',
                'alunosDestino',
                'temTurmasPromocao',
                'transferExecutada',
                'transicaoExecutada',
                'assocOrigem',
                'assocDestino'
            ),
            'preview' => $preview,
            'checklist_activar' => $checklistActivar,
        ];
    }
}
