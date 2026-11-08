<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesquisaGlobalController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $user = $request->user();

        // Pesquisa vazia: acções rápidas com atalhos (ajuda contextual).
        if ($q === '') {
            return response()->json(['items' => $this->accoesRapidas($user)]);
        }

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $items = collect($this->rotasEstaticas($user))
            ->filter(fn ($r) => str_contains(mb_strtolower($r['label']), mb_strtolower($q))
                || str_contains(mb_strtolower($r['group']), mb_strtolower($q)))
            ->take(8);

        if ($user->can('alunos.ver') || $user->can('notas.lancar')) {
            $alunos = Aluno::query()
                ->where('activo', true)
                ->where('nome', 'like', "%{$q}%")
                ->limit(5)
                ->get()
                ->map(fn (Aluno $a) => [
                    'label' => $a->nome,
                    'group' => 'Alunos',
                    'url' => route('alunos.show', $a),
                    'icon' => 'bi-person-badge',
                ]);
            $items = $items->merge($alunos);
        }

        if ($user->can('turmas.ver') || $user->can('notas.lancar')) {
            $turmas = Turma::query()
                ->where('activo', true)
                ->where('nome', 'like', "%{$q}%")
                ->limit(5)
                ->get()
                ->map(fn (Turma $t) => [
                    'label' => $t->nome,
                    'group' => 'Turmas',
                    'url' => route('turmas.show', $t),
                    'icon' => 'bi-people',
                ]);
            $items = $items->merge($turmas);
        }

        return response()->json(['items' => $items->take(12)->values()]);
    }

    /**
     * @return array<int, array{label:string, group:string, url:string, icon:string}>
     */
    private function rotasEstaticas($user): array
    {
        $rotas = [];

        if ($user->can('notas.lancar')) {
            $rotas[] = [
                'label' => 'Lançar notas',
                'group' => 'Acções',
                'url' => route('notas.lancar'),
                'icon' => 'bi-pencil-square',
                'shortcut' => 'Ctrl+Shift+N',
            ];
        }
        if ($user->can('pautas.ver')) {
            $rotas[] = [
                'label' => 'Pautas',
                'group' => 'Acções',
                'url' => route('pautas.index'),
                'icon' => 'bi-file-earmark-text',
                'shortcut' => 'Ctrl+Shift+P',
            ];
        }
        if (\App\Support\NotaLancamentoRules::podeAcederModuloExames($user)) {
            $rotas[] = ['label' => 'Recuperação', 'group' => 'Exames', 'url' => route('recuperacoes.index'), 'icon' => 'bi-bandaid'];
            $rotas[] = ['label' => 'Recurso', 'group' => 'Exames', 'url' => route('recursos.index'), 'icon' => 'bi-arrow-repeat'];
        }
        if ($user->can('configuracoes.ver')) {
            $rotas[] = ['label' => 'Configurações', 'group' => 'Admin', 'url' => route('configuracoes.index'), 'icon' => 'bi-gear'];
        }
        if ($user->can('auditoria.ver')) {
            $rotas[] = ['label' => 'Auditoria', 'group' => 'Admin', 'url' => route('auditoria.index'), 'icon' => 'bi-shield-check'];
        }

        $rotas[] = ['label' => 'Regras de avaliação', 'group' => 'Ajuda', 'url' => route('regras-avaliacao'), 'icon' => 'bi-journal-text', 'shortcut' => 'Ctrl+Shift+R'];

        return $rotas;
    }

    /**
     * Acções rápidas mostradas ao abrir Ctrl+K (sem termo de pesquisa).
     *
     * @return array<int, array{label:string, group:string, url:string, icon:string, shortcut?:string}>
     */
    private function accoesRapidas($user): array
    {
        $items = [
            ['label' => 'Dashboard', 'group' => 'Atalhos', 'url' => route('dashboard'), 'icon' => 'bi-speedometer2', 'shortcut' => 'Ctrl+Shift+D'],
        ];

        if ($user->can('notas.lancar')) {
            $items[] = ['label' => 'Lançar notas', 'group' => 'Atalhos', 'url' => route('notas.lancar'), 'icon' => 'bi-pencil-square', 'shortcut' => 'Ctrl+Shift+N'];
        }
        if ($user->can('pautas.ver')) {
            $items[] = ['label' => 'Pautas', 'group' => 'Atalhos', 'url' => route('pautas.index'), 'icon' => 'bi-file-earmark-text', 'shortcut' => 'Ctrl+Shift+P'];
        }

        $items[] = ['label' => 'Regras de avaliação', 'group' => 'Atalhos', 'url' => route('regras-avaliacao'), 'icon' => 'bi-journal-text', 'shortcut' => 'Ctrl+Shift+R'];

        if ($user->can('assistente.usar')) {
            $items[] = [
                'label' => 'Assistente IA',
                'group' => 'Atalhos',
                'url' => '#assistente',
                'icon' => 'bi-stars',
                'shortcut' => 'Ctrl+Shift+A',
            ];
        }

        return $items;
    }
}
