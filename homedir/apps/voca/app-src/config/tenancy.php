<?php

return [
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'app.voca.co.za'),
    'default_slug' => env('TENANCY_DEFAULT_SLUG', 'voca'),
    'trusted_hosts' => array_filter(array_map('trim', explode(',', env('TENANCY_TRUSTED_HOSTS', 'localhost,127.0.0.1')))),
];
