<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Detalhes do Aluno</h2>
            <a href="{{ route('alunos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informações do Aluno</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex gap-3 mb-3">
                                        @if($aluno->foto_path)
                                            <img src="{{ $aluno->fotoUrl() }}" alt="Foto" class="rounded border" style="width:88px;height:110px;object-fit:cover;">
                                        @endif
                                        <dl class="row mb-0 flex-grow-1">
                                        <dt class="col-sm-5">N.º de Processo</dt>
                                        <dd class="col-sm-7"><code>{{ $aluno->numero_processo }}</code></dd>

                                        <dt class="col-sm-5">Nome</dt>
                                        <dd class="col-sm-7">
                                            {{ $aluno->nome }}
                                            @if($aluno->educacao_especial)
                                                <span class="badge bg-info ms-1" title="{{ $aluno->necessidades_especificas }}" data-bs-toggle="tooltip">
                                                    <i class="bi bi-universal-access"></i> NEE
                                                </span>
                                            @endif
                                        </dd>
                                        @if($aluno->bi_numero)
                                            <dt class="col-sm-5">Nº BI</dt>
                                            <dd class="col-sm-7">{{ $aluno->bi_numero }}</dd>
                                        @endif
                                        @if($aluno->educacao_especial && $aluno->necessidades_especificas)
                                            <dt class="col-sm-5">Necessidades específicas</dt>
                                            <dd class="col-sm-7"><small class="text-muted">{{ $aluno->necessidades_especificas }}</small></dd>
                                        @endif

                                        <dt class="col-sm-5">Data de Nascimento</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->data_nascimento)
                                                {{ $aluno->data_nascimento->format('d/m/Y') }}
                                                <span class="text-muted">({{ $aluno->idade }} anos)</span>
                                            @else
                                                -
                                            @endif
                                        </dd>

                                        <dt class="col-sm-5">Genero</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->genero === 'M')
                                                Masculino
                                            @elseif($aluno->genero === 'F')
                                                Feminino
                                            @else
                                                -
                                            @endif
                                        </dd>
                                    </dl>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Turma</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->turma)
                                                <a href="{{ route('turmas.show', $aluno->turma) }}">{{ $aluno->turma->nome }}</a>
                                            @else
                                                -
                                            @endif
                                        </dd>

                                        <dt class="col-sm-5">Curso</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->turma?->curso)
                                                <a href="{{ route('cursos.show', $aluno->turma->curso) }}">{{ $aluno->turma->curso->nome }}</a>
                                            @else
                                                -
                                            @endif
                                        </dd>

                                        <dt class="col-sm-5">N.º de Ordem</dt>
                                        <dd class="col-sm-7"><strong>{{ $aluno->numero_ordem }}</strong></dd>

                                        <dt class="col-sm-5">Estado</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->activo)
                                                <span class="badge bg-success">Ativo</span>
                                            @else
                                                <span class="badge bg-secondary">Inativo</span>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-5">Portal — notas</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->consulta_notas_portal)
                                                <span class="badge bg-success">Liberado</span>
                                            @else
                                                <span class="badge bg-danger">Bloqueado</span>
                                            @endif
                                            @can('update', $aluno)
                                                <form action="{{ route('alunos.toggle-consulta-portal', $aluno) }}" method="POST" class="d-inline ms-2">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-{{ $aluno->consulta_notas_portal ? 'danger' : 'success' }}">
                                                        {{ $aluno->consulta_notas_portal ? 'Bloquear' : 'Liberar' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </dd>
                                        @if(!$aluno->consulta_notas_portal && $aluno->motivo_bloqueio_portal)
                                            <dt class="col-sm-5">Motivo bloqueio</dt>
                                            <dd class="col-sm-7"><small class="text-muted">{{ $aluno->motivo_bloqueio_portal }}</small></dd>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Turno</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->turma?->turno)
                                                {{ $aluno->turma->turno->nome }}
                                            @else
                                                -
                                            @endif
                                        </dd>

                                        <dt class="col-sm-5">Sala</dt>
                                        <dd class="col-sm-7">
                                            @if($aluno->turma?->sala)
                                                {{ $aluno->turma->sala->nome }}
                                            @else
                                                -
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Criado em</dt>
                                        <dd class="col-sm-7">{{ $aluno->created_at->format('d/m/Y H:i') }}</dd>

                                        <dt class="col-sm-5">Atualizado em</dt>
                                        <dd class="col-sm-7">{{ $aluno->updated_at->format('d/m/Y H:i') }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        @can('update', $aluno)
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <a href="{{ route('alunos.edit', $aluno) }}" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            @can('secretaria.ver')
                                <a href="{{ route('secretaria.matriculas.historico', $aluno) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-clock-history"></i> Histórico de matrícula
                                </a>
                            @endcan
                            @can('secretaria.documentos.emitir')
                                <a href="{{ route('secretaria.documentos.emitir', ['aluno_id' => $aluno->id, 'tipo' => 'ficha']) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-file-earmark-pdf"></i> Ficha PDF
                                </a>
                            @endcan
                        </div>
                    @endcan
                        @can('reporPalavraPasse', $aluno)
                            <div class="card-footer border-top bg-light">
                                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                                    <div class="small text-muted">
                                        <i class="bi bi-key me-1"></i>
                                        Conta de acesso:
                                        @if($aluno->user)
                                            <span class="text-success">activa</span>
                                            @if($aluno->user->must_change_password)
                                                <span class="badge bg-warning text-dark ms-1">deve alterar senha</span>
                                            @endif
                                        @else
                                            <span class="text-warning">sem conta</span>
                                        @endif
                                    </div>
                                    <form action="{{ route('alunos.repor-palavra-passe', $aluno) }}" method="POST"
                                          onsubmit="return confirm('Gerar uma nova palavra-passe temporária para este aluno? Será apresentada uma única vez e o aluno terá de alterá-la no próximo acesso.');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i> Repor palavra-passe
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    </div>

                    <!-- Documentos digitalizados -->
                    @can('view', $aluno)
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Documentos</h5>
                            </div>
                            <div class="card-body">
                                @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead><tr><th>Título</th><th>Tipo</th><th>Data</th><th></th></tr></thead>
                                        <tbody>
                                            @forelse($aluno->documentos as $doc)
                                                <tr>
                                                    <td>{{ $doc->titulo }}</td>
                                                    <td>{{ \App\Models\AlunoDocumento::tipos()[$doc->tipo] ?? $doc->tipo }}</td>
                                                    <td>{{ $doc->created_at?->format('d/m/Y') }}</td>
                                                    <td class="text-end">
                                                        <a href="{{ route('secretaria.alunos.documentos.download', [$aluno, $doc]) }}" class="btn btn-link btn-sm">Download</a>
                                                        @can('secretaria.alunos.documentos')
                                                            <form action="{{ route('secretaria.alunos.documentos.destroy', [$aluno, $doc]) }}" method="POST" class="d-inline" onsubmit="return confirm('Eliminar documento?');">
                                                                @csrf @method('DELETE')
                                                                <button class="btn btn-link btn-sm text-danger">Eliminar</button>
                                                            </form>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-muted">Sem documentos anexados.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @can('secretaria.alunos.documentos')
                                    <form method="POST" action="{{ route('secretaria.alunos.documentos.store', $aluno) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-3">
                                            <label class="form-label">Tipo</label>
                                            <select name="tipo" class="form-select form-select-sm" required>
                                                @foreach(\App\Models\AlunoDocumento::tipos() as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Título</label>
                                            <input type="text" name="titulo" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Ficheiro</label>
                                            <input type="file" name="ficheiro" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-primary w-100">Anexar</button>
                                        </div>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endcan

                    <!-- Card de Transferência de Turma -->
                    @can('update', $aluno)
                        <div class="card mb-4">
                            <div class="card-header bg-warning bg-opacity-10">
                                <h5 class="mb-0">
                                    <i class="bi bi-arrow-left-right me-2"></i>Transferência de Turma
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Transferir o aluno para outra turma do mesmo ano lectivo (<strong>{{ $aluno->turma->ano_lectivo }}</strong>).
                                </p>

                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Atenção:</strong> A transferência irá:
                                    <ul class="mb-0 mt-2">
                                        <li>Mover o aluno para a turma de destino</li>
                                        <li>Recalcular números de ordem em ambas as turmas</li>
                                        <li>Permitir escolher se mantém ou remove as notas lançadas ({{ $aluno->notas->count() }} nota(s))</li>
                                    </ul>
                                </div>

                                <a href="{{ route('alunos.transfer-form', $aluno) }}" class="btn btn-warning">
                                    <i class="bi bi-arrow-left-right me-1"></i>Iniciar Transferência
                                </a>
                            </div>
                        </div>
                    @endcan

                    @if(auth()->user()->temAcessoSecretariaAlunos() || auth()->user()->temVisaoInstitucional())
                        @can('update', $aluno)
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-archive me-2"></i>Arquivar aluno</h5>
                                </div>
                                <div class="card-body">
                                    @if($aluno->activo)
                                        <p class="text-muted small">Inactiva o aluno e regista no histórico de matrículas. Não elimina dados.</p>
                                        <form method="POST" action="{{ route('alunos.arquivar', $aluno) }}"
                                              data-confirm="Arquivar este aluno? Ficará inactivo."
                                              data-confirm-title="Arquivar aluno"
                                              data-confirm-icon="warning"
                                              data-confirm-ok="Sim, arquivar"
                                              data-confirm-danger="1">
                                            @csrf
                                            <div class="mb-2">
                                                <label class="form-label">Motivo *</label>
                                                <input type="text" name="motivo" class="form-control form-control-sm" required maxlength="500">
                                            </div>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Arquivar</button>
                                        </form>
                                    @else
                                        <p class="text-muted mb-0">Aluno já arquivado/inactivo.</p>
                                    @endif
                                </div>
                            </div>
                        @endcan
                    @endif

                    @php
                        $turmaEscopo = app(\App\Services\TurmaEscopoService::class);
                        $conversaService = app(\App\Services\Portal\PortalConversaService::class);
                        $podeEmailAluno = auth()->user()->temAcessoSecretariaAlunos()
                            || auth()->user()->temVisaoInstitucional()
                            || (auth()->user()->can('turma.comunicar') && $aluno->turma_id
                                && $turmaEscopo->podeAcederTurma(auth()->user(), (int) $aluno->turma_id));
                        $podeMsgPortal = $aluno->turma_id
                            && $conversaService->podeUsarInboxStaff(auth()->user())
                            && $turmaEscopo->podeAcederTurma(auth()->user(), (int) $aluno->turma_id)
                            && $conversaService->resolverEncarregadoUserDoAluno($aluno);
                    @endphp
                    @if($podeMsgPortal)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Mensagem no portal</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-2">Fale com o encarregado na app — recebe notificação no portal.</p>
                                <a href="{{ route('mensagens-encarregados.create', ['aluno_id' => $aluno->id]) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-send"></i> Iniciar conversa
                                </a>
                            </div>
                        </div>
                    @endif
                    @if($podeEmailAluno)
                        @can('update', $aluno)
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Comunicar com encarregado</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('alunos.enviar-email', $aluno) }}">
                                        @csrf
                                        @php
                                            $emailEncarregado = $aluno->encarregados->first()?->email;
                                        @endphp
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Destinatário *</label>
                                                <input type="email" name="destinatario" class="form-control form-control-sm"
                                                       value="{{ old('destinatario', $emailEncarregado) }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Nome do destinatário</label>
                                                <input type="text" name="nome_destinatario" class="form-control form-control-sm"
                                                       value="{{ old('nome_destinatario', $aluno->encarregados->first()?->nome) }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Assunto *</label>
                                                <input type="text" name="assunto" class="form-control form-control-sm" required maxlength="160">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Mensagem *</label>
                                                <textarea name="mensagem" class="form-control form-control-sm" rows="4" required maxlength="5000"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary btn-sm">Enviar email</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    @endif

                    @if(!empty($cargosAssinatura))
                        <div class="card mb-4 fade-in-scale">
                            <div class="card-header bg-gradient-primary">
                                <h5 class="mb-0 text-white">
                                    <i class="bi bi-pen me-2"></i>Assinatura digital
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    Assine o boletim ou documentos académicos como Director, Coordenador ou Secretaria.
                                    Ano lectivo: <strong>{{ $anoAssinatura }}</strong>
                                </p>

                                @if($assinaturasBoletim->isNotEmpty())
                                    <div class="mb-3">
                                        <div class="fw-semibold mb-1">Assinaturas do boletim (anual)</div>
                                        <ul class="list-unstyled small mb-0">
                                            @foreach($assinaturasBoletim as $a)
                                                <li class="mb-1">
                                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                                    {{ \App\Models\DocumentoAssinatura::cargos()[$a->cargo] ?? $a->cargo }}
                                                    — {{ $a->user?->name ?? '—' }}
                                                    <span class="text-muted">({{ $a->assinado_em?->format('d/m/Y H:i') }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('documentos.assinar-boletim', $aluno) }}">
                                            @csrf
                                            <label class="form-label">Assinar boletim</label>
                                            <div class="input-group input-group-sm mb-2">
                                                <select name="cargo" class="form-select" required>
                                                    @foreach($cargosAssinatura as $cargo => $rotulo)
                                                        <option value="{{ $cargo }}">{{ $rotulo }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="trimestre" class="form-select" style="max-width:7rem">
                                                    <option value="">Anual</option>
                                                    <option value="1">1.º T</option>
                                                    <option value="2">2.º T</option>
                                                    <option value="3">3.º T</option>
                                                </select>
                                            </div>
                                            <input type="hidden" name="ano_lectivo" value="{{ $anoAssinatura }}">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pen me-1"></i>Assinar boletim
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('documentos.assinar-academico', $aluno) }}">
                                            @csrf
                                            <label class="form-label">Assinar documento</label>
                                            <div class="input-group input-group-sm mb-2">
                                                <select name="tipo_documento" class="form-select" required>
                                                    <option value="frequencia">Declaração de frequência</option>
                                                    <option value="matricula">Certificado / matrícula</option>
                                                    <option value="comprovativo">Comprovativo</option>
                                                    <option value="ficha">Ficha do aluno</option>
                                                </select>
                                                <select name="cargo" class="form-select" required>
                                                    @foreach($cargosAssinatura as $cargo => $rotulo)
                                                        <option value="{{ $cargo }}">{{ $rotulo }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="hidden" name="ano_lectivo" value="{{ $anoAssinatura }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark-check me-1"></i>Assinar documento
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Situação Académica -->
                    <div class="card mb-4 fade-in-scale">
                        <div class="card-header bg-gradient-primary">
                            <h5 class="mb-0 text-white">
                                <i class="bi bi-trophy me-2"></i>Situação Académica
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center mb-3">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Situação Atual:</strong></p>
                                    <div>{!! $aluno->situacao_academica_badge !!}</div>
                                </div>
                                <div class="col-md-6 text-end">
                                    @if($aluno->situacao_academica === 'aprovado')
                                        <i class="bi bi-emoji-smile fs-1 text-success"></i>
                                    @elseif($aluno->situacao_academica === 'reprovado')
                                        <i class="bi bi-emoji-frown fs-1 text-danger"></i>
                                    @elseif($aluno->situacao_academica === 'desistente')
                                        <i class="bi bi-emoji-neutral fs-1 text-warning"></i>
                                    @elseif($aluno->situacao_academica === 'transferido')
                                        <i class="bi bi-arrow-right-circle fs-1 text-info"></i>
                                    @else
                                        <i class="bi bi-person-check fs-1 text-primary"></i>
                                    @endif
                                </div>
                            </div>

                            @can('updateSituacao', $aluno)
                                <hr>
                                <form action="{{ route('alunos.update-situacao', $aluno) }}" method="POST" id="formSituacao">
                                    @csrf
                                    @method('PATCH')

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="situacao_academica" class="form-label">
                                                    <i class="bi bi-check-circle me-1"></i>Alterar Situação
                                                </label>
                                                <select class="form-select @error('situacao_academica') is-invalid @enderror"
                                                        id="situacao_academica" name="situacao_academica" required>
                                                    <option value="matriculado" {{ $aluno->situacao_academica === 'matriculado' ? 'selected' : '' }}>
                                                        Matriculado
                                                    </option>
                                                    <option value="aprovado" {{ $aluno->situacao_academica === 'aprovado' ? 'selected' : '' }}>
                                                        Aprovado
                                                    </option>
                                                    <option value="reprovado" {{ $aluno->situacao_academica === 'reprovado' ? 'selected' : '' }}>
                                                        Reprovado
                                                    </option>
                                                    <option value="desistente" {{ $aluno->situacao_academica === 'desistente' ? 'selected' : '' }}>
                                                        Desistente
                                                    </option>
                                                    <option value="transferido" {{ $aluno->situacao_academica === 'transferido' ? 'selected' : '' }}>
                                                        Transferido
                                                    </option>
                                                </select>
                                                @error('situacao_academica')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="observacao" class="form-label">
                                                    <i class="bi bi-chat-left-text me-1"></i>Observação (Opcional)
                                                </label>
                                                <textarea class="form-control @error('observacao') is-invalid @enderror"
                                                          id="observacao" name="observacao" rows="3"
                                                          maxlength="500"
                                                          placeholder="Descreva o motivo da alteração..."></textarea>
                                                <div class="form-text">Máximo 500 caracteres</div>
                                                @error('observacao')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info d-flex align-items-center" role="alert">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <div>
                                            Esta alteração ficará registada no histórico do aluno e poderá ser visualizada no log de auditoria.
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Actualizar Situação
                                    </button>
                                </form>
                            @else
                                <div class="alert alert-warning mb-0 mt-3">
                                    <i class="bi bi-lock me-2"></i>
                                    Não tem permissão para alterar a situação académica deste aluno.
                                </div>
                            @endcan
                        </div>
                    </div>

                    @if($aluno->notas->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Notas do Aluno</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Disciplina</th>
                                                <th>Periodo</th>
                                                <th>Nota</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($aluno->notas as $nota)
                                                <tr>
                                                    <td>{{ $nota->disciplina->nome }}</td>
                                                    <td>{{ $nota->periodo }}</td>
                                                    <td>
                                                        <span class="badge {{ $nota->valor >= 10 ? 'bg-success' : 'bg-danger' }}">
                                                            {{ number_format($nota->valor, 0) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $nota->created_at->format('d/m/Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
