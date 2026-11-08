<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Planos de Aula</h2>
            @can('pedagogico.ia.gerar')
            <form method="POST" action="{{ route('pedagogico.planos.gerar', $planificacao) }}" id="formGerarPlano" class="d-flex flex-wrap align-items-center gap-2">
                @csrf
                @if($planificacao->quinzenas->isNotEmpty())
                <select name="quinzena_id" class="form-select form-select-sm w-auto" id="selQuinzena">
                    @foreach($planificacao->quinzenas as $q)
                        <option value="{{ $q->id }}">Q{{ $q->numero }} — {{ Str::limit($q->tema, 40) }}</option>
                    @endforeach
                </select>
                @endif
                @if($planificacao->aulas->isNotEmpty())
                <select name="aula_id" class="form-select form-select-sm w-auto" id="selAula">
                    <option value="">— Aula do calendário (opcional) —</option>
                    @foreach($planificacao->aulas->sortBy('data') as $aula)
                        <option value="{{ $aula->id }}" data-tempos="{{ $aula->tempos ?? 1 }}" data-quinzena="{{ $aula->quinzena_id }}">
                            {{ $aula->data?->format('d/m') }} — {{ Str::limit($aula->tema_planeado ?? 'Sem tema', 35) }} ({{ $aula->tempos ?? 1 }}T)
                        </option>
                    @endforeach
                </select>
                @endif
                <select name="tempos" class="form-select form-select-sm w-auto" id="selTempos" required title="1 tempo = 45 min">
                    @foreach([1,2,3,4,5,6,7,8] as $t)
                        <option value="{{ $t }}">{{ $t }} tempo{{ $t > 1 ? 's' : '' }} ({{ $t * 45 }} min)</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm" id="btnGerarPlano">
                    <i class="bi bi-stars"></i> Gerar com IA
                </button>
            </form>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <x-tip-card icon="bi-journal-richtext" variant="info" title="Planos de aula" id="tip-planos-index" compact>
                Gere planos detalhados com IA para cada período/aula. Marque como «dado» quando for ministrado — isso alimenta a geração de provas.
            </x-tip-card>

            <div class="alert alert-info py-2 small">
                <i class="bi bi-clock me-1"></i>
                <strong>1 tempo = 45 minutos.</strong> O volume gerado escala com os tempos: mais tempos → mais texto a ditar, exercícios e tarefas.
                <span id="escalaPreview" class="d-block mt-1 fw-semibold"></span>
            </div>

            <div class="card">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Versão</th>
                            <th>Quinzena / Aula</th>
                            <th>Tempos</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($planificacao->planosAula as $plano)
                        <tr>
                            <td>v{{ $plano->versao }}</td>
                            <td>
                                {{ $plano->quinzena->tema ?? '-' }}
                                @if($plano->aula)
                                    <br><small class="text-muted">{{ $plano->aula->data?->format('d/m/Y') }}</small>
                                @endif
                            </td>
                            <td>{{ $plano->tempos ?? 1 }}T ({{ ($plano->tempos ?? 1) * 45 }} min)</td>
                            <td>
                                @php
                                    $badge = match($plano->estado) {
                                        'dado' => 'success',
                                        'aprovado' => 'primary',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $plano->estado }}</span>
                                @if($plano->ministrado_em)
                                    <br><small class="text-muted">{{ $plano->ministrado_em->format('d/m/Y H:i') }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pedagogico.planos.show', $plano) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                <a href="{{ route('pedagogico.planos.pdf', $plano) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">Sem planos gerados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    @php $escalaRef = \App\Services\Pedagogico\TempoAulaHelper::tabelaReferencia(); @endphp
    <script>
        const escalaRef = @json($escalaRef);

        function actualizarEscalaPreview() {
            const sel = document.getElementById('selTempos');
            const el = document.getElementById('escalaPreview');
            if (!sel || !el) return;
            const e = escalaRef[sel.value];
            if (!e) return;
            el.textContent = 'Alvo para ' + e.tempos + 'T (' + e.minutos + ' min): ≈' + e.palavras + ' palavras, conceitos e exercícios proporcionais.';
        }

        document.getElementById('formGerarPlano')?.addEventListener('submit', function () {
            const btn = document.getElementById('btnGerarPlano');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A gerar…';
        });

        const selAula = document.getElementById('selAula');
        const selTempos = document.getElementById('selTempos');
        const selQuinzena = document.getElementById('selQuinzena');

        selAula?.addEventListener('change', function () {
            const opt = this.selectedOptions[0];
            if (!opt || !opt.value) return;
            if (opt.dataset.tempos) selTempos.value = opt.dataset.tempos;
            if (opt.dataset.quinzena && selQuinzena) selQuinzena.value = opt.dataset.quinzena;
            actualizarEscalaPreview();
        });

        selTempos?.addEventListener('change', actualizarEscalaPreview);
        actualizarEscalaPreview();
    </script>
    @endpush
</x-app-layout>
