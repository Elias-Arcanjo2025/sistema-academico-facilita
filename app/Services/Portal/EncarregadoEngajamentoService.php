<?php

namespace App\Services\Portal;

use App\Models\Aluno;
use App\Models\BoletimAssinatura;
use App\Models\Configuracao;
use App\Models\DocumentoPedido;
use App\Models\Encarregado;
use App\Models\Nota;
use App\Models\Ocorrencia;
use App\Models\PortalConversa;
use App\Models\PortalMensagem;
use App\Models\PresencaRegisto;
use App\Models\User;
use App\Services\ConfiguracaoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Acções prioritárias ("O que fazer agora") e linha do tempo do educando.
 */
class EncarregadoEngajamentoService
{
    public function __construct(
        private readonly EncarregadoDashboardService $dashboard,
        private readonly ConfiguracaoService $configuracao,
    ) {}

    /**
     * @param  Collection<int, Aluno>  $filhos
     * @return list<array{prioridade:int, tipo:string, titulo:string, texto:string, url:string, aluno_id:int, aluno_nome:string, icone:string}>
     */
    public function acoesAgora(Encarregado $encarregado, User $user, Collection $filhos, int $limite = 6): array
    {
        $acoes = [];

        foreach ($filhos as $filho) {
            $acoes = array_merge($acoes, $this->acoesParaFilho($encarregado, $user, $filho));
        }

        usort($acoes, fn (array $a, array $b) => $a['prioridade'] <=> $b['prioridade']);

        return array_slice($acoes, 0, $limite);
    }

    /**
     * @return list<array{prioridade:int, tipo:string, titulo:string, texto:string, url:string, aluno_id:int, aluno_nome:string, icone:string}>
     */
    public function acoesParaFilho(Encarregado $encarregado, User $user, Aluno $aluno, int $limite = 4): array
    {
        $cartao = $this->dashboard->cartaoFilho($encarregado, $aluno);
        $nome = $cartao['nome_curto'];
        $acoes = [];

        $msgsNaoLidas = $this->contarMensagensNaoLidas($user, $aluno);
        if ($msgsNaoLidas > 0) {
            $acoes[] = [
                'prioridade' => 10,
                'tipo' => 'mensagem',
                'titulo' => 'Ler mensagem da escola',
                'texto' => $nome.': '.$msgsNaoLidas.' mensagem(ns) por ler.',
                'url' => route('portal.encarregado.mensagens.show', $aluno),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-chat-dots',
            ];
        }

        $docPronto = DocumentoPedido::query()
            ->where('encarregado_id', $encarregado->id)
            ->where('aluno_id', $aluno->id)
            ->where('estado', DocumentoPedido::ESTADO_PRONTO)
            ->latest('atendido_em')
            ->first();

        if ($docPronto) {
            $acoes[] = [
                'prioridade' => 15,
                'tipo' => 'documento',
                'titulo' => 'Levantar documento',
                'texto' => $nome.': '.$docPronto->tipoLabel().' pronto na secretaria.',
                'url' => route('portal.encarregado.documentos.index'),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-file-earmark-check',
            ];
        }

        $ocorrenciaRecente = Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->where('data', '>=', now()->subDays(14)->toDateString())
            ->latest('data')
            ->first();

        if ($ocorrenciaRecente) {
            $tipoLabel = Ocorrencia::tipos()[$ocorrenciaRecente->tipo] ?? $ocorrenciaRecente->tipo;
            $acoes[] = [
                'prioridade' => 20,
                'tipo' => 'ocorrencia',
                'titulo' => 'Ver ocorrência recente',
                'texto' => $nome.': '.$tipoLabel.' em '.$ocorrenciaRecente->data->format('d/m').'.',
                'url' => route('portal.encarregado.ocorrencias', $aluno),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-exclamation-octagon',
            ];
        }

        $faltasRecentes = PresencaRegisto::query()
            ->where('aluno_id', $aluno->id)
            ->where('estado', PresencaRegisto::FALTA_INJUSTIFICADA)
            ->whereHas('aula', fn ($q) => $q->where('data', '>=', now()->subDays(14)->toDateString()))
            ->count();

        if ($faltasRecentes > 0 || ($cartao['faltas_injustificadas'] ?? 0) >= 3) {
            $texto = $faltasRecentes > 0
                ? $nome.': '.$faltasRecentes.' falta(s) injustificada(s) nos últimos 14 dias. Consulte e fale com a escola se precisar justificar.'
                : $nome.': acumula '.$cartao['faltas_injustificadas'].' faltas injustificadas. Reveja a assiduidade.';
            $acoes[] = [
                'prioridade' => 30,
                'tipo' => 'faltas',
                'titulo' => 'Rever assiduidade',
                'texto' => $texto,
                'url' => route('portal.encarregado.assiduidade', $aluno),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-person-check',
            ];
        }

        if ($cartao['pode_notas'] && ($cartao['negativas'] ?? 0) > 0) {
            $acoes[] = [
                'prioridade' => 40,
                'tipo' => 'notas',
                'titulo' => 'Ver negativas',
                'texto' => $nome.' tem '.$cartao['negativas'].' negativa(s) (CFD). Abra o dashboard e fale com a escola se necessário.',
                'url' => route('portal.encarregado.filho', $aluno),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-journal-x',
            ];
        }

        if ($this->precisaAssinarBoletim($encarregado, $aluno, $cartao['pode_notas'])) {
            $trimestre = $this->configuracao->getTrimestreActual();
            $acoes[] = [
                'prioridade' => 50,
                'tipo' => 'assinatura',
                'titulo' => 'Assinar boletim',
                'texto' => 'Confirme a leitura do boletim (T'.$trimestre.') de '.$nome.'.',
                'url' => route('portal.encarregado.filho', $aluno).'#assinatura-boletim',
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-pen',
            ];
        }

        if (in_array($cartao['score_label'], ['Situação crítica', 'Precisa de acompanhamento'], true)) {
            $acoes[] = [
                'prioridade' => 60,
                'tipo' => 'score',
                'titulo' => 'Acompanhar de perto',
                'texto' => $nome.': '.$cartao['score_label'].'. Veja a linha do tempo e contacte a escola.',
                'url' => route('portal.encarregado.filho.timeline', $aluno),
                'aluno_id' => (int) $aluno->id,
                'aluno_nome' => $nome,
                'icone' => 'bi-heart-pulse',
            ];
        }

        usort($acoes, fn (array $a, array $b) => $a['prioridade'] <=> $b['prioridade']);

        return array_slice($acoes, 0, $limite);
    }

