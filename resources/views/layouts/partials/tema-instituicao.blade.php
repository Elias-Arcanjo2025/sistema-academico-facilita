@if(!empty($temaInstituicao))
<style id="tema-instituicao">
:root,
[data-theme="light"] {
@foreach($temaInstituicao['light'] as $var => $value)
    {{ $var }}: {{ $value }};
@endforeach
@foreach($temaInstituicao['welcome'] as $var => $value)
    {{ $var }}: {{ $value }};
@endforeach
}
[data-theme="dark"] {
@foreach($temaInstituicao['dark'] as $var => $value)
    {{ $var }}: {{ $value }};
@endforeach
@foreach($temaInstituicao['welcome'] as $var => $value)
    {{ $var }}: {{ $value }};
@endforeach
}

/* Bootstrap & componentes alinhados ao tema */
.btn-primary,
.btn-primary:active,
.btn-primary:first-child:active {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-color: var(--primary-color);
}
.btn-primary:hover,
.btn-primary:focus {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
    border-color: var(--primary-dark);
}
.btn-outline-primary {
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 45%, transparent);
}
.btn-outline-primary:hover {
    background: var(--primary-soft-bg);
    border-color: var(--primary-color);
    color: var(--primary-dark);
}
.btn-go,
.btn-modern.btn-primary {
    background: var(--primary-color);
    border-color: var(--primary-color);
}
.text-primary { color: var(--primary-color) !important; }
.bg-primary { background-color: var(--primary-color) !important; }
.border-primary { border-color: var(--primary-color) !important; }
.link-primary { color: var(--primary-color) !important; }
.link-primary:hover { color: var(--primary-dark) !important; }
.page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
.form-control:focus,
.form-select:focus {
    border-color: color-mix(in srgb, var(--primary-color) 55%, #fff);
    box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--primary-color) 18%, transparent);
}
.alert-info {
    background: var(--secondary-soft-bg);
    border-color: color-mix(in srgb, var(--secondary-color) 25%, transparent);
    color: color-mix(in srgb, var(--secondary-color) 65%, #0f172a);
}
.tip-card--primary {
    border-left-color: var(--primary-color);
    background: var(--primary-soft-bg);
}
.tip-card--primary .tip-card__icon {
    background: var(--primary-soft-icon);
    color: var(--primary-color);
}
.tip-card--primary .tip-card__title { color: var(--primary-color); }
.tip-card--info {
    border-left-color: var(--secondary-color);
    background: var(--secondary-soft-bg);
}
.tip-card--info .tip-card__icon {
    background: var(--secondary-soft-icon);
    color: var(--secondary-color);
}
.tip-card--info .tip-card__title { color: var(--secondary-color); }
.assistente-root {
    --assistente-primary: var(--primary-color);
    --assistente-primary-dark: var(--primary-dark);
}
.assistente-fab {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color)) !important;
    color: #fff !important;
}
.assistente-fab i {
    color: #fff !important;
}
.welcome-theme-toggle {
    background: color-mix(in srgb, var(--primary-color) 90%, transparent) !important;
    box-shadow: 0 4px 20px color-mix(in srgb, var(--primary-color) 40%, transparent) !important;
}
.cta {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color), var(--accent-color)) !important;
}
[data-theme="dark"] .btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-color: var(--primary-color);
}
[data-theme="dark"] .btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
    border-color: var(--primary-dark);
}
[data-theme="dark"] .btn-outline-primary {
    color: color-mix(in srgb, var(--primary-color) 85%, #fff);
    border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);
}
[data-theme="dark"] .btn-outline-primary:hover {
    background: var(--primary-soft-bg);
    border-color: var(--primary-color);
    color: #fff;
}
[data-theme="dark"] .tip-card--primary { background: var(--primary-soft-bg); }
[data-theme="dark"] .tip-card--info { background: var(--secondary-soft-bg); }
</style>
@endif
