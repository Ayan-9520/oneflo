<?php

require __DIR__.'/../vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'ONEFLO_PLATFORM_HOSTS' => 'localhost,127.0.0.1,oneflo.in,www.oneflo.in,app.oneflo.in,api.oneflo.in',
] as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    if ($value === '') {
        putenv($name);
    } else {
        putenv($name.'='.$value);
    }
}

if (class_exists(Illuminate\Support\Env::class)) {
    $repository = new ReflectionProperty(Illuminate\Support\Env::class, 'repository');
    $repository->setValue(null, null);
}
