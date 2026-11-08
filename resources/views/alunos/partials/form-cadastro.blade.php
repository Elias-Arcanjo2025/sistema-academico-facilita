{{-- Campos partilhados: cadastro de aluno e matrícula (secretaria) --}}
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="numero_processo" class="form-label">
            <i class="bi bi-file-earmark-text me-1"></i>Número de Processo <span class="text-danger">*</span>
            <x-help-icon
                type="popover"
                title="Número de Processo"
                content="Identificador único do aluno no sistema escolar. Geralmente fornecido pela secretaria da escola."
            />
        </label>
        <input type="text" class="form-control @error('numero_processo') is-invalid @enderror" id="numero_processo" name="numero_processo" value="{{ old('numero_processo') }}" required maxlength="50">
        @error('numero_processo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="turma_id" class="form-label">
            <i class="bi bi-people-fill me-1"></i>Turma <span class="text-danger">*</span>
        </label>
        <select class="form-select @error('turma_id') is-invalid @enderror {{ $turmaSelectClass ?? '' }}" id="turma_id" name="turma_id" required>
            <option value="">Selecione uma turma</option>
            @foreach($turmas as $turma)
                <option value="{{ $turma->id }}" {{ old('turma_id') == $turma->id ? 'selected' : '' }}>
                    {{ $turma->nome }} - {{ $turma->curso->nome ?? '' }}
                </option>
            @endforeach
        </select>
        @error('turma_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="nome" class="form-label">
        <i class="bi bi-person me-1"></i>Nome Completo <span class="text-danger">*</span>
    </label>
    <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required maxlength="255">
    @error('nome')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="bi_numero" class="form-label">Nº BI</label>
        <input type="text" class="form-control @error('bi_numero') is-invalid @enderror" id="bi_numero" name="bi_numero" value="{{ old('bi_numero') }}" maxlength="50">
        @error('bi_numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="foto" class="form-label">Fotografia</label>
        <input type="file" class="form-control @error('foto') is-invalid @enderror" id="foto" name="foto" accept="image/*">
        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div id="foto-preview-wrap" class="mt-2 d-none">
            <img id="foto-preview" src="" alt="Pré-visualização" class="img-thumbnail" style="max-width:120px;max-height:150px;object-fit:cover;">
            <button type="button" class="btn btn-sm btn-outline-danger d-block mt-1" id="foto-remover">Remover foto</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="data_nascimento" class="form-label">
            <i class="bi bi-calendar-event me-1"></i>Data de Nascimento
        </label>
        <input type="date" class="form-control @error('data_nascimento') is-invalid @enderror" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento') }}">
        @error('data_nascimento')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="genero" class="form-label">
            <i class="bi bi-gender-ambiguous me-1"></i>Género
        </label>
        <select class="form-select @error('genero') is-invalid @enderror" id="genero" name="genero">
            <option value="">Selecione</option>
            <option value="M" {{ old('genero') === 'M' ? 'selected' : '' }}>Masculino</option>
            <option value="F" {{ old('genero') === 'F' ? 'selected' : '' }}>Feminino</option>
        </select>
        @error('genero')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="card mb-3">
    <div class="card-header py-2">
        <strong><i class="bi bi-people me-1"></i>Filiação</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label for="pai_nome" class="form-label">Nome do pai</label>
                <input type="text" class="form-control @error('pai_nome') is-invalid @enderror" id="pai_nome" name="pai_nome" value="{{ old('pai_nome') }}" maxlength="255">
                @error('pai_nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
                <label for="pai_telefone" class="form-label">Telefone do pai</label>
                <input type="text" class="form-control @error('pai_telefone') is-invalid @enderror" id="pai_telefone" name="pai_telefone" value="{{ old('pai_telefone') }}" maxlength="40" placeholder="9xx xxx xxx">
                @error('pai_telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8 mb-3">
                <label for="mae_nome" class="form-label">Nome da mãe</label>
                <input type="text" class="form-control @error('mae_nome') is-invalid @enderror" id="mae_nome" name="mae_nome" value="{{ old('mae_nome') }}" maxlength="255">
                @error('mae_nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
                <label for="mae_telefone" class="form-label">Telefone da mãe</label>
                <input type="text" class="form-control @error('mae_telefone') is-invalid @enderror" id="mae_telefone" name="mae_telefone" value="{{ old('mae_telefone') }}" maxlength="40" placeholder="9xx xxx xxx">
                @error('mae_telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input type="hidden" name="activo" value="0">
        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
        <label class="form-check-label" for="activo">
            Aluno ativo
        </label>
    </div>
</div>

<div class="card border-warning mb-3">
    <div class="card-header bg-warning bg-opacity-10 py-2">
        <strong><i class="bi bi-clipboard-check me-1"></i>Condições para Exame Extraordinário</strong>
        <small class="text-muted ms-2">(Art.º 32 do DE 424/25)</small>
    </div>
    <div class="card-body">
        <div class="form-check">
            <input type="hidden" name="atleta_alta_competicao" value="0">
            <input class="form-check-input" type="checkbox" id="atleta_alta_competicao" name="atleta_alta_competicao" value="1" {{ old('atleta_alta_competicao') ? 'checked' : '' }}>
            <label class="form-check-label" for="atleta_alta_competicao">
                Atleta de alta competição / selecção provincial ou nacional
            </label>
        </div>
        <div class="form-check">
            <input type="hidden" name="faltou_exame_normal" value="0">
            <input class="form-check-input" type="checkbox" id="faltou_exame_normal" name="faltou_exame_normal" value="1" {{ old('faltou_exame_normal') ? 'checked' : '' }}>
            <label class="form-check-label" for="faltou_exame_normal">
                Faltou ao exame nacional/recurso por motivo justificado
            </label>
        </div>
        <small class="form-text text-muted">
            Estas flags são usadas pelo sistema para determinar a elegibilidade ao Exame Extraordinário.
        </small>
    </div>
</div>

<div class="card border-info mb-3">
    <div class="card-header bg-info bg-opacity-10 py-2">
        <strong><i class="bi bi-universal-access me-1"></i>Educação Especial</strong>
        <small class="text-muted ms-2">(Art.º 29 do DE 424/25)</small>
    </div>
    <div class="card-body">
        <div class="form-check mb-2">
            <input type="hidden" name="educacao_especial" value="0">
            <input class="form-check-input" type="checkbox" id="educacao_especial" name="educacao_especial" value="1" {{ old('educacao_especial') ? 'checked' : '' }}>
            <label class="form-check-label" for="educacao_especial">
                Aluno público-alvo da Educação Especial (NEE)
            </label>
        </div>
        <div id="nee-detalhes" style="{{ old('educacao_especial') ? '' : 'display:none' }}">
            <label for="necessidades_especificas" class="form-label small">
                Necessidades específicas e adaptações de avaliação
            </label>
            <textarea class="form-control @error('necessidades_especificas') is-invalid @enderror"
                id="necessidades_especificas" name="necessidades_especificas" rows="3"
                placeholder="Ex.: deficiência visual — Braille; tempo suplementar de 30%; ditado das respostas a um professor; sala separada.">{{ old('necessidades_especificas') }}</textarea>
            @error('necessidades_especificas')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="form-text text-muted">
                Documente o tipo de necessidade e as adaptações exigidas (instrumentos, comunicação, periodicidade — alíneas a, b, c do n.º 5).
            </small>
        </div>
    </div>
</div>

<x-info-card type="info" title="Número de Ordem">
    O número de ordem será atribuído automaticamente com base na turma selecionada.
</x-info-card>
