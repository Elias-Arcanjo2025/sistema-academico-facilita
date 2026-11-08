<x-app-layout>

    <x-slot name="header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h2 class="h4 mb-0">

                    <i class="bi bi-calendar-check me-2 text-success"></i>Lançar CAs Anteriores

                </h2>

                <small class="text-muted">

                    {{ $turma->nome }} ({{ $turma->classe?->nome ?? '—' }}) | {{ $turma->curso?->nome }} | Ano Lectivo {{ $anoLectivo }}

                </small>

            </div>

            <a href="{{ route('cfds-anteriores.index') }}" class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left"></i> Voltar

            </a>

        </div>

    </x-slot>



    <div class="py-4">

        <div class="container-fluid px-4">

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('success'))

                <div class="alert alert-success alert-dismissible fade show">

                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

            @endif



            @if($aviso ?? null)

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $aviso }}

                </div>

            @endif



            <x-tip-card icon="bi-info-circle" variant="info" title="CAs e CFDs de anos anteriores" id="tip-cas-so-ca">

                <strong>Disciplinas contínuas</strong> (ex.: Portuguesa 10.ª–11.ª, Matemática 10.ª–12.ª): lance as <strong>CAs</strong> em falta.
                <br><strong>11.ª classe:</strong> CA-10ª em falta; a CA-11ª vem da avaliação do ano actual. Na pauta: CA-11 = 60% CF + 40% PG; CFD = (CA-10 + CA-11) / 2.
                <br><strong>12.ª classe:</strong> CA-10ª e CA-11ª em falta. CFD final = (CA-10 + CA-11 + CA-12) / 3.

                <br><br><strong>Disciplinas terminais solo-classe</strong> (ex.: TIC 10.ª–10.ª, FAI 11.ª–11.ª): lance a <strong>CFD</strong> directamente — não use CA.
                <br>Na pauta da 12.ª, estas CFDs aparecem nas colunas à esquerda (carry-over).
            </x-tip-card>



            @if(!($podeEditar ?? true))
                <div class="alert alert-warning">
                    <i class="bi bi-lock-fill me-2"></i>
                    Modo consulta: pode ver CAs/CFDs de anos anteriores, mas só o administrador pode alterá-las.
                </div>
            @endif

            @if(!empty($gruposCa) || !empty($gruposCfd))

                <form method="POST" action="{{ route('cfds-anteriores.guardar', $turma) }}">

                    @csrf

                    <input type="hidden" name="ano_lectivo" value="{{ $anoLectivo }}">



                    @foreach($gruposCfd ?? [] as $grupo)

                        <div class="card shadow-soft mb-4">

                            <div class="card-header d-flex justify-content-between align-items-center">

                                <h5 class="mb-0"><i class="bi bi-award me-2 text-success"></i>{{ $grupo['titulo'] }}</h5>

                                <span class="badge bg-success">

                                    {{ count($alunos) }} alunos × {{ $grupo['disciplinas']->count() }} disciplinas

                                </span>

                            </div>

                            <div class="card-body p-0">

                                <p class="small text-muted px-3 pt-3 mb-0">

                                    Ano lectivo de referência:

                                    @if($grupo['ano_referencia'] ?? null)

                                        <strong>{{ $grupo['ano_referencia'] }}</strong>.

                                    @else

                                        conforme o percurso do aluno.

                                    @endif

                                    Disciplinas que terminam só nesta classe — lance a <strong>CFD final</strong> (não a CA).

                                </p>

                                @include('cfds-anteriores.partials.tabela-cfds', [

                                    'alunos' => $alunos,

                                    'disciplinas' => $grupo['disciplinas'],

                                    'cfdsAnteriores' => $cfdsAnteriores ?? [],

                                    'classeTerminal' => $grupo['classe_terminal'],

                                    'tituloGrupo' => $grupo['titulo'],

                                    'corFundo' => $grupo['corFundo'],

                                    'podeEditar' => $podeEditar ?? true,

                                ])

                            </div>

                        </div>

                    @endforeach



                    @foreach($gruposCa ?? [] as $grupo)

                        <div class="card shadow-soft mb-4">

                            <div class="card-header d-flex justify-content-between align-items-center">

                                <h5 class="mb-0"><i class="bi bi-calendar-check me-2 text-success"></i>{{ $grupo['titulo'] }}</h5>

                                <span class="badge bg-success">

                                    {{ count($alunos) }} alunos × {{ $grupo['disciplinas']->count() }} disciplinas

                                </span>

                            </div>

                            <div class="card-body p-0">

                                <p class="small text-muted px-3 pt-3 mb-0">

                                    Ano lectivo de referência:

                                    @if($grupo['ano_referencia'] ?? null)

                                        <strong>{{ $grupo['ano_referencia'] }}</strong>.

                                    @else

                                        conforme o percurso do aluno.

                                    @endif

                                    Inclui disciplinas contínuas (ex.: Portuguesa, Matemática). Disciplinas terminais solo-classe estão na secção CFD acima.

                                </p>

                                @include('cfds-anteriores.partials.tabela-cas', [

                                    'alunos' => $alunos,

                                    'disciplinas' => $grupo['disciplinas'],

                                    'casAnteriores' => $casAnteriores ?? [],

                                    'podeEditar' => $podeEditar ?? true,

                                    'tipoCa' => $grupo['tipo'],

                                    'tituloGrupo' => $grupo['titulo'],

                                    'corFundo' => $grupo['corFundo'],

                                ])

                            </div>

                        </div>

                    @endforeach



                    <div class="text-end ca-lancamento-accoes sticky-bottom bg-body border-top py-3 px-3 mt-2 rounded shadow-sm">

                        <a href="{{ route('cfds-anteriores.index') }}" class="btn btn-outline-secondary">

                            <i class="bi bi-x-lg me-1"></i>Cancelar

                        </a>

                        @if($podeEditar ?? true)
                        <button type="submit" class="btn btn-success">

                            <i class="bi bi-save me-1"></i>Guardar notas

                        </button>
                        @endif

                    </div>

                </form>

            @endif

        </div>

    </div>

    @push('styles')
    <style>
        .ca-lancamento-scroll {
            overflow-x: auto;
            max-height: min(70vh, 720px);
            overflow-y: auto;
            border-radius: 0 0 0.375rem 0.375rem;
        }
        .ca-lancamento-table { font-size: 0.8125rem; margin-bottom: 0; }
        .ca-lancamento-table thead th {
            position: sticky; top: 0; z-index: 3;
            background: #f8f9fa; border-color: #dee2e6;
            vertical-align: middle; padding: 0.35rem 0.25rem;
        }
        .ca-lancamento-grupo .ca-grupo-titulo {
            background: linear-gradient(180deg, #d1e7dd 0%, #e8f8ee 100%);
            color: #0f5132; font-weight: 700; font-size: 0.875rem;
        }
        .ca-col-num { width: 42px; min-width: 42px; }
        .ca-col-nome {
            min-width: 200px; max-width: 240px; text-align: left;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ca-col-disc {
            min-width: 72px; max-width: 88px; background: #fafafa; line-height: 1.15;
        }
        .ca-disc-codigo {
            display: block; font-weight: 700; font-size: 0.75rem; color: #198754;
        }
        .ca-disc-meta {
            display: block; font-size: 0.625rem; color: #6c757d; font-weight: 500;
        }
        .ca-sticky {
            position: sticky; left: 0; z-index: 2;
            background: #fff; box-shadow: 2px 0 4px rgba(0,0,0,.04);
        }
        .ca-sticky-nome { left: 42px; z-index: 2; }
        .ca-lancamento-table thead .ca-sticky,
        .ca-lancamento-table thead .ca-sticky-nome { z-index: 4; background: #f8f9fa; }
        .ca-celula-nota {
            background: var(--ca-fundo, #f8f9fa);
            padding: 0.2rem !important; vertical-align: middle;
        }
        .ca-input-nota {
            width: 52px; height: 30px; margin: 0 auto;
            padding: 0.15rem 0.2rem; font-weight: 700; font-size: 0.8125rem;
            border: 1px solid #ced4da; border-radius: 0.25rem; background: #fff;
        }
        .ca-input-nota:focus {
            border-color: #198754; box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.2);
        }
        .ca-input-nota::placeholder { color: #adb5bd; font-weight: 400; }
        .ca-nota-pos .ca-input-nota { color: #0d6efd; border-color: #9ec5fe; }
        .ca-nota-neg .ca-input-nota {
            color: #dc3545; border-color: #f1aeb5; background: #fff5f5;
        }
        .ca-lancamento-table tbody tr:hover td { filter: brightness(0.98); }
        .ca-lancamento-accoes { position: sticky; bottom: 0; z-index: 5; }
    </style>
    @endpush

    @push('scripts')
    <script>
        document.querySelectorAll('.ca-input-nota').forEach(function (input) {
            function actualizarCor() {
                var td = input.closest('td');
                if (!td) return;
                td.classList.remove('ca-nota-pos', 'ca-nota-neg');
                var v = parseFloat(input.value);
                if (!isNaN(v) && input.value !== '') {
                    td.classList.add(v >= 10 ? 'ca-nota-pos' : 'ca-nota-neg');
                }
            }
            input.addEventListener('input', actualizarCor);
            input.addEventListener('blur', actualizarCor);
        });
    </script>
    @endpush

</x-app-layout>
