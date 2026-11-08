<?php

namespace App\Services\WhatsApp;

use App\Models\Aluno;
use App\Models\Encarregado;
use App\Models\WhatsappMensagem;
use App\Services\OCR\OCRServiceInterface;
use App\Services\Portal\AlunoPortalAcessoService;
use App\Services\Portal\AlunoPortalNotasService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bot inbound: interpreta mensagens de texto e imagens (OCR).
 */
class WhatsAppBotService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly AlunoPortalNotasService $notasService,
        private readonly AlunoPortalAcessoService $acessoNotas,
        private readonly OCRServiceInterface $ocr,
    ) {}

    public function handleInbound(string $telefone, string $texto, ?string $mediaPath = null): void
    {
        if (! config('whatsapp.bot_enabled', true)) {
            return;
        }

        $phone = $this->whatsapp->normalizePhone($telefone);
        WhatsappMensagem::query()->create([
            'direction' => 'in',
            'telefone' => $phone,
            'tipo' => $mediaPath ? 'image' : 'text',
            'corpo' => $texto,
            'status' => 'received',
            'payload' => $mediaPath ? ['media' => $mediaPath] : null,
        ]);

        $encarregado = Encarregado::query()
            ->where('activo', true)
            ->where('whatsapp_opt_in', true)
            ->where('telefone_normalizado', $phone)
            ->first();

        // Fallback para registos ainda sem telefone_normalizado
        if (! $encarregado) {
            $encarregado = Encarregado::query()
                ->where('activo', true)
                ->where('whatsapp_opt_in', true)
                ->whereNotNull('telefone')
                ->whereNull('telefone_normalizado')
                ->get()
                ->first(fn (Encarregado $e) => $this->whatsapp->normalizePhone((string) $e->telefone) === $phone);

            if ($encarregado) {
                $encarregado->forceFill(['telefone_normalizado' => $phone])->saveQuietly();
            }
        }

        if (! $encarregado) {
            $this->whatsapp->sendText($phone, 'Facilita: número não associado a um encarregado. Contacte a secretaria da escola.');

            return;
        }

        WhatsappMensagem::query()
            ->where('telefone', $phone)
            ->where('direction', 'in')
            ->latest('id')
            ->first()
            ?->update(['encarregado_id' => $encarregado->id]);

        if ($mediaPath) {
            $this->handleImage($encarregado, $phone, $mediaPath);

            return;
        }

        $cmd = Str::lower(trim($texto));
        $alunos = $encarregado->alunos()
            ->with('turma')
            ->where('alunos.activo', true)
            ->get();

        if ($alunos->isEmpty()) {
            $this->whatsapp->sendText($phone, 'Facilita: não há educandos ligados a esta conta.', $encarregado);

            return;
        }

        if (str_contains($cmd, 'propina') || str_contains($cmd, 'pagamento') || str_contains($cmd, 'mensalidade')) {
            $this->responderPropina($encarregado, $phone, $alunos);

            return;
        }

        if (str_contains($cmd, 'nota') || str_contains($cmd, 'boletim')) {
            $comNotas = $alunos->filter(fn (Aluno $a) => (bool) ($a->pivot->pode_ver_notas ?? false));
            if ($comNotas->isEmpty()) {
                $this->whatsapp->sendText(
                    $phone,
                    'Facilita: não há educandos com permissão de consulta de notas nesta conta.',
                    $encarregado
                );

                return;
            }

            $linhas = [];
            foreach ($comNotas as $aluno) {
                $linhas[] = "{$aluno->nome}: ".$this->resumoNotas($aluno);
            }
            $this->whatsapp->sendText($phone, "Facilita — Notas\n".implode("\n", $linhas), $encarregado);

            return;
        }

        if (str_contains($cmd, 'horário') || str_contains($cmd, 'horario')) {
            $linhas = $alunos->map(
                fn (Aluno $a) => "{$a->nome}: turma ".($a->turma?->nome ?? $a->turma?->codigo ?? '—')
            )->all();
            $this->whatsapp->sendText(
                $phone,
                "Facilita — Horário\n".implode("\n", $linhas)."\nConsulte o detalhe no portal.",
                $encarregado
            );

            return;
        }

        if (str_contains($cmd, 'ajuda') || $cmd === 'menu' || $cmd === 'oi' || $cmd === 'olá') {
            $this->whatsapp->sendText(
                $phone,
                "Facilita Bot\nComandos:\n• Notas\n• Horário\n• Propina\n• Envie foto de prova (OCR)\nPortal: ".url('/login'),
                $encarregado
            );

            return;
        }

        $this->whatsapp->sendText(
            $phone,
            'Facilita: não entendi. Envie "ajuda", "notas", "horário" ou "propina".',
            $encarregado
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function responderPropina(Encarregado $encarregado, string $phone, $alunos): void
    {
        $linhas = [];

        foreach ($alunos as $aluno) {
            if ($this->acessoNotas->podeConsultarNotas($aluno)) {
                $linhas[] = "{$aluno->nome}: acesso ao boletim/notas regularizado.";
            } else {
                $motivo = $this->acessoNotas->mensagemBloqueio($aluno);
                $linhas[] = "{$aluno->nome}: pendente — ".Str::limit($motivo, 140);
            }
        }

        $this->whatsapp->sendText(
            $phone,
            "Facilita — Propina / pagamento\n"
            .implode("\n", $linhas)
            ."\nPara propinas escolares e regularização, contacte a secretaria. Portal: ".url('/login'),
            $encarregado
        );
    }

    private function handleImage(Encarregado $encarregado, string $phone, string $mediaPath): void
    {
        try {
            $absolute = Storage::disk('local')->path($mediaPath);
            $result = $this->ocr->extractText($absolute);
            $texto = is_object($result) ? (string) ($result->texto ?? '') : (string) $result;
            $preview = Str::limit(trim($texto) ?: '(sem texto reconhecido)', 800);
            $this->whatsapp->sendText(
                $phone,
                "Facilita OCR — texto extraído:\n{$preview}\n\n(A correção completa fica no módulo pedagógico da escola.)",
                $encarregado
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp OCR falhou', ['error' => $e->getMessage()]);
            $this->whatsapp->sendText($phone, 'Facilita: não foi possível ler a imagem. Tente outra foto nítida.', $encarregado);
        }
    }

    private function resumoNotas(Aluno $aluno): string
    {
        try {
            return $this->notasService->resumoRapido($aluno);
        } catch (\Throwable) {
            return 'consulte o portal para o detalhe';
        }
    }
}
