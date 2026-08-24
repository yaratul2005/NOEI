<?php

declare(strict_types=1);

return [
    'name' => 'NOEI CMS',
    'version' => '1.0.0-alpha',
    'env' => 'development',
    'debug' => true,
    'url' => 'http://localhost:8000',
    'timezone' => 'UTC',
    'locale' => 'en',
    'charset' => 'UTF-8',

    // Security baseline defaults
    'cipher' => 'AES-256-CBC',
    'session_name' => 'NOEI_SESSID',
    'session_lifetime' => 7200, // 2 hours
];
