<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repetição espaçada (FSRS)
    |--------------------------------------------------------------------------
    |
    | retention: probabilidade alvo de lembrar um cartão no momento da revisão.
    | 0.90 é o padrão do Anki — valores maiores geram revisões mais frequentes.
    |
    | max_interval: intervalo máximo (em dias) entre revisões.
    |
    */

    'retention' => (float) env('SRS_RETENTION', 0.90),

    'max_interval' => (int) env('SRS_MAX_INTERVAL', 36500),

];
