<div class="pp-preview-wrap mb-0" id="ppPreviewWrap">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-1"></i>Pré-visualização ao vivo</h6>
        <small class="text-muted">Actualiza ao alterar as cores</small>
    </div>
    <div class="pp-preview" id="ppPreview">
        <div class="pp-preview__layout">
            <aside class="pp-preview__sidebar">
                <div class="pp-preview__brand">Menu</div>
                <div class="pp-preview__menu-item pp-preview__menu-item--active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </div>
                <div class="pp-preview__menu-item"><i class="bi bi-journal-text"></i> Notas</div>
            </aside>
            <main class="pp-preview__main">
                <div class="pp-preview__banner">
                    <div class="pp-preview__banner-title">Painel administrativo</div>
                    <div class="pp-preview__banner-sub">Ano lectivo activo</div>
                </div>
                <div class="pp-preview__tip">
                    <i class="bi bi-lightbulb"></i>
                    <span>Alerta com cor secundária</span>
                </div>
                <div class="pp-preview__actions">
                    <span class="pp-preview__btn pp-preview__btn--primary">Guardar</span>
                    <span class="pp-preview__btn pp-preview__btn--outline">Cancelar</span>
                </div>
            </main>
        </div>
        <div class="pp-preview__hero">
            <span class="pp-preview__hero-badge">Página pública</span>
            <span class="pp-preview__hero-title">Hero · gradiente</span>
        </div>
        <div class="pp-preview__swatches">
            <span class="pp-preview__swatch"><i id="ppSwatchPrimary"></i> Principal</span>
            <span class="pp-preview__swatch"><i id="ppSwatchDark"></i> Escura</span>
            <span class="pp-preview__swatch"><i id="ppSwatchSecondary"></i> Secundária</span>
            <span class="pp-preview__swatch"><i id="ppSwatchAccent"></i> Acento</span>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.pp-preview-wrap { border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 1rem; background: var(--bg-light, #f8fafc); }
.pp-preview { --pp-primary: #4f46e5; --pp-primary-dark: #3730a3; --pp-secondary: #06b6d4; --pp-accent: #5b7ce6; --pp-primary-rgb: 79, 70, 229; --pp-secondary-rgb: 6, 182, 212; border-radius: 10px; overflow: hidden; background: #fff; box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06); }
[data-theme="dark"] .pp-preview { background: #151d2e; }
.pp-preview__layout { display: flex; min-height: 140px; border-bottom: 1px solid rgba(0,0,0,.06); }
.pp-preview__sidebar { width: 120px; flex-shrink: 0; padding: .75rem .5rem; background: linear-gradient(180deg, #fff, #f8fafc); border-right: 1px solid #e2e8f0; }
.pp-preview__brand { font-size: .65rem; font-weight: 700; text-transform: uppercase; color: var(--pp-primary); margin-bottom: .5rem; }
.pp-preview__menu-item { display: flex; align-items: center; gap: .35rem; font-size: .72rem; padding: .35rem .45rem; border-radius: 8px; color: #64748b; margin-bottom: .15rem; }
.pp-preview__menu-item--active { color: var(--pp-primary); background: rgba(var(--pp-primary-rgb), .1); border-left: 3px solid var(--pp-primary); font-weight: 600; }
.pp-preview__main { flex: 1; padding: .75rem; background: #f1f5f9; }
.pp-preview__banner { border-radius: 10px; padding: .6rem .8rem; margin-bottom: .45rem; color: #fff; background: linear-gradient(135deg, var(--pp-primary), var(--pp-accent)); }
.pp-preview__banner-title { font-weight: 700; font-size: .78rem; }
.pp-preview__banner-sub { font-size: .68rem; opacity: .9; }
.pp-preview__tip { display: flex; align-items: center; gap: .4rem; font-size: .65rem; padding: .4rem .55rem; border-radius: 8px; margin-bottom: .45rem; border-left: 3px solid var(--pp-secondary); background: rgba(var(--pp-secondary-rgb), .08); }
.pp-preview__actions { display: flex; gap: .35rem; }
.pp-preview__btn { font-size: .65rem; padding: .28rem .55rem; border-radius: 6px; font-weight: 600; }
.pp-preview__btn--primary { color: #fff; background: linear-gradient(135deg, var(--pp-primary), var(--pp-accent)); }
.pp-preview__btn--outline { color: var(--pp-primary); border: 1px solid color-mix(in srgb, var(--pp-primary) 40%, transparent); background: rgba(var(--pp-primary-rgb), .06); }
.pp-preview__hero { padding: .75rem 1rem; color: #fff; background: linear-gradient(160deg, var(--pp-primary-dark) 0%, var(--pp-primary) 55%, var(--pp-accent) 100%); }
.pp-preview__hero-badge { display: inline-block; font-size: .58rem; padding: .12rem .45rem; border-radius: 50px; background: rgba(255,255,255,.15); margin-bottom: .3rem; }
.pp-preview__hero-title { font-size: .75rem; font-weight: 700; }
.pp-preview__swatches { display: flex; flex-wrap: wrap; gap: .5rem 1rem; padding: .55rem 1rem; font-size: .65rem; color: #64748b; background: #f8fafc; border-top: 1px solid #e2e8f0; }
.pp-preview__swatch { display: inline-flex; align-items: center; gap: .35rem; }
.pp-preview__swatch i { display: inline-block; width: 14px; height: 14px; border-radius: 4px; border: 1px solid rgba(0,0,0,.1); }
</style>
@endpush
@endonce
