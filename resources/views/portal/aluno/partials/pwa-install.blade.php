@php
    use App\Support\DeviceDetect;
    $pwaMobile = DeviceDetect::suportaPwa();
@endphp
@if($pwaMobile)
<div id="portalPwaInstall" class="facilita-pwa-install d-none" role="region" aria-label="Instalar aplicação">
    <div class="facilita-pwa-install__inner">
        <div>
            <i class="bi bi-phone me-1"></i>
            <span>Instale o portal no telemóvel para acesso rápido.</span>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-primary btn-sm" id="portalPwaInstallBtn">Instalar</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="portalPwaInstallDismiss" aria-label="Fechar">×</button>
        </div>
    </div>
</div>
@endif
