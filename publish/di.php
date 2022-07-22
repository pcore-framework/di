<?php

declare(strict_types=1);

return [
    'aop' => [
        'cache' => false,
        'paths' => ['./src'],
        'runtimeDir' => './var/runtime',
        'collectors' => [
            'PCore\HttpServer\RouteCollector',
            'PCore\Event\ListenerCollector',
            'PCore\Console\CommandCollector'
        ]
    ],
    'bindings' => [
        'PCore\Config\Contracts\ConfigInterface' => 'PCore\Config\Repository',
        'Psr\EventDispatcher\EventDispatcherInterface' => 'PCore\Event\EventDispatcher',
        'Psr\Log\LoggerInterface' => 'App\Kernel\Logger'
    ]
];