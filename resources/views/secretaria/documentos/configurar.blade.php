<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Personalizar documentos académicos</h2></x-slot>
    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Documentos', 'url' => route('secretaria.documentos.index')],
                ['label' => 'Personalizar', 'url' => route('secretaria.documentos.configurar')],
            ]" />

            <x-tip-card icon="bi-sliders" variant="info" title="Personalização de documentos" id="tip-secretaria-documentos-config" compact>
                Defina títulos, cor, campos visíveis e textos de fecho. A pré-visualização à direita actualiza em tempo real antes de guardar.
            </x-tip-card>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <form method="POST" action="{{ route('secretaria.documentos.configurar.guardar') }}" id="formDocsConfig">
                            @csrf
                            <h6 class="fw-semibold mb-3">Títulos dos documentos</h6>
                            <div class="mb-2">
                                <label class="form-label">Declaração de frequência</label>
                                <input type="text" name="titulo_frequencia" id="titulo_frequencia" class="form-control preview-field" value="{{ $config['titulo_frequencia'] }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Declaração de matrícula</label>
                                <input type="text" name="titulo_matricula" id="titulo_matricula" class="form-control preview-field" value="{{ $config['titulo_matricula'] }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Comprovativo de inscrição</label>
                                <input type="text" name="titulo_comprovativo" id="titulo_comprovativo" class="form-control preview-field" value="{{ $config['titulo_comprovativo'] }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ficha do aluno</label>
                                <input type="text" name="titulo_ficha" id="titulo_ficha" class="form-control preview-field" value="{{ $config['titulo_ficha'] }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cor de destaque</label>
                                <input type="color" name="cor_destaque" id="cor_destaque" class="form-control form-control-color preview-field" value="{{ $config['cor_destaque'] }}">
                            </div>

                            <h6 class="fw-semibold mb-2">Campos visíveis</h6>
                            <div class="mb-3">
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_logo" value="1" id="mostrar_logo" @checked($config['mostrar_logo'])><label class="form-check-label" for="mostrar_logo">Logótipo / cabeçalho institucional</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_bi" value="1" id="mostrar_bi" @checked($config['mostrar_bi'])><label class="form-check-label" for="mostrar_bi">Nº BI</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_filiacao" value="1" id="mostrar_filiacao" @checked($config['mostrar_filiacao'])><label class="form-check-label" for="mostrar_filiacao">Nome dos pais</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_encarregado" value="1" id="mostrar_encarregado" @checked($config['mostrar_encarregado'])><label class="form-check-label" for="mostrar_encarregado">Encarregado de educação</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_nascimento" value="1" id="mostrar_nascimento" @checked($config['mostrar_nascimento'])><label class="form-check-label" for="mostrar_nascimento">Data de nascimento</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_genero" value="1" id="mostrar_genero" @checked($config['mostrar_genero'])><label class="form-check-label" for="mostrar_genero">Género</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_foto_ficha" value="1" id="mostrar_foto_ficha" @checked($config['mostrar_foto_ficha'])><label class="form-check-label" for="mostrar_foto_ficha">Foto na ficha</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_assinatura" value="1" id="mostrar_assinatura" @checked($config['mostrar_assinatura'])><label class="form-check-label" for="mostrar_assinatura">Área de assinatura</label></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Local de emissão</label>
                                <input type="text" name="local_emissao" id="local_emissao" class="form-control preview-field" value="{{ $config['local_emissao'] }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cargo na assinatura</label>
                                <input type="text" name="cargo_assinatura" id="cargo_assinatura" class="form-control preview-field" value="{{ $config['cargo_assinatura'] }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto de fecho (frequência)</label>
                                <textarea name="texto_fecho_frequencia" id="texto_fecho_frequencia" class="form-control preview-field" rows="2">{{ $config['texto_fecho_frequencia'] }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto de fecho (matrícula)</label>
                                <textarea name="texto_fecho_matricula" id="texto_fecho_matricula" class="form-control preview-field" rows="2">{{ $config['texto_fecho_matricula'] }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rodapé</label>
                                <input type="text" name="texto_rodape" id="texto_rodape" class="form-control preview-field" value="{{ $config['texto_rodape'] }}">
                            </div>

                            <button class="btn btn-primary">Guardar personalização</button>
                            <a href="{{ route('secretaria.documentos.index') }}" class="btn btn-outline-secondary">Emitir documentos</a>
                        </form>
                    </div></div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Pré-visualização em tempo real</span>
                            <select id="preview_tipo" class="form-select form-select-sm w-auto">
                                <option value="frequencia">Frequência</option>
                                <option value="matricula">Matrícula</option>
                                <option value="comprovativo">Comprovativo</option>
                                <option value="ficha">Ficha</option>
                            </select>
                        </div>
                        <div class="card-body bg-light">
                            <div id="preview-doc" class="bg-white border rounded-3 p-3 shadow-sm" style="min-height:420px;">
                                <div id="prev-header" class="text-center mb-3">
                                    <div id="prev-logo" class="mx-auto mb-2 rounded" style="width:48px;height:48px;background:#e5e7eb;line-height:48px;font-size:10px;">LOGO</div>
                                    <div class="small fw-bold text-uppercase">República de Angola</div>
                                    <div class="small text-muted">Ministério da Educação</div>
                                    <div class="fw-semibold" id="prev-escola" style="font-size:13px;">{{ $instituicao['nome'] ?? 'Instituição' }}</div>
                                    <hr class="my-2">
                                </div>
                                <div id="prev-titulo" class="text-center fw-bold text-uppercase text-decoration-underline mb-3" style="font-size:15px;"></div>
                                <div class="small" style="text-align:justify;line-height:1.6;">
                                    Declaramos que o(a) estudante <strong>Exemplo Estudante</strong>,
                                    nº processo <strong>2026001</strong><span id="prev-bi-inline">, BI nº <strong>000000000LA000</strong></span><span id="prev-filiacao-inline">, filho(a) de <strong>Pai Exemplo</strong> e de <strong>Mãe Exemplo</strong></span>,
                                    frequenta a turma <strong>TI12AM</strong> do curso <strong>Informática</strong>.
                                </div>
                                <div id="prev-caixa" class="border rounded p-2 mt-3 small bg-light">
                                    <div id="prev-nascimento"><strong>Nascimento:</strong> 01/01/2010</div>
                                    <div id="prev-genero"><strong>Género:</strong> Masculino</div>
                                    <div id="prev-encarregado"><strong>Encarregado:</strong> Encarregado Exemplo — 923 000 000</div>
                                    <div id="prev-foto-ficha" class="mt-2"><span class="badge text-bg-secondary">Foto na ficha: activa</span></div>
                                </div>
                                <div id="prev-fecho" class="small mt-3" style="text-align:justify;"></div>
                                <div id="prev-local" class="small mt-3"></div>
                                <div id="prev-assinatura" class="text-center mt-4">
                                    <div class="border-top mx-auto mb-1" style="width:180px;"></div>
                                    <div id="prev-cargo" class="small"></div>
                                </div>
                                <div id="prev-rodape" class="text-center text-muted mt-3" style="font-size:11px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            function el(id) { return document.getElementById(id); }
            function checked(id) { const n = el(id); return n ? n.checked : false; }

            function actualizarPreview() {
                const tipo = el('preview_tipo').value;
                const titulos = {
                    frequencia: el('titulo_frequencia').value,
                    matricula: el('titulo_matricula').value,
                    comprovativo: el('titulo_comprovativo').value,
                    ficha: el('titulo_ficha').value
                };
                const cor = el('cor_destaque').value;
                el('prev-titulo').textContent = titulos[tipo] || '';
                el('prev-titulo').style.color = cor;
                el('prev-escola').style.color = cor;
                el('prev-header').style.display = checked('mostrar_logo') ? 'block' : 'none';
                el('prev-bi-inline').style.display = checked('mostrar_bi') ? 'inline' : 'none';
                el('prev-filiacao-inline').style.display = checked('mostrar_filiacao') ? 'inline' : 'none';
                el('prev-nascimento').style.display = checked('mostrar_nascimento') ? 'block' : 'none';
                el('prev-genero').style.display = checked('mostrar_genero') ? 'block' : 'none';
                el('prev-encarregado').style.display = checked('mostrar_encarregado') ? 'block' : 'none';
                el('prev-foto-ficha').style.display = (tipo === 'ficha' && checked('mostrar_foto_ficha')) ? 'block' : 'none';
                el('prev-fecho').textContent = tipo === 'matricula'
                    ? el('texto_fecho_matricula').value
                    : el('texto_fecho_frequencia').value;
                el('prev-local').textContent = (el('local_emissao').value || 'Local') + ', aos 14 de Julho de 2026.';
                el('prev-cargo').textContent = el('cargo_assinatura').value;
                el('prev-assinatura').style.display = checked('mostrar_assinatura') ? 'block' : 'none';
                el('prev-rodape').textContent = el('texto_rodape').value;
            }

            function bind() {
                document.querySelectorAll('#formDocsConfig .preview-field, #preview_tipo').forEach(function (node) {
                    node.addEventListener('input', actualizarPreview);
                    node.addEventListener('change', actualizarPreview);
                });
                actualizarPreview();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bind);
            } else {
                bind();
            }
        })();
    </script>
</x-app-layout>
