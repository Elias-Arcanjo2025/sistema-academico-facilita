<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">{{ $orientacao->titulo }}</h2>
            <div class="d-flex gap-2">
                @if($podeGerir && $orientacao->estado === 'rascunho')
                    <a href="{{ route('pedagogico.orientacoes.edit', $orientacao) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                    <form method="POST" action="{{ route('pedagogico.orientacoes.publicar', $orientacao) }}">
                        @csrf
                        <button class="btn btn-success btn-sm" onclick="return confirm('Publicar e notificar professores?')">Publicar</button>
                    </form>
                @endif
                @if($podeGerir && $orientacao->estado === 'publicado')
                    <form method="POST" action="{{ route('pedagogico.orientacoes.arquivar', $orientacao) }}">
                        @csrf
                        <button class="btn btn-outline-warning btn-sm">Arquivar</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 800px;">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card shadow-soft mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-{{ $orientacao->estado === 'publicado' ? 'success' : ($orientacao->estado === 'rascunho' ? 'secondary' : 'warning') }}">
                            {{ $estados[$orientacao->estado] ?? $orientacao->estado }}
                        </span>
                        <span class="text-muted small">{{ $orientacao->disciplina?->nome }} · {{ $orientacao->classe?->nome }}</span>
                        @if($orientacao->trimestre)
                            <span class="text-muted small">· {{ $orientacao->trimestre }}º trimestre</span>
                        @endif
                    </div>
                    <div class="orientacao-conteudo" style="white-space: pre-wrap;">{{ $orientacao->conteudo }}</div>
                    <hr>
                    <div class="small text-muted">
                        Criado por {{ $orientacao->autor?->name }}
                        @if($orientacao->publicado_em)
                            · Publicado {{ $orientacao->publicado_em->format('d/m/Y H:i') }}
                        @endif
                    </div>
                </div>
            </div>

            <a href="{{ route('pedagogico.orientacoes.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </div>
</x-app-layout>
