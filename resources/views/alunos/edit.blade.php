<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Editar Aluno</h2>
            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <x-tip-card icon="bi-pencil" variant="info" title="Editar Aluno" id="tip-alunos-edit" compact>
                                Actualize os dados do aluno. Se precisar transferir o aluno de turma, pode utilizar a funcionalidade de transferência disponível na página de detalhes do aluno.
                            </x-tip-card>
                            <form action="{{ route('alunos.update', $aluno) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

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
                                        <input type="text" class="form-control @error('numero_processo') is-invalid @enderror" id="numero_processo" name="numero_processo" value="{{ old('numero_processo', $aluno->numero_processo) }}" required maxlength="50">
                                        @error('numero_processo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="turma_id" class="form-label">
                                            <i class="bi bi-people-fill me-1"></i>Turma <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select @error('turma_id') is-invalid @enderror" id="turma_id" name="turma_id" required>
                                            <option value="">Selecione uma turma</option>
                                            @foreach($turmas as $turma)
                                                <option value="{{ $turma->id }}" {{ old('turma_id', $aluno->turma_id) == $turma->id ? 'selected' : '' }}>
                                                    {{ $turma->nome }} - {{ $turma->curso->nome }}
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
                                    <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome', $aluno->nome) }}" required maxlength="255">
                                    @error('nome')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="bi_numero" class="form-label">Nº BI</label>
                                        <input type="text" class="form-control @error('bi_numero') is-invalid @enderror" id="bi_numero" name="bi_numero" value="{{ old('bi_numero', $aluno->bi_numero) }}" maxlength="50">
                                        @error('bi_numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="foto" class="form-label">Fotografia</label>
                                        @if($aluno->foto_path)
                                            <div class="mb-1"><img src="{{ $aluno->fotoUrl() }}" alt="Foto" style="height:56px" class="rounded border"></div>
                                        @endif
                                        <input type="file" class="form-control @error('foto') is-invalid @enderror" id="foto" name="foto" accept="image/*">
                                        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="data_nascimento" class="form-label">
                                            <i class="bi bi-calendar-event me-1"></i>Data de Nascimento
                                        </label>
                                        <input type="date" class="form-control @error('data_nascimento') is-invalid @enderror" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento', $aluno->data_nascimento?->format('Y-m-d')) }}">
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
                                            <option value="M" {{ old('genero', $aluno->genero) === 'M' ? 'selected' : '' }}>Masculino</option>
                                            <option value="F" {{ old('genero', $aluno->genero) === 'F' ? 'selected' : '' }}>Feminino</option>
                                        </select>
                                        @error('genero')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="activo" value="0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $aluno->activo) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="activo">
                                            Aluno ativo
                                        </label>
                                    </div>
                                </div>

                                <div class="card border-info mb-3">
                                    <div class="card-header bg-info bg-opacity-10 py-2">
                                        <strong><i class="bi bi-phone me-1"></i>Portal do Aluno — consulta de notas</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-3">
                                            <input type="hidden" name="consulta_notas_portal" value="0">
                                            <input class="form-check-input" type="checkbox" id="consulta_notas_portal" name="consulta_notas_portal" value="1"
                                                {{ old('consulta_notas_portal', $aluno->consulta_notas_portal ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="consulta_notas_portal">
                                                Permitir consulta de notas e download de boletim no portal
                                            </label>
                                        </div>
                                        <div class="mb-0">
                                            <label for="motivo_bloqueio_portal" class="form-label">Motivo do bloqueio (opcional)</label>
                                            <textarea class="form-control @error('motivo_bloqueio_portal') is-invalid @enderror"
                                                      id="motivo_bloqueio_portal" name="motivo_bloqueio_portal" rows="2"
                                                      placeholder="Ex.: Regularize o pagamento do boletim na secretaria.">{{ old('motivo_bloqueio_portal', $aluno->motivo_bloqueio_portal) }}</textarea>
                                            @error('motivo_bloqueio_portal')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- Condições especiais para Exame Extraordinário — Art.º 32.º --}}
                                <div class="card border-warning mb-3">
                                    <div class="card-header bg-warning bg-opacity-10 py-2">
                                        <strong><i class="bi bi-clipboard-check me-1"></i>Condições para Exame Extraordinário</strong>
                                        <small class="text-muted ms-2">(Art.º 32 do DE 424/25)</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input type="hidden" name="atleta_alta_competicao" value="0">
                                            <input class="form-check-input" type="checkbox" id="atleta_alta_competicao" name="atleta_alta_competicao" value="1" {{ old('atleta_alta_competicao', $aluno->atleta_alta_competicao) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="atleta_alta_competicao">
                                                Atleta de alta competição / selecção provincial ou nacional
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="hidden" name="faltou_exame_normal" value="0">
                                            <input class="form-check-input" type="checkbox" id="faltou_exame_normal" name="faltou_exame_normal" value="1" {{ old('faltou_exame_normal', $aluno->faltou_exame_normal) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="faltou_exame_normal">
                                                Faltou ao exame nacional/recurso por motivo justificado
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">
                                            Estas flags são usadas pelo sistema para determinar a elegibilidade ao Exame Extraordinário.
                                        </small>
                                    </div>
                                </div>

                                {{-- Educação Especial — Art.º 29 do Decreto Executivo 424/25 --}}
                                @php $nee = old('educacao_especial', $aluno->educacao_especial); @endphp
                                <div class="card border-info mb-3">
                                    <div class="card-header bg-info bg-opacity-10 py-2">
                                        <strong><i class="bi bi-universal-access me-1"></i>Educação Especial</strong>
                                        <small class="text-muted ms-2">(Art.º 29 do DE 424/25)</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input type="hidden" name="educacao_especial" value="0">
                                            <input class="form-check-input" type="checkbox" id="educacao_especial" name="educacao_especial" value="1" {{ $nee ? 'checked' : '' }}>
                                            <label class="form-check-label" for="educacao_especial">
                                                Aluno público-alvo da Educação Especial (NEE)
                                            </label>
                                        </div>
                                        <div id="nee-detalhes" style="{{ $nee ? '' : 'display:none' }}">
                                            <label for="necessidades_especificas" class="form-label small">
                                                Necessidades específicas e adaptações de avaliação
                                            </label>
                                            <textarea class="form-control @error('necessidades_especificas') is-invalid @enderror"
                                                id="necessidades_especificas" name="necessidades_especificas" rows="3"
                                                placeholder="Ex.: deficiência visual — Braille; tempo suplementar de 30%; ditado das respostas a um professor; sala separada.">{{ old('necessidades_especificas', $aluno->necessidades_especificas) }}</textarea>
                                            @error('necessidades_especificas')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">
                                                Documente o tipo de necessidade e as adaptações exigidas (instrumentos, comunicação, periodicidade — alíneas a, b, c do n.º 5).
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    document.getElementById('educacao_especial').addEventListener('change', function () {
                                        document.getElementById('nee-detalhes').style.display = this.checked ? '' : 'none';
                                    });
                                </script>

                                <x-info-card type="info" title="Número de Ordem">
                                    Número de ordem atual: <strong>{{ $aluno->numero_ordem }}</strong>.
                                    Se mudar de turma, um novo número será atribuído automaticamente.
                                </x-info-card>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Actualizar Aluno
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
