<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\NotificacaoCampanha;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CampanhaNotificacaoService
{
    public function podeEnviar(User $user): bool
    {
        return $user->can('secretaria.campanhas.enviar')
            || $user->temVisaoInstitucional();
    }

    /**
     * @return array{campanha: NotificacaoCampanha, enviados: int}
     */
    public function enviar(
        User $autor,
        string $titulo,
        string $mensagem,
        string $publico,
        ?int $turmaId = null,
    ): array {
        abort_unless($this->podeEnviar($autor), 403);

        if (! array_key_exists($publico, NotificacaoCampanha::publicos())) {
            throw new InvalidArgumentException('Público inválido.');
        }

        if ($publico === NotificacaoCampanha::PUBLICO_TURMA && ! $turmaId) {
            throw new InvalidArgumentException('Seleccione a turma para este público.');
        }

        $destinatarios = $this->resolverDestinatarios($publico, $turmaId)
            ->reject(fn (User $u) => (int) $u->id === (int) $autor->id)
            ->unique('id')
            ->values();

        $enviados = 0;
        $ids = [];

        foreach ($destinatarios as $user) {
            try {
                $user->notify(new StaffAlertaAcademicoNotification('campanha_sistema', [
                    'titulo' => $titulo,
                    'mensagem' => $mensagem,
                    'url' => $this->urlPara($user),
                ]));
                $enviados++;
                $ids[] = (int) $user->id;
            } catch (\Throwable $e) {
                Log::warning('Falha campanha notificação', [
                    'user_id' => $user->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        $campanha = NotificacaoCampanha::create([
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'publico' => $publico,
            'turma_id' => $turmaId,
            'enviados' => $enviados,
            'destinatario_ids' => $ids,
            'criado_por' => $autor->id,
        ]);

        return ['campanha' => $campanha, 'enviados' => $enviados];
    }

    /**
     * @return Collection<int, User>
     */
    public function resolverDestinatarios(string $publico, ?int $turmaId = null): Collection
    {
        return match ($publico) {
            NotificacaoCampanha::PUBLICO_PROFESSORES => User::role('professor')
                ->where('estado', 'activo')
                ->whereNull('aluno_id')
                ->get(),
            NotificacaoCampanha::PUBLICO_DIRECTORES => User::role('director_turma')
                ->where('estado', 'activo')
                ->get(),
            NotificacaoCampanha::PUBLICO_STAFF => User::query()
                ->where('estado', 'activo')
                ->whereNull('aluno_id')
                ->whereNull('encarregado_id')
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['aluno', 'encarregado']))
                ->get(),
            NotificacaoCampanha::PUBLICO_ENCARREGADOS => User::role('encarregado')
                ->where('estado', 'activo')
                ->whereNotNull('encarregado_id')
                ->get(),
            NotificacaoCampanha::PUBLICO_ALUNOS => User::role('aluno')
                ->where('estado', 'activo')
                ->whereNotNull('aluno_id')
                ->get(),
            NotificacaoCampanha::PUBLICO_TURMA => $this->destinatariosTurma((int) $turmaId),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function destinatariosTurma(int $turmaId): Collection
    {
        $turma = Turma::find($turmaId);
        if (! $turma) {
            return collect();
        }

        $alunos = Aluno::query()
            ->where('turma_id', $turmaId)
            ->activo()
            ->with(['user', 'encarregados.user'])
            ->get();

        $users = collect();

        foreach ($alunos as $aluno) {
            if ($aluno->user) {
                $users->push($aluno->user);
            }
            foreach ($aluno->encarregados as $enc) {
                if ($enc->user) {
                    $users->push($enc->user);
                }
            }
        }

        $directores = User::role('director_turma')
            ->where('estado', 'activo')
            ->whereHas('directorTurmas', fn ($q) => $q->where('turma_id', $turmaId)->where('activo', true))
            ->get();

        return $users->merge($directores)->unique('id')->values();
    }

    private function urlPara(User $user): string
    {
        if ($user->hasRole('aluno') || $user->aluno_id) {
            return route('portal.aluno.notificacoes');
        }

        if ($user->hasRole('encarregado') || $user->encarregado_id) {
            return route('portal.encarregado.dashboard');
        }

        return route('notificacoes.index');
    }
}
