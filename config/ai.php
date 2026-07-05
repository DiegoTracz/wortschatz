<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enriquecimento de cartões via OpenAI
    |--------------------------------------------------------------------------
    |
    | Modelo padrão usado quando o usuário não escolheu um nas configurações,
    | e a tabela de preços por modelo (em USD por 1 milhão de tokens — é como
    | a OpenAI cobra). O custo de cada requisição é calculado a partir daqui e
    | guardado em `ai_usages`. Ajuste os valores quando a OpenAI mudar os preços.
    |
    */

    'default_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    // Câmbio USD→BRL de reserva, usado quando a cotação ao vivo (AwesomeAPI) falha.
    'usd_brl_fallback' => env('USD_BRL_RATE', 5.40),

    'models' => [
        'gpt-4o-mini' => [
            'label' => 'GPT-4o mini',
            'input' => 0.15,
            'output' => 0.60,
        ],
        'gpt-4.1-mini' => [
            'label' => 'GPT-4.1 mini',
            'input' => 0.40,
            'output' => 1.60,
        ],
        'gpt-4o' => [
            'label' => 'GPT-4o',
            'input' => 2.50,
            'output' => 10.00,
        ],
    ],

];
