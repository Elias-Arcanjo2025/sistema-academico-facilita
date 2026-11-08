<x-guest-layout>
    <h5 class="text-center mb-3 fw-semibold" style="color: var(--text-dark);">
        Verificar e-mail
    </h5>

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center mb-3"
             style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 50%;">
            <i class="bi bi-envelope-check" style="font-size: 2rem; color: var(--primary-color);"></i>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Obrigado por se registar! Antes de começar, verifique o seu e-mail
            clicando no link que acabámos de enviar.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            Um novo link de verificação foi enviado para o seu e-mail.
        </div>
    @endif

    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('verification.send') }}" class="flex-grow-1">
            @csrf
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-arrow-repeat me-2"></i>Reenviar e-mail
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</x-guest-layout>