    /**
     * @return Collection<int, array{tipo:string, titulo:string, texto:string, data:Carbon, url:?string, icone:string, tom:string}>
     */
    public function timeline(Encarregado $encarregado, User $user, Aluno $aluno, int $limite = 40): Collection
    {
        $eventos = collect();
        $anoId = Configuracao::getAnoLectivoActualId();
        $cartao = $this->dashboard->cartaoFilho($encarregado, $aluno);

        $presencas = PresencaRegisto::query()
            ->with(['aula.disciplina'])
            ->where('aluno_id', $aluno->id)
            ->whereIn('estado', [
                PresencaRegisto::FALTA_INJUSTIFICADA,
                PresencaRegisto::FALTA_JUSTIFICADA,
                PresencaRegisto::ATRASO,
            ])
            ->whereHas('aula', fn ($q) => $q->when($anoId, fn ($qq) => $qq->where('ano_lectivo_id', $anoId)))
            ->latest('id')
            ->limit(25)
            ->get();

        foreach ($presencas as $reg) {
            $data = $reg->aula?->data ? Carbon::parse($reg->aula->data) : $reg->created_at;
            $estadoLabel = PresencaRegisto::estados()[$reg->estado] ?? $reg->estado;
            $disc = $reg->aula?->disciplina?->nome;
            $eventos->push([
                'tipo' => 'presenca',
                'titulo' => $estadoLabel,
                'texto' => trim(($disc ? $disc.' · ' : '').$estadoLabel),
                'data' => $data,
                'url' => route('portal.encarregado.assiduidade', $aluno),
                'icone' => 'bi-person-check',
                'tom' => $reg->estado === PresencaRegisto::FALTA_INJUSTIFICADA ? 'warn' : 'neutral',
            ]);
        }

        $ocorrencias = Ocorrencia::query()
            ->where('aluno_id', $aluno->id)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->latest('data')
            ->limit(20)
            ->get();

        foreach ($ocorrencias as $oc) {
            $eventos->push([
                'tipo' => 'ocorrencia',
                'titulo' => Ocorrencia::tipos()[$oc->tipo] ?? $oc->tipo,
                'texto' => \Illuminate\Support\Str::limit((string) $oc->descricao, 100),
                'data' => Carbon::parse($oc->data),
                'url' => route('portal.encarregado.ocorrencias', $aluno),
                'icone' => 'bi-exclamation-octagon',
                'tom' => 'warn',
            ]);
        }

        if ($cartao['pode_notas'] && $anoId) {
            $notas = Nota::query()
                ->with('disciplina')
                ->where('aluno_id', $aluno->id)
                ->whereNull('archived_at')
                ->where('ano_lectivo_id', $anoId)
                ->latest('updated_at')
                ->limit(25)
                ->get();

            $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
            foreach ($notas as $nota) {
                $valor = $nota->valor !== null ? number_format((float) $nota->valor, 1) : '—';
                $eventos->push([
                    'tipo' => 'nota',
                    'titulo' => ($nota->disciplina?->nome ?? 'Disciplina').' · '.$nota->tipo,
                    'texto' => 'Nota '.$valor.($nota->trimestre ? ' · T'.$nota->trimestre : ''),
                    'data' => $nota->updated_at ?? $nota->created_at,
                    'url' => route('portal.encarregado.filho', $aluno),
                    'icone' => 'bi-journal-text',
                    'tom' => ($nota->valor !== null && (float) $nota->valor < $notaMinima) ? 'warn' : 'ok',
                ]);
            }
        }

        $conversaIds = PortalConversa::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_user_id', $user->id)
            ->pluck('id');

        if ($conversaIds->isNotEmpty()) {
            $mensagens = PortalMensagem::query()
                ->with('autor')
                ->whereIn('conversa_id', $conversaIds)
                ->latest('created_at')
                ->limit(20)
                ->get();

            foreach ($mensagens as $msg) {
                $deEscola = (int) $msg->user_id !== (int) $user->id;
                $eventos->push([
                    'tipo' => 'mensagem',
                    'titulo' => $deEscola ? 'Mensagem da escola' : 'A sua mensagem',
                    'texto' => \Illuminate\Support\Str::limit($msg->corpo, 100),
                    'data' => $msg->created_at,
                    'url' => route('portal.encarregado.mensagens.show', $aluno),
                    'icone' => 'bi-chat-dots',
                    'tom' => $deEscola && ! $msg->read_at ? 'warn' : 'neutral',
                ]);
            }
        }

        return $eventos
            ->sortByDesc(fn (array $e) => $e['data']?->timestamp ?? 0)
            ->values()
            ->take($limite);
    }

    private function contarMensagensNaoLidas(User $user, Aluno $aluno): int
    {
        $conversaIds = PortalConversa::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_user_id', $user->id)
            ->pluck('id');

        if ($conversaIds->isEmpty()) {
            return 0;
        }

        return PortalMensagem::query()
            ->whereIn('conversa_id', $conversaIds)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    private function precisaAssinarBoletim(Encarregado $encarregado, Aluno $aluno, bool $podeNotas): bool
    {
        if (! $podeNotas) {
            return false;
        }

        $pivot = $encarregado->alunos()->where('alunos.id', $aluno->id)->first();
        if (! $pivot || ! ($pivot->pivot->pode_assinar_boletim ?? false)) {
            return false;
        }

        $ano = Configuracao::getAnoLectivoActual();
        $trimestre = $this->configuracao->getTrimestreActual();

        return ! BoletimAssinatura::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_id', $encarregado->id)
            ->where('ano_lectivo', $ano)
            ->where('trimestre', $trimestre)
            ->exists();
    }
}
