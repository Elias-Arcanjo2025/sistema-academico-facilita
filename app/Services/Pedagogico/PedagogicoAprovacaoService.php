<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PedagogicoAprovacaoService
{
    public function podeAprovar(User $user, int $disciplinaId, ?int $classeId = null): bool
    {
        if (! $user->can('pedagogico.aprovar')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($user->hasRole('coordenador_curso')) {
            return true; // escopo já filtrado nas listagens; aprovação permitida se tem permissão
        }

        return $this->coordenaDisciplina($user, $disciplinaId, null, $classeId);
    }

    /**
     * Pode aprovar este artefacto? Coordenador pode submeter na própria disciplina,
     * mas não pode auto-aprovar o trabalho de que é autor (excepto visão institucional).
     */
    public function podeAprovarPlanificacao(User $user, PedPlanificacao $planificacao): bool
    {
        $planificacao->loadMissing('turma');
        $classeId = $planificacao->turma?->classe_id ? (int) $planificacao->turma->classe_id : null;

        if (! $this->podeAprovar($user, (int) $planificacao->disciplina_id, $classeId)) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        return (int) $planificacao->user_id !== (int) $user->id;
    }

    public function podeAprovarProva(User $user, PedProva $prova): bool
    {
        $prova->loadMissing(['turma', 'planificacao']);
        $classeId = $prova->turma?->classe_id ? (int) $prova->turma->classe_id : null;

        if (! $this->podeAprovar($user, (int) $prova->disciplina_id, $classeId)) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        $autorId = (int) ($prova->planificacao?->user_id ?? 0);

        return $autorId === 0 || $autorId !== (int) $user->id;
    }

    /**
     * Sem $classeId: coordena a disciplina em qualquer classe (ex.: banco de questões).
     * Com $classeId: só se a classe estiver na atribuição.
     */
    public function coordenaDisciplina(
        User $user,
        int $disciplinaId,
        ?int $anoLectivoId = null,
        ?int $classeId = null,
    ): bool {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        return CoordenadorDisciplina::query()
            ->paraDisciplinaClasse($disciplinaId, $classeId, $anoLectivoId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function submeterPlanificacao(User $user, PedPlanificacao $planificacao): PedPlanificacao
    {
        if (! PedagogicoAccess::podeEditarPlanificacao($user, $planificacao)) {
            abort(403);
        }

        if ($planificacao->estado !== PedPlanificacao::ESTADO_RASCUNHO) {
            throw new InvalidArgumentException('Só planificações em rascunho podem ser submetidas.');
        }

        $planificacao->update([
            'estado' => PedPlanificacao::ESTADO_PENDENTE,
            'submetido_em' => now(),
            'motivo_rejeicao' => null,
            'aprovado_por' => null,
            'aprovado_em' => null,
        ]);

        $planificacao->loadMissing('turma', 'disciplina');

        $this->notificarCoordenadores(
            (int) $planificacao->disciplina_id,
            $planificacao->turma?->classe_id ? (int) $planificacao->turma->classe_id : null,
            'Planificação pendente de aprovação',
            ($planificacao->disciplina?->nome ?? 'Disciplina').' — turma '.($planificacao->turma?->nome ?? ''),
            route('pedagogico.aprovacoes.index'),
            $user->id,
        );

        return $planificacao->fresh();
    }

    public function aprovarPlanificacao(User $user, PedPlanificacao $planificacao): PedPlanificacao
    {
        if (! $this->podeAprovarPlanificacao($user, $planificacao)) {
            abort(403, 'Não pode aprovar a sua própria planificação. Aguarde outro coordenador ou a direcção.');
        }

        if ($planificacao->estado !== PedPlanificacao::ESTADO_PENDENTE) {
            throw new InvalidArgumentException('A planificação não está pendente de aprovação.');
        }

        $planificacao->update([
            'estado' => PedPlanificacao::ESTADO_APROVADA,
            'aprovado_por' => $user->id,
            'aprovado_em' => now(),
            'motivo_rejeicao' => null,
        ]);

        $this->notificarProfessor(
            $planificacao->professor,
            'Planificação aprovada',
            'A sua planificação foi aprovada e pode ser activada.',
            route('pedagogico.planificacoes.show', $planificacao),
        );

        return $planificacao->fresh();
    }

    public function rejeitarPlanificacao(User $user, PedPlanificacao $planificacao, string $motivo): PedPlanificacao
    {
        if (! $this->podeAprovarPlanificacao($user, $planificacao)) {
            abort(403, 'Não pode rejeitar a sua própria planificação.');
        }

        if ($planificacao->estado !== PedPlanificacao::ESTADO_PENDENTE) {
            throw new InvalidArgumentException('A planificação não está pendente de aprovação.');
        }

        $planificacao->update([
            'estado' => PedPlanificacao::ESTADO_RASCUNHO,
            'aprovado_por' => $user->id,
            'aprovado_em' => now(),
            'motivo_rejeicao' => $motivo,
        ]);

        $this->notificarProfessor(
            $planificacao->professor,
            'Planificação rejeitada',
            $motivo,
            route('pedagogico.planificacoes.show', $planificacao),
        );

        return $planificacao->fresh();
    }

    public function submeterProva(User $user, PedProva $prova): PedProva
    {
        if (! PedagogicoAccess::temAcessoPlanificacao($user, $prova->planificacao ?? PedPlanificacao::findOrFail($prova->planificacao_id))) {
            abort(403);
        }

        if ($prova->estado !== PedProva::ESTADO_RASCUNHO) {
            throw new InvalidArgumentException('Só provas em rascunho podem ser submetidas.');
        }

        $prova->update([
            'estado' => PedProva::ESTADO_PENDENTE,
            'submetido_em' => now(),
            'motivo_rejeicao' => null,
            'aprovado_por' => null,
            'aprovado_em' => null,
        ]);

        $prova->loadMissing('turma', 'disciplina');

        $this->notificarCoordenadores(
            (int) $prova->disciplina_id,
            $prova->turma?->classe_id ? (int) $prova->turma->classe_id : null,
            'Prova pendente de aprovação',
            ($prova->disciplina?->nome ?? 'Disciplina').' — '.$prova->tipo,
            route('pedagogico.aprovacoes.index'),
            $user->id,
        );

        return $prova->fresh();
    }

    public function aprovarProva(User $user, PedProva $prova): PedProva
    {
        if (! $this->podeAprovarProva($user, $prova)) {
            abort(403, 'Não pode aprovar a sua própria prova. Aguarde outro coordenador ou a direcção.');
        }

        if ($prova->estado !== PedProva::ESTADO_PENDENTE) {
            throw new InvalidArgumentException('A prova não está pendente de aprovação.');
        }

        $prova->update([
            'estado' => PedProva::ESTADO_APROVADA,
            'aprovado_por' => $user->id,
            'aprovado_em' => now(),
            'motivo_rejeicao' => null,
        ]);

        $professor = $prova->planificacao?->professor;
        $this->notificarProfessor(
            $professor,
            'Prova aprovada',
            'A sua prova '.$prova->tipo.' foi aprovada.',
            route('pedagogico.provas.show', $prova),
        );

        return $prova->fresh();
    }

    public function rejeitarProva(User $user, PedProva $prova, string $motivo): PedProva
    {
        if (! $this->podeAprovarProva($user, $prova)) {
            abort(403, 'Não pode rejeitar a sua própria prova.');
        }

        if ($prova->estado !== PedProva::ESTADO_PENDENTE) {
            throw new InvalidArgumentException('A prova não está pendente de aprovação.');
        }

        $prova->update([
            'estado' => PedProva::ESTADO_RASCUNHO,
            'aprovado_por' => $user->id,
            'aprovado_em' => now(),
            'motivo_rejeicao' => $motivo,
        ]);

        $this->notificarProfessor(
            $prova->planificacao?->professor,
            'Prova rejeitada',
            $motivo,
            route('pedagogico.provas.show', $prova),
        );

        return $prova->fresh();
    }

    public function publicarProva(User $user, PedProva $prova): PedProva
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $prova) {
            $prova = PedProva::query()->whereKey($prova->id)->lockForUpdate()->firstOrFail();

            $planificacao = $prova->planificacao ?? PedPlanificacao::findOrFail($prova->planificacao_id);
            $planificacao->loadMissing('turma');
            $classeId = $planificacao->turma?->classe_id ? (int) $planificacao->turma->classe_id : null;

            if (! PedagogicoAccess::podeEditarPlanificacao($user, $planificacao) && ! $this->podeAprovar($user, (int) $prova->disciplina_id, $classeId)) {
                abort(403);
            }

            if ($prova->estado !== PedProva::ESTADO_APROVADA) {
                throw new InvalidArgumentException('Só provas aprovadas podem ser publicadas.');
            }

            $prova->update(['estado' => PedProva::ESTADO_PUBLICADA]);

            return $prova->fresh();
        });
    }

    private function notificarCoordenadores(
        int $disciplinaId,
        ?int $classeId,
        string $titulo,
        string $mensagem,
        string $url,
        int $excetoUserId,
    ): void {
        $anoId = Configuracao::getAnoLectivoActualId();
        $userIds = CoordenadorDisciplina::query()
            ->paraDisciplinaClasse($disciplinaId, $classeId, $anoId)
            ->pluck('user_id')
            ->unique()
            ->reject(fn ($id) => (int) $id === $excetoUserId);

        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $dest) {
            $this->enviar($dest, $titulo, $mensagem, $url);
        }
    }

    private function notificarProfessor(?User $professor, string $titulo, string $mensagem, string $url): void
    {
        if (! $professor) {
            return;
        }
        $this->enviar($professor, $titulo, $mensagem, $url);
    }

    private function enviar(User $user, string $titulo, string $mensagem, string $url): void
    {
        try {
            $user->notify(new StaffAlertaAcademicoNotification('pedagogico_aprovacao', [
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'url' => $url,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha notificação pedagógica: '.$e->getMessage(), ['user_id' => $user->id]);
        }
    }
}
