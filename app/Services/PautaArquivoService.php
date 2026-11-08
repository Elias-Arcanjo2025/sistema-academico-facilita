<?php

namespace App\Services;

use App\Exports\MiniPautaExport;
use App\Exports\PautaFinalDisciplinaExport;
use App\Exports\PautaFinalTurmaExport;
use App\Exports\PautaTrimestralCompletaExport;
use App\Models\PautaArquivo;
use App\Models\Turma;
use App\Models\User;
use App\Support\PautaMomentoConselho;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class PautaArquivoService
{
    public function __construct(
        protected ExportService $exportService,
        protected ConfiguracaoService $configuracaoService,
    ) {}

    /**
     * Gera Excel oficial do sistema e arquiva em pauta_arquivos.
     */
    public function arquivarDoSistema(
        string $tipoPauta,
        int $turmaId,
        string $anoLectivo,
        int $trimestre,
        ?int $disciplinaId,
        User $user,
    ): PautaArquivo {
        $turma = Turma::with('curso')->findOrFail($turmaId);

        [$export, $nomeOriginal] = $this->criarExportacao(
            $tipoPauta,
            $turmaId,
            $disciplinaId,
            $trimestre,
            $anoLectivo,
        );

        $proximaVersao = PautaArquivo::getProximaVersao(
            (int) $turma->curso_id,
            $anoLectivo,
            $trimestre,
            $disciplinaId,
        );

        $nomeArmazenado = $this->gerarNomeArmazenado(
            (int) $turma->curso_id,
            $trimestre,
            $proximaVersao,
            'xlsx',
        );

        $caminhoPasta = "pautas/{$anoLectivo}/curso_{$turma->curso_id}";
        $caminhoCompleto = "{$caminhoPasta}/{$nomeArmazenado}";

        Excel::store($export, $caminhoCompleto, 'local');

        $fullPath = Storage::disk('local')->path($caminhoCompleto);

        return PautaArquivo::create([
            'curso_id' => $turma->curso_id,
            'turma_id' => $turmaId,
            'disciplina_id' => $disciplinaId,
            'uploaded_by' => $user->id,
            'ano_lectivo' => $anoLectivo,
            'trimestre' => $trimestre,
            'versao' => $proximaVersao,
            'tipo_pauta' => $tipoPauta,
            'origem' => PautaArquivo::ORIGEM_SISTEMA,
            'nome_original' => $nomeOriginal,
            'nome_armazenado' => $nomeArmazenado,
            'caminho_arquivo' => $caminhoCompleto,
            'tamanho_bytes' => (int) filesize($fullPath),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'hash_arquivo' => hash_file('sha256', $fullPath),
            'descricao' => 'Gerado e arquivado automaticamente pelo sistema em ' . now()->format('d/m/Y H:i'),
            'activo' => true,
        ]);
    }

    /**
     * Guarda ficheiro enviado manualmente.
     */
    public function persistirUpload(UploadedFile $arquivo, array $dados, User $user): PautaArquivo
    {
        $hashArquivo = hash_file('sha256', $arquivo->getRealPath());
        $extensao = $arquivo->getClientOriginalExtension();

        $proximaVersao = PautaArquivo::getProximaVersao(
            (int) $dados['curso_id'],
            $dados['ano_lectivo'],
            (int) $dados['trimestre'],
            $dados['disciplina_id'] ?? null,
        );

        $nomeArmazenado = $this->gerarNomeArmazenado(
            (int) $dados['curso_id'],
            (int) $dados['trimestre'],
            $proximaVersao,
            $extensao,
        );

        $caminhoPasta = "pautas/{$dados['ano_lectivo']}/curso_{$dados['curso_id']}";
        $caminhoCompleto = $arquivo->storeAs($caminhoPasta, $nomeArmazenado, 'local');

        return PautaArquivo::create([
            'curso_id' => $dados['curso_id'],
            'turma_id' => $dados['turma_id'] ?? null,
            'turma_manual' => $dados['turma_manual'] ?? null,
            'sala_id' => $dados['sala_id'] ?? null,
            'sala_manual' => $dados['sala_manual'] ?? null,
            'disciplina_id' => $dados['disciplina_id'] ?? null,
            'disciplina_manual' => $dados['disciplina_manual'] ?? null,
            'uploaded_by' => $user->id,
            'ano_lectivo' => $dados['ano_lectivo'],
            'trimestre' => $dados['trimestre'],
            'versao' => $proximaVersao,
            'tipo_pauta' => $dados['tipo_pauta'],
            'origem' => $dados['origem'],
            'nome_original' => $arquivo->getClientOriginalName(),
            'nome_armazenado' => $nomeArmazenado,
            'caminho_arquivo' => $caminhoCompleto,
            'tamanho_bytes' => $arquivo->getSize(),
            'mime_type' => $arquivo->getMimeType(),
            'hash_arquivo' => $hashArquivo,
            'descricao' => $dados['descricao'] ?? null,
            'activo' => (bool) ($dados['activo'] ?? true),
        ]);
    }

    /**
     * @return array{0: object, 1: string}
     */
    protected function criarExportacao(
        string $tipoPauta,
        int $turmaId,
        ?int $disciplinaId,
        int $trimestre,
        string $anoLectivo,
    ): array {
        $instituicao = $this->configuracaoService->getInstituicao();
        $turma = Turma::findOrFail($turmaId);
        $turmaNome = $turma->nome;

        return match ($tipoPauta) {
            PautaArquivo::TIPO_MINI_PAUTA => (function () use ($turmaId, $disciplinaId, $trimestre, $anoLectivo, $instituicao, $turmaNome) {
                if (! $disciplinaId) {
                    throw new InvalidArgumentException('A disciplina é obrigatória para mini-pauta.');
                }
                $dados = $this->exportService->gerarDadosMiniPauta($turmaId, $disciplinaId, $trimestre, $anoLectivo);
                $dados['instituicao'] = $instituicao;
                $disc = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);

                return [new MiniPautaExport($dados), "mini_pauta_{$turmaNome}_{$disc}_T{$trimestre}.xlsx"];
            })(),

            PautaArquivo::TIPO_FINAL_DISCIPLINA => (function () use ($turmaId, $disciplinaId, $anoLectivo, $instituicao, $turmaNome) {
                if (! $disciplinaId) {
                    throw new InvalidArgumentException('A disciplina é obrigatória para pauta final de disciplina.');
                }
                $dados = $this->exportService->gerarDadosPautaFinalDisciplina($turmaId, $disciplinaId, $anoLectivo);
                $dados['instituicao'] = $instituicao;
                $disc = $dados['disciplina']->codigo ?? substr($dados['disciplina']->nome, 0, 10);

                return [new PautaFinalDisciplinaExport($dados), "pauta_final_{$turmaNome}_{$disc}.xlsx"];
            })(),

            PautaArquivo::TIPO_FINAL_TURMA => (function () use ($turmaId, $anoLectivo, $instituicao, $turmaNome) {
                $dados = $this->exportService->gerarDadosPautaFinalTurma($turmaId, $anoLectivo);
                $dados['instituicao'] = $instituicao;

                return [new PautaFinalTurmaExport($dados), "pauta_final_turma_{$turmaNome}.xlsx"];
            })(),

            PautaArquivo::TIPO_COMPLETA => (function () use ($turmaId, $trimestre, $anoLectivo, $instituicao, $turmaNome) {
                if ($trimestre < 1 || $trimestre > 3) {
                    throw new InvalidArgumentException('Seleccione o trimestre para a pauta completa.');
                }
                $dados = $this->exportService->gerarDadosPautaTrimestralCompleta(
                    $turmaId,
                    $trimestre,
                    $anoLectivo,
                    PautaMomentoConselho::POS,
                );
                $dados['instituicao'] = $instituicao;

                return [new PautaTrimestralCompletaExport($dados), "pauta_aproveitamento_{$turmaNome}_T{$trimestre}.xlsx"];
            })(),

            default => throw new InvalidArgumentException('Tipo de pauta não suportado para geração automática.'),
        };
    }

    protected function gerarNomeArmazenado(
        int $cursoId,
        int $trimestre,
        int $versao,
        string $extensao,
    ): string {
        $timestamp = now()->format('Ymd_His');
        $trimestreStr = $trimestre === 0 ? 'anual' : "t{$trimestre}";

        return "pauta_curso{$cursoId}_{$trimestreStr}_v{$versao}_{$timestamp}.{$extensao}";
    }
}
