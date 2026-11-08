<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Exercício — {{ $exercicio->tipo }}</h2>
            <a href="{{ route('pedagogico.exercicios.pdf', $exercicio) }}" class="btn btn-outline-secondary btn-sm">PDF</a>
        </div>
    </x-slot>
    <div class="py-4">
        <div class="container">
            @php $c = $exercicio->conteudo_json ?? []; @endphp
            <div class="card">
                <div class="card-body">
                    @if(!empty($c['enunciado']))
                        <h5>Enunciado</h5>
                        <p>{!! nl2br(e($c['enunciado'])) !!}</p>
                    @endif
                    @if(!empty($c['questoes']))
                        <h5 class="mt-3">Questões</h5>
                        <ol>
                            @foreach($c['questoes'] as $q)
                                <li class="mb-2">{{ is_array($q) ? ($q['texto'] ?? json_encode($q)) : $q }}</li>
                            @endforeach
                        </ol>
                    @endif
                    @if(empty($c))
                        <pre class="mb-0">{{ json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
