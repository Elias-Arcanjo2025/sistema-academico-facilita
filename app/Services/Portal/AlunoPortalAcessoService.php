<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Services\ConfiguracaoService;
use Illuminate\Http\Exceptions\HttpResponseException;

class AlunoPortalAcessoService
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    public function portalNotasGlobalmenteActivo(): bool
    {
        return filter_var(
            $this->configuracaoService->get('portal_aluno_notas_activo', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function podeConsultarNotas(Aluno $aluno): bool
    {
        if (! $this->portalNotasGlobalmenteActivo()) {
            return false;
        }

        return (bool) $aluno->consulta_notas_portal;
    }

    public function mensagemBloqueio(Aluno $aluno): string
    {
        if (! $this->portalNotasGlobalmenteActivo()) {
            return 'A consulta de notas no portal está temporariamente desactivada pela administração da escola.';
        }

        if ($aluno->motivo_bloqueio_portal) {
            return $aluno->motivo_bloqueio_portal;
        }

        return (string) $this->configuracaoService->get(
            'portal_aluno_mensagem_bloqueio',
            'A consulta de notas e o download do boletim estão indisponíveis. Regularize o pagamento do boletim na secretaria da escola.'
        );
    }

    /**
     * Bloqueia com 403 e view amigável se o aluno não pode consultar notas.
     */
    public function assertPodeConsultarNotas(Aluno $aluno): void
    {
        if ($this->podeConsultarNotas($aluno)) {
            return;
        }

        throw new HttpResponseException(
            response()->view('portal.aluno.bloqueado', [
                'aluno' => $aluno,
                'mensagem' => $this->mensagemBloqueio($aluno),
            ], 403)
        );
    }
}
