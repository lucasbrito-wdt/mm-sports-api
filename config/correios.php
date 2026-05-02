<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    | 'producao' usa https://api.correios.com.br
    | 'homologacao' usa https://apihom.correios.com.br
    */
    'ambiente' => env('CORREIOS_AMBIENTE', 'homologacao'),

    'urls' => [
        'producao'    => 'https://api.correios.com.br',
        'homologacao' => 'https://apihom.correios.com.br',
    ],

    /*
    |--------------------------------------------------------------------------
    | Credenciais
    |--------------------------------------------------------------------------
    | Geradas no portal CWS (https://cws.correios.com.br) — login do
    | "Meu Correios" + código de acesso à API + cartão de postagem.
    */
    'credenciais' => [
        'usuario'         => env('CORREIOS_USUARIO'),
        'codigo_acesso'   => env('CORREIOS_CODIGO_ACESSO'),
        'cartao_postagem' => env('CORREIOS_CARTAO_POSTAGEM'),
        'contrato'        => env('CORREIOS_CONTRATO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipo de autenticação
    |--------------------------------------------------------------------------
    | usuario | contrato | cartao_postagem
    */
    'tipo_autenticacao' => env('CORREIOS_TIPO_AUTH', 'cartao_postagem'),

    /*
    |--------------------------------------------------------------------------
    | Cache do Token
    |--------------------------------------------------------------------------
    | O token JWT dos Correios dura 24h. Aqui definimos por quanto tempo
    | mantemos em cache (sempre menor que 24h pra renovar antes).
    */
    'cache' => [
        'store'      => env('CORREIOS_CACHE_STORE', 'redis'),
        'key'        => env('CORREIOS_CACHE_KEY', 'correios:token'),
        'ttl_horas'  => 23,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout'        => env('CORREIOS_HTTP_TIMEOUT', 30),
        'retry_times'    => env('CORREIOS_HTTP_RETRY', 2),
        'retry_sleep_ms' => env('CORREIOS_HTTP_RETRY_SLEEP', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('CORREIOS_LOG_ENABLED', true),
        'channel' => env('CORREIOS_LOG_CHANNEL', 'stack'),
    ],
];
