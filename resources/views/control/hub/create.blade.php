@extends('layouts.control-hub')

@section('title', 'Registar escola')
@section('page-title', 'Registar escola')

@section('content')
<div class="container-fluid py-3" style="max-width: 720px;">
    <div class="mb-4">
        <a href="{{ route('control.hub.index') }}" class="text-decoration-none small">&larr; Voltar</a>
        <h1 class="h3 mt-2">Registar escola</h1>
        <p class="text-muted">Cria a instalação no hub e gera o secret para configurar o `.env` da escola.</p>
    </div>

    <form method="POST" action="{{ route('control.hub.escolas.store') }}" class="border rounded-3 p-4 bg-white">
        @csrf
        <div class="mb-3">
            <label class="form-label">Nome da escola *</label>
            <input type="text" name="nome" value="{{ old('nome') }}" class="form-control @error('nome') is-invalid @enderror" required>
            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">URL da instalação</label>
            <input type="url" name="app_url" value="{{ old('app_url') }}" class="form-control @error('app_url') is-invalid @enderror" placeholder="https://escola.exemplo.ao">
            @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Email de contacto</label>
                <input type="email" name="contacto_email" value="{{ old('contacto_email') }}" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="contacto_telefone" value="{{ old('contacto_telefone') }}" class="form-control">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Preço anual por aluno</label>
            <input type="number" step="0.01" min="0" name="preco_anual_aluno" value="{{ old('preco_anual_aluno', config('control.preco_anual_aluno_default')) }}" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Installation ID (opcional)</label>
            <input type="text" name="installation_id" value="{{ old('installation_id') }}" class="form-control" placeholder="Deixe vazio para gerar automaticamente">
            <div class="form-text">Se a escola já tiver um ID, cole-o aqui.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Notas internas</label>
            <textarea name="notas_internas" rows="3" class="form-control">{{ old('notas_internas') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">Criar e gerar secret</button>
    </form>
</div>
@endsection
