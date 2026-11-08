@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-whatsapp text-success me-2"></i>WhatsApp — Teste</h1>
            <p class="text-muted mb-0">
                Provider: <strong>{{ $provider }}</strong>
                · Canal: {{ $enabled ? 'activo' : 'desligado' }}
                · Bot: {{ $botEnabled ? 'activo' : 'desligado' }}
            </p>
        </div>
        <form method="POST" action="{{ route('whatsapp.demo') }}">
            @csrf
            <button class="btn btn-outline-success btn-sm">
                <i class="bi bi-person-plus me-1"></i>Criar encarregado demo
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if(! $enabled)
        <div class="alert alert-warning">
            WhatsApp está desligado. No <code>.env</code> defina <code>WHATSAPP_ENABLED=true</code> e execute
            <code>php artisan config:clear</code>.
        </div>
    @elseif($provider === 'log')
        <div class="alert alert-warning border-0">
            <strong>Ainda em modo log</strong> — as mensagens <em>não</em> chegam ao telemóvel.
            Para envio real, configure a Meta Cloud API no <code>.env</code> (ver caixa abaixo) e mude
            <code>WHATSAPP_PROVIDER=meta</code>.
        </div>
        <div class="border rounded-3 p-3 mb-4 bg-white">
            <h2 class="h6">Activar envio real (Meta Cloud API)</h2>
            <ol class="small mb-3">
                <li>Crie uma app em <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers</a> → produto <strong>WhatsApp</strong>.</li>
                <li>Em <strong>API Setup</strong>, copie o <strong>Phone number ID</strong> e o <strong>Temporary access token</strong> (ou token permanente).</li>
                <li>Em <strong>To</strong>, adicione o número do telemóvel de teste (com código do país, ex. 2449…).</li>
                <li>Cole no <code>.env</code>:
<pre class="bg-light p-2 rounded small mb-0">WHATSAPP_PROVIDER=meta
WHATSAPP_PHONE_NUMBER_ID=seu_phone_number_id
WHATSAPP_ACCESS_TOKEN=seu_token
WHATSAPP_APP_SECRET=seu_app_secret</pre>
                </li>
                <li>Execute <code>php artisan config:clear</code>, volte aqui e clique em <strong>Testar ligação Meta</strong>.</li>
            </ol>
            <p class="small text-muted mb-0">
                Nota: em contas de teste só pode enviar para números na lista «To». Texto livre só funciona
                se o destinatário lhe escreveu nas últimas 24h; caso contrário use um template aprovado.
            </p>
        </div>
    @elseif(!empty($diagnostico) && empty($diagnostico['ok']))
        <div class="alert alert-danger">
            {{ $diagnostico['message'] }}
            @if(!empty($diagnostico['missing']))
                <div class="small mt-1">Preencha no .env: {{ implode(', ', $diagnostico['missing']) }}</div>
            @endif
        </div>
    @elseif($provider === 'meta')
        <div class="alert alert-success border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><strong>Modo Meta activo</strong> — os envios vão para o telemóvel real.</span>
            <form method="POST" action="{{ route('whatsapp.testar') }}" class="m-0">
                @csrf
                <button class="btn btn-sm btn-outline-success">Testar ligação Meta</button>
            </form>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100 bg-white">
                <h2 class="h6 mb-3">1. Simular mensagem do encarregado (bot)</h2>
                <p class="small text-muted">O bot responde a: <em>ajuda</em>, <em>notas</em>, <em>horário</em>, <em>propina</em>.</p>
                <form method="POST" action="{{ route('whatsapp.simular') }}" class="vstack gap-2">
                    @csrf
                    <div>
                        <label class="form-label">Telefone (from)</label>
                        <input type="text" name="from" class="form-control" value="{{ old('from', '244900000001') }}" required>
                        <div class="form-text">Use o telefone do encarregado demo ou de um encarregado com opt-in.</div>
                    </div>
                    <div>
                        <label class="form-label">Texto</label>
                        <select name="text" class="form-select mb-2" id="waCmdSelect">
                            <option value="ajuda">ajuda</option>
                            <option value="notas">notas</option>
                            <option value="horário">horário</option>
                            <option value="propina">propina</option>
                            <option value="custom">outro…</option>
                        </select>
                        <input type="text" name="text_custom" id="waCmdCustom" class="form-control d-none" placeholder="Escreva o comando">
                    </div>
                    <button class="btn btn-primary">Simular entrada</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100 bg-white">
                <h2 class="h6 mb-3">2. Enviar mensagem / template</h2>
                <form method="POST" action="{{ route('whatsapp.enviar') }}" class="vstack gap-2 mb-4">
                    @csrf
                    <div>
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="{{ old('telefone', '244900000001') }}" required>
                    </div>
                    <div>
                        <label class="form-label">Mensagem</label>
                        <textarea name="corpo" class="form-control" rows="2" required>{{ old('corpo', 'Olá! Teste Facilita WhatsApp.') }}</textarea>
                    </div>
                    <button class="btn btn-outline-primary">Enviar texto</button>
                </form>

                <form method="POST" action="{{ route('whatsapp.template') }}" class="vstack gap-2">
                    @csrf
                    <div>
                        <label class="form-label">Encarregado (opt-in)</label>
                        <select name="encarregado_id" class="form-select" required>
                            <option value="">— seleccionar —</option>
                            @foreach($encarregados as $e)
                                <option value="{{ $e->id }}">{{ $e->nome }} ({{ $e->telefone }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Template</label>
                        <select name="template" class="form-select" required>
                            <option value="mac_em_falta">MAC em falta</option>
                            <option value="prova_amanha">Prova amanhã</option>
                            <option value="boletim_disponivel">Boletim disponível</option>
                            <option value="falta_registada">Falta registada</option>
                            <option value="ocorrencia_escolar">Ocorrência escolar</option>
                            <option value="resumo_semanal">Resumo semanal</option>
                            <option value="cobranca_facilita">Cobrança Facilita</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Nome do aluno (opcional)</label>
                        <input type="text" name="aluno" class="form-control" placeholder="ex.: Maria Silva">
                    </div>
                    <button class="btn btn-success">Enviar template</button>
                </form>
            </div>
        </div>
    </div>

    <div class="border rounded-3 p-3 bg-white mb-4">
        <h2 class="h6 mb-3">Histórico recente</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Dir.</th>
                        <th>Telefone</th>
                        <th>Encarregado</th>
                        <th>Estado</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mensagens as $m)
                        <tr>
                            <td class="small text-nowrap">{{ $m->created_at?->format('d/m H:i:s') }}</td>
                            <td>
                                <span class="badge text-bg-{{ $m->direction === 'in' ? 'info' : 'secondary' }}">
                                    {{ $m->direction === 'in' ? 'IN' : 'OUT' }}
                                </span>
                            </td>
                            <td class="small">{{ $m->telefone }}</td>
                            <td class="small">{{ $m->encarregado?->nome ?? '—' }}</td>
                            <td><span class="badge text-bg-light text-dark">{{ $m->status }}</span></td>
                            <td class="small" style="max-width:28rem;white-space:pre-wrap;">
                                {{ \Illuminate\Support\Str::limit($m->corpo, 280) }}
                                @if($m->erro)
                                    <div class="text-danger mt-1">{{ \Illuminate\Support\Str::limit($m->erro, 200) }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Ainda sem mensagens. Crie o demo e simule «ajuda».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="border rounded-3 p-3 bg-light small">
        <h2 class="h6">Webhook (Meta Cloud API)</h2>
        <p class="mb-1"><strong>URL:</strong> <code>{{ $webhookUrl }}</code></p>
        <p class="mb-1"><strong>Verify token:</strong>
            @if($verifyTokenConfigurado ?? false)
                <span class="badge text-bg-success">configurado no .env</span>
                <span class="text-muted">(valor oculto por segurança)</span>
            @else
                <span class="badge text-bg-warning">não configurado</span>
            @endif
        </p>
        <p class="mb-0 text-muted">
            Para telemóvel real: <code>WHATSAPP_PROVIDER=meta</code> + Phone Number ID + Access Token no .env,
            e um túnel HTTPS (ngrok) apontando para esta URL.
        </p>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var select = document.getElementById('waCmdSelect');
    var custom = document.getElementById('waCmdCustom');
    var form = select?.closest('form');
    if (!select || !form) return;

    function sync() {
        var isCustom = select.value === 'custom';
        custom.classList.toggle('d-none', !isCustom);
        if (isCustom) {
            custom.setAttribute('name', 'text');
            select.removeAttribute('name');
        } else {
            select.setAttribute('name', 'text');
            custom.removeAttribute('name');
        }
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
@endsection
