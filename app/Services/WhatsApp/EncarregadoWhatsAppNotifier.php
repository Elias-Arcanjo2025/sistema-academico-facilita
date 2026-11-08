<?php

namespace App\Services\WhatsApp;

use App\Models\Aluno;
use App\Models\Ocorrencia;
use App\Models\PresencaAula;
use Illuminate\Support\Facades\Log;

/**
 * Dispara avisos WhatsApp a encarregados com opt-in.
 */
class EncarregadoWhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function faltaRegistada(Aluno $aluno, PresencaAula $aula): void
    {
        $disciplina = $aula->disciplina?->nome ?? 'aula';
        $data = $aula->data
            ? \Carbon\Carbon::parse($aula->data)->format('d/m/Y')
            : now()->format('d/m/Y');

        $this->notificarEncarregados($aluno, 'falta_registada', [
            'aluno' => $aluno->nome,
            'disciplina' => $disciplina,
            'data' => $data,
        ]);
    }

    public function ocorrencia(Ocorrencia $ocorrencia): void
    {
        $ocorrencia->loadMissing(['aluno', 'turma']);
        $aluno = $ocorrencia->aluno;
        if (! $aluno) {
            return;
        }

        $tipo = Ocorrencia::tipos()[$ocorrencia->tipo] ?? $ocorrencia->tipo;
        $severidade = Ocorrencia::severidades()[$ocorrencia->severidade] ?? $ocorrencia->severidade;

        $this->notificarEncarregados($aluno, 'ocorrencia_escolar', [
            'aluno' => $aluno->nome,
            'tipo' => $tipo,
            'severidade' => $severidade,
            'resumo' => \Illuminate\Support\Str::limit((string) $ocorrencia->descricao, 120),
        ]);
    }

    public function boletimDisponivel(Aluno $aluno, ?string $periodo = null): void
    {
        $this->notificarEncarregados($aluno, 'boletim_disponivel', [
            'aluno' => $aluno->nome,
            'periodo' => $periodo ?: 'trimestre actual',
        ]);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function notificarEncarregados(Aluno $aluno, string $templateKey, array $params): void
    {
        if (! $this->whatsapp->enabled()) {
            return;
        }

        $aluno->loadMissing('encarregados');

        foreach ($aluno->encarregados as $encarregado) {
            if (! $encarregado->whatsapp_opt_in || ! $encarregado->telefone) {
                continue;
            }

            try {
                $this->whatsapp->notifyEncarregado($encarregado, $templateKey, $params);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp encarregado falhou', [
                    'template' => $templateKey,
                    'aluno_id' => $aluno->id,
                    'encarregado_id' => $encarregado->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
