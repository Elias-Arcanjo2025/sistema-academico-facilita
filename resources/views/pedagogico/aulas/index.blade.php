<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">Aulas — {{ $planificacao->disciplina->nome ?? '' }}</h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-calendar3" variant="info" title="Calendário de aulas" id="tip-aulas-index" compact>
                Lista das aulas geradas após activar a planificação. Pode marcar estados (ministrada, adiada, etc.) e registar o conteúdo efectivamente dado.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))<div class="alert alert-{{ $variant }}">{{ session($key) }}</div>@endif
            @endforeach

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Data</th><th>Horário</th><th>Tempos</th><th>Tema</th><th>Obj. geral</th><th>Obj. específicos</th><th>Estado</th><th>Acções</th></tr>
                        </thead>
                        <tbody>
                            @foreach($planificacao->aulas as $aula)
                            <tr>
                                <td>{{ $aula->data?->format('d/m/Y') }}</td>
                                <td>{{ $aula->hora_inicio ? substr($aula->hora_inicio,0,5) : '-' }} - {{ $aula->hora_fim ? substr($aula->hora_fim,0,5) : '-' }}</td>
                                <td>{{ $aula->tempos ?? 1 }}T</td>
                                <td>{{ Str::limit($aula->tema_planeado ?? '—', 35) }}</td>
                                <td>{{ Str::limit($aula->objetivos_geral ?? '—', 30) }}</td>
                                <td>{{ Str::limit($aula->objetivos_especificos ?? '—', 30) }}</td>
                                <td><span class="badge bg-secondary">{{ $aula->estado }}</span></td>
                                <td>
                                    @can('pedagogico.planificacao.editar')
                                    @if(in_array($aula->estado, ['planeada','adiada']))
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAula{{ $aula->id }}">Registar</button>
                                    @endif
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @can('pedagogico.planificacao.editar')
            @foreach($planificacao->aulas as $aula)
            <div class="modal fade" id="modalAula{{ $aula->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('pedagogico.aulas.update', $aula) }}" class="modal-content">
                        @csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Actualizar Aula</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Acção</label>
                                <select name="accao" class="form-select" required>
                                    <option value="ministrada">Ministrada</option>
                                    <option value="adiar">Adiar</option>
                                    <option value="cancelar">Cancelar</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data</label>
                                <input type="date" name="data" class="form-control" value="{{ $aula->data?->format('Y-m-d') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tema ministrado</label>
                                <input type="text" name="tema_ministrado" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Conteúdo ministrado</label>
                                <textarea name="conteudo_ministrado" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Motivo (adiar/cancelar)</label>
                                <input type="text" name="motivo" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
            @endcan

            <a href="{{ route('pedagogico.planificacoes.show', $planificacao) }}" class="btn btn-outline-secondary mt-3">Voltar</a>
        </div>
    </div>
</x-app-layout>
