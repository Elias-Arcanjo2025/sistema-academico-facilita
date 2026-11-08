<?php

namespace App\Services\Portal;

use App\Models\AgendaEvento;
use App\Models\Aluno;
use App\Models\CalendarioProva;
use App\Models\Encarregado;
use App\Models\Nota;
use App\Models\Turma;
use App\Notifications\AlunoAgendaEventoNotification;
use App\Notifications\AlunoNotaPublicadaNotification;
use App\Notifications\AlunoProvaAgendadaNotification;
use App\Notifications\EncarregadoNotaPublicadaNotification;
use Illuminate\Support\Facades\Log;

class PortalNotificacaoService
{
    public function notificarNotaPublicada(Nota $nota): void
    {
        $tiposVisiveis = [
            Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_PG,
            Nota::TIPO_MT, Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12,
            Nota::TIPO_RECUPERACAO, Nota::TIPO_MELHORIA, Nota::TIPO_RECURSO,
        ];

        if (! in_array($nota->tipo, $tiposVisiveis, true)) {
            return;
        }

        $nota->loadMissing(['aluno.user', 'aluno.encarregados.user', 'disciplina']);

        $aluno = $nota->aluno;
        if (! $aluno) {
            return;
        }

        $user = $aluno->user;

        if ($user && $user->hasRole('aluno')) {
            try {
                $user->notify(new AlunoNotaPublicadaNotification($nota));
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar aluno sobre nota: '.$e->getMessage(), [
                    'nota_id' => $nota->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        $this->notificarEncarregadosNotaPublicada($nota, $aluno);
    }

    private function notificarEncarregadosNotaPublicada(Nota $nota, Aluno $aluno): void
    {
        $aluno->encarregados
            ->filter(fn (Encarregado $e) => (bool) ($e->pivot->pode_ver_notas ?? false))
            ->each(function (Encarregado $encarregado) use ($nota, $aluno) {
                $user = $encarregado->user;

                if (! $user || ! $user->hasRole('encarregado')) {
                    return;
                }

                try {
                    $user->notify(new EncarregadoNotaPublicadaNotification($nota, $aluno));
                } catch (\Throwable $e) {
                    Log::warning('Falha ao notificar encarregado sobre nota: '.$e->getMessage(), [
                        'nota_id' => $nota->id,
                        'encarregado_id' => $encarregado->id,
                        'user_id' => $user->id,
                    ]);
                }
            });
    }

    public function notificarProvaAgendada(CalendarioProva $prova): void
    {
        $prova->loadMissing(['disciplina', 'turma']);

        if (! $prova->turma_id) {
            return;
        }

        Aluno::query()
            ->where('turma_id', $prova->turma_id)
            ->where('activo', true)
            ->with('user')
            ->get()
            ->each(function (Aluno $aluno) use ($prova) {
                $user = $aluno->user;

                if (! $user || ! $user->hasRole('aluno')) {
                    return;
                }

                try {
                    $user->notify(new AlunoProvaAgendadaNotification($prova));
                } catch (\Throwable $e) {
                    Log::warning('Falha ao notificar aluno sobre prova: '.$e->getMessage(), [
                        'prova_id' => $prova->id,
                        'aluno_id' => $aluno->id,
                    ]);
                }
            });
    }

    public function notificarTurmaProvasAtualizadas(Turma $turma, CalendarioProva $prova): void
    {
        $this->notificarProvaAgendada($prova);
    }

    public function notificarAgendaEvento(AgendaEvento $evento): void
    {
        $query = Aluno::query()->where('activo', true)->with('user');

        if ($evento->visibilidade === AgendaEvento::VIS_TURMA && $evento->turma_id) {
            $query->where('turma_id', $evento->turma_id);
        } elseif ($evento->visibilidade === AgendaEvento::VIS_CURSO && $evento->curso_id) {
            $query->whereHas('turma', fn ($t) => $t->where('curso_id', $evento->curso_id));
        }

        $query->get()->each(function (Aluno $aluno) use ($evento) {
            $user = $aluno->user;

            if (! $user || ! $user->hasRole('aluno')) {
                return;
            }

            try {
                $user->notify(new AlunoAgendaEventoNotification($evento));
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar aluno sobre agenda: '.$e->getMessage(), [
                    'evento_id' => $evento->id,
                    'aluno_id' => $aluno->id,
                ]);
            }
        });
    }
}
