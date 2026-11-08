<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0">{{ $conteudo->titulo }}</h2>
                <div class="small text-muted">
                    {{ $conteudo->disciplina?->nome }} · {{ $conteudo->classe?->nome }}
                    @if($conteudo->curso) · {{ $conteudo->curso->nome }} @endif
                    · v{{ $conteudo->versao }}
                </div>
            </div>
            <span class="badge bg-{{ $conteudo->estado === 'publicado' ? 'success' : ($conteudo->estado === 'rascunho' ? 'secondary' : 'warning') }}">
                {{ $estados[$conteudo->estado] ?? $conteudo->estado }}
            </span>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-list-check" variant="info" title="Programa oficial" id="tip-conteudos-show" compact>
                Unidades e tópicos do referencial. Em rascunho pode editar itens; após publicar, use «Nova versão» para alterações.
            </x-tip-card>

            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('pedagogico.conteudos.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
                @if($podeGerir)
                    <a href="{{ route('pedagogico.conteudos.edit', $conteudo) }}" class="btn btn-outline-primary btn-sm">Editar cabeçalho</a>
                @endif
                @if($podePublicar && $conteudo->isRascunho())
                    <form method="POST" action="{{ route('pedagogico.conteudos.publicar', $conteudo) }}"
                          data-confirm-title="Publicar programa"
                          data-confirm="Publicar este programa? A versão publicada anterior do mesmo âmbito será arquivada."
                          data-confirm-ok="Publicar">
                        @csrf
                        <button class="btn btn-success btn-sm">Publicar</button>
                    </form>
                @endif
                @if($podePublicar && $conteudo->isPublicado())
                    <form method="POST" action="{{ route('pedagogico.conteudos.arquivar', $conteudo) }}"
                          data-confirm-title="Arquivar"
                          data-confirm="Arquivar este programa publicado?"
                          data-confirm-ok="Arquivar"
                          data-confirm-danger="1">
                        @csrf
                        <button class="btn btn-outline-warning btn-sm">Arquivar</button>
                    </form>
                @endif
                @if($podeNovaVersao && (auth()->user()->can('conteudos.gerir') || auth()->user()->can('conteudos.publicar')))
                    <form method="POST" action="{{ route('pedagogico.conteudos.nova-versao', $conteudo) }}"
                          data-confirm-title="Nova versão"
                          data-confirm="Criar rascunho v{{ $conteudo->versao + 1 }} com cópia dos itens?"
                          data-confirm-ok="Criar">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm">Nova versão</button>
                    </form>
                @endif
            </div>

            @if($conteudo->descricao)
                <div class="card shadow-soft mb-3">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Descrição</div>
                        {!! nl2br(e($conteudo->descricao)) !!}
                    </div>
                </div>
            @endif

            <div class="card shadow-soft mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Unidades e tópicos</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($conteudo->itensRaiz as $unidade)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <strong>{{ $unidade->codigo ? $unidade->codigo.' — ' : '' }}{{ $unidade->titulo }}</strong>
                                    @unless($unidade->obrigatorio)<span class="badge bg-light text-muted">opcional</span>@endunless
                                    @if($unidade->descricao)
                                        <div class="small text-muted">{{ $unidade->descricao }}</div>
                                    @endif
                                </div>
                                @if($podeGerir)
                                    <form method="POST" action="{{ route('pedagogico.conteudos.itens.destroy', [$conteudo, $unidade]) }}"
                                          data-confirm-title="Remover"
                                          data-confirm="Remover esta unidade e subtópicos?"
                                          data-confirm-ok="Remover"
                                          data-confirm-danger="1">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">×</button>
                                    </form>
                                @endif
                            </div>
                            @if($unidade->filhos->isNotEmpty())
                                <ul class="mt-2 mb-0 ps-3">
                                    @foreach($unidade->filhos as $filho)
                                        <li class="d-flex justify-content-between gap-2 py-1">
                                            <span>
                                                {{ $filho->codigo ? $filho->codigo.' — ' : '' }}{{ $filho->titulo }}
                                                @if($filho->trimestre_sugerido)<span class="small text-muted">(T{{ $filho->trimestre_sugerido }})</span>@endif
                                            </span>
                                            @if($podeGerir)
                                                <form method="POST" action="{{ route('pedagogico.conteudos.itens.destroy', [$conteudo, $filho]) }}"
                                                      data-confirm="Remover tópico?" data-confirm-ok="Remover" data-confirm-danger="1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-link text-danger p-0">remover</button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @empty
                        <div class="p-3 text-muted">Ainda sem itens.</div>
                    @endforelse
                </div>
            </div>

            @if($podeGerir)
                <div class="card shadow-soft">
                    <div class="card-header">Adicionar item</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pedagogico.conteudos.itens.store', $conteudo) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Título *</label>
                                <input name="titulo" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Código</label>
                                <input name="codigo" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dentro de (unidade)</label>
                                <select name="parent_id" class="form-select form-select-sm">
                                    <option value="">— Unidade de topo —</option>
                                    @foreach($conteudo->itensRaiz as $u)
                                        <option value="{{ $u->id }}">{{ $u->titulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Trim. sugerido</label>
                                <select name="trimestre_sugerido" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @for($t = 1; $t <= 3; $t++)
                                        <option value="{{ $t }}">{{ $t }}º</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-sm btn-primary w-100">+</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
