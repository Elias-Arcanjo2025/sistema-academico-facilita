<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <h2 class="h4 mb-0">Instrumento {{ $prova->tipo }} — {{ $prova->disciplina->nome ?? '' }}</h2>
            <div>
                @if($prova->estado === 'rascunho')
                    <form method="POST" action="{{ route('pedagogico.provas.submeter', $prova) }}" class="d-inline"
                          data-confirm-title="Submeter prova"
                          data-confirm="Submeter esta prova para aprovação do coordenador de disciplina?"
                          data-confirm-ok="Sim, submeter">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Submeter aprovação</button>
                    </form>
                @endif
                @can('pedagogico.aprovar')
                    @php $autorProvaId = (int) ($prova->planificacao?->user_id ?? 0); @endphp
                    @if($prova->estado === 'pendente_aprovacao' && $autorProvaId !== (int) auth()->id())
                        <form method="POST" action="{{ route('pedagogico.provas.aprovar', $prova) }}" class="d-inline"
                              data-confirm-title="Aprovar prova"
                              data-confirm="Confirmar aprovação desta prova?"
                              data-confirm-ok="Sim, aprovar"
                              data-confirm-color="#198754">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Aprovar</button>
                        </form>
                        <form method="POST" action="{{ route('pedagogico.provas.rejeitar', $prova) }}" class="d-inline"
                              data-confirm-prompt="Motivo da rejeição"
                              data-confirm-prompt-field="motivo_rejeicao"
                              data-confirm-title="Rejeitar prova"
                              data-confirm-ok="Rejeitar"
                              data-confirm-danger="1">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Rejeitar</button>
                        </form>
                    @endif
                @endcan
                @if($prova->estado === 'aprovada')
                    <form method="POST" action="{{ route('pedagogico.provas.publicar', $prova) }}" class="d-inline"
                          data-confirm-title="Publicar prova"
                          data-confirm="Publicar esta prova?"
                          data-confirm-ok="Sim, publicar"
                          data-confirm-color="#198754">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Publicar</button>
                    </form>
                @endif
                @can('pedagogico.ia.gerar')
                <form method="POST" action="{{ route('pedagogico.provas.regenerar', $prova) }}" class="d-inline"
                      data-confirm-title="Regenerar com IA"
                      data-confirm="Regenerar a prova com IA? O conteúdo actual será substituído."
                      data-confirm-ok="Sim, regenerar"
                      data-confirm-icon="warning"
                      data-confirm-danger="1">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">Regenerar</button>
                </form>
                @endcan
                <a href="{{ route('pedagogico.provas.pdf', $prova) }}" class="btn btn-outline-secondary btn-sm">PDF Prova</a>
                <a href="{{ route('pedagogico.provas.pdf-matriz', $prova) }}" class="btn btn-outline-secondary btn-sm">Matriz</a>
                <a href="{{ route('pedagogico.provas.pdf-chave', $prova) }}" class="btn btn-outline-secondary btn-sm">Chave</a>
                @can('pedagogico.correcao.usar')
                <a href="{{ route('pedagogico.correcoes.create', ['prova_id' => $prova->id]) }}" class="btn btn-primary btn-sm">Corrigir</a>
                @endcan
            </div>
        </div>
    </x-slot>
    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-file-earmark-text" variant="info" title="Instrumento de prova" id="tip-prova-show" compact>
                Rascunho → submeter → aprovação → publicar. Não pode auto-aprovar instrumentos da sua própria planificação; outro coordenador ou a direcção trata disso.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach
            @if($prova->motivo_rejeicao)
                <div class="alert alert-warning small">Última rejeição: {{ $prova->motivo_rejeicao }}</div>
            @endif
            <div class="card mb-3">
                <div class="card-body row">
                    <div class="col-md-3"><strong>Turma:</strong> {{ $prova->turma->nome ?? '-' }}</div>
                    <div class="col-md-3"><strong>Tempo:</strong> {{ $prova->tempo_minutos }} min</div>
                    <div class="col-md-3"><strong>Cotação:</strong> {{ $prova->cotacao_total }} valores</div>
                    <div class="col-md-3"><strong>Estado:</strong> {{ $prova->estado }}</div>
                </div>
            </div>
            @if(($bancoQuestoes ?? collect())->isNotEmpty() && $prova->estado === 'rascunho')
                <div class="card mb-3">
                    <div class="card-header">Importar do banco de questões</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pedagogico.provas.importar-banco', $prova) }}">
                            @csrf
                            <div class="row g-2">
                                @foreach($bancoQuestoes as $bq)
                                    <div class="col-md-6">
                                        <label class="form-check small">
                                            <input type="checkbox" class="form-check-input" name="questao_ids[]" value="{{ $bq->id }}">
                                            {{ \Illuminate\Support\Str::limit($bq->enunciado, 80) }}
                                            <span class="text-muted">({{ $bq->tipo }} · {{ $bq->cotacao }})</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary mt-2">Importar seleccionadas</button>
                        </form>
                    </div>
                </div>
            @endif
            @php $c = $prova->conteudo_json ?? []; @endphp
            <div class="card">
                <div class="card-header">Conteúdo</div>
                <div class="card-body">
                    @if(!empty($c['titulo']))
                        <h5 class="mb-3">{{ $c['titulo'] }}</h5>
                    @endif
                    @if(!empty($c['instrucoes']))
                        <p><strong>Instruções:</strong> {{ $c['instrucoes'] }}</p>
                    @endif
                    @if(!empty($c['questoes']))
                        <ol class="mb-0">
                            @foreach($c['questoes'] as $q)
                                @include('pedagogico.provas._questao', ['q' => $q])
                            @endforeach
                        </ol>
                    @else
                        <p class="text-muted mb-0">Sem questões no conteúdo.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
