@extends('configuracoes.pagina-publica.layout')

@section('pagina-publica-content')
<div class="content-card">
    <div class="content-card-header">
        <h6 class="content-card-title mb-0"><i class="bi bi-toggles me-2"></i>Opções de apresentação</h6>
    </div>
    <div class="content-card-body">
        <p class="text-muted small mb-3">
            Logo e contactos vêm de <a href="{{ route('configuracoes.index') }}#instituicao">Dados da Instituição</a> nas configurações gerais.
        </p>

        <form action="{{ route('configuracoes.pagina-publica.opcoes.update') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="pagina_publica_mostrar_ano_lectivo" value="0">
                        <input class="form-check-input" type="checkbox" name="pagina_publica_mostrar_ano_lectivo" value="1" id="pp_mostrar_ano"
                            {{ ($pp['pagina_publica_mostrar_ano_lectivo'] ?? '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pp_mostrar_ano">Mostrar badge do ano lectivo</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="pagina_publica_mostrar_contactos" value="0">
                        <input class="form-check-input" type="checkbox" name="pagina_publica_mostrar_contactos" value="1" id="pp_mostrar_contactos"
                            {{ ($pp['pagina_publica_mostrar_contactos'] ?? '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pp_mostrar_contactos">Mostrar contactos no rodapé</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="pagina_publica_usar_logo_instituicao" value="0">
                        <input class="form-check-input" type="checkbox" name="pagina_publica_usar_logo_instituicao" value="1" id="pp_usar_logo"
                            {{ ($pp['pagina_publica_usar_logo_instituicao'] ?? '0') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pp_usar_logo">Usar logo da instituição na navbar</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="pagina_publica_mostrar_registo" value="0">
                        <input class="form-check-input" type="checkbox" name="pagina_publica_mostrar_registo" value="1" id="pp_mostrar_registo"
                            {{ ($pp['pagina_publica_mostrar_registo'] ?? '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pp_mostrar_registo">Mostrar botão «Criar conta» na página</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="registo_publico_activo" value="0">
                        <input class="form-check-input" type="checkbox" name="registo_publico_activo" value="1" id="pp_registo_activo"
                            {{ ($pp['registo_publico_activo'] ?? '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pp_registo_activo">Permitir registo público de contas</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm mt-3">
                <i class="bi bi-check-lg me-1"></i>Guardar opções
            </button>
        </form>
    </div>
</div>
@endsection
