<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Personalizar boletim</h2></x-slot>
    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Boletim', 'url' => route('secretaria.boletim.configurar')],
                ['label' => 'Personalizar', 'url' => route('secretaria.boletim.configurar')],
            ]" />

            <x-tip-card icon="bi-file-earmark-richtext" variant="info" title="Modelos de boletim da escola" id="tip-secretaria-boletim" compact>
                Escolha o modelo visual e as cores da escola. Aplica-se a todos os boletins PDF (individual, portal do encarregado/aluno e emissão em lote).
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('secretaria.boletim.guardar') }}" id="formBoletimLayout">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-header"><strong>1. Escolher modelo</strong></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    @foreach($modelos as $chave => $modelo)
                                        <div class="col-md-4">
                                            <label class="boletim-modelo-card {{ $config['modelo'] === $chave ? 'is-selected' : '' }}">
                                                <input type="radio" name="modelo" value="{{ $chave }}" class="form-check-input modelo-radio"
                                                       @checked(old('modelo', $config['modelo']) === $chave) required>
                                                <div class="boletim-modelo-card__preview" style="background: {{ $modelo['preview'] }}"></div>
                                                <div class="boletim-modelo-card__body">
                                                    <strong>{{ $modelo['nome'] }}</strong>
                                                    <p class="small text-muted mb-0">{{ $modelo['descricao'] }}</p>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('modelo')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>2. Cores da escola</strong></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="cor_primaria">Cor primária</label>
                                        <input type="color" name="cor_primaria" id="cor_primaria" class="form-control form-control-color w-100 preview-field"
                                               value="{{ old('cor_primaria', $config['cor_primaria']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="cor_secundaria">Cor secundária</label>
                                        <input type="color" name="cor_secundaria" id="cor_secundaria" class="form-control form-control-color w-100 preview-field"
                                               value="{{ old('cor_secundaria', $config['cor_secundaria']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="cor_tabela">Cabeçalho da tabela</label>
                                        <input type="color" name="cor_tabela" id="cor_tabela" class="form-control form-control-color w-100 preview-field"
                                               value="{{ old('cor_tabela', $config['cor_tabela']) }}" required>
                                    </div>
                                </div>
                                <p class="small text-muted mt-3 mb-0">
                                    Primária: títulos e destaques. Secundária: modelo Institucional. Cabeçalho: fundo da tabela de notas.
                                </p>
                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                    <button type="submit" class="btn btn-primary">Guardar personalização</button>
                                    @can('boletins.ver')
                                        <a href="{{ route('relatorios.boletins') }}" class="btn btn-outline-secondary">Ir a emitir boletins</a>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card sticky-top" style="top: 1rem;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Pré-visualização</span>
                                <span class="badge text-bg-secondary" id="prevModeloBadge">{{ $modelos[$config['modelo']]['nome'] ?? 'Clássico' }}</span>
                            </div>
                            <div class="card-body">
                                <div id="boletimPreview" class="boletim-preview modelo-classico"
                                     style="--p: {{ $config['cor_primaria'] }}; --s: {{ $config['cor_secundaria'] }}; --t: {{ $config['cor_tabela'] }};">
                                    <div class="boletim-preview__top">
                                        <div class="boletim-preview__school">{{ $instituicao['nome'] ?? 'Escola' }}</div>
                                        <div class="boletim-preview__sub">Boletim — 1º Trimestre</div>
                                    </div>
                                    <div class="boletim-preview__info">
                                        <span>Aluno: Exemplo Silva</span>
                                        <span>Turma: TI12AM</span>
                                    </div>
                                    <table class="boletim-preview__table">
                                        <thead>
                                            <tr><th>Disciplina</th><th>MT</th><th>Sit.</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td>Matemática</td><td>14</td><td>OK</td></tr>
                                            <tr><td>Português</td><td>12</td><td>OK</td></tr>
                                            <tr><td>Física</td><td>09</td><td>—</td></tr>
                                        </tbody>
                                    </table>
                                    <div class="boletim-preview__sign">Encarregado · Assinatura</div>
                                </div>
                                <p class="small text-muted mt-2 mb-0">Pré-visualização aproximada. O PDF final inclui logótipo, QR e notas reais.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('styles')
    <style>
        .boletim-modelo-card {
            display: block; height: 100%; cursor: pointer;
            border: 2px solid var(--bs-border-color); border-radius: .85rem; overflow: hidden;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .boletim-modelo-card:hover { border-color: var(--bs-primary); }
        .boletim-modelo-card.is-selected {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .15);
        }
        .boletim-modelo-card input { position: absolute; opacity: 0; pointer-events: none; }
        .boletim-modelo-card__preview { height: 72px; }
        .boletim-modelo-card__body { padding: .75rem; background: var(--bs-body-bg); }

        .boletim-preview {
            border: 1px solid #e2e8f0; border-radius: .75rem; padding: .85rem;
            background: #fff; color: #1e293b; font-size: .78rem;
        }
        .boletim-preview__top { border-bottom: 2px solid var(--p); padding-bottom: .5rem; margin-bottom: .65rem; }
        .boletim-preview__school { font-weight: 800; color: var(--p); font-size: .95rem; }
        .boletim-preview__sub { color: #64748b; font-size: .72rem; margin-top: .15rem; }
        .boletim-preview__info {
            display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap;
            background: #f1f5f9; border-radius: .5rem; padding: .45rem .55rem; margin-bottom: .65rem;
        }
        .boletim-preview__table { width: 100%; border-collapse: collapse; }
        .boletim-preview__table th {
            background: var(--t); color: #111; padding: .35rem .4rem; text-align: left; font-size: .7rem;
        }
        .boletim-preview__table td { border-bottom: 1px solid #e2e8f0; padding: .35rem .4rem; }
        .boletim-preview__sign { margin-top: .75rem; text-align: center; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: .45rem; }

        .boletim-preview.modelo-moderno .boletim-preview__top {
            background: var(--p); color: #fff; border: 0; margin: -.85rem -.85rem .65rem; padding: .75rem .85rem; border-radius: .75rem .75rem 0 0;
        }
        .boletim-preview.modelo-moderno .boletim-preview__school,
        .boletim-preview.modelo-moderno .boletim-preview__sub { color: #fff; }
        .boletim-preview.modelo-moderno .boletim-preview__table th { background: var(--p); color: #fff; }
        .boletim-preview.modelo-moderno .boletim-preview__info { border-left: 3px solid var(--p); }

        .boletim-preview.modelo-formal {
            border: 2px solid var(--s); box-shadow: inset 0 0 0 1px var(--p);
        }
        .boletim-preview.modelo-formal .boletim-preview__top { border-bottom: 3px double var(--s); }
        .boletim-preview.modelo-formal .boletim-preview__school { color: var(--s); text-transform: uppercase; letter-spacing: .04em; }
        .boletim-preview.modelo-formal .boletim-preview__table th { background: var(--s); color: #fff; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var form = document.getElementById('formBoletimLayout');
        var preview = document.getElementById('boletimPreview');
        var badge = document.getElementById('prevModeloBadge');
        var nomes = @json(collect($modelos)->mapWithKeys(fn ($m, $k) => [$k => $m['nome']]));
        if (!form || !preview) return;

        function actualizar() {
            var modelo = (form.querySelector('input[name="modelo"]:checked') || {}).value || 'classico';
            var p = document.getElementById('cor_primaria').value;
            var s = document.getElementById('cor_secundaria').value;
            var t = document.getElementById('cor_tabela').value;
            preview.style.setProperty('--p', p);
            preview.style.setProperty('--s', s);
            preview.style.setProperty('--t', t);
            preview.classList.remove('modelo-classico', 'modelo-moderno', 'modelo-formal');
            preview.classList.add('modelo-' + modelo);
            document.querySelectorAll('.boletim-modelo-card').forEach(function (card) {
                var radio = card.querySelector('input');
                card.classList.toggle('is-selected', radio && radio.checked);
            });
            if (badge) badge.textContent = nomes[modelo] || modelo;
        }

        form.addEventListener('change', actualizar);
        form.querySelectorAll('.preview-field').forEach(function (el) {
            el.addEventListener('input', actualizar);
        });
        actualizar();
    })();
    </script>
    @endpush
</x-app-layout>
