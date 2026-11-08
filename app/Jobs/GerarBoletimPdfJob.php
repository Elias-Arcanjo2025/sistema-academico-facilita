<?php

namespace App\Jobs;

use App\Models\Aluno;
use App\Models\User;
use App\Services\BoletimLayoutService;
use App\Services\ConfiguracaoService;
use App\Services\ExportService;
use App\Services\PdfExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GerarBoletimPdfJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número de tentativas
     */
    public int $tries = 3;

    /**
     * Tempo máximo de execução em segundos
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $alunoId,
        public ?int $trimestre = null,
        public ?int $userId = null
    ) {
        // Define a fila como 'pdf' para separar jobs pesados
        $this->onQueue('pdf');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ExportService $exportService,
        PdfExportService $pdfExportService,
        BoletimLayoutService $boletimLayout,
    ): void {
        try {
            $aluno = Aluno::findOrFail($this->alunoId);

            // Gerar dados do boletim
            if ($this->trimestre) {
                // Boletim de trimestre específico
                $dados = $exportService->gerarDadosBoletimTrimestre($aluno, $this->trimestre);
                $view = $boletimLayout->viewTrimestre();
                $filename = sprintf(
                    'boletim_%s_t%d_%s.pdf',
                    Str::slug($aluno->nome),
                    $this->trimestre,
                    now()->format('YmdHis')
                );
            } else {
                // Boletim anual completo
                $dados = $exportService->gerarDadosBoletim($aluno);
                $view = $boletimLayout->viewAnual();
                $filename = sprintf(
                    'boletim_%s_anual_%s.pdf',
                    Str::slug($aluno->nome),
                    now()->format('YmdHis')
                );
            }

            $dados['instituicao'] = app(ConfiguracaoService::class)->getInstituicao();
            $dados = array_merge($dados, $boletimLayout->dadosParaView());

            $pdf = $pdfExportService->loadView($view, $dados);

            // Salvar em storage
            $path = 'boletins/' . $filename;
            Storage::put($path, $pdf->output());

            // Registrar atividade
            activity()
                ->performedOn($aluno)
                ->causedBy($this->userId)
                ->withProperties([
                    'arquivo' => $filename,
                    'trimestre' => $this->trimestre,
                    'tipo' => 'boletim_pdf',
                ])
                ->log('Boletim PDF gerado via fila');

            // Notificar usuário se foi especificado
            if ($this->userId) {
                $user = User::find($this->userId);
                if ($user) {
                    $user->notify(new \App\Notifications\BoletimGeradoNotification(
                        $aluno,
                        $filename,
                        Storage::url($path)
                    ));
                }
            }

            Log::info('Boletim PDF gerado com sucesso', [
                'aluno_id' => $this->alunoId,
                'arquivo' => $filename,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar boletim PDF', [
                'aluno_id' => $this->alunoId,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw para acionar retry
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de geração de boletim falhou definitivamente', [
            'aluno_id' => $this->alunoId,
            'trimestre' => $this->trimestre,
            'erro' => $exception->getMessage(),
        ]);

        // Notificar administradores sobre a falha
        $admins = User::role('admin_full')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\JobFalhadoNotification(
                'Geração de Boletim PDF',
                $this->alunoId,
                $exception->getMessage()
            ));
        }
    }
}
