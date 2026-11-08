<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function escolaNova(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('configuracoes.ver') || $user->hasRole('admin_full')),
            403
        );

        if (AnoLectivo::exists() && Configuracao::getAnoLectivoActualId()) {
            return redirect()
                ->route('dashboard')
                ->with('info', 'A escola já tem ano lectivo configurado.');
        }

        $passos = [
            [
                'titulo' => 'Criar e activar o ano lectivo',
                'descricao' => 'Ex.: 2026-2027. Sem isto, turmas, alunos e notas ficam bloqueados.',
                'feito' => AnoLectivo::exists(),
                'url' => route('configuracoes.index').'#anos-lectivos-section',
                'cta' => 'Ir a Anos Lectivos',
            ],
            [
                'titulo' => 'Dados da instituição',
                'descricao' => 'Nome, logo e contactos (aparecem na página pública e nos documentos).',
                'feito' => filled(Configuracao::get('instituicao_nome')),
                'url' => route('configuracoes.index').'#instituicao',
                'cta' => 'Configurar instituição',
            ],
            [
                'titulo' => 'Cursos e turmas',
                'descricao' => 'Crie pelo menos um curso e as turmas do ano actual.',
                'feito' => Curso::query()->where('activo', true)->exists() && Turma::query()->where('activo', true)->exists(),
                'url' => route('cursos.index'),
                'cta' => 'Gerir cursos',
            ],
            [
                'titulo' => 'Utilizadores e cargos',
                'descricao' => 'Professores, secretaria e direcção — com passwords alteradas no 1.º login.',
                'feito' => User::role('professor')->exists(),
                'url' => route('users.index'),
                'cta' => 'Gerir utilizadores',
            ],
        ];

        return view('onboarding.escola-nova', [
            'passos' => $passos,
            'concluidos' => collect($passos)->where('feito', true)->count(),
            'total' => count($passos),
        ]);
    }
}
