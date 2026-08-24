<?php

declare(strict_types=1);

/**
 * NOEI CMS - Database Configuration Template.
 * Copy this file to database.php or let the installer wizard configure it.
 */

return [
    'installed' => false,
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'database'  => 'noei_cms',
    'username'  => 'db_user',
    'password'  => 'db_password',
    'prefix'    => 'cms_',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
