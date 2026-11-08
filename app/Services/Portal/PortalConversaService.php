<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\CoordenadorDisciplina;
use App\Models\DirectorTurma;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\TurmaEscopoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PortalConversaService
{
    public function __construct(
        private EncarregadoPortalAcessoService $acesso,
        private TurmaEscopoService $turmaEscopo,
    ) {}

    public function obterOuCriarParaEncarregado(User $encarregadoUser, Aluno $aluno): PortalConversa
    {
        $encarregado = $this->acesso->encarregadoDoUser($encarregadoUser);
        $this->acesso->assertPodeVer($encarregado, $aluno);

        return PortalConversa::firstOrCreate(
            [
                'aluno_id' => $aluno->id,
                'encarregado_user_id' => $encarregadoUser->id,
            ],
            ['last_message_at' => null]
        );
    }

    /**
     * Staff inicia (ou reabre) conversa com o encarregado do aluno, no âmbito do cargo.
     */
    public function obterOuCriarParaStaff(User $staff, Aluno $aluno): PortalConversa
    {
        abort_unless($this->podeUsarInboxStaff($staff), 403);

        if (! $aluno->turma_id || ! $this->turmaEscopo->podeAcederTurma($staff, (int) $aluno->turma_id)) {
            abort(403, 'Só pode contactar encarregados de alunos do seu âmbito.');
        }

        $encarregadoUser = $this->resolverEncarregadoUserDoAluno($aluno);
        if (! $encarregadoUser) {
            throw ValidationException::withMessages([
                'aluno_id' => 'Este aluno não tem encarregado com conta activa no portal.',
            ]);
        }

        return PortalConversa::firstOrCreate(
            [
                'aluno_id' => $aluno->id,
                'encarregado_user_id' => $encarregadoUser->id,
            ],
            ['last_message_at' => null]
        );
    }

    /**
     * Alunos do âmbito do staff com encarregado contactável no portal.
     *
     * @return Collection<int, Aluno>
     */
    public function alunosContactaveis(User $staff, ?string $pesquisa = null): Collection
    {
        $turmaIds = $this->turmaEscopo->turmaIdsConsultaveis($staff);
        if ($turmaIds->isEmpty()) {
            return collect();
        }

        $pesquisa = $pesquisa !== null ? trim($pesquisa) : null;

        return Aluno::query()
            ->with(['turma:id,nome,codigo', 'encarregados.user'])
            ->whereIn('turma_id', $turmaIds)
            ->where('activo', true)
            ->whereHas('encarregados', function ($q) {
                $q->where('encarregados.activo', true)
                    ->whereHas('user', fn ($uq) => $uq->where('estado', 'activo'));
            })
            ->when($pesquisa !== null && $pesquisa !== '', function ($q) use ($pesquisa) {
                $like = '%'.$pesquisa.'%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('nome', 'like', $like)
                        ->orWhere('numero_processo', 'like', $like);
                });
            })
            ->orderBy('nome')
            ->limit(150)
            ->get();
    }

    public function resolverEncarregadoUserDoAluno(Aluno $aluno): ?User
    {
        $aluno->loadMissing('encarregados.user');

        $principal = $aluno->encarregadoPrincipal();
        if ($principal?->user && $principal->user->estado === 'activo') {
            return $principal->user;
        }

        foreach ($aluno->encarregados as $encarregado) {
            $user = $encarregado->user;
            if ($user && $user->estado === 'activo') {
                return $user;
            }
        }

        return null;
    }

    public function podeUsarInboxStaff(User $user): bool
    {
        $roles = $user->getRoleNames();

        if ($roles->contains('encarregado') || $roles->contains('aluno')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $user->can('turma.comunicar')) {
            return true;
        }

        return $roles->intersect([
            'director_turma',
            'coordenador_curso',
            'coordenador_disciplina',
            'subdiretor_pedagogico',
            'professor',
            'secretaria',
            'inspetor',
        ])->isNotEmpty();
    }

    public function podeAcederComoStaff(User $user, PortalConversa $conversa): bool
    {
        if (! $this->podeUsarInboxStaff($user)) {
            return false;
        }

        $conversa->loadMissing('aluno');
        $aluno = $conversa->aluno;
        if (! $aluno?->turma_id) {
            return false;
        }

        return $this->turmaEscopo->podeAcederTurma($user, (int) $aluno->turma_id);
    }

    /** @deprecated Use podeAcederComoStaff */
    public function podeAcederComoDirector(User $user, PortalConversa $conversa): bool
    {
        return $this->podeAcederComoStaff($user, $conversa);
    }

    public function podeAcederComoEncarregado(User $user, PortalConversa $conversa): bool
    {
        return (int) $conversa->encarregado_user_id === (int) $user->id
            && $user->hasRole('encarregado');
    }

    /**
     * @return Collection<int, PortalConversa>
     */
    public function listarParaStaff(User $user): Collection
    {
        $turmaIds = $this->turmaEscopo->turmaIdsConsultaveis($user);

        if ($turmaIds->isEmpty()) {
            return collect();
        }

        return PortalConversa::query()
            ->with(['aluno.turma', 'encarregadoUser', 'mensagens' => fn ($q) => $q->latest()->limit(1)])
            ->whereHas('aluno', fn ($q) => $q->whereIn('turma_id', $turmaIds))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @deprecated Use listarParaStaff */
    public function listarParaDirector(User $user): Collection
    {
        return $this->listarParaStaff($user);
    }

    public function enviar(User $autor, PortalConversa $conversa, string $corpo): PortalMensagem
    {
        $corpo = trim($corpo);
        if ($corpo === '') {
            throw ValidationException::withMessages([
                'corpo' => 'Escreva uma mensagem antes de enviar.',
            ]);
        }

        $comoEncarregado = $this->podeAcederComoEncarregado($autor, $conversa);
        $comoStaff = $this->podeAcederComoStaff($autor, $conversa);
        abort_unless($comoEncarregado || $comoStaff, 403);

        $conversa->loadMissing('aluno');

        if ($comoEncarregado && $this->staffDaTurmaDoAluno($conversa->aluno)->isEmpty()) {
            throw ValidationException::withMessages([
                'corpo' => 'Não há destinatários na escola para esta turma (director, professor ou coordenação). Contacte a secretaria.',
            ]);
        }

        $mensagem = DB::transaction(function () use ($autor, $conversa, $corpo) {
            $msg = PortalMensagem::create([
                'conversa_id' => $conversa->id,
                'user_id' => $autor->id,
                'corpo' => $corpo,
            ]);

            $conversa->forceFill(['last_message_at' => now()])->save();

            return $msg;
        });

        $this->notificarDestinatarios($autor, $conversa, $mensagem);

        return $mensagem;
    }

    public function marcarLidasPara(User $user, PortalConversa $conversa): void
    {
        PortalMensagem::query()
            ->where('conversa_id', $conversa->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, User>
     */
    public function directoresDaTurmaDoAluno(Aluno $aluno): Collection
    {
        if (! $aluno->turma_id) {
            return collect();
        }

        $anoId = Configuracao::getAnoLectivoActualId();

        $query = DirectorTurma::query()
            ->where('turma_id', $aluno->turma_id)
            ->where('activo', true);

        if ($anoId) {
            $doAno = (clone $query)->where('ano_lectivo_id', $anoId)->pluck('user_id');
            $ids = $doAno->isNotEmpty()
                ? $doAno
                : $query->pluck('user_id');
        } else {
            $ids = $query->pluck('user_id');
        }

        return User::query()
            ->whereIn('id', $ids->unique()->filter())
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get();
    }

    /**
     * Staff do âmbito da turma: DT, professores, coordenadores de curso/disciplina.
     *
     * @return Collection<int, User>
     */
    public function staffDaTurmaDoAluno(Aluno $aluno): Collection
    {
        if (! $aluno->turma_id) {
            return collect();
        }

        $turmaId = (int) $aluno->turma_id;
        $anoId = Configuracao::getAnoLectivoActualId();
        $ids = collect();

        $ids = $ids->merge($this->directoresDaTurmaDoAluno($aluno)->pluck('id'));

        $ids = $ids->merge(
            ProfessorDisciplinaTurma::query()
                ->where('turma_id', $turmaId)
                ->where('activo', true)
                ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->pluck('user_id')
        );

        $turma = $aluno->relationLoaded('turma') ? $aluno->turma : Turma::find($turmaId);

        if ($turma?->curso_id) {
            $ids = $ids->merge(
                CoordenadorCurso::query()
                    ->where('curso_id', $turma->curso_id)
                    ->where('activo', true)
                    ->when($anoId, function ($q) use ($anoId) {
                        $q->where(function ($qq) use ($anoId) {
                            $qq->where('ano_lectivo_id', $anoId)->orWhereNull('ano_lectivo_id');
                        });
                    })
                    ->pluck('user_id')
            );
        }

        if ($turma?->classe_id && $anoId) {
            $coordDiscIds = CoordenadorDisciplina::query()
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoId)
                ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
                ->pluck('user_id');
            $ids = $ids->merge($coordDiscIds);
        }

        return User::query()
            ->whereIn('id', $ids->unique()->filter())
            ->where('estado', 'activo')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['aluno', 'encarregado']))
            ->orderBy('name')
            ->get();
    }

    private function notificarDestinatarios(User $autor, PortalConversa $conversa, PortalMensagem $mensagem): void
    {
        $conversa->loadMissing(['aluno.turma', 'encarregadoUser']);
        $preview = mb_substr($mensagem->corpo, 0, 120);
        $titulo = 'Nova mensagem — '.$conversa->aluno?->nome;

        if ($this->podeAcederComoEncarregado($autor, $conversa)) {
            $url = route('mensagens-encarregados.show', $conversa, absolute: false);
            foreach ($this->staffDaTurmaDoAluno($conversa->aluno) as $staff) {
                if ((int) $staff->id === (int) $autor->id) {
                    continue;
                }
                $this->notify($staff, $titulo, $preview, $url);
            }

            return;
        }

        // Staff escreveu: notificar encarregado + restantes staff do âmbito
        $encarregado = $conversa->encarregadoUser;
        if ($encarregado && (int) $encarregado->id !== (int) $autor->id) {
            $url = route('portal.encarregado.mensagens.show', $conversa->aluno_id, absolute: false);
            $this->notify($encarregado, $titulo, $preview, $url);
        }

        $urlStaff = route('mensagens-encarregados.show', $conversa, absolute: false);
        foreach ($this->staffDaTurmaDoAluno($conversa->aluno) as $staff) {
            if ((int) $staff->id === (int) $autor->id) {
                continue;
            }
            $this->notify($staff, $titulo, $preview, $urlStaff);
        }
    }

    private function notify(User $user, string $titulo, string $mensagem, string $url): void
    {
        try {
            $user->notify(new StaffAlertaAcademicoNotification('mensagem_portal', [
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'url' => $url,
                'tipo_evento' => 'mensagem_portal',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha notificação mensagem portal: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }
}
