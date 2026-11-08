<?php

namespace App\Services\Assistente;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Atalhos seguros (rotas reais) para o chat — nunca URLs inventadas pela IA.
 */
class AssistantAcoesService
{
    /**
     * @return array<int, array{label:string, url:string, icon?:string}>
     */
    public function paraUtilizador(User $user, ?string $routeName = null): array
    {
        $acoes = [];

        if ($user->hasRole('aluno')) {
            $acoes = array_merge($acoes, $this->aluno($routeName));
        } elseif ($user->hasRole('encarregado')) {
            $acoes = array_merge($acoes, $this->encarregado($routeName));
        } else {
            $acoes = array_merge($acoes, $this->staff($user, $routeName));
        }

        return collect($acoes)
            ->filter(fn (array $a) => ! empty($a['url']))
            ->unique('url')
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * @return array<int, array{label:string, url:string, icon?:string}>
     */
    protected function aluno(?string $routeName): array
    {
        $base = [
            ['label' => 'As minhas notas', 'url' => $this->route('portal.aluno.notas'), 'icon' => 'bi-journal-text'],
            ['label' => 'Horário', 'url' => $this->route('portal.aluno.horario'), 'icon' => 'bi-calendar-week'],
            ['label' => 'Boletim PDF', 'url' => $this->route('portal.aluno.boletim.pdf'), 'icon' => 'bi-file-earmark-pdf'],
            ['label' => 'Início do portal', 'url' => $this->route('portal.aluno.dashboard'), 'icon' => 'bi-house'],
        ];

        return match (true) {
            str_starts_with((string) $routeName, 'portal.aluno.notas') => [
                ['label' => 'Ver boletim PDF', 'url' => $this->route('portal.aluno.boletim.pdf'), 'icon' => 'bi-file-earmark-pdf'],
                ['label' => 'Voltar ao início', 'url' => $this->route('portal.aluno.dashboard'), 'icon' => 'bi-house'],
            ],
            str_starts_with((string) $routeName, 'portal.aluno.horario') => [
                ['label' => 'Calendário de provas', 'url' => $this->route('portal.aluno.provas'), 'icon' => 'bi-calendar-event'],
                ['label' => 'As minhas notas', 'url' => $this->route('portal.aluno.notas'), 'icon' => 'bi-journal-text'],
            ],
            default => $base,
        };
    }

    /**
     * @return array<int, array{label:string, url:string, icon?:string}>
     */
    protected function encarregado(?string $routeName): array
    {
        return [
            ['label' => 'Meus educandos', 'url' => $this->route('portal.encarregado.dashboard'), 'icon' => 'bi-people'],
            ['label' => 'Esta semana', 'url' => $this->route('portal.encarregado.resumo-semanal'), 'icon' => 'bi-calendar-week'],
            ['label' => 'Documentos', 'url' => $this->route('portal.encarregado.documentos.index'), 'icon' => 'bi-file-earmark-text'],
            ['label' => 'Alertas', 'url' => $this->route('portal.encarregado.notificacoes'), 'icon' => 'bi-bell'],
        ];
    }

    /**
     * @return array<int, array{label:string, url:string, icon?:string}>
     */
    protected function staff(User $user, ?string $routeName): array
    {
        $acoes = [];

        if ($user->can('notas.lancar') && Route::has('notas.lancar')) {
            $acoes[] = ['label' => 'Lançar notas', 'url' => $this->route('notas.lancar'), 'icon' => 'bi-pencil-square'];
        }
        if ($user->can('pautas.ver') && Route::has('pautas.index')) {
            $acoes[] = ['label' => 'Ver pautas', 'url' => $this->route('pautas.index'), 'icon' => 'bi-table'];
        }
        if ($user->can('secretaria.ver')) {
            $acoes[] = ['label' => 'Secretaria', 'url' => $this->route('secretaria.dashboard'), 'icon' => 'bi-inbox'];
            $acoes[] = ['label' => 'Pedidos do portal', 'url' => $this->route('secretaria.documentos.pedidos'), 'icon' => 'bi-file-earmark-text'];
        }
        if (Route::has('dashboard')) {
            $acoes[] = ['label' => 'Dashboard', 'url' => $this->route('dashboard'), 'icon' => 'bi-speedometer2'];
        }

        if (str_starts_with((string) $routeName, 'notas.')) {
            array_unshift($acoes, [
                'label' => 'Ajuda: regras MAC/PP',
                'url' => $this->route('regras-avaliacao'),
                'icon' => 'bi-info-circle',
            ]);
        }

        return $acoes;
    }

    protected function route(string $name): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }
}
