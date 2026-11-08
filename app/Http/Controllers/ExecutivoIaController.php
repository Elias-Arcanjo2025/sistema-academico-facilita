<?php

namespace App\Http\Controllers;

use App\Services\ExecutivoIaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutivoIaController extends Controller
{
    public function __construct(private ExecutivoIaService $executivo)
    {
        $this->middleware('auth');
        $this->middleware('can:assistente.usar');
        $this->middleware('throttle:ai-generation')->only(['actualizar']);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $dados = $this->executivo->gerarResumo($user);

        if ($user->hasRole('aluno')) {
            return view('portal.aluno.executivo-ia', compact('dados'));
        }

        return view('executivo-ia.index', compact('dados'));
    }

    public function actualizar(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->executivo->invalidarCache($user);
        $dados = $this->executivo->gerarResumo($user);

        return response()->json([
            'success' => true,
            'resumo' => $dados['resumo'],
            'resumo_html' => \Illuminate\Support\Str::markdown($dados['resumo']),
            'fonte' => $dados['fonte'],
            'gerado_em' => $dados['gerado_em'],
            'sem_ano_lectivo' => ! empty($dados['sem_ano_lectivo']),
        ]);
    }
}
