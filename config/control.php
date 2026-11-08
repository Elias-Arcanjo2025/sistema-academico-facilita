<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de controlo Facilita
    |--------------------------------------------------------------------------
    |
    | off    — desactivado (comportamento actual)
    | school — instalação de escola (envia métricas/alunos e respeita licença)
    | hub    — painel central (recebe dados e bloqueia escolas)
    |
    */
    'mode' => env('CONTROL_MODE', 'off'),

    /*
    | URL base do hub (apenas modo school), ex.: https://controlo.facilita.ao
    */
    'hub_url' => rtrim((string) env('CONTROL_HUB_URL', ''), '/'),

    /*
    | Identidade desta instalação (modo school)
    */
    'installation_id' => env('CONTROL_INSTALLATION_ID', ''),
    'secret' => env('CONTROL_SECRET', ''),

    /*
    | Intervalo de sincronização com o hub (minutos, 1–59) e grace period offline (horas).
    | CONTROL_SYNC_INTERVAL controla o cron de `php artisan control:sync`.
    */
    'sync_interval_minutes' => max(1, min(59, (int) env('CONTROL_SYNC_INTERVAL', 30))),
    'grace_hours' => (int) env('CONTROL_GRACE_HOURS', 72),

    /*
    | Em pedidos web, a escola consulta GET /license no hub no máximo a cada N minutos
    | para aplicar bloqueio/cobrança sem esperar o sync completo.
    */
    'license_check_minutes' => max(1, (int) env('CONTROL_LICENSE_CHECK_MINUTES', 1)),

    /*
    | Bypass local de emergência (suporte). Nunca activar em produção normal.
    */
    'bypass' => (bool) env('CONTROL_BYPASS', false),

    /*
    | Prefixo das rotas API do hub
    */
    'api_prefix' => 'api/control/v1',

    /*
    | Valor anual por aluno (referência para faturação no hub). Ajuste por escola.
    */
    'preco_anual_aluno_default' => (float) env('CONTROL_PRECO_ANUAL_ALUNO', 0),

];
