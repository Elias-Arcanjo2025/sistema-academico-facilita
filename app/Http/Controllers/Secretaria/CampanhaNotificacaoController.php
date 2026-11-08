<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Configuracao;
use App\Models\NotificacaoCampanha;
use App\Models\Turma;
use App\Services\CampanhaNotificacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CampanhaNotificacaoController extends Controller
{
    public function __construct(
        private CampanhaNotificacaoService $campanhas,
    ) {
        $this->middleware('can:secretaria.campanhas.enviar');
    }

    public function index(): View
    {
        $itens = NotificacaoCampanha::query()
            ->with(['autor:id,name', 'turma:id,nome'])
            ->latest()
            ->paginate(20);

        return view('secretaria.campanhas.index', [
            'itens' => $itens,
            'publicos' => NotificacaoCampanha::publicos(),
        ]);
    }

    public function create(): View
    {
        $anoId = Configuracao::getAnoLectivoActualId();

        return view('secretaria.campanhas.create', [
            'publicos' => NotificacaoCampanha::publicos(),
            'turmas' => Turma::activo()
                ->where('ano_lectivo_id', $anoId)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'mensagem' => ['required', 'string', 'max:2000'],
            'publico' => ['required', 'in:'.implode(',', array_keys(NotificacaoCampanha::publicos()))],
            'turma_id' => ['nullable', 'exists:turmas,id', 'required_if:publico,turma'],
        ]);

        try {
            $resultado = $this->campanhas->enviar(
                $request->user(),
                $data['titulo'],
                $data['mensagem'],
                $data['publico'],
                ! empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('secretaria.campanhas.index')
            ->with('success', "Campanha enviada a {$resultado['enviados']} destinatário(s).");
    }
}
