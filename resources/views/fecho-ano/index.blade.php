<x-app-layout>

    <x-slot name="header">

        <div class="d-flex justify-content-between align-items-center">

            <h2 class="h4 mb-0">

                <i class="bi bi-calendar2-check me-2 text-primary"></i>Fecho de Ano Lectivo

            </h2>

        </div>

    </x-slot>



    <div class="py-4">

        <div class="container">

            <x-breadcrumb :items="[

                ['label' => 'Configurações', 'url' => route('configuracoes.index')],

                ['label' => 'Fecho de Ano Lectivo']

            ]" />



            @if($errors->any())

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        @foreach($errors->all() as $erro)

                            <li>{{ $erro }}</li>

                        @endforeach

                    </ul>

                </div>

            @endif



            @if(session('success'))

                <div class="alert alert-success">{{ session('success') }}</div>

            @endif

            @if(session('warning'))

                <div class="alert alert-warning">{{ session('warning') }}</div>

            @endif

            @if(session('error'))

                <div class="alert alert-danger">{{ session('error') }}</div>

            @endif

            @if(session('info'))

                <div class="alert alert-info">{{ session('info') }}</div>

            @endif



            @if(session('transicao_stats'))

                @php $ts = session('transicao_stats'); @endphp

                @if(!empty($ts['erros']))

                    <div class="alert alert-warning">

                        <strong>Erros na transição:</strong>

                        <ul class="mb-0 small">

                            @foreach($ts['erros'] as $erro)

                                <li>{{ $erro }}</li>

                            @endforeach

                        </ul>

                    </div>

                @endif

            @endif



            <div class="card shadow-soft mb-4">

                <div class="card-header">

                    <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i>Configurar anos lectivos</h5>

                </div>

                <div class="card-body">

                    <form action="{{ route('fecho-ano.configurar') }}" method="POST" class="row g-3 align-items-end">

                        @csrf

                        <div class="col-md-5">

                            <label class="form-label">Ano de origem</label>

                            <select name="ano_origem" class="form-select" required>

                                @foreach($anosDisponiveis as $ano)

                                    <option value="{{ $ano }}" {{ $anoOrigem === $ano ? 'selected' : '' }}>{{ $ano }}</option>

                                @endforeach

                            </select>

                        </div>

                        <div class="col-md-5">

                            <label class="form-label">Ano de destino (NOVO)</label>

                            <select name="ano_destino" class="form-select" required>

                                <option value="">Seleccione...</option>

                                @foreach($anosNovos as $ano)

                                    <option value="{{ $ano }}" {{ $anoDestino === $ano ? 'selected' : '' }}>{{ $ano }}</option>

                                @endforeach

                            </select>

                            <small class="text-muted">Crie o ano em Configurações se não existir.</small>

                        </div>

                        <div class="col-md-2">

                            <button type="submit" class="btn btn-primary w-100">Continuar</button>

                        </div>

                    </form>

                </div>

            </div>



            @if($anoDestino)

                <div class="row mb-4">

                    @foreach($estado['passos'] as $passo)

                        <div class="col-lg-4 col-md-6 mb-3">

                            <div class="card h-100 border-{{ $passo['concluido'] ? 'success' : 'secondary' }}">

                                <div class="card-body">

                                    <div class="d-flex align-items-start gap-2">

                                        <span class="badge rounded-pill bg-{{ $passo['concluido'] ? 'success' : 'secondary' }}">{{ $passo['numero'] }}</span>

                                        <div>

                                            <h6 class="mb-1">{{ $passo['titulo'] }}</h6>

                                            <p class="small text-muted mb-1">{{ $passo['descricao'] }}</p>

                                            <small class="text-{{ $passo['concluido'] ? 'success' : 'muted' }}">{{ $passo['detalhe'] }}</small>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>



                @if(!empty($estado['preview']['stats']))

                    <div class="card shadow-soft mb-4">

                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-eye me-1"></i>Pré-visualização da transição</h6></div>

                        <div class="card-body">

                            <div class="row g-3 mb-3">

                                <div class="col-md-3"><span class="text-muted">Total:</span> <strong>{{ $estado['preview']['stats']['total_alunos'] }}</strong></div>

                                <div class="col-md-3"><span class="text-success">Aprovados:</span> <strong>{{ $estado['preview']['stats']['aprovados'] }}</strong></div>

                                <div class="col-md-3"><span class="text-danger">Reprovados:</span> <strong>{{ $estado['preview']['stats']['reprovados'] }}</strong></div>

                                <div class="col-md-3"><span class="text-warning">Pendentes:</span> <strong>{{ $estado['preview']['stats']['pendentes'] }}</strong></div>

                            </div>

                            @if(!empty($estado['preview']['warnings']))

                                <ul class="small text-muted mb-0">

                                    @foreach($estado['preview']['warnings'] as $aviso)

                                        <li>{{ $aviso }}</li>

                                    @endforeach

                                </ul>

                            @endif

                        </div>

                    </div>

                @endif



                <div class="row g-4">

                    <div class="col-lg-6">

                        <div class="card shadow-soft h-100">

                            <div class="card-header"><h6 class="mb-0">Transferir estrutura</h6></div>

                            <div class="card-body">

                                <p class="small text-muted">Copia turmas 1:1, associações, directores, coordenadores e opcionalmente horários/calendário.</p>

                                <form action="{{ route('fecho-ano.transferir') }}" method="POST">

                                    @csrf

                                    <div class="form-check mb-2">

                                        <input type="hidden" name="transferir_associacoes" value="0">

                                        <input class="form-check-input" type="checkbox" name="transferir_associacoes" value="1" id="ta" checked>

                                        <label class="form-check-label" for="ta">Associações professor</label>

                                    </div>

                                    <div class="form-check mb-2">

                                        <input type="hidden" name="transferir_directores" value="0">

                                        <input class="form-check-input" type="checkbox" name="transferir_directores" value="1" id="td" checked>

                                        <label class="form-check-label" for="td">Directores de turma</label>

                                    </div>

                                    <div class="form-check mb-2">

                                        <input type="hidden" name="transferir_coordenadores" value="0">

                                        <input class="form-check-input" type="checkbox" name="transferir_coordenadores" value="1" id="tc" checked>

                                        <label class="form-check-label" for="tc">Coordenadores de curso</label>

                                    </div>

                                    <div class="form-check mb-2">

                                        <input type="hidden" name="transferir_horarios" value="0">

                                        <input class="form-check-input" type="checkbox" name="transferir_horarios" value="1" id="th">

                                        <label class="form-check-label" for="th">Horários de aula</label>

                                    </div>

                                    <div class="form-check mb-3">

                                        <input type="hidden" name="transferir_calendario" value="0">

                                        <input class="form-check-input" type="checkbox" name="transferir_calendario" value="1" id="tcal">

                                        <label class="form-check-label" for="tcal">Calendário de provas</label>

                                    </div>

                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Executar transferência de estrutura?')">

                                        <i class="bi bi-arrow-left-right me-1"></i>Transferir estrutura

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>



                    <div class="col-lg-6">

                        <div class="card shadow-soft h-100">

                            <div class="card-header"><h6 class="mb-0">Transitar alunos</h6></div>

                            <div class="card-body">

                                <p class="small text-muted">Regras oficiais DE 424/25 (T3). Aprovados → classe seguinte; reprovados → mesma classe.</p>

                                @if(!($estado['estatisticas']['temTurmasPromocao'] ?? false))

                                    <div class="alert alert-warning py-2 small">

                                        Crie turmas da classe seguinte no ano destino para permitir promoção.

                                    </div>

                                @endif

                                <form action="{{ route('fecho-ano.transitar') }}" method="POST">

                                    @csrf

                                    <div class="form-check mb-3">

                                        <input type="hidden" name="transitar_reprovados" value="0">

                                        <input class="form-check-input" type="checkbox" name="transitar_reprovados" value="1" id="tr">

                                        <label class="form-check-label" for="tr">Incluir reprovados (repetência)</label>

                                    </div>

                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Executar transição de alunos?')">

                                        <i class="bi bi-arrow-right-circle me-1"></i>Transitar alunos

                                    </button>

                                    <a href="{{ route('alunos.transicao.index') }}" class="btn btn-outline-secondary btn-sm ms-2">

                                        Transição avançada

                                    </a>

                                </form>

                            </div>

                        </div>

                    </div>



                    <div class="col-12">

                        <div class="card shadow-soft border-success">

                            <div class="card-header bg-success bg-opacity-10">

                                <h6 class="mb-0 text-success"><i class="bi bi-check-circle me-1"></i>Activar ano lectivo</h6>

                            </div>

                            <div class="card-body">

                                <div class="row align-items-center g-3">

                                    <div class="col-lg-8">

                                        <p class="mb-2">Tornar <strong>{{ $anoDestino }}</strong> o ano lectivo actual do sistema.</p>

                                        @if(!empty($estado['checklist_activar']['erros']))

                                            <div class="alert alert-danger py-2 small mb-2">

                                                <strong>Pendências:</strong>

                                                <ul class="mb-0">

                                                    @foreach($estado['checklist_activar']['erros'] as $erro)

                                                        <li>{{ $erro }}</li>

                                                    @endforeach

                                                </ul>

                                            </div>

                                        @endif

                                        @if(!empty($estado['checklist_activar']['avisos']))

                                            <ul class="small text-muted mb-0">

                                                @foreach($estado['checklist_activar']['avisos'] as $aviso)

                                                    <li>{{ $aviso }}</li>

                                                @endforeach

                                            </ul>

                                        @endif

                                    </div>

                                    <div class="col-lg-4 text-lg-end">

                                        <form action="{{ route('fecho-ano.activar') }}" method="POST">

                                            @csrf

                                            <button type="submit" class="btn btn-success btn-lg" {{ $estado['pode_activar'] ? '' : 'disabled' }}

                                                    onclick="return confirm('Activar o ano lectivo {{ $anoDestino }}? As notas de {{ $anoOrigem }} serão arquivadas.')">

                                                Activar {{ $anoDestino }}

                                            </button>

                                        </form>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            @endif

        </div>

    </div>

</x-app-layout>

