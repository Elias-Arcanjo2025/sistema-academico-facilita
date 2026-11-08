@extends('configuracoes.pagina-publica.layout')

@section('pagina-publica-content')
<div class="content-card">
    <div class="content-card-header">
        <h6 class="content-card-title mb-0"><i class="bi bi-text-paragraph me-2"></i>Textos e conteúdo</h6>
    </div>
    <div class="content-card-body">
        <form action="{{ route('configuracoes.pagina-publica.textos.update') }}" method="POST">
            @csrf

            <h6 class="fw-semibold mb-2">Hero (topo da página)</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Título (linha 1)</label>
                    <input type="text" class="form-control" name="pagina_publica_titulo" value="{{ $pp['pagina_publica_titulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Destaque (linha 2)</label>
                    <input type="text" class="form-control" name="pagina_publica_destaque" value="{{ $pp['pagina_publica_destaque'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Subtítulo</label>
                    <textarea class="form-control" name="pagina_publica_subtitulo" rows="2">{{ $pp['pagina_publica_subtitulo'] ?? '' }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Texto do badge</label>
                    <input type="text" class="form-control" name="pagina_publica_badge" value="{{ $pp['pagina_publica_badge'] ?? '' }}" placeholder="Ano Lectivo {ano}">
                    <small class="text-muted">Use <code>{ano}</code> para o ano lectivo activo.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição SEO</label>
                    <input type="text" class="form-control" name="pagina_publica_meta_descricao" value="{{ $pp['pagina_publica_meta_descricao'] ?? '' }}">
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Secção «Sobre»</h6>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label">Título</label>
                    <input type="text" class="form-control" name="pagina_publica_sobre_titulo" value="{{ $pp['pagina_publica_sobre_titulo'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Texto</label>
                    <textarea class="form-control" name="pagina_publica_sobre_texto" rows="3">{{ $pp['pagina_publica_sobre_texto'] ?? '' }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Destaques (um por linha)</label>
                    <textarea class="form-control" name="pagina_publica_sobre_itens" rows="4">{{ $pp['pagina_publica_sobre_itens'] ?? '' }}</textarea>
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Portal do Aluno</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Título da secção</label>
                    <input type="text" class="form-control" name="pagina_publica_alunos_titulo" value="{{ $pp['pagina_publica_alunos_titulo'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Subtítulo (alunos)</label>
                    <textarea class="form-control" name="pagina_publica_alunos_subtitulo" rows="2">{{ $pp['pagina_publica_alunos_subtitulo'] ?? '' }}</textarea>
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Funcionalidades (escola), CTA e rodapé</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Título secção escola</label>
                    <input type="text" class="form-control" name="pagina_publica_func_titulo" value="{{ $pp['pagina_publica_func_titulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subtítulo secção escola</label>
                    <input type="text" class="form-control" name="pagina_publica_func_subtitulo" value="{{ $pp['pagina_publica_func_subtitulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Título CTA final (geral)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_titulo" value="{{ $pp['pagina_publica_cta_titulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Texto CTA final (geral)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_texto" value="{{ $pp['pagina_publica_cta_texto'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CTA — título (aluno)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_aluno_titulo" value="{{ $pp['pagina_publica_cta_aluno_titulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CTA — texto (aluno)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_aluno_texto" value="{{ $pp['pagina_publica_cta_aluno_texto'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CTA — título (escola)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_escola_titulo" value="{{ $pp['pagina_publica_cta_escola_titulo'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CTA — texto (escola)</label>
                    <input type="text" class="form-control" name="pagina_publica_cta_escola_texto" value="{{ $pp['pagina_publica_cta_escola_texto'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Texto do rodapé</label>
                    <textarea class="form-control" name="pagina_publica_footer_texto" rows="2">{{ $pp['pagina_publica_footer_texto'] ?? '' }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg me-1"></i>Guardar textos
            </button>
        </form>
    </div>
</div>
@endsection
