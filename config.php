<?php

$localConfig = [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'mf_customer_service',
    'DB_USER' => 'root',
    'DB_PASS' => '1234',
    'DB_PORT' => '3306',

    'REDIS_HOST' => '120.77.216.162',
    'REDIS_PORT' => '13583',
    'REDIS_PASS' => 'wyf666',
];

$devConfig = [
    'DB_HOST' => '120.77.216.162',
    'DB_NAME' => 'mf_customer_service',
    'DB_USER' => 'root',
    'DB_PASS' => '1234',
    'DB_PORT' => '10352',

    'REDIS_HOST' => '120.77.216.162',
    'REDIS_PORT' => '13583',
    'REDIS_PASS' => 'wyf666',
];

$proCOnfig = [];

$_ENV = isset($_SERVER['ENV']) ? $_SERVER['ENV'] : 'dev';

if($_ENV == 'local') return $localConfig;

else if($_ENV == 'dev') return $devConfig;

else if($_ENV == 'pro') return $proCOnfig;
