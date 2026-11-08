<?php

namespace App\Http\Controllers;

use App\Http\Requests\FechoAnoConfigurarRequest;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Services\AlunoTransicaoService;
use App\Services\AnoLectivoTransferService;
use App\Services\FechoAnoValidationService;
use App\Services\FechoAnoWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FechoAnoController extends Controller
{
    public function __construct(
        protected FechoAnoWizardService $wizardService,
        protected AnoLectivoTransferService $transferService,
        protected AlunoTransicaoService $transicaoService,
        protected FechoAnoValidationService $validationService,
    ) {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->hasRole('admin_full')) {
                abort(403, 'Apenas administradores podem executar o fecho de ano lectivo.');
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $anoOrigem = $request->session()->get('fecho_ano_origem', Configuracao::getAnoLectivoActual());
        $anoDestino = $request->session()->get('fecho_ano_destino');

        $anosNovos = AnoLectivo::novos()->orderBy('nome', 'desc')->pluck('nome');
        $anosDisponiveis = AnoLectivo::orderBy('nome', 'desc')->pluck('nome');

        $estado = $this->wizardService->obterEstado($anoOrigem, $anoDestino);

        return view('fecho-ano.index', compact(
            'anoOrigem',
            'anoDestino',
            'anosNovos',
            'anosDisponiveis',
            'estado'
        ));
    }

    public function configurar(FechoAnoConfigurarRequest $request): RedirectResponse
    {
        $errors = $this->validationService->validateConfigurar(
            $request->input('ano_origem'),
            $request->input('ano_destino')
        );

        if (! empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        $request->session()->put('fecho_ano_origem', $request->input('ano_origem'));
        $request->session()->put('fecho_ano_destino', $request->input('ano_destino'));

        return redirect()->route('fecho-ano.index')
            ->with('success', 'Anos lectivos configurados para o fecho.');
    }

    public function transferir(Request $request): RedirectResponse
    {
        $anos = $this->anosDaSessao($request);
        if (! $anos) {
            return redirect()->route('fecho-ano.index')->with('error', 'Configure primeiro os anos de origem e destino.');
        }
        [$anoOrigem, $anoDestino] = $anos;

        $errors = $this->validationService->validateTransferir($anoOrigem, $anoDestino);
        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        $result = $this->transferService->executeTransfer(
            $anoOrigem,
            $anoDestino,
            $request->boolean('transferir_associacoes', true),
            $request->boolean('transferir_directores', true),
            $request->boolean('transferir_coordenadores', true),
            $request->boolean('transferir_horarios', false),
            $request->boolean('transferir_calendario', false)
        );

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        $this->validationService->marcarTransferenciaExecutada($anoOrigem, $anoDestino);

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['ano_origem' => $anoOrigem, 'ano_destino' => $anoDestino, 'via' => 'fecho_ano', 'stats' => $result['stats']])
            ->log('Transferência de estrutura via wizard de fecho');

        return back()->with('success', $result['message']);
    }

    public function transitar(Request $request): RedirectResponse
    {
        $anos = $this->anosDaSessao($request);
        if (! $anos) {
            return redirect()->route('fecho-ano.index')->with('error', 'Configure primeiro os anos de origem e destino.');
        }
        [$anoOrigem, $anoDestino] = $anos;

        $errors = $this->validationService->validateTransitar($anoOrigem, $anoDestino);
        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        $anoOrigemId = AnoLectivo::getIdByNome($anoOrigem);
        $anoDestinoId = AnoLectivo::getIdByNome($anoDestino);

        $turmasOrigem = \App\Models\Turma::with(['curso', 'classe', 'turno'])
            ->where('ano_lectivo_id', $anoOrigemId)->get();
        $turmasDestino = \App\Models\Turma::with(['curso', 'classe', 'turno'])
            ->where('ano_lectivo_id', $anoDestinoId)->get();

        $mapeamentoPromocao = $this->transicaoService->criarMapeamentoPromocao($turmasOrigem, $turmasDestino);
        $mapeamentoRepetencia = $this->transicaoService->criarMapeamentoRepetencia($turmasOrigem, $turmasDestino);

        $result = $this->transicaoService->executeTransicao(
            $anoOrigem,
            $anoDestino,
            $mapeamentoPromocao,
            $mapeamentoRepetencia,
            $request->boolean('transitar_reprovados', false),
            null
        );

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? $result['error'] ?? 'Erro na transição.');
        }

        $this->validationService->marcarTransicaoExecutada($anoOrigem, $anoDestino);

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['ano_origem' => $anoOrigem, 'ano_destino' => $anoDestino, 'stats' => $result['stats'], 'via' => 'fecho_ano'])
            ->log('Transição de alunos via wizard de fecho');

        $flash = ($result['partial'] ?? false) ? 'warning' : 'success';

        return back()
            ->with($flash, $result['message'])
            ->with('transicao_stats', $result['stats']);
    }

    public function activar(Request $request): RedirectResponse
    {
        $anos = $this->anosDaSessao($request);
        if (! $anos) {
            return redirect()->route('fecho-ano.index')->with('error', 'Configure primeiro os anos de origem e destino.');
        }
        [$anoOrigem, $anoDestino] = $anos;

        $checklist = $this->validationService->validateActivar($anoOrigem, $anoDestino);
        if (! $checklist['ok']) {
            return back()->withErrors($checklist['erros']);
        }

        $anoDestinoModel = AnoLectivo::where('nome', $anoDestino)->first();
        if (! $anoDestinoModel) {
            return back()->with('error', 'Ano de destino não encontrado.');
        }

        if ($anoDestinoModel->isActual()) {
            return back()->with('info', 'Este ano lectivo já está activo.');
        }

        try {
            $notasArquivadas = $this->transicaoService->arquivarNotasAnoLectivo($anoOrigem);
            Configuracao::fecharTrimestre(3);

            AnoLectivo::setActual($anoDestino);

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'ano_origem' => $anoOrigem,
                    'ano_destino' => $anoDestino,
                    'notas_arquivadas' => $notasArquivadas,
                    'via' => 'fecho_ano',
                ])
                ->log('Ano lectivo activado via wizard de fecho');

            $msg = "Ano lectivo {$anoDestino} activado com sucesso.";
            if ($notasArquivadas > 0) {
                $msg .= " {$notasArquivadas} nota(s) do ano anterior arquivadas.";
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao activar ano: '.$e->getMessage());
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function anosDaSessao(Request $request): ?array
    {
        $anoOrigem = $request->session()->get('fecho_ano_origem');
        $anoDestino = $request->session()->get('fecho_ano_destino');

        if (! $anoOrigem || ! $anoDestino) {
            return null;
        }

        return [$anoOrigem, $anoDestino];
    }
}
