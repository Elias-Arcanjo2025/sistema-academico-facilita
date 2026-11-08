@php
    use App\Support\PautaRegrasTransicaoMinisterio;
    use App\Support\PautaResultadoTransicao;

    $notaMin = (float) ($configuracoes['avaliacao']['nota_minima_aprovacao'] ?? 10);
    $maxReprov = (int) ($configuracoes['avaliacao']['max_disciplinas_reprovacao'] ?? 2);
    $maxDesist = (int) ($configuracoes['avaliacao']['max_disciplinas_sem_nota_desistente'] ?? 3);
    $maxRecurso12 = (int) ($configuracoes['avaliacao']['resultado_12_max_negativas_recurso']
        ?? PautaResultadoTransicao::DEFAULT_MAX_NEGATIVAS_RECURSO_12);
    $maxRecuperacao = (int) ($configuracoes['avaliacao']['resultado_max_negativas_recuperacao']
        ?? PautaResultadoTransicao::DEFAULT_MAX_NEGATIVAS_RECUPERACAO);
    $maxCont = PautaResultadoTransicao::MAX_NEGATIVAS_CONTINUIDADE;
@endphp

<div class="col-12" id="painel-regras-resultado">
    <hr class="my-1">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="text-uppercase small fw-bold text-muted">
            <i class="bi bi-flag me-1"></i>Regras de Resultado / Estado do Aluno
        </div>
        <span class="badge bg-light text-muted border small">
            Afecta boletins, estatísticas, mini-pautas e pautas
        </span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                Máx. disciplinas com negativa
                <small class="text-muted fw-normal">(reprovação geral)</small>
            </label>
            <input type="number" min="0" max="30"
                   class="form-control @error('max_disciplinas_reprovacao') is-invalid @enderror"
                   name="max_disciplinas_reprovacao" id="max_disciplinas_reprovacao"
                   value="{{ old('max_disciplinas_reprovacao', $maxReprov) }}">
            @error('max_disciplinas_reprovacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                Máx. disciplinas sem qualquer nota
                <small class="text-muted fw-normal">(desistência automática)</small>
            </label>
            <input type="number" min="0" max="30"
                   class="form-control @error('max_disciplinas_sem_nota_desistente') is-invalid @enderror"
                   name="max_disciplinas_sem_nota_desistente" id="max_disciplinas_sem_nota_desistente"
                   value="{{ old('max_disciplinas_sem_nota_desistente', $maxDesist) }}">
            @error('max_disciplinas_sem_nota_desistente')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                10.ª / 11.ª — máx. negativas para RECUPERAÇÃO
                <small class="text-muted fw-normal">(pauta T3)</small>
            </label>
            <input type="number" min="0" max="30"
                   class="form-control @error('resultado_max_negativas_recuperacao') is-invalid @enderror"
                   name="resultado_max_negativas_recuperacao" id="resultado_max_negativas_recuperacao"
                   value="{{ old('resultado_max_negativas_recuperacao', $maxRecuperacao) }}">
            @error('resultado_max_negativas_recuperacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">
                0 → <strong>TRANSITA</strong>;
                1 a este valor → <strong>RECUPERAÇÃO</strong> (terminais) ou <strong>TRANSITA/C DEF</strong> (só contínuas);
                mais → <strong>N/TRANSITA</strong>.
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                12.ª classe — máx. negativas para RECURSO
                <small class="text-muted fw-normal">(pauta T3)</small>
            </label>
            <input type="number" min="0" max="30"
                   class="form-control @error('resultado_12_max_negativas_recurso') is-invalid @enderror"
                   name="resultado_12_max_negativas_recurso" id="resultado_12_max_negativas_recurso"
                   value="{{ old('resultado_12_max_negativas_recurso', $maxRecurso12) }}">
            @error('resultado_12_max_negativas_recurso')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">
                0 negativas → <strong>APTO</strong>;
                1 a este valor → <strong>RECURSO</strong>;
                mais → <strong>N/APTO</strong>.
            </div>
        </div>
    </div>

    {{-- Pré-visualização dinâmica --}}
    <div class="card border-info border-opacity-50 mb-3">
        <div class="card-body py-3 small">
            <h6 class="text-info fw-bold mb-2">
                <i class="bi bi-eye me-1"></i>Pré-visualização do efeito (valores actuais do formulário)
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 bg-white">
                    <thead class="table-light">
                        <tr>
                            <th>Regra configurável</th>
                            <th class="text-center" style="width:28%">Cenário</th>
                            <th>Resultado no sistema</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-exemplos-regras">
                        <tr>
                            <td>Nota &lt; <span class="preview-nota-min">{{ number_format($notaMin, 1, ',', '') }}</span> = negativa</td>
                            <td class="text-center">1 disciplina com <span class="preview-nota-exemplo">{{ max(0, (int) floor($notaMin - 1)) }}</span></td>
                            <td><span class="badge bg-danger">Negativa</span> (conta para reprovação)</td>
                        </tr>
                        <tr data-regra="reprov">
                            <td>Máx. <strong class="preview-max-reprov">{{ $maxReprov }}</strong> negativas permitidas</td>
                            <td class="text-center"><span class="preview-reprov-ok-qtd">{{ $maxReprov }}</span> negativas</td>
                            <td><span class="badge bg-success preview-reprov-ok">Aprovado / APTO</span></td>
                        </tr>
                        <tr data-regra="reprov">
                            <td>Máx. <strong class="preview-max-reprov">{{ $maxReprov }}</strong> negativas permitidas</td>
                            <td class="text-center"><span class="preview-reprov-fail-qtd">{{ $maxReprov + 1 }}</span> negativas</td>
                            <td><span class="badge bg-danger preview-reprov-fail">Reprovado / NÃO APTO</span></td>
                        </tr>
                        <tr data-regra="desist">
                            <td>Máx. <strong class="preview-max-desist">{{ $maxDesist }}</strong> disciplinas sem MAC/PP/PT</td>
                            <td class="text-center"><span class="preview-desist-ok-qtd">{{ $maxDesist }}</span> sem nota</td>
                            <td><span class="badge bg-secondary preview-desist-ok">Ainda activo</span></td>
                        </tr>
                        <tr data-regra="desist">
                            <td>Máx. <strong class="preview-max-desist">{{ $maxDesist }}</strong> disciplinas sem MAC/PP/PT</td>
                            <td class="text-center"><span class="preview-desist-fail-qtd">{{ $maxDesist + 1 }}</span> sem nota</td>
                            <td><span class="badge bg-dark preview-desist-fail">Desistente automático</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted mb-0 mt-2">
                <i class="bi bi-info-circle me-1"></i>
                «Mais do que N» significa: com <strong>N+1</strong> ou mais disciplinas aplica-se a consequência.
            </p>
        </div>
    </div>

    {{-- Transição DE 424/25 (10.ª/11.ª) + Resultado 12.ª --}}
    <div class="card border-success border-opacity-50">
        <div class="card-body py-3 small">
            <h6 class="text-success fw-bold mb-1">
                <i class="bi bi-journal-check me-1"></i>
                Resultado na pauta T3 (10.ª–12.ª)
            </h6>
            <p class="text-muted mb-2">
                Limiares de <strong>RECUPERAÇÃO</strong> (10.ª/11.ª) e <strong>RECURSO</strong> (12.ª) configuráveis acima.
            </p>

            <div class="row g-2">
                <div class="col-lg-4">
                    <div class="border rounded p-2 h-100 bg-light">
                        <strong class="d-block mb-1">10.ª / 11.ª</strong>
                        <span class="badge bg-primary me-1">TRANSITA</span>
                        <span class="text-muted">0 negativas</span><br>
                        <span class="badge bg-warning text-dark me-1">TRANSITA/C DEF</span>
                        <span class="text-muted">só contínuas (≤{{ $maxRecuperacao }})</span><br>
                        <span class="badge bg-warning text-dark me-1">RECUPERAÇÃO</span>
                        <span class="text-muted">terminais (≤{{ $maxRecuperacao }})</span><br>
                        <span class="badge bg-danger me-1">N/TRANSITA</span>
                        <span class="text-muted">&gt;{{ $maxRecuperacao }} ou {{ $maxCont }}+ em continuidade</span>
                        <hr class="my-2">
                        <span class="text-muted">OBS: <code>Def.: …</code> | <code>Rec.: …</code></span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-2 h-100 bg-light">
                        <strong class="d-block mb-1">12.ª classe</strong>
                        <span class="badge bg-primary me-1">APTO</span>
                        <span class="text-muted">0 negativas</span><br>
                        <span class="badge bg-warning text-dark me-1">RECURSO</span>
                        <span class="text-muted">1 a {{ $maxRecurso12 }} negativas</span><br>
                        <span class="badge bg-danger me-1">N/APTO</span>
                        <span class="text-muted">&gt;{{ $maxRecurso12 }} negativas</span>
                        <hr class="my-2">
                        <span class="text-muted">OBS RECURSO: <code>Rec.: MAT, TLP…</code><br>
                        OBS N/APTO: lista de todas as negativas</span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-2 h-100 bg-light">
                        <strong class="d-block mb-1">O que conta como negativa</strong>
                        <ul class="mb-0 ps-3">
                            <li>Disciplinas de <strong>continuidade</strong> → CA &lt; {{ PautaRegrasTransicaoMinisterio::APROVACAO_MINIMA }}</li>
                            <li>Disciplinas <strong>terminais</strong> (acabam nesta classe) → CFD &lt; {{ PautaRegrasTransicaoMinisterio::APROVACAO_MINIMA }}</li>
                            <li>A partir da 11.ª: CFD de terminais anteriores também conta</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
