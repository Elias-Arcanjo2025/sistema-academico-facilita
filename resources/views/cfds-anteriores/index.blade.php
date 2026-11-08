<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-calendar-check me-2 text-success"></i>CAs de Anos Anteriores
                </h2>
                <small class="text-muted">
                    Lance manualmente as CAs em falta — a CFD é calculada automaticamente na pauta.
                </small>
            </div>
            <a href="{{ route('pautas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-info-circle" variant="info" title="Quando usar esta página"
                        id="tip-cas-anteriores">
                Use quando faltam CAs no histórico (escola nova no sistema, alunos transferidos, etc.).
                <br><strong>11.ª:</strong> lance <strong>CA-10ª</strong> — a CFD na pauta calcula (CA-10ª + CA-11ª) / 2.
                <br><strong>12.ª:</strong> lance <strong>CA-10ª</strong> e <strong>CA-11ª</strong> — a CFD calcula (CA-10ª + CA-11ª + CA-12ª) / 3.
            </x-tip-card>

            <div class="card shadow-soft">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Turmas elegíveis (11.ª e 12.ª)</h5>
                    <span class="badge bg-primary">{{ count($turmas) }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Turma</th>
                                <th>Classe</th>
                                <th>Curso</th>
                                <th style="width: 180px;" class="text-end">Acção</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($turmas as $t)
                                <tr>
                                    <td class="fw-bold">{{ $t->nome }}</td>
                                    <td>{{ $t->classe?->nome ?? '—' }}</td>
                                    <td>{{ $t->curso?->nome ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('cfds-anteriores.editar', ['turma' => $t, 'ano_lectivo' => $anoLectivo]) }}"
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-pencil-square me-1"></i>Lançar CAs
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Nenhuma turma da 11.ª ou 12.ª encontrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
