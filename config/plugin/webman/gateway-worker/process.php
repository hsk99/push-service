<?php

use Webman\GatewayWorker\Gateway;
use Webman\GatewayWorker\BusinessWorker;
use Webman\GatewayWorker\Register;

return [
    'websocket' => [
        'handler'     => Gateway::class,
        'listen'      => 'websocket://0.0.0.0:8790',
        'count'       => 1,
        'reloadable'  => false,
        'constructor' => ['config' => [
            'lanIp'                => '127.0.0.1',
            'startPort'            => 8810,
            'pingInterval'         => 55,
            'pingNotResponseLimit' => 1,
            'pingData'             => '',
            'registerAddress'      => '127.0.0.1:8800',
            'onConnect'            => function () {
            },
        ]]
    ],
    'worker' => [
        'handler'     => BusinessWorker::class,
        'count'       => 1,
        'constructor' => ['config' => [
            'eventHandler'    => plugin\webman\gateway\Events::class,
            'registerAddress' => '127.0.0.1:8800',
        ]]
    ],
    'register' => [
        'handler'     => Register::class,
        'listen'      => 'text://0.0.0.0:8800',
        'count'       => 1, // Must be 1
        'constructor' => []
    ],
];
