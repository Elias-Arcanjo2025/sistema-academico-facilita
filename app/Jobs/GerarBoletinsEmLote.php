<?php

namespace App\Jobs;

use App\Models\Aluno;
use App\Models\Turma;
use App\Models\User;
use App\Services\BoletimLayoutService;
use App\Services\ConfiguracaoService;
use App\Services\ExportService;
use App\Services\PdfExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ZipArchive;

class GerarBoletinsEmLote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutos

    protected int $turmaId;
    protected ?string $anoLectivo;
    protected int $userId;

    public function __construct(int $turmaId, ?string $anoLectivo, int $userId)
    {
        $this->turmaId = $turmaId;
        $this->anoLectivo = $anoLectivo;
        $this->userId = $userId;
    }

    public function handle(
        ExportService $exportService,
        PdfExportService $pdfExportService,
        BoletimLayoutService $boletimLayout,
    ): void {
        $turma = Turma::with(['curso', 'alunos' => function ($q) {
            $q->orderBy('numero_ordem');
        }])->findOrFail($this->turmaId);

        $user = User::find($this->userId);
        $timestamp = now()->format('YmdHis');
        $zipFilename = "boletins_{$turma->codigo}_{$timestamp}.zip";
        $zipPath = storage_path("app/exports/{$zipFilename}");
        $storagePath = "exports/{$zipFilename}";

        // Garantir que o directorio existe
        if (!file_exists(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Nao foi possivel criar o ficheiro ZIP.');
        }

        $gerados = 0;
        $erros = [];
        $layoutView = $boletimLayout->dadosParaView();

        foreach ($turma->alunos as $aluno) {
            try {
                $dados = $exportService->gerarDadosBoletim($aluno, $this->anoLectivo);
                $dados['instituicao'] = app(ConfiguracaoService::class)->getInstituicao();
                $dados = array_merge($dados, $layoutView);

                $pdf = $pdfExportService->loadView($boletimLayout->viewAnual(), $dados);

                $pdfContent = $pdf->output();
                $pdfFilename = sprintf(
                    '%02d_%s.pdf',
                    $aluno->numero_ordem,
                    $this->sanitizeFilename($aluno->nome)
                );

                $zip->addFromString($pdfFilename, $pdfContent);
                $gerados++;
            } catch (\Exception $e) {
                $erros[] = [
                    'aluno' => $aluno->nome,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        $zip->close();

        activity()
            ->causedBy($user)
            ->performedOn($turma)
            ->withProperties([
                'tipo' => 'boletins_em_lote',
                'formato' => 'zip',
                'turma' => $turma->codigo,
                'ano_lectivo' => $this->anoLectivo,
                'total_alunos' => $turma->alunos->count(),
                'gerados' => $gerados,
                'erros' => count($erros),
                'ficheiro' => $zipFilename,
            ])
            ->log('Geracao de boletins em lote');

        cache()->put(
            "boletins_lote_{$this->userId}_{$this->turmaId}",
            [
                'ficheiro' => $storagePath,
                'turma' => $turma->codigo,
                'gerados' => $gerados,
                'erros' => $erros,
                'criado_em' => now()->toDateTimeString(),
            ],
            now()->addHours(24)
        );
    }

    protected function sanitizeFilename(string $filename): string
    {
        // Converter para ASCII (remove acentos mantendo maiúsculas/minúsculas)
        $filename = \Illuminate\Support\Str::ascii($filename);

        // Remover caracteres especiais, manter apenas letras, números e espaços
        $filename = preg_replace('/[^a-zA-Z0-9\s]/', '', $filename);

        // Substituir múltiplos espaços por underscore único
        $filename = preg_replace('/\s+/', '_', trim($filename));

        // Converter para Title Case (primeira letra de cada palavra maiúscula)
        $filename = ucwords(strtolower($filename), '_');

        // Limitar tamanho
        return substr($filename, 0, 50);
    }

    public function failed(\Throwable $exception): void
    {
        activity()
            ->causedBy(User::find($this->userId))
            ->withProperties([
                'turma_id' => $this->turmaId,
                'erro' => $exception->getMessage(),
            ])
            ->log('Erro na geracao de boletins em lote');
    }
}
