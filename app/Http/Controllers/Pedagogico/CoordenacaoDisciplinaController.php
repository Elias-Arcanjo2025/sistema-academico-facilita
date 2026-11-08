<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Services\Pedagogico\AvisoDisciplinaService;
use App\Services\Pedagogico\RelatorioDisciplinaService;
use App\Services\Pedagogico\SupervisaoLancamentosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordenacaoDisciplinaController extends Controller
{
    public function __construct(
        private SupervisaoLancamentosService $supervisao,
        private RelatorioDisciplinaService $relatorio,
        private AvisoDisciplinaService $avisos,
    ) {
        $this->middleware('can:notas.supervisionar')->only(['lancamentos']);
        $this->middleware('can:relatorios.disciplina')->only(['relatorio']);
        $this->middleware('can:disciplina.avisar')->only(['avisosForm', 'avisosEnviar']);
    }

    public function lancamentos(Request $request): View
    {
        $trimestre = $request->integer('trimestre') ?: null;
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $dados = $this->supervisao->checklist($request->user(), $trimestre, $anoLectivoId);

        return view('pedagogico.coordenacao.lancamentos', [
            'dados' => $dados,
            'trimestre' => $dados['trimestre'],
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
        ]);
    }

    public function relatorio(Request $request): View
    {
        $trimestre = $request->integer('trimestre') ?: null;
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $dados = $this->relatorio->rendimento($request->user(), $trimestre, $anoLectivoId);

        return view('pedagogico.coordenacao.relatorio', [
            'dados' => $dados,
            'trimestre' => $dados['trimestre'],
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
        ]);
    }

    public function avisosForm(Request $request): View
    {
        abort_unless($this->avisos->podeAvisar($request->user()), 403);

        return view('pedagogico.coordenacao.avisos', [
            'opcoes' => $this->avisos->opcoesAmbito($request->user()),
        ]);
    }

    public function avisosEnviar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'classe_id' => ['nullable', 'exists:classes,id'],
            'titulo' => ['required', 'string', 'max:160'],
            'mensagem' => ['required', 'string', 'max:2000'],
        ]);

        $resultado = $this->avisos->enviar(
            $request->user(),
            (int) $data['disciplina_id'],
            $data['titulo'],
            $data['mensagem'],
            ! empty($data['classe_id']) ? (int) $data['classe_id'] : null,
        );

        return redirect()
            ->route('pedagogico.coordenacao.avisos')
            ->with('success', "Aviso enviado a {$resultado['enviados']} professor(es).");
    }
}
