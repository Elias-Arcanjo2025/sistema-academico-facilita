<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Correcção — Nota sugerida: {{ $correcao->sugestao_nota ?? '-' }}</h2>
    </x-slot>
    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-check2-square" variant="info" title="Rever correcção" id="tip-correcao-show" compact>
                Valide o texto lido (OCR), ajuste pontos por questão se necessário e confirme a nota. Rejeite se a imagem não for uma prova do aluno.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="card mb-4">
                <div class="card-body">
                    <p><strong>Prova:</strong> {{ $correcao->prova->tipo }} — {{ $correcao->prova->disciplina->nome ?? '' }}</p>
                    <p><strong>Aluno:</strong> {{ $correcao->aluno->nome ?? 'Não identificado' }}</p>
                    <p><strong>Estado:</strong> {{ $correcao->estado }}</p>
                    <p><strong>OCR confiança:</strong> {{ $correcao->ocr_confianca_global ? number_format($correcao->ocr_confianca_global * 100, 1).'%' : '-' }}</p>
                    @php
                        $ocr = $correcao->ocr_texto ?? '';
                        $parecePlano = str_contains(Str::lower($ocr), 'conteúdo para ditar')
                            || str_contains(Str::lower($ocr), 'conteudo para ditar')
                            || str_contains(Str::lower($ocr), 'aula 1');
                    @endphp
                    @if($parecePlano)
                        <div class="alert alert-warning py-2 mb-0">
                            O texto lido parece um <strong>plano de aula</strong>, não uma prova preenchida pelo aluno.
                            Envie foto/PDF da folha de respostas do aluno.
                        </div>
                    @endif
                    @if($ocr !== '')
                        <details class="mt-2">
                            <summary class="small text-muted">Ver texto OCR</summary>
                            <pre class="small mt-2 mb-0" style="max-height:200px;overflow:auto;">{{ $ocr }}</pre>
                        </details>
                    @endif
                </div>
            </div>

            @if($correcao->estado === 'pendente')
            <form method="POST" action="{{ route('pedagogico.correcoes.confirmar', $correcao) }}">
                @csrf
                <div class="card mb-3">
                    <table class="table mb-0">
                        <thead><tr><th>Questão</th><th>Extraída</th><th>Esperada</th><th>Sugestão</th><th>Confirmar</th></tr></thead>
                        <tbody>
                            @foreach($correcao->itens as $item)
                            <tr>
                                <td>{{ $item->questao_ref }}</td>
                                <td><small>{{ $item->resposta_extraida ? Str::limit($item->resposta_extraida, 80) : '—' }}</small></td>
                                <td><small>{{ $item->resposta_esperada ? Str::limit($item->resposta_esperada, 80) : '—' }}</small></td>
                                <td>{{ $item->sugestao_pontos }}</td>
                                <td>
                                    <input type="hidden" name="itens[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                    <input type="number" step="0.5" name="itens[{{ $loop->index }}][pontos_confirmados]" class="form-control form-control-sm" value="{{ $item->sugestao_pontos }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mb-3 col-md-3">
                    <label class="form-label">Nota confirmada</label>
                    <input type="number" step="0.1" name="nota_confirmada" class="form-control" value="{{ $correcao->sugestao_nota }}" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">Confirmar</button>
                </div>
            </form>
            <form method="POST" action="{{ route('pedagogico.correcoes.rejeitar', $correcao) }}" class="mt-2"
                  data-confirm-title="Rejeitar correcção"
                  data-confirm="Rejeitar esta correcção? Poderá iniciar uma nova mais tarde."
                  data-confirm-ok="Sim, rejeitar"
                  data-confirm-danger="1">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Rejeitar</button>
            </form>
            @else
            <div class="alert alert-info">Correcção {{ $correcao->estado }}. Nota confirmada: {{ $correcao->nota_confirmada ?? '-' }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
