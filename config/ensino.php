<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Perfis de ensino desta instalação
    |--------------------------------------------------------------------------
    |
    | Defina no .env na instalação, ou depois em Configurações → Perfis de ensino.
    | A UI (BD) tem prioridade sobre o .env.
    |
    | Se alterar só o .env após já ter gravado na UI, use Configurações ou:
    | php artisan ensino:sincronizar-classes
    |
    | Valores (separados por vírgula):
    |   primario  → 1.ª–6.ª classe
    |   i_ciclo   → 7.ª–9.ª classe
    |   ii_ciclo  → 10.ª–13.ª classe
    |
    | Exemplos:
    |   ENSINO_PERFIS=ii_ciclo
    |   ENSINO_PERFIS=i_ciclo,ii_ciclo
    |   ENSINO_PERFIS=primario,i_ciclo,ii_ciclo
    |
    */
    'perfis' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ENSINO_PERFIS', 'ii_ciclo'))
    ))),

    'mapa' => [
        'primario' => [1, 2, 3, 4, 5, 6],
        'i_ciclo' => [7, 8, 9],
        'ii_ciclo' => [10, 11, 12, 13],
    ],

    'rotulos' => [
        'primario' => 'Ensino Primário (1.ª–6.ª)',
        'i_ciclo' => 'Iº Ciclo (7.ª–9.ª)',
        'ii_ciclo' => 'IIº Ciclo (10.ª–13.ª)',
    ],

];
