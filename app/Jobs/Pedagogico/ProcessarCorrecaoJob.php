<?php

namespace App\Jobs\Pedagogico;

use App\Models\Pedagogico\PedGeracaoIA;
use App\Models\Pedagogico\PedProva;
use App\Models\User;
use App\Services\Pedagogico\CorrecaoAssistidaService;
use App\Services\Pedagogico\IaGenerationLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessarCorrecaoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $provaId,
        public string $ficheiroPath,
        public int $professorId,
        public ?int $alunoId = null,
        public ?int $geracaoIaId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(CorrecaoAssistidaService $service): void
    {
        $prova = PedProva::findOrFail($this->provaId);
        $professor = User::findOrFail($this->professorId);
        $ficheiro = $this->criarUploadedFile($this->ficheiroPath);

        $service->processar($prova, $ficheiro, $professor, $this->alunoId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessarCorrecaoJob falhou', [
            'prova_id' => $this->provaId,
            'ficheiro_path' => $this->ficheiroPath,
            'erro' => $exception->getMessage(),
        ]);

        if ($this->geracaoIaId) {
            $geracao = PedGeracaoIA::find($this->geracaoIaId);
            if ($geracao && $geracao->status === PedGeracaoIA::STATUS_PROCESSING) {
                app(IaGenerationLogger::class)->falhar($geracao, $exception->getMessage());
            }
        }
    }

    protected function criarUploadedFile(string $path): UploadedFile
    {
        $disk = config('pedagogico.ocr.storage_disk', 'local');
        $fullPath = Storage::disk($disk)->path($path);

        return new UploadedFile(
            $fullPath,
            basename($fullPath),
            mime_content_type($fullPath) ?: null,
            null,
            true
        );
    }
}
