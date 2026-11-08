<?php

namespace App\Http\Controllers\Assiduidade;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Ocorrencia;
use App\Models\PresencaAula;
use App\Models\PresencaRegisto;
use App\Services\PresencaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InspetorPainelController extends Controller
{
    public function __construct(
        private PresencaService $presencas,
    ) {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user?->temVisaoAssiduidade() && ! $user?->hasRole('inspetor')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $anoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $hoje = now()->toDateString();

        $aulasHoje = PresencaAula::query()
            ->with(['turma', 'disciplina'])
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->whereDate('data', $hoje)
            ->count();

        $faltasHoje = PresencaRegisto::query()
            ->whereIn('estado', [
                PresencaRegisto::FALTA_INJUSTIFICADA,
                PresencaRegisto::FALTA_JUSTIFICADA,
            ])
            ->whereHas('aula', function ($q) use ($hoje, $anoId) {
                $q->whereDate('data', $hoje);
                if ($anoId) {
                    $q->where('ano_lectivo_id', $anoId);
                }
            })
            ->count();

        $ocorrenciasAbertas = Ocorrencia::query()
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->where('estado', Ocorrencia::ESTADO_ABERTA)
            ->count();

        $ocorrenciasGraves = Ocorrencia::query()
            ->with(['aluno', 'turma'])
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->where('estado', Ocorrencia::ESTADO_ABERTA)
            ->where('severidade', Ocorrencia::SEV_ALTA)
            ->latest('data')
            ->limit(10)
            ->get();

        $slotsHoje = $this->presencas->aulasDoDia($request->user(), $hoje, $anoId);

        return view('assiduidade.inspetor.painel', [
            'aulasHoje' => $aulasHoje,
            'faltasHoje' => $faltasHoje,
            'ocorrenciasAbertas' => $ocorrenciasAbertas,
            'ocorrenciasGraves' => $ocorrenciasGraves,
            'slotsHoje' => $slotsHoje,
            'hoje' => $hoje,
            'anoLectivoId' => $anoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
        ]);
    }
}
