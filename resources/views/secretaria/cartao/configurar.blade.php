<x-app-layout>
    <x-slot name="header"><h2 class="h4 mb-0">Personalizar cartão de estudante</h2></x-slot>
    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[
                ['label' => 'Secretaria', 'url' => route('secretaria.dashboard')],
                ['label' => 'Cartões', 'url' => route('secretaria.cartao.emitir')],
                ['label' => 'Personalizar', 'url' => route('secretaria.cartao.configurar')],
            ]" />

            <x-tip-card icon="bi-palette" variant="info" title="Personalização do cartão" id="tip-secretaria-cartao-config" compact>
                Ajuste cores, campos visíveis, validade e verso. A pré-visualização actualiza em tempo real; guarde antes de emitir PDFs.
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
                        <form method="POST" action="{{ route('secretaria.cartao.guardar') }}" id="formCartao">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Título</label>
                                <input type="text" name="titulo" id="titulo" class="form-control preview-field" value="{{ $config['titulo'] }}" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label">Cor primária</label>
                                    <input type="color" name="cor_primaria" id="cor_primaria" class="form-control form-control-color w-100 preview-field" value="{{ $config['cor_primaria'] }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Cor secundária</label>
                                    <input type="color" name="cor_secundaria" id="cor_secundaria" class="form-control form-control-color w-100 preview-field" value="{{ $config['cor_secundaria'] }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Cor do texto</label>
                                    <input type="color" name="cor_texto" id="cor_texto" class="form-control form-control-color w-100 preview-field" value="{{ $config['cor_texto'] }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Campos visíveis</label>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_logo" value="1" id="mostrar_logo" @checked($config['mostrar_logo'] ?? true)><label class="form-check-label" for="mostrar_logo">Logótipo</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_foto" value="1" id="mostrar_foto" @checked($config['mostrar_foto'])><label class="form-check-label" for="mostrar_foto">Fotografia</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_processo" value="1" id="mostrar_processo" @checked($config['mostrar_processo'])><label class="form-check-label" for="mostrar_processo">Nº processo</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_bi" value="1" id="mostrar_bi" @checked($config['mostrar_bi'] ?? true)><label class="form-check-label" for="mostrar_bi">Nº BI</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_turma" value="1" id="mostrar_turma" @checked($config['mostrar_turma'])><label class="form-check-label" for="mostrar_turma">Turma</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_curso" value="1" id="mostrar_curso" @checked($config['mostrar_curso'])><label class="form-check-label" for="mostrar_curso">Curso</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_ano" value="1" id="mostrar_ano" @checked($config['mostrar_ano'])><label class="form-check-label" for="mostrar_ano">Ano lectivo</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_validade" value="1" id="mostrar_validade" @checked($config['mostrar_validade'])><label class="form-check-label" for="mostrar_validade">Validade</label></div>
                                <div class="form-check"><input class="form-check-input preview-field" type="checkbox" name="mostrar_verso" value="1" id="mostrar_verso" @checked($config['mostrar_verso'])><label class="form-check-label" for="mostrar_verso">Mostrar verso</label></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto de validade</label>
                                <input type="text" name="validade_texto" id="validade_texto" class="form-control preview-field" value="{{ $config['validade_texto'] }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto do verso</label>
                                <textarea name="verso_texto" id="verso_texto" class="form-control preview-field" rows="3">{{ $config['verso_texto'] }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Orientação PDF</label>
                                <select name="orientacao" id="orientacao" class="form-select preview-field">
                                    <option value="landscape" @selected($config['orientacao']==='landscape')>Horizontal</option>
                                    <option value="portrait" @selected($config['orientacao']==='portrait')>Vertical</option>
                                </select>
                            </div>
                            <button class="btn btn-primary">Guardar personalização</button>
                            <a href="{{ route('secretaria.cartao.emitir') }}" class="btn btn-outline-secondary">Emitir cartões</a>
                        </form>
                    </div></div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Pré-visualização em tempo real</span>
                            <span class="badge text-bg-secondary" id="prev-orientacao-badge">CR80</span>
                        </div>
                        <div class="card-body bg-light">
                            <div id="preview-cartao" style="max-width:340px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.15);">
                                <div id="prev-accent" style="height:14px;"></div>
                                <div style="padding:14px 16px;">
                                    <div class="d-flex gap-2 align-items-start mb-2">
                                        <div id="prev-logo" style="width:36px;height:36px;border-radius:6px;background:rgba(255,255,255,.25);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;">LOGO</div>
                                        <div>
                                            <div id="prev-titulo" class="fw-semibold" style="font-size:14px;line-height:1.2;"></div>
                                            <div id="prev-escola" style="font-size:11px;opacity:.9;">{{ $instituicao['nome'] ?? 'Instituição' }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-3 align-items-start">
                                        <div id="prev-foto" style="width:72px;height:90px;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);border-radius:6px;flex-shrink:0;"></div>
                                        <div style="font-size:12px;line-height:1.45;">
                                            <div class="fw-semibold mb-1">Exemplo Estudante</div>
                                            <div id="prev-processo"><strong>Processo:</strong> 2026001</div>
                                            <div id="prev-bi"><strong>BI:</strong> 000000000LA000</div>
                                            <div id="prev-turma"><strong>Turma:</strong> TI12AM</div>
                                            <div id="prev-curso"><strong>Curso:</strong> Informática</div>
                                            <div id="prev-ano"><strong>Ano:</strong> 2025/2026</div>
                                            <div id="prev-validade"><strong>Validade:</strong> <span id="prev-validade-txt"></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="prev-verso-box" class="mt-3 mx-auto" style="max-width:340px;border:1px solid #ccc;border-radius:12px;padding:12px;background:#fff;font-size:12px;color:#333;">
                                <div class="fw-semibold mb-1" id="prev-verso-title">Verso do cartão</div>
                                <div id="prev-verso"></div>
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
                const prim = el('cor_primaria').value;
                const sec = el('cor_secundaria').value;
                const txt = el('cor_texto').value;
                const box = el('preview-cartao');
                box.style.background = prim;
                box.style.color = txt;
                el('prev-accent').style.background = sec;
                el('prev-titulo').textContent = el('titulo').value;
                el('prev-validade-txt').textContent = el('validade_texto').value;
                el('prev-verso').textContent = el('verso_texto').value;
                el('prev-verso-title').style.color = prim;

                el('prev-logo').style.display = checked('mostrar_logo') ? 'flex' : 'none';
                el('prev-foto').style.display = checked('mostrar_foto') ? 'block' : 'none';
                el('prev-processo').style.display = checked('mostrar_processo') ? 'block' : 'none';
                el('prev-bi').style.display = checked('mostrar_bi') ? 'block' : 'none';
                el('prev-turma').style.display = checked('mostrar_turma') ? 'block' : 'none';
                el('prev-curso').style.display = checked('mostrar_curso') ? 'block' : 'none';
                el('prev-ano').style.display = checked('mostrar_ano') ? 'block' : 'none';
                el('prev-validade').style.display = checked('mostrar_validade') ? 'block' : 'none';
                el('prev-verso-box').style.display = checked('mostrar_verso') ? 'block' : 'none';
                el('prev-orientacao-badge').textContent = el('orientacao').value === 'portrait' ? 'Vertical' : 'Horizontal · CR80';
            }

            function bindPreview() {
                document.querySelectorAll('#formCartao .preview-field').forEach(function (node) {
                    node.addEventListener('input', actualizarPreview);
                    node.addEventListener('change', actualizarPreview);
                });
                actualizarPreview();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindPreview);
            } else {
                bindPreview();
            }
        })();
    </script>
</x-app-layout>
