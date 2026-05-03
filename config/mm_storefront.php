<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Home da loja (destaques + blocos por categoria)
    |--------------------------------------------------------------------------
    |
    | category_blocks_default: quantos blocos de categoria (2–5) quando a query
    | `categories` não for enviada. section_slugs: ordem dos slugs; só os que
    | existirem e estiverem ativos entram, até o limite solicitado.
    |
    */
    'home' => [
        'destaques_limit' => (int) env('MM_STOREFRONT_HOME_DESTAQUES', 12),
        'products_per_section' => (int) env('MM_STOREFRONT_HOME_PER_SECTION', 12),
        'category_blocks_default' => (int) env('MM_STOREFRONT_HOME_CATEGORY_BLOCKS', 4),
        'section_slugs' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'MM_STOREFRONT_HOME_SECTION_SLUGS',
                'selecoes,times-europeus,bones-e-gorros,times-nacionais'
            ))
        ))),
    ],
];
