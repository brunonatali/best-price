<?php declare(strict_types=1);

namespace BrunoNatali\BestPrice;

interface MainDefaultsIterface extends FactoryInterface
{
    const CONFIG_APP = [
        'app' => [
            'app_name' => 'BP', // Best Price
            'log_debug_enable' => false, // Output verbose
            'http_server_ip' => '0.0.0.0', // HTTP server listen IP. Default is listen on all interfaces
            'http_server_port' => 80, // HTTP server port. Default is a non HTTPS port
            'http_server_cert' => null // Provide a HTTPS certificate file if you want run encrypted
        ]
    ];

    const CONFIG_HTTP_ROWSER = [
        'browser' => [
            'http_client_timeout' => self::HTTP_CLIENT_TIME_OUT // Browser timeot on recovery web page in seconds
        ]
    ];

    /**
     * Bocked client IP
     * 
     * Originally this is an empty list. 
     * You could add bolcked clients adding its IP as array item 
    */
    const CONFIG_HTTP_SERVER_BLACKLIST = [
        'http_server_ip_blacklist' => []
    ];
}