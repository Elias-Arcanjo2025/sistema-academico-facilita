<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\MatriculaHistorico;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:secretaria.ver');
    }

    public function __invoke(): View
    {
        return view('secretaria.dashboard', [
            'totalAlunos' => Aluno::activo()->count(),
            'matriculasRecentes' => MatriculaHistorico::with(['aluno', 'turma'])
                ->where('tipo', MatriculaHistorico::TIPO_MATRICULA)
                ->latest()
                ->limit(8)
                ->get(),
            'transferenciasRecentes' => MatriculaHistorico::with(['aluno', 'deTurma', 'paraTurma'])
                ->whereIn('tipo', [MatriculaHistorico::TIPO_TRANSFERENCIA, MatriculaHistorico::TIPO_MUDANCA_CURSO])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
