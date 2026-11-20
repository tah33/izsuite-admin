<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront addresses (Doc 19 section 19.5)
    |--------------------------------------------------------------------------
    |
    | Every storefront has an address of its own under izSuite: its code under
    | this domain - rahman-online.store.izsuite.com. The server answers for
    | every name under it, with one wildcard certificate.
    |
    */

    'storefront_domain' => env('ECOMMERCE_STOREFRONT_DOMAIN', 'store.izsuite.com'),

    /*
    |--------------------------------------------------------------------------
    | A business's own domain
    |--------------------------------------------------------------------------
    |
    | Where a business points its own domain, with a CNAME record, so the
    | server answers for it - and issues its certificate - once the domain is
    | verified by its TXT record.
    |
    */

    'cname_target' => env('ECOMMERCE_CNAME_TARGET', 'domains.store.izsuite.com'),

];
