@can('assistente.usar')
@php
    $routeActual = optional(request()->route())->getName();
    $sugestoesSvc = app(\App\Services\Assistente\AssistantSugestoesService::class);
    $acoesSvc = app(\App\Services\Assistente\AssistantAcoesService::class);
    $user = auth()->user();
    $sugestoesIniciais = array_slice($sugestoesSvc->paraUtilizador($user, $routeActual), 0, 4);
    $acoesIniciais = array_slice($acoesSvc->paraUtilizador($user, $routeActual), 0, 3);
    $rotaHistorico = str_replace('999991', '__CID__', route('assistente.conversa.historico', ['conversa' => 999991]));
    $rotaFeedback = str_replace('999992', '__MID__', route('assistente.feedback', ['mensagem' => 999992]));
    $storageKey = 'facilita_assistente_conversa_'.$user->id;
    $rotaMensagem = route('assistente.mensagem');
    $rotaNovaConversa = route('assistente.conversa.nova');
    $rotaConversas = route('assistente.conversas');
    $rotaMeta = route('assistente.meta');
@endphp
@once
@push('styles')
<style>
    /* Overrides locais mínimos — layout principal em custom.css */
    [x-cloak] { display: none !important; }
</style>
@endpush
@endonce

<script>
    window.facilitaAssistente = {
        routes: {
            mensagem: @json($rotaMensagem),
            novaConversa: @json($rotaNovaConversa),
            conversas: @json($rotaConversas),
            historico: @json($rotaHistorico),
            feedback: @json($rotaFeedback),
            meta: @json($rotaMeta),
        },
        routeName: @json($routeActual),
        sugestoes: @json($sugestoesIniciais),
        acoes: @json($acoesIniciais),
        storageKey: @json($storageKey),
    };
</script>

<div
    id="assistente-facilita"
    class="assistente-root"
    x-data="assistenteFacilita"
    x-init="init()"
    x-cloak
    :class="{ 'is-open': aberto, 'is-fullscreen-mobile': fullscreenMobile }"
