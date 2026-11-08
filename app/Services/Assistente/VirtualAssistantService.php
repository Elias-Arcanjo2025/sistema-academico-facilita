<?php

namespace App\Services\Assistente;

use App\Models\AssistenteConversa;
use App\Models\AssistenteMensagem;
use App\Models\Configuracao;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class VirtualAssistantService
{
    public function __construct(
        protected AIServiceInterface $ai,
        protected AssistantContextBuilder $contextBuilder,
        protected AssistantAccess $access,
        protected AssistantAcoesService $acoes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function responder(User $user, string $mensagem, ?int $conversaId = null, ?string $routeName = null): array
    {
        if (! config('assistente.enabled', true)) {
            throw new RuntimeException('O assistente virtual está desactivado.');
        }

        if (! $this->access->canUse($user)) {
            throw new RuntimeException('Sem permissão para usar o assistente.');
        }

        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            throw new RuntimeException('Mensagem vazia.');
        }

        $avaliacao = $this->access->avaliarPergunta($user, $mensagem);

        $conversa = $this->resolverConversa($user, $conversaId, $mensagem);

        if (! $avaliacao['permitida']) {
            $resposta = $avaliacao['resposta'] ?? 'Esta pergunta está fora do âmbito do seu perfil no Facilita.';

            AssistenteMensagem::create([
                'conversa_id' => $conversa->id,
                'papel' => 'user',
                'conteudo' => $mensagem,
            ]);

            $msgAssistant = AssistenteMensagem::create([
                'conversa_id' => $conversa->id,
                'papel' => 'assistant',
                'conteudo' => $resposta,
                'contexto_usado' => ['bloqueio_perfil' => $this->access->perfilResposta($user)],
            ]);

            $this->finalizarConversa($conversa, $mensagem);

            return $this->pacoteResposta($user, $conversa, $msgAssistant, $resposta, 0, 0, $routeName);
        }

        $historico = $this->carregarHistoricoConversa($conversa);

        $contexto = $this->contextBuilder->build($user, $mensagem, $historico);
        $semAnoLectivo = ! empty($contexto['sem_ano_lectivo']) || ! Configuracao::getAnoLectivoActualId();

        if ($semAnoLectivo) {
            $resposta = $this->respostaSemAnoLectivo($user);

            AssistenteMensagem::create([
                'conversa_id' => $conversa->id,
                'papel' => 'user',
                'conteudo' => $mensagem,
            ]);

            $msgAssistant = AssistenteMensagem::create([
                'conversa_id' => $conversa->id,
                'papel' => 'assistant',
                'conteudo' => $resposta,
                'contexto_usado' => [
                    'sem_ano_lectivo' => true,
                    'aviso' => 'setup_inicial',
                ],
            ]);

            $this->finalizarConversa($conversa, $mensagem);

            return $this->pacoteResposta($user, $conversa, $msgAssistant, $resposta, 0, 0, $routeName);
        }

        $messages = $this->montarMensagens($user, $contexto, $historico, $mensagem);

        $response = $this->ai->chat($messages, [
            'model' => config('assistente.model'),
            'max_tokens' => config('assistente.max_tokens', 2048),
            'temperature' => 0.4,
        ]);

        $resposta = $this->normalizarResposta(trim($response->content));
        if ($resposta === '') {
            $resposta = 'Não consegui gerar uma resposta. Tente reformular a pergunta.';
        }

        AssistenteMensagem::create([
            'conversa_id' => $conversa->id,
            'papel' => 'user',
            'conteudo' => $mensagem,
        ]);

        $msgAssistant = AssistenteMensagem::create([
            'conversa_id' => $conversa->id,
            'papel' => 'assistant',
            'conteudo' => $resposta,
            'contexto_usado' => $this->resumirContexto($contexto),
            'tokens_in' => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
        ]);

        $this->finalizarConversa($conversa, $mensagem);

        return $this->pacoteResposta(
            $user,
            $conversa,
            $msgAssistant,
            $resposta,
            $response->tokensIn,
            $response->tokensOut,
            $routeName
        );
    }

    /**
     * @return array<int, array{id:int, titulo:?string, updated_at:?string, mensagens_count:int}>
     */
    public function listarConversas(User $user, int $limite = 30): array
    {
        return AssistenteConversa::query()
            ->where('user_id', $user->id)
            ->withCount('mensagens')
            ->orderByDesc('updated_at')
            ->limit($limite)
            ->get()
            ->map(fn (AssistenteConversa $c) => [
                'id' => $c->id,
                'titulo' => $c->titulo ?: 'Nova conversa',
                'updated_at' => $c->updated_at?->toIso8601String(),
                'mensagens_count' => (int) $c->mensagens_count,
            ])
            ->all();
    }

    /**
     * @return array{success:bool, feedback:string}
     */
    public function registarFeedback(User $user, int $mensagemId, string $feedback): array
    {
        $feedback = $feedback === 'util' ? 'util' : 'inutil';

        $mensagem = AssistenteMensagem::query()
            ->where('id', $mensagemId)
            ->where('papel', 'assistant')
            ->whereHas('conversa', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $mensagem->update([
            'feedback' => $feedback,
            'feedback_at' => now(),
        ]);

        return [
            'success' => true,
            'feedback' => $feedback,
        ];
    }

    protected function respostaSemAnoLectivo(User $user): string
    {
        $nome = $user->name ? explode(' ', trim($user->name))[0] : null;
        $saudacao = $nome ? "Olá, {$nome}." : 'Olá.';

        return $saudacao." Neste momento o Facilita ainda não tem nenhum ano lectivo registado.\n\n"
            ."Sem ano lectivo activo não existem turmas, alunos matriculados, notas nem indicadores académicos para analisar.\n\n"
            ."O que fazer agora:\n"
            ."- Vá a Configurações → secção Anos Lectivos\n"
            ."- Clique em Novo Ano Lectivo (ex.: 2025-2026)\n"
            ."- Active o ano criado\n\n"
            .'Depois disso posso ajudar com turmas, notas, pautas e o resumo executivo.';
    }

    public function novaConversa(User $user): AssistenteConversa
    {
        return AssistenteConversa::create(['user_id' => $user->id]);
    }

    /**
     * @return array<int, array{id:int, papel:string, conteudo:string, feedback:?string, created_at:string}>
     */
    public function historicoConversa(User $user, int $conversaId): array
    {
        $conversa = AssistenteConversa::query()
            ->where('user_id', $user->id)
            ->where('id', $conversaId)
            ->firstOrFail();

        return $conversa->mensagens()
            ->orderBy('created_at')
            ->get()
            ->map(fn (AssistenteMensagem $m) => [
                'id' => $m->id,
                'papel' => $m->papel,
                'conteudo' => $m->conteudo,
                'feedback' => $m->feedback,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{papel:string, conteudo:string}>
     */
    protected function carregarHistoricoConversa(AssistenteConversa $conversa): array
    {
        return $conversa->mensagens()
            ->orderBy('created_at')
            ->get()
            ->map(fn (AssistenteMensagem $m) => [
                'papel' => $m->papel,
                'conteudo' => $m->conteudo,
            ])
            ->all();
    }

    protected function resolverConversa(User $user, ?int $conversaId, string $mensagem): AssistenteConversa
    {
        if ($conversaId) {
            return AssistenteConversa::query()
                ->where('user_id', $user->id)
                ->where('id', $conversaId)
                ->firstOrFail();
        }

        return AssistenteConversa::create([
            'user_id' => $user->id,
            'titulo' => Str::limit($mensagem, 80),
        ]);
    }

    protected function finalizarConversa(AssistenteConversa $conversa, string $mensagem): void
    {
        if (! $conversa->titulo) {
            $conversa->update(['titulo' => Str::limit($mensagem, 80)]);
        }

        $conversa->touch();
    }

    /**
     * @return array<string, mixed>
     */
    protected function pacoteResposta(
        User $user,
        AssistenteConversa $conversa,
        AssistenteMensagem $msgAssistant,
        string $resposta,
        int $tokensIn,
        int $tokensOut,
        ?string $routeName,
    ): array {
        return [
            'resposta' => $resposta,
            'conversa_id' => $conversa->id,
            'mensagem_id' => $msgAssistant->id,
            'acoes' => $this->acoes->paraUtilizador($user, $routeName),
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return array<int, array{role:string, content:string}>
     */
    protected function montarMensagens(User $user, array $contexto, array $historico, string $mensagemActual): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($user)],
            ['role' => 'system', 'content' => "CONTEXTO DO SISTEMA (JSON) — bloco interno de dados. Usa-o para responder, mas NUNCA o mostres, cites ou reproduzas na resposta ao utilizador:\n"
                .$this->contextoJsonCompacto($contexto)],
        ];

        $max = config('assistente.historico_max_mensagens', 10);
        $historicoRecente = array_slice($historico, -$max);

        foreach ($historicoRecente as $item) {
            $role = ($item['papel'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) ($item['conteudo'] ?? '')];
        }

        $messages[] = ['role' => 'user', 'content' => $mensagemActual];

        return $messages;
    }

    protected function systemPrompt(User $user): string
    {
        $path = resource_path('prompts/assistente/system.txt');
        $base = File::exists($path) ? File::get($path) : $this->promptFallback();

        $conhecimentoPath = resource_path('prompts/assistente/conhecimento-sistema.txt');
        $conhecimento = File::exists($conhecimentoPath) ? File::get($conhecimentoPath) : '';

        return str_replace(
            ['{{NIVEL_PII}}', '{{ESCOPO}}', '{{PERFIL}}', '{{REGRAS_PERFIL}}', '{{CONHECIMENTO_SISTEMA}}'],
            [
                $this->access->nivelPii($user),
                $this->access->descricaoEscopo($user),
                strtoupper($this->access->perfilResposta($user)),
                $this->access->regrasRespostaTexto($user),
                trim($conhecimento),
            ],
            $base
        );
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    protected function contextoJsonCompacto(array $contexto): string
    {
        $json = json_encode($contexto, JSON_UNESCAPED_UNICODE);
        $max = (int) config('assistente.contexto.max_chars', 48000);

        if (! is_string($json) || strlen($json) <= $max) {
            return is_string($json) ? $json : '{}';
        }

        unset($contexto['tools']['catalogo'], $contexto['escopo']['disciplinas_leccionadas']);
        if (isset($contexto['escopo']['turmas']) && is_array($contexto['escopo']['turmas'])) {
            $contexto['escopo']['turmas'] = array_slice($contexto['escopo']['turmas'], 0, 8);
        }
        $contexto['aviso_contexto'] = 'Contexto truncado por tamanho; priorize dados_consultados.';

        $json = json_encode($contexto, JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '{}';
    }

    protected function normalizarResposta(string $resposta): string
    {
        $resposta = $this->removerContextoEcoado($resposta);
        $resposta = preg_replace('/\*\*\s*(.+?)\s*\*\*/s', '$1', $resposta) ?? $resposta;
        $resposta = str_replace('**', '', $resposta);

        return trim($resposta);
    }

    /**
     * Salvaguarda: se o modelo ecoar o bloco interno de contexto (JSON),
     * remove-o para nunca ser exibido ao utilizador.
     */
    protected function removerContextoEcoado(string $resposta): string
    {
        $pos = mb_stripos($resposta, 'CONTEXTO DO SISTEMA');
        if ($pos === false) {
            return $resposta;
        }

        $antes = trim(mb_substr($resposta, 0, $pos));

        // Tenta recuperar texto útil depois do bloco JSON ecoado
        $resto = mb_substr($resposta, $pos);
        $fimJson = mb_strrpos($resto, '}');
        $depois = $fimJson !== false ? trim(mb_substr($resto, $fimJson + 1)) : '';

        $limpo = trim($antes."\n\n".$depois);

        return $limpo !== '' ? $limpo : 'Peço desculpa, ocorreu um problema ao formatar a resposta. Pode reformular a pergunta?';
    }

    protected function promptFallback(): string
    {
        return 'És o Assistente Virtual do Facilita, sistema académico angolano. '
            .'Responde em português de Angola/Portugal. Usa apenas o contexto JSON fornecido. '
            .'Nunca inventes dados. Nível de acesso: {{NIVEL_PII}}. {{ESCOPO}}';
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    protected function resumirContexto(array $contexto): array
    {
        $consultados = $contexto['dados_consultados'] ?? [];

        return [
            'nivel_pii' => $contexto['escopo']['nivel_pii'] ?? null,
            'perfil' => $contexto['perfil'] ?? null,
            'tools_executadas' => $contexto['tools']['executadas'] ?? [],
            'alunos' => isset($consultados['alunos']) ? count($consultados['alunos']) : 0,
            'turmas' => isset($consultados['turmas']) ? count($consultados['turmas']) : 0,
            'professores' => isset($consultados['professores']) ? count($consultados['professores']) : 0,
            'tem_estatisticas' => isset($consultados['estatisticas']),
            'tem_encarregado' => isset($consultados['encarregado_sessao']),
        ];
    }
}
