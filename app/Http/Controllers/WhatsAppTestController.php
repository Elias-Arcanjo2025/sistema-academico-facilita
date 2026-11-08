<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Encarregado;
use App\Models\WhatsappMensagem;
use App\Services\WhatsApp\WhatsAppBotService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppTestController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppBotService $bot,
    ) {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->temControloAcademicoInstitucional()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $mensagens = WhatsappMensagem::query()
            ->with('encarregado')
            ->latest('id')
            ->limit(40)
            ->get();

        $encarregados = Encarregado::query()
            ->where('activo', true)
            ->where('whatsapp_opt_in', true)
            ->whereNotNull('telefone')
            ->orderBy('nome')
            ->limit(100)
            ->get(['id', 'nome', 'telefone']);

        return view('whatsapp.index', [
            'mensagens' => $mensagens,
            'encarregados' => $encarregados,
            'enabled' => $this->whatsapp->enabled(),
            'provider' => config('whatsapp.provider'),
            'botEnabled' => (bool) config('whatsapp.bot_enabled'),
            'webhookUrl' => url('/webhooks/whatsapp'),
            'verifyTokenConfigurado' => filled(config('whatsapp.verify_token')),
            'diagnostico' => $this->whatsapp->diagnostico(),
            'metaPronto' => $this->whatsapp->isMeta() && $this->whatsapp->diagnostico()['ok'],
        ]);
    }

    public function testarLigacao(): RedirectResponse
    {
        $resultado = $this->whatsapp->testarLigacao();

        return back()->with(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['message']
        );
    }

    public function simularEntrada(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $this->bot->handleInbound($data['from'], $data['text']);

        return back()->with('success', 'Mensagem de entrada simulada. Veja o histórico abaixo.');
    }

    public function enviar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'telefone' => ['required', 'string', 'max:40'],
            'corpo' => ['required', 'string', 'max:1000'],
            'encarregado_id' => ['nullable', 'integer', 'exists:encarregados,id'],
        ]);

        if ($this->whatsapp->isMeta()) {
            $diag = $this->whatsapp->diagnostico();
            if (! $diag['ok']) {
                return back()->with('error', $diag['message']);
            }
        }

        $encarregado = isset($data['encarregado_id'])
            ? Encarregado::query()->find($data['encarregado_id'])
            : null;

        $msg = $this->whatsapp->sendText($data['telefone'], $data['corpo'], $encarregado);

        if ($msg->status === 'failed') {
            return back()->with('error', 'Falha no envio: '.($msg->erro ?: 'erro desconhecido'));
        }

        $modo = config('whatsapp.provider') === 'meta' ? 'REAL (Meta)' : 'log';

        return back()->with(
            'success',
            "Mensagem enviada em modo {$modo} — estado: {$msg->status}"
            .($msg->provider_message_id ? " · id: {$msg->provider_message_id}" : '')
        );
    }

    public function template(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'encarregado_id' => ['required', 'integer', 'exists:encarregados,id'],
            'template' => ['required', 'string', 'in:mac_em_falta,prova_amanha,boletim_disponivel,cobranca_facilita,falta_registada,ocorrencia_escolar,resumo_semanal'],
            'aluno' => ['nullable', 'string', 'max:120'],
        ]);

        $encarregado = Encarregado::query()->findOrFail($data['encarregado_id']);
        $msg = $this->whatsapp->notifyEncarregado(
            $encarregado,
            $data['template'],
            ['aluno' => $data['aluno'] ?: 'o seu educando']
        );

        if (! $msg) {
            return back()->with('error', 'Encarregado sem opt-in WhatsApp ou sem telefone.');
        }

        if ($msg->status === 'failed') {
            return back()->with('error', 'Falha no template: '.($msg->erro ?: 'erro desconhecido'));
        }

        return back()->with('success', 'Template «'.$data['template'].'» enviado (estado: '.$msg->status.').');
    }

    public function criarDemo(Request $request): RedirectResponse
    {
        $aluno = Aluno::query()->where('activo', true)->orderBy('id')->first();
        if (! $aluno) {
            return back()->with('error', 'Não há alunos activos para ligar ao encarregado demo.');
        }

        $telefone = '244900000001';
        $encarregado = Encarregado::query()->firstOrCreate(
            ['telefone' => $telefone],
            [
                'nome' => 'Encarregado Demo WhatsApp',
                'email' => 'whatsapp.demo@facilita.local',
                'whatsapp_opt_in' => true,
                'whatsapp_opt_in_at' => now(),
                'activo' => true,
            ]
        );

        $encarregado->update([
            'whatsapp_opt_in' => true,
            'whatsapp_opt_in_at' => $encarregado->whatsapp_opt_in_at ?? now(),
            'activo' => true,
        ]);

        if (! $encarregado->alunos()->where('alunos.id', $aluno->id)->exists()) {
            $encarregado->alunos()->attach($aluno->id, [
                'parentesco' => 'encarregado',
                'principal' => true,
                'pode_ver_notas' => true,
                'pode_assinar_boletim' => true,
            ]);
        }

        return back()->with(
            'success',
            "Encarregado demo pronto. Telefone: {$telefone}. Aluno: {$aluno->nome}. Simule «ajuda» ou «notas»."
        );
    }
}
