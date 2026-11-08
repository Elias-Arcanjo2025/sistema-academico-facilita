<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Registar encarregado</h2>
            <a href="{{ route('encarregados.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Encarregados', 'url' => route('encarregados.index')],
                ['label' => 'Registar'],
            ]" />

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:2.5rem;height:2.5rem;background:rgba(79,70,229,.1);color:var(--primary-color,#4f46e5)">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold mb-1">Conta separada</div>
                                    <p class="text-muted small mb-0">
                                        O encarregado tem portal próprio (não partilha login com o aluno). Pode acompanhar vários educandos.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:2.5rem;height:2.5rem;background:rgba(16,185,129,.1);color:#10b981">
                                    <i class="bi bi-pen"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold mb-1">Assinatura e notas</div>
                                    <p class="text-muted small mb-0">
                                        Pode ver notas e assinar boletins dos educandos associados. O primeiro da lista fica como principal.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:2.5rem;height:2.5rem;background:rgba(6,182,212,.1);color:#06b6d4">
                                    <i class="bi bi-chat-dots"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold mb-1">Comunicação</div>
                                    <p class="text-muted small mb-0">
                                        Chat com o Director de Turma e, se activar opt-in, alertas via WhatsApp.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-soft border-0">
                        <div class="card-body p-4">
                            <x-tip-card icon="bi-person-heart" variant="info" title="Novo encarregado" id="tip-encarregado-create" compact>
                                Preencha os dados de contacto e seleccione os educandos. Se criar conta de portal, a senha temporária aparece uma vez após guardar.
                            </x-tip-card>

                            @if($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0 small">
                                        @foreach($errors->all() as $erro)
                                            <li>{{ $erro }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('encarregados.store') }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label" for="nome">Nome *</label>
                                    <input type="text" name="nome" id="nome"
                                           class="form-control @error('nome') is-invalid @enderror"
                                           value="{{ old('nome') }}" required autofocus>
                                    @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="telefone">Telefone / WhatsApp</label>
                                        <input type="text" name="telefone" id="telefone"
                                               class="form-control @error('telefone') is-invalid @enderror"
                                               value="{{ old('telefone') }}" placeholder="9xx xxx xxx">
                                        @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" name="email" id="email"
                                               class="form-control @error('email') is-invalid @enderror"
                                               value="{{ old('email') }}">
                                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="documento">Documento (BI)</label>
                                    <input type="text" name="documento" id="documento"
                                           class="form-control @error('documento') is-invalid @enderror"
                                           value="{{ old('documento') }}">
                                    @error('documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="aluno_ids">Educandos</label>
                                    <select name="aluno_ids[]" id="aluno_ids"
                                            class="form-select select2-educandos @error('aluno_ids') is-invalid @enderror @error('aluno_ids.*') is-invalid @enderror"
                                            multiple
                                            data-placeholder="Pesquisar e seleccionar alunos...">
                                        @foreach($alunos as $aluno)
                                            <option value="{{ $aluno->id }}"
                                                @selected(collect(old('aluno_ids', []))->contains($aluno->id))>
                                                {{ $aluno->nome }} ({{ $aluno->numero_processo }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Pode seleccionar vários. O primeiro fica como encarregado principal.</div>
                                    @error('aluno_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="border rounded-3 p-3 mb-4 bg-light-subtle">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="whatsapp_opt_in" value="1"
                                               id="wa" @checked(old('whatsapp_opt_in'))>
                                        <label class="form-check-label" for="wa">
                                            Opt-in WhatsApp (alertas e bot)
                                        </label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="criar_conta" value="1"
                                               id="conta" @checked(old('criar_conta', true))>
                                        <label class="form-check-label" for="conta">
                                            Criar conta de portal (senha temporária)
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('encarregados.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i> Guardar encarregado
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function () {
            $('#aluno_ids').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: $('#aluno_ids').data('placeholder') || 'Pesquisar e seleccionar alunos...',
                allowClear: true,
                closeOnSelect: false,
                language: {
                    noResults: function () { return 'Nenhum aluno encontrado'; },
                    searching: function () { return 'A procurar...'; },
                    maximumSelected: function () { return 'Limite de selecção atingido'; }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
