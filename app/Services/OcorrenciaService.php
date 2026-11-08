<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\DirectorTurma;
use App\Models\Ocorrencia;
use App\Models\User;
use App\Notifications\StaffAlertaAcademicoNotification;
use App\Services\WhatsApp\EncarregadoWhatsAppNotifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OcorrenciaService
{
    public function __construct(
        private TurmaEscopoService $escopo,
        private PresencaService $presencas,
        private EncarregadoWhatsAppNotifier $whatsappNotifier,
    ) {}

    public function podeVer(User $user, Ocorrencia $ocorrencia): bool
    {
        return $this->presencas->podeVerTurma($user, (int) $ocorrencia->turma_id);
    }

    public function podeRegistarEmTurma(User $user, int $turmaId): bool
    {
        if (! $user->can('ocorrencias.registar')) {
            return false;
        }

        return $this->presencas->podeVerTurma($user, $turmaId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $query = Ocorrencia::query()
            ->with(['aluno', 'turma', 'registadoPor'])
            ->latest('data')
            ->latest('id');

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['turma_id'])) {
            $query->where('turma_id', $filtros['turma_id']);
        }
        if (! empty($filtros['severidade'])) {
            $query->where('severidade', $filtros['severidade']);
        }
        if (! empty($filtros['aluno_id'])) {
            $query->where('aluno_id', $filtros['aluno_id']);
        }

        $anoId = $filtros['ano_lectivo_id'] ?? Configuracao::getAnoLectivoActualId();
        if ($anoId) {
            $query->where('ano_lectivo_id', $anoId);
        }

        if (! $user->temVisaoAssiduidade()) {
            $turmaIds = $this->turmaIdsAcessiveis($user);
            $query->whereIn('turma_id', $turmaIds ?: [-1]);
        }

        return $query->paginate(20)->withQueryString();
    }

    /**
     * @param  array{aluno_id:int,data:string,tipo:string,severidade:string,descricao:string,medida?:?string}  $dados
     */
    public function criar(User $user, array $dados): Ocorrencia
    {
        $aluno = Aluno::query()->findOrFail($dados['aluno_id']);
        if (! $aluno->turma_id) {
            abort(422, 'Aluno sem turma associada.');
        }

        if (! $this->podeRegistarEmTurma($user, (int) $aluno->turma_id)) {
            abort(403, 'Não pode registar ocorrências nesta turma.');
        }

        $aluno->loadMissing('turma');
        $anoLectivoId = (int) ($aluno->turma?->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());

        if (! Configuracao::podeEditarAnoLectivoById($anoLectivoId, $user)) {
            abort(403, 'Ano lectivo anterior — só o administrador pode registar ocorrências.');
        }

        $ocorrencia = Ocorrencia::create([
            'aluno_id' => $aluno->id,
            'turma_id' => $aluno->turma_id,
            'ano_lectivo_id' => $anoLectivoId,
            'data' => $dados['data'],
            'tipo' => $dados['tipo'],
            'severidade' => $dados['severidade'],
            'descricao' => $dados['descricao'],
            'medida' => $dados['medida'] ?? null,
            'estado' => Ocorrencia::ESTADO_ABERTA,
            'registado_por' => $user->id,
        ]);

        $this->notificarInteressados($ocorrencia);

        return $ocorrencia;
    }

    public function resolver(User $user, Ocorrencia $ocorrencia, ?string $medida = null): Ocorrencia
    {
        if (! $user->can('ocorrencias.resolver')) {
            abort(403);
        }
        if (! $this->podeVer($user, $ocorrencia)) {
            abort(403);
        }

        $anoLectivoId = (int) ($ocorrencia->ano_lectivo_id ?: Configuracao::getAnoLectivoActualId());
        if (! Configuracao::podeEditarAnoLectivoById($anoLectivoId, $user)) {
            abort(403, 'Ano lectivo anterior — só o administrador pode resolver ocorrências.');
        }

        $ocorrencia->estado = Ocorrencia::ESTADO_RESOLVIDA;
        if ($medida !== null && $medida !== '') {
            $ocorrencia->medida = $medida;
        }
        $ocorrencia->resolvido_por = $user->id;
        $ocorrencia->resolvido_em = now();
        $ocorrencia->save();

        return $ocorrencia;
    }

    /**
     * @return array<int, int>
     */
    private function turmaIdsAcessiveis(User $user): array
    {
        $ids = collect();

        if ($user->hasRole('director_turma')) {
            $ids = $ids->merge(
                $user->turmasComoDirector()
                    ->where('director_turma.activo', true)
                    ->pluck('turmas.id')
            );
        }

        if ($user->hasRole('professor')) {
            $ids = $ids->merge(
                DB::table('professor_disciplina_turma')
                    ->where('user_id', $user->id)
                    ->where('activo', true)
                    ->pluck('turma_id')
            );
        }

        return $ids->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function notificarInteressados(Ocorrencia $ocorrencia): void
    {
        $url = route('ocorrencias.show', $ocorrencia);
        $titulo = 'Nova ocorrência disciplinar';
        $mensagem = ($ocorrencia->aluno?->nome ?? 'Aluno').' — '
            .(Ocorrencia::tipos()[$ocorrencia->tipo] ?? $ocorrencia->tipo)
            .' ('.(Ocorrencia::severidades()[$ocorrencia->severidade] ?? $ocorrencia->severidade).')';

        $destinatarios = collect();

        $directores = DirectorTurma::query()
            ->where('turma_id', $ocorrencia->turma_id)
            ->where('activo', true)
            ->pluck('user_id');

        if ($directores->isNotEmpty()) {
            $destinatarios = $destinatarios->merge(User::query()->whereIn('id', $directores)->get());
        }

        if ($ocorrencia->severidade === Ocorrencia::SEV_ALTA) {
            $destinatarios = $destinatarios->merge(
                User::role('inspetor')->get()
            );
        }

        $destinatarios = $destinatarios
            ->unique('id')
            ->reject(fn (User $u) => (int) $u->id === (int) $ocorrencia->registado_por);

        foreach ($destinatarios as $user) {
            try {
                $user->notify(new StaffAlertaAcademicoNotification('ocorrencia', [
                    'titulo' => $titulo,
                    'mensagem' => $mensagem,
                    'url' => $url,
                ]));
            } catch (\Throwable $e) {
                Log::warning('Falha notificação ocorrência: '.$e->getMessage(), [
                    'ocorrencia_id' => $ocorrencia->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        $this->notificarEncarregados($ocorrencia, $mensagem);
    }

    private function notificarEncarregados(Ocorrencia $ocorrencia, string $mensagemResumo): void
    {
        $ocorrencia->loadMissing('aluno.encarregados.user');
        $aluno = $ocorrencia->aluno;
        if (! $aluno) {
            return;
        }

        $titulo = match ($ocorrencia->tipo) {
            Ocorrencia::TIPO_ADVERTENCIA => 'Advertência disciplinar',
            Ocorrencia::TIPO_COMPORTAMENTO => 'Registo de comportamento',
            default => 'Ocorrência escolar',
        };

        foreach ($aluno->encarregados as $encarregado) {
            $user = $encarregado->user;
            if (! $user) {
                continue;
            }

            try {
                $user->notify(new StaffAlertaAcademicoNotification('ocorrencia_encarregado', [
                    'titulo' => $titulo,
                    'mensagem' => $mensagemResumo,
                    'url' => route('portal.encarregado.ocorrencias', $aluno),
                    'tipo_evento' => 'alerta_academico',
                ]));
            } catch (\Throwable $e) {
                Log::warning('Falha notificação encarregado ocorrência: '.$e->getMessage(), [
                    'ocorrencia_id' => $ocorrencia->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        try {
            $this->whatsappNotifier->ocorrencia($ocorrencia);
        } catch (\Throwable $e) {
            Log::warning('Falha WhatsApp ocorrência: '.$e->getMessage(), [
                'ocorrencia_id' => $ocorrencia->id,
            ]);
        }
    }
}
