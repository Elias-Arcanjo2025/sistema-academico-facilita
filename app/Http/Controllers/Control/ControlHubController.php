<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\ControlAluno;
use App\Models\Control\ControlCobranca;
use App\Models\Control\ControlEscola;
use App\Services\Control\ControlHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ControlHubController extends Controller
{
    public function __construct(
        private readonly ControlHubService $hubService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureHub();

        $escolas = ControlEscola::query()
            ->withCount([
                'alunos as alunos_activos_count' => fn ($q) => $q->where('activo', true),
            ])
            ->orderBy('nome')
            ->get();

        $totais = [
            'escolas' => $escolas->count(),
            'activas' => $escolas->where('status', ControlEscola::STATUS_ACTIVO)->count(),
            'bloqueadas' => $escolas->where('status', ControlEscola::STATUS_BLOQUEADO)->count(),
            'alunos' => $escolas->sum('total_alunos'),
            'online' => $escolas->filter->isOnline()->count(),
            'valor_estimado' => $escolas->sum(fn (ControlEscola $e) => $e->valorEstimado()),
            'valor_em_aberto' => (float) ControlCobranca::query()
                ->whereIn('status', [ControlCobranca::STATUS_ABERTA, ControlCobranca::STATUS_VENCIDA])
                ->sum('valor_total'),
            'valor_recebido' => (float) ControlCobranca::query()
                ->where('status', ControlCobranca::STATUS_PAGA)
                ->sum('valor_total'),
        ];

        return view('control.hub.index', compact('escolas', 'totais'));
    }

    public function create(): View
    {
        $this->ensureHub();

        return view('control.hub.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'app_url' => ['nullable', 'url', 'max:500'],
            'contacto_email' => ['nullable', 'email', 'max:255'],
            'contacto_telefone' => ['nullable', 'string', 'max:50'],
            'preco_anual_aluno' => ['nullable', 'numeric', 'min:0'],
            'notas_internas' => ['nullable', 'string', 'max:2000'],
            'installation_id' => ['nullable', 'uuid'],
        ]);

        $secret = Str::random(48);
        $escola = $this->hubService->createEscola($data, $secret, $request->user());

        return redirect()
            ->route('control.hub.escolas.show', $escola)
            ->with('success', 'Escola registada.')
            ->with('control_secret_once', $secret);
    }

    public function show(ControlEscola $escola): View
    {
        $this->ensureHub();

        $escola->loadCount([
            'alunos as alunos_activos_count' => fn ($q) => $q->where('activo', true),
        ]);

        $alunos = ControlAluno::query()
            ->where('control_escola_id', $escola->id)
            ->where('activo', true)
            ->orderBy('nome')
            ->paginate(50);

        $eventos = $escola->eventos()
            ->with('user')
            ->latest()
            ->limit(30)
            ->get();

        $cobrancas = $escola->cobrancas()
            ->with(['criadoPor', 'pagoPor'])
            ->latest()
            ->limit(20)
            ->get();

        $cobrancaAberta = $escola->cobrancaAberta();

        return view('control.hub.show', compact('escola', 'alunos', 'eventos', 'cobrancas', 'cobrancaAberta'));
    }

    public function update(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'app_url' => ['nullable', 'url', 'max:500'],
            'contacto_email' => ['nullable', 'email', 'max:255'],
            'contacto_telefone' => ['nullable', 'string', 'max:50'],
            'preco_anual_aluno' => ['nullable', 'numeric', 'min:0'],
            'minutos_ate_aviso' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'minutos_ate_atraso' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'minutos_ate_bloqueio' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'notas_internas' => ['nullable', 'string', 'max:2000'],
        ]);

        $escola->update($data);

        return back()->with('success', 'Dados da escola actualizados.');
    }

    public function bloquear(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->hubService->setStatus(
            $escola,
            ControlEscola::STATUS_BLOQUEADO,
            $data['motivo'],
            $request->user()
        );

        return back()->with('success', 'Escola bloqueada. A instalação remota será suspensa em até cerca de 1 minuto (ou no próximo sync).');
    }

    public function desbloquear(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $this->hubService->setStatus(
            $escola,
            ControlEscola::STATUS_ACTIVO,
            'Desbloqueio manual',
            $request->user()
        );

        return back()->with('success', 'Escola desbloqueada.');
    }

    public function marcarCobranca(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $this->hubService->setStatus(
            $escola,
            ControlEscola::STATUS_EM_COBRANCA,
            'Período de cobrança iniciado',
            $request->user()
        );

        return back()->with('success', 'Estado actualizado para em cobrança.');
    }

    public function criarCobranca(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'ano_lectivo' => ['nullable', 'string', 'max:32'],
            'total_alunos' => ['nullable', 'integer', 'min:0'],
            'preco_unitario' => ['nullable', 'numeric', 'min:0'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'vencimento_em' => ['nullable', 'date'],
            'minutos_ate_aviso' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'minutos_ate_atraso' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'minutos_ate_bloqueio' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'aviso_escola' => ['nullable', 'string', 'max:500'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $cobranca = $this->hubService->criarCobranca($escola, $data, $request->user());

        $msg = 'Cobrança '.$cobranca->referencia.' criada ('.number_format((float) $cobranca->valor_total, 2, ',', '.').').';
        if ($cobranca->aviso_em && $cobranca->aviso_em->isFuture()) {
            $msg .= ' Aviso na escola a partir de '.$cobranca->aviso_em->format('d/m/Y H:i').'.';
        } else {
            $msg .= ' A escola verá o aviso em até ~1 minuto.';
        }
        if ($cobranca->atraso_em) {
            $msg .= ' Atraso: '.$cobranca->atraso_em->format('d/m/Y H:i').'.';
        }
        if ($cobranca->bloqueio_em) {
            $msg .= ' Bloqueio: '.$cobranca->bloqueio_em->format('d/m/Y H:i').'.';
        }

        return back()->with('success', $msg);
    }

    public function pagarCobranca(Request $request, ControlCobranca $cobranca): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'metodo_pagamento' => ['nullable', 'string', 'max:100'],
            'referencia_pagamento' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->hubService->marcarCobrancaPaga($cobranca, $data, $request->user());

        return back()->with('success', 'Pagamento registado. Escola reactivada.');
    }

    public function cancelarCobranca(Request $request, ControlCobranca $cobranca): RedirectResponse
    {
        $this->ensureHub();

        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->hubService->cancelarCobranca($cobranca, $data['motivo'] ?? null, $request->user());

        return back()->with('success', 'Cobrança cancelada.');
    }

    public function marcarAtraso(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $this->hubService->setStatus(
            $escola,
            ControlEscola::STATUS_EM_ATRASO,
            'Pagamento em atraso',
            $request->user()
        );

        return back()->with('success', 'Estado actualizado para em atraso.');
    }

    public function regenerarSecret(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $secret = Str::random(48);
        $this->hubService->rotateSecret($escola, $secret, $request->user());

        return back()
            ->with('success', 'Novo secret gerado. Copie agora — não será mostrado novamente.')
            ->with('control_secret_once', $secret);
    }

    public function pedirSync(Request $request, ControlEscola $escola): RedirectResponse
    {
        $this->ensureHub();

        $result = $this->hubService->pedirSyncEscola($escola, $request->user());

        return back()->with(
            $result['pushed'] ? 'success' : 'warning',
            $result['message']
        );
    }

    private function ensureHub(): void
    {
        abort_unless(config('control.mode') === 'hub', 404);
        abort_unless(auth()->user()?->hasRole('admin_full'), 403);
    }
}