>
    <div
        class="assistente-panel"
        :class="{ 'is-fullscreen-mobile': fullscreenMobile }"
        x-show="aberto"
        @click.outside="if (aberto && !fullscreenMobile && !mostrarHistorico) aberto = false"
    >
        <div class="assistente-header">
            <div class="assistente-header-info">
                <i class="bi bi-stars"></i>
                <div>
                    <strong>Assistente Facilita</strong>
                    <small>IA · dados do sistema</small>
                </div>
            </div>
            <div class="assistente-header-actions">
                <button type="button" class="assistente-btn-icon" @click="toggleHistorico()" title="Histórico" :class="{ 'is-on': mostrarHistorico }" :disabled="aEnviar">
                    <i class="bi bi-clock-history"></i>
                </button>
                <button type="button" class="assistente-btn-icon" @click="novaConversa()" title="Nova conversa" :disabled="aEnviar">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button type="button" class="assistente-btn-icon d-md-none" @click="fullscreenMobile = !fullscreenMobile" title="Expandir">
                    <i class="bi" :class="fullscreenMobile ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"></i>
                </button>
                <button type="button" class="assistente-btn-icon" @click="aberto = false; mostrarHistorico = false" title="Fechar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="assistente-history" x-show="mostrarHistorico" x-cloak>
            <div class="assistente-history-title">Conversas recentes</div>
            <template x-if="conversas.length === 0">
                <div class="small text-muted px-1 py-1">Ainda não há conversas guardadas.</div>
            </template>
            <template x-for="c in conversas" :key="c.id">
                <button
                    type="button"
                    class="assistente-history-item"
                    :class="{ 'is-active': conversaId === c.id }"
                    @click="abrirConversa(c.id)"
                    x-text="c.titulo"
                ></button>
            </template>
        </div>

        <div class="assistente-messages" x-ref="messages" role="log" aria-live="polite">
            <div class="assistente-welcome" x-show="mensagens.length === 0 && !aEnviar && !mostrarHistorico">
                <div class="assistente-welcome-hero">
                    <strong>Olá!</strong>
                    @if(auth()->user()?->hasRole('aluno'))
                    <p>Posso ajudar com as suas notas, turma e o portal — com base nos seus dados no Facilita.</p>
                    @elseif(auth()->user()?->hasRole('encarregado'))
                    <p>Posso ajudar com os educandos associados à sua conta e com o portal do encarregado.</p>
                    @else
                    <p>Posso ajudar com alunos, turmas, notas e funcionalidades no seu âmbito no Facilita.</p>
                    @endif
                </div>

                <div class="assistente-welcome-label">Perguntas sugeridas</div>
                <div class="assistente-sugestoes">
                    <template x-for="(s, i) in sugestoes" :key="'s'+i">
                        <button type="button" @click="usarSugestao(s.texto)" x-text="s.rotulo"></button>
                    </template>
                </div>

                <template x-if="acoes.length">
                    <div>
                        <div class="assistente-welcome-label">Atalhos</div>
                        <div class="assistente-acoes">
                            <template x-for="(a, i) in acoes" :key="'a'+i">
                                <a class="assistente-acao-chip" :href="a.url">
                                    <i class="bi" :class="a.icon || 'bi-link-45deg'"></i>
                                    <span x-text="a.label"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <template x-for="(msg, idx) in mensagens" :key="msg.id || ('m'+idx)">
                <div class="assistente-msg" :class="'assistente-msg--' + msg.papel">
                    <div class="assistente-msg-avatar" x-show="msg.papel === 'assistant'">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <div class="assistente-msg-bubble" x-html="formatar(msg.conteudo)"></div>
                        <div class="assistente-msg-meta" x-show="msg.papel === 'assistant'">
                            <template x-if="msg.retryable">
                                <button type="button" class="assistente-retry" @click="retryUltima()" :disabled="aEnviar">
                                    Tentar novamente
                                </button>
                            </template>
                            <template x-if="msg.id && !msg.retryable">
                                <span class="d-inline-flex gap-1">
                                    <button type="button" class="assistente-fb" :class="{ 'is-active': msg.feedback === 'util' }" @click="enviarFeedback(msg, 'util')" title="Útil">👍</button>
                                    <button type="button" class="assistente-fb" :class="{ 'is-active': msg.feedback === 'inutil' }" @click="enviarFeedback(msg, 'inutil')" title="Pouco útil">👎</button>
                                </span>
                            </template>
                            <template x-if="msg.acoes && msg.acoes.length">
                                <span class="assistente-acoes">
                                    <template x-for="(a, ai) in msg.acoes" :key="'ma'+idx+'-'+ai">
                                        <a class="assistente-acao-chip" :href="a.url" x-text="a.label"></a>
                                    </template>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <div class="assistente-msg assistente-msg--assistant" x-show="aEnviar">
                <div class="assistente-msg-avatar"><i class="bi bi-stars"></i></div>
                <div class="assistente-msg-bubble assistente-typing">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>

        <form class="assistente-input" @submit.prevent="enviar()">
            <textarea
                x-ref="input"
                x-model="texto"
                rows="1"
                placeholder="Escreva a sua pergunta..."
                :disabled="aEnviar"
                @keydown.enter.prevent="if (!$event.shiftKey) enviar()"
                maxlength="4000"
                aria-label="Mensagem para o assistente"
            ></textarea>
            <button type="submit" class="assistente-send" :disabled="aEnviar || !texto.trim()" title="Enviar">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>

    <button
        type="button"
        class="assistente-fab"
        x-show="!aberto"
        @click="toggle()"
        title="Assistente Facilita"
        aria-label="Abrir assistente virtual"
    >
        <i class="bi bi-chat-dots-fill"></i>
    </button>
</div>
@endcan
