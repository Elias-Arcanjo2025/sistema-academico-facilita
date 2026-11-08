<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Configuração inicial da escola</h2>
    </x-slot>

    <div class="content-card">
        <div class="content-card-body">
            <p class="text-muted mb-3">
                Complete estes passos para o Facilita ficar operacional.
                Progresso: <strong>{{ $concluidos }}/{{ $total }}</strong>
            </p>

            <div class="progress mb-4" style="height: .5rem;">
                <div class="progress-bar" role="progressbar"
                     style="width: {{ $total > 0 ? round(($concluidos / $total) * 100) : 0 }}%"
                     aria-valuenow="{{ $concluidos }}" aria-valuemin="0" aria-valuemax="{{ $total }}"></div>
            </div>

            <div class="list-group list-group-flush">
                @foreach($passos as $i => $passo)
                    <div class="list-group-item px-0 d-flex gap-3 align-items-start">
                        <div class="pt-1">
                            @if($passo['feito'])
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            @else
                                <span class="badge rounded-pill text-bg-secondary">{{ $i + 1 }}</span>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $passo['titulo'] }}</div>
                            <div class="small text-muted mb-2">{{ $passo['descricao'] }}</div>
                            @unless($passo['feito'])
                                <a href="{{ $passo['url'] }}" class="btn btn-sm btn-primary">{{ $passo['cta'] }}</a>
                            @else
                                <a href="{{ $passo['url'] }}" class="btn btn-sm btn-outline-secondary">Rever</a>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
