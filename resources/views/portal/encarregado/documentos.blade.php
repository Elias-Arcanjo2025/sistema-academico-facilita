@extends('layouts.portal-encarregado')

@section('title', 'Pedidos de documentos')

@section('content')
<div class="enc-dash">
    <a href="{{ route('portal.encarregado.dashboard') }}" class="enc-back">&larr; Início</a>

    <div class="enc-hero mb-3">
        <div class="enc-hero__eyebrow">Secretaria</div>
        <h1 class="enc-hero__title">Pedir documentos</h1>
        <p class="enc-hero__sub mb-0">Solicite declarações e cartões pelo portal e acompanhe o estado. Quando estiver «Pronto para levantar», o documento físico fica disponível na secretaria (não é enviado por email nem descarregado aqui).</p>
    </div>

    <div class="alert alert-light border small mb-3">
        <strong>Documentos disponíveis:</strong> declaração de frequência, declaração de matrícula, comprovativo de inscrição, ficha do aluno e cartão de estudante.
        Declarações com notas e certificados vêm da escola mãe e não se pedem neste portal.
    </div>

    <div class="enc-panel mb-3">
        <div class="enc-panel__head">
            <strong><i class="bi bi-plus-circle me-1"></i> Novo pedido</strong>
        </div>
        <form method="POST" action="{{ route('portal.encarregado.documentos.store') }}">
            @csrf
            <div class="mb-2">
                <label class="form-label small">Educando</label>
                <select name="aluno_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach($filhos as $filho)
                        <option value="{{ $filho->id }}" @selected(old('aluno_id') == $filho->id)>{{ $filho->nome }}</option>
                    @endforeach
                </select>
                @error('aluno_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-2">
                <label class="form-label small">Documento</label>
                <select name="tipo" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach($tipos as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                @error('tipo')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label small">Nota (opcional)</label>
                <textarea name="observacao" class="form-control" rows="2" maxlength="500" placeholder="Ex.: preciso até sexta-feira">{{ old('observacao') }}</textarea>
                @error('observacao')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary w-100" style="background: var(--enc-accent); border-color: var(--enc-accent);">
                Enviar pedido
            </button>
        </form>
    </div>

    <div class="enc-panel">
        <div class="enc-panel__head">
            <strong><i class="bi bi-list-check me-1"></i> Os meus pedidos</strong>
        </div>
        @if($pedidos->isEmpty())
            <p class="text-muted small mb-0">Ainda não pediu nenhum documento.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach($pedidos as $pedido)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-2 align-items-start">
                            <div>
                                <div class="fw-semibold">{{ $pedido->tipoLabel() }}</div>
                                <div class="small text-muted">
                                    {{ $pedido->aluno?->nome }}
                                    @if($pedido->aluno?->turma)
                                        · {{ $pedido->aluno->turma->codigo ?? $pedido->aluno->turma->nome }}
                                    @endif
                                </div>
                                <div class="small mt-1">
                                    <span class="badge
                                        @if($pedido->estado === 'pronto') bg-success
                                        @elseif($pedido->estado === 'cancelado') bg-secondary
                                        @elseif($pedido->estado === 'entregue') bg-dark
                                        @elseif($pedido->estado === 'em_preparacao') bg-info text-dark
                                        @else bg-warning text-dark
                                        @endif">
                                        {{ $pedido->estadoLabel() }}
                                    </span>
                                    <span class="text-muted ms-1">{{ $pedido->created_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                @if($pedido->estado === 'pronto')
                                    <div class="small text-success mt-1">
                                        <i class="bi bi-geo-alt me-1"></i>Pronto na secretaria — leve um documento de identificação para levantar.
                                    </div>
                                @endif
                                @if($pedido->nota_secretaria)
                                    <div class="small text-muted mt-1"><i class="bi bi-chat-left-quote me-1"></i>{{ $pedido->nota_secretaria }}</div>
                                @endif
                            </div>
                            @if($pedido->podeCancelar())
                                <form method="POST" action="{{ route('portal.encarregado.documentos.cancelar', $pedido) }}" onsubmit="return confirm('Cancelar este pedido?');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Cancelar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2">{{ $pedidos->links() }}</div>
        @endif
    </div>
</div>
@endsection
