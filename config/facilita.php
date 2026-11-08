<?php

return [

    /*
    | Prefixo das rotas autenticadas (ex.: app → /app/dashboard).
    | Deixe vazio para URLs na raiz (comportamento antigo).
    */
    'route_prefix' => env('FACILITA_ROUTE_PREFIX', 'app'),

    /*
    | Validade por defeito de links assinados para documentos (horas).
    */
    'signed_document_ttl_hours' => (int) env('FACILITA_SIGNED_DOCUMENT_TTL', 24),

    /*
    | Domínio público para URLs assinadas do QR (ex.: https://www.facilita.it.ao).
    | Vazio = usa APP_URL do .env.
    */
    'signed_document_url_root' => env('FACILITA_SIGNED_DOCUMENT_URL', env('APP_URL')),

    /*
    | PWA: activo apenas em dispositivos móveis (detecção server + client).
    */
    'pwa_mobile_only' => env('FACILITA_PWA_MOBILE_ONLY', true),

    /*
    | Content-Security-Policy (report-only por defeito em produção).
    */
    'csp_enabled' => env('FACILITA_CSP_ENABLED', false),
    'csp_report_only' => env('FACILITA_CSP_REPORT_ONLY', true),

    /*
    | QR de verificação no boletim PDF.
    */
    'boletim_qr_enabled' => env('FACILITA_BOLETIM_QR', true),

];
