<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnoLectivoTransferRequest;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Services\AnoLectivoTransferService;
use App\Services\AlunoTransicaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnoLectivoTransferController extends Controller
{
    private AnoLectivoTransferService $service;

    public function __construct(AnoLectivoTransferService $service)
    {
        $this->service = $service;

        // Apenas admin_full pode acessar
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('admin_full')) {
                abort(403, 'Acesso negado. Apenas administradores podem transferir dados entre anos lectivos.');
            }
            return $next($request);
        });
    }

    /**
     * Step 1: Formulário de configuração
     */
    public function index()
    {
        $anosDisponiveis = \App\Models\AnoLectivo::where('estado', '!=', \App\Models\AnoLectivo::ESTADO_NOVO)
            ->orderBy('nome', 'desc')
            ->pluck('nome');

        $anosNovos = \App\Models\AnoLectivo::novos()
            ->pluck('nome');

        $anoActual = Configuracao::getAnoLectivoActual();

        return view('ano-lectivo-transfer.index', compact(
            'anosDisponiveis',
            'anosNovos',
            'anoActual'
        ));
    }

    /**
     * Step 2: Preview da transferência
     */
    public function preview(Request $request)
    {
        $anoOrigem = $request->input('ano_origem');
        $anoDestino = $request->input('ano_destino');

        // Validar
        $errors = $this->service->validateTransfer($anoOrigem, $anoDestino);

        if (!empty($errors)) {
            return redirect()->route('ano-lectivo-transfer.index')
                ->withErrors($errors)
                ->withInput();
        }

        // Gerar preview
        $preview = $this->service->previewTransfer($anoOrigem, $anoDestino);

        $opcoes = [
            'transferir_associacoes' => $request->boolean('transferir_associacoes', true),
            'transferir_directores' => $request->boolean('transferir_directores', true),
            'transferir_coordenadores' => $request->boolean('transferir_coordenadores', true),
            'transitar_alunos' => $request->boolean('transitar_alunos', false),
            'transitar_reprovados' => $request->boolean('transitar_reprovados', false),
        ];

        return view('ano-lectivo-transfer.preview', compact('preview', 'opcoes', 'anoOrigem', 'anoDestino'));
    }

    /**
     * Step 3: Executar transferência
     */
    public function execute(AnoLectivoTransferRequest $request)
    {
        $anoOrigem = $request->input('ano_origem');
        $anoDestino = $request->input('ano_destino');

        $transferirAssociacoes = $request->boolean('transferir_associacoes', true);
        $transferirDirectores = $request->boolean('transferir_directores', true);
        $transferirCoordenadores = $request->boolean('transferir_coordenadores', true);
        $transitarAlunos = $request->boolean('transitar_alunos', false);

        // Executar transferência
        $result = $this->service->executeTransfer(
            $anoOrigem,
            $anoDestino,
            $transferirAssociacoes,
            $transferirDirectores,
            $transferirCoordenadores
        );

        if (!$result['success']) {
            return redirect()->route('ano-lectivo-transfer.index')
                ->with('error', $result['message'])
                ->withInput();
        }

        // Transitar alunos (se solicitado)
        if ($transitarAlunos && !empty($result['turma_mapping'])) {
            $alunoService = app(AlunoTransicaoService::class);
            $transitarReprovados = $request->boolean('transitar_reprovados', false);

            $resultadoTransicao = $alunoService->transitarAlunosAposTransferenciaEstrutura(
                $anoOrigem,
                $anoDestino,
                $result['turma_mapping'],
                $transitarReprovados
            );

            $result['stats']['alunos_transitados'] = $resultadoTransicao['stats']['alunos_transitados'] ?? 0;
            $result['stats']['alunos_reprovados_transitados'] = $resultadoTransicao['stats']['alunos_reprovados'] ?? 0;
            $result['stats']['transicao_erros'] = $resultadoTransicao['stats']['erros'] ?? [];
        }

        // Registrar auditoria
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'ano_origem' => $anoOrigem,
                'ano_destino' => $anoDestino,
                'stats' => $result['stats'],
                'opcoes' => [
                    'associacoes' => $transferirAssociacoes,
                    'directores' => $transferirDirectores,
                    'coordenadores' => $transferirCoordenadores,
                    'transitar_alunos' => $transitarAlunos,
                ],
            ])
            ->log('Transferência de ano lectivo executada');

        return view('ano-lectivo-transfer.result', [
            'success' => true,
            'stats' => $result['stats'],
            'message' => $result['message'],
            'anoOrigem' => $anoOrigem,
            'anoDestino' => $anoDestino,
        ]);
    }
}
