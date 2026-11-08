<?php

namespace App\Services\Portal;

use App\Models\StaffConversa;
use App\Models\StaffMensagem;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mensagens internas entre colaboradores (professor, DT, coordenação, secretaria, etc.).
 */
class StaffMensagemService
{
    public function __construct(
        private PortalConversaService $portalConversas,
    ) {}

    public function podeUsar(User $user): bool
    {
        return $this->portalConversas->podeUsarInboxStaff($user);
    }

    /**
     * Colaboradores contactáveis (qualquer staff activo excepto o próprio).
     *
     * @return Collection<int, User>
     */
    public function colaboradoresContactaveis(User $user, ?string $pesquisa = null): Collection
    {
        abort_unless($this->podeUsar($user), 403);

        $pesquisa = $pesquisa !== null ? trim($pesquisa) : null;

        return User::query()
            ->with('roles')
            ->where('estado', 'activo')
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['aluno', 'encarregado']))
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['aluno', 'encarregado']))
            ->when($pesquisa !== null && $pesquisa !== '', function ($q) use ($pesquisa) {
                $like = '%'.$pesquisa.'%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(150)
            ->get();
    }

    public function obterOuCriar(User $autor, User $destinatario): StaffConversa
    {
        abort_unless($this->podeUsar($autor), 403);
        abort_unless($this->podeUsar($destinatario) || $this->eStaffActivo($destinatario), 403);
        abort_if((int) $autor->id === (int) $destinatario->id, 422, 'Não pode iniciar conversa consigo próprio.');

        [$low, $high] = $this->ordenarPar((int) $autor->id, (int) $destinatario->id);

        return StaffConversa::firstOrCreate(
            [
                'user_low_id' => $low,
                'user_high_id' => $high,
            ],
            ['last_message_at' => null]
        );
    }

    public function podeAceder(User $user, StaffConversa $conversa): bool
    {
        return $this->podeUsar($user) && $conversa->incluiUser($user);
    }

    /**
     * @return Collection<int, StaffConversa>
     */
    public function listar(User $user): Collection
    {
        abort_unless($this->podeUsar($user), 403);

        return StaffConversa::query()
            ->with(['userLow.roles', 'userHigh.roles', 'mensagens' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($q) use ($user) {
                $q->where('user_low_id', $user->id)
                    ->orWhere('user_high_id', $user->id);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    public function enviar(User $autor, StaffConversa $conversa, string $corpo): StaffMensagem
    {
        abort_unless($this->podeAceder($autor, $conversa), 403);

        $corpo = trim($corpo);
        if ($corpo === '') {
            throw ValidationException::withMessages([
                'corpo' => 'Escreva uma mensagem antes de enviar.',
            ]);
        }

        $mensagem = DB::transaction(function () use ($autor, $conversa, $corpo) {
            $msg = StaffMensagem::create([
                'conversa_id' => $conversa->id,
                'user_id' => $autor->id,
                'corpo' => $corpo,
            ]);

            $conversa->forceFill(['last_message_at' => now()])->save();

            return $msg;
        });

        $outro = $conversa->outroParticipante($autor);
        if ($outro) {
            $this->notificar($outro, $autor, $conversa, $mensagem);
        }

        return $mensagem;
    }

    public function marcarLidasPara(User $user, StaffConversa $conversa): void
    {
        StaffMensagem::query()
            ->where('conversa_id', $conversa->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function cargoLabel(User $user): string
    {
        $roles = $user->getRoleNames();
        $mapa = [
            'admin_full' => 'Administração',
            'directora_geral' => 'Direcção',
            'gestor_academico' => 'Gestão académica',
            'subdiretor_pedagogico' => 'Subdirecção pedagógica',
            'coordenador_curso' => 'Coord. de curso',
            'coordenador_disciplina' => 'Coord. de disciplina',
            'director_turma' => 'Director de turma',
            'professor' => 'Professor',
            'secretaria' => 'Secretaria',
            'inspetor' => 'Inspecção',
        ];

        foreach ($mapa as $role => $label) {
            if ($roles->contains($role)) {
                return $label;
            }
        }

        return $roles->first() ?? 'Colaborador';
    }

    private function eStaffActivo(User $user): bool
    {
        if ($user->estado !== 'activo') {
            return false;
        }

        $roles = $user->getRoleNames();

        return $roles->isNotEmpty()
            && ! $roles->contains('aluno')
            && ! $roles->contains('encarregado');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function ordenarPar(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    private function notificar(User $destinatario, User $autor, StaffConversa $conversa, StaffMensagem $mensagem): void
    {
        try {
            $destinatario->notify(new StaffAlertaAcademicoNotification('mensagem_interna', [
                'titulo' => 'Mensagem de '.$autor->name,
                'mensagem' => mb_substr($mensagem->corpo, 0, 120),
                'url' => route('mensagens-internas.show', $conversa, absolute: false),
                'tipo_evento' => 'mensagem_interna',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha notificação mensagem interna: '.$e->getMessage(), [
                'user_id' => $destinatario->id,
            ]);
        }
    }
}
