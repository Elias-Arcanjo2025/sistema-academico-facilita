<?php

return [

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    /*
    | Provider: meta (Cloud API) | log (desenvolvimento — só grava na BD)
    */
    'provider' => env('WHATSAPP_PROVIDER', 'log'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'facilita-whatsapp'),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),

    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'api_base' => 'https://graph.facebook.com',
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'pt_PT'),

    /*
    | Templates aprovados na Meta (nomes exactos). Vazio = envia texto livre (só em modo log/dev).
    */
    'templates' => [
        'mac_em_falta' => env('WHATSAPP_TPL_MAC', ''),
        'prova_amanha' => env('WHATSAPP_TPL_PROVA', ''),
        'boletim_disponivel' => env('WHATSAPP_TPL_BOLETIM', ''),
        'cobranca_facilita' => env('WHATSAPP_TPL_COBRANCA', ''),
        'resumo_semanal' => env('WHATSAPP_TPL_RESUMO_SEMANAL', ''),
        'falta_registada' => env('WHATSAPP_TPL_FALTA', ''),
        'ocorrencia_escolar' => env('WHATSAPP_TPL_OCORRENCIA', ''),
    ],

    'bot_enabled' => (bool) env('WHATSAPP_BOT_ENABLED', true),

];
