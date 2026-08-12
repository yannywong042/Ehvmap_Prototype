<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Translation Driver
    |--------------------------------------------------------------------------
    |
    | Selects the concrete machine-translation implementation bound to the
    | App\Contracts\Translator contract (see AppServiceProvider). Supported
    | values:
    |
    | - "null"  : NullTranslator passthrough. Makes NO external calls and echoes
    |             the original text. This is the safe default so nothing breaks
    |             and no story text leaves the server until an operator opts in.
    | - "deepl" : DeepLTranslator, calling the DeepL REST API (below). Falls back
    |             to the NullTranslator automatically when DEEPL_API_KEY is unset,
    |             so a half-configured install never errors.
    |
    */

    'driver' => env('TRANSLATION_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | DeepL Provider
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoint for the "deepl" driver. DEEPL_API_URL defaults to
    | the Free API host; set it to https://api.deepl.com for a Pro key. Sending
    | user-submitted story text to DeepL is external data egress the operator
    | opts into by setting DEEPL_API_KEY.
    |
    */

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'url' => env('DEEPL_API_URL', 'https://api-free.deepl.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The locales a story is translated into. This is the single source of truth
    | for "every supported locale" used by the translation job and the backfill
    | command, so the list is never hard-coded in two places.
    |
    */

    'locales' => ['en', 'nl'],

];
