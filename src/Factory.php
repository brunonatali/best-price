<?php declare(strict_types=1);

namespace BrunoNatali\BestPrice;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\File\JsonFile;
use BrunoNatali\Tools\Communication\SimpleHttpClient as HttpClient;
use BrunoNatali\Tools\Communication\SimpleHttpServer as HttpServer;
use BrunoNatali\Tools\Communication\SimpleHttpServerInterface as HttpServerInterface;

use React\EventLoop\Factory as LoppFactory;
use React\Promise\Deferred;

final class Factory implements FactoryInterface
{
    /**
     * HTTP SERVER
    */
    private $httpServer = null; // Hadle http server constructor

    /**
     * SYSTEM VARIABLES
    */
    private $loop = null; // Store PHP React loop factory

    private $browser = null; // Store HTTP client 

    private $config = []; // Store system configuration

    private $sysConfig = []; // Used to store / transport online configs 

    function __construct()
    {
        /**
         * Build loop factory
        */
        $this->loop = LoppFactory::create();

        /**
         * Get registered configs
        */
        $this->config = JsonFile::readAsArray('/etc/desh/config.json');

        $this->sysConfig = [
            "outSystemName" => $this->config['app_name'] ?? 'BP', // Best Price
            "outSystemEnabled" => $this->config['log_debug_enable'] ?? false
        ];
        
        $this->browser = new \BrunoNatali\Tools\Communication\HttpClient(
            $this->loop, 
            $this->sysConfig,
            [
                'timeout' => (isset($this->config['http_client_timeout']) && \is_int($this->config['http_client_timeout']) ?
                    $this->config['http_client_timeout'] : self::HTTP_CLIENT_TIME_OUT),
                'verify_peer' => false
            ]
        );
    }

    /**
     * Register services and starts entire system 
    */
    public function start()
    {
        $this->outSystem->stdout("Initializing Best Price...", OutSystem::LEVEL_NOTICE);

        $this->startHttpServer();

        // Starts Browser without Loop
        $this->browser->start(false);

        $this->loop->run();
    }


    /**
     * Check if provided IPv4 number is right
     * 
     * @param string $ip 
     * @return bool
    */
    private function startHttpServer()
    {
        // Get configured http server IP or use default listen for any source IP
        $this->sysConfig['http_server_ip'] = ($this->sanitizeCheckIpv4($this->config['http_server_ip'] ?? '') ? 
            $this->config['http_server_ip'] : '0.0.0.0');

        // Use default HTTP port 80 if not configured
        $this->sysConfig['http_server_port'] = $this->sanitizeCheckPort($this->config['http_server_port'] ?? '80');

        $this->httpServer = new HttpServer(
            $this->loop,
            \array_merge(
                $this->sysConfig,
                [
                    'ip' => $this->sysConfig['http_server_ip'], 
                    'port' => $this->sysConfig['http_server_port'],
                    'stream' => false,
                    'cert' => (isset($this->config['http_server_cert']) && @\file_exists($this->config['http_server_cert']) ? 
                        $this->config['http_server_cert'] : null),
                    'sequence' => HttpServerInterface::SERVER_HTTP_PROC_ORDER_SQH,
                    'on_server_params' => function ($params) {

                        /**
                         * Check if requester IP is black listed
                        */
                        if (isset($this->config['http_server_ip_blacklist']) && !empty($this->config['http_server_ip_blacklist'])) {
                            foreach ($this->config['http_server_ip_blacklist'] as $BlakListIp)
                                if ($params['REMOTE_ADDR'] ===  $BlakListIp) {
                                    $this->outSystem->stdout(
                                        'Request from: ' . $params['REMOTE_ADDR'] . ' [BLOCKED] in BLACK LIST', 
                                        OutSystem::LEVEL_WARNING
                                    );

                                    return false;
                                }

                            $this->outSystem->stdout(
                                'Request from: ' . $params['REMOTE_ADDR'] . ' not black listed', 
                                OutSystem::LEVEL_NOTICE
                            );
                        } else {
                            $this->outSystem->stdout('Request from: ' . $params['REMOTE_ADDR'], OutSystem::LEVEL_NOTICE);
                        }
                    },
                    'on_query' => function ($query, &$content) {
                        return $this->getHttpResult($query, $content);
                    }
                ]
            )
        );

        // Do not start Loop automatically, will be handled outside
        $this->httpServer->start(false);
    }

    /**
     * Parse http request 
     * 
     * @param string $query requester querys
     * @param string $content Requester body content
     * @return bool or Deferred
    */
    private function getHttpResult(&$query, &$content) 
    {
        if (isset($query['access']) && $query['access'] === 'build') {
            $this->outSystem->stdout('Simple CORS access page', OutSystem::LEVEL_NOTICE);
        }
    }

    /**
     * Request web page 
     * 
     * @param string $url Desired URL
     * @param bool $replace Indicates whether the recovered content needs to have the URLs swaped  
     * @param callable $onSuccess Function to call on recovered page
     * @param callable $onError Function to call if recovery fails
    */
    private function requestPage(string $url, bool $replace = false, callable $onSuccess = null, callable $onError = null)
    {
        $this->browser->request(
            $url,
            function ($headers, $data) use ($url, $replace, $onSuccess) {
                if (isset($headers['Content-Encoding']) && $headers['Content-Encoding'][0] === 'gzip') {
                    $gzipEncoded = true;
                    $data = \gzdecode($data);
                } else
                    $gzipEncoded = false;
                
                if ($replace) {
                    $this->outSystem->stdout(
                        "Response from ($url) size: " . \strlen($data) . ' replaced: ' . $this->replaceUrl($data),
                        OutSystem::LEVEL_ALL
                    );
    
                    // Check why cause error => "Transfer-Encoding:chunked
                    unset($headers['Transfer-Encoding']); 
    
                    if ($gzipEncoded) {
                        $data = \gzencode($data);
                        $headers['Content-Length'] = [
                            \strlen($data)
                        ];
                    }
                } else {
                    $this->outSystem->stdout("Response from ($url) size: " . \strlen($data), OutSystem::LEVEL_ALL);
                }

                // Convert Headers
                /*
                foreach ($headers as $name => $value) 
                    if (isset($value[0]))
                        \header("$name:" . $value[0], true);
                */

                if (\is_callable($onSuccess))
                    ($onSuccess)($data, $headers);
            },
            function (\Exception $e) use ($url, $onError) {
                $this->outSystem->stdout("[ERROR] Requested '$url': " . $e->getMessage(), OutSystem::LEVEL_ALL);

                if (\is_callable($onError))
                    ($onError)($e);
            },
            $headers
        );
    }

    /**
     * Check if provided IPv4 number is right
     * 
     * @param string $ip 
     * @return bool
    */
    private function sanitizeCheckIpv4(string $ip): bool
    {
        return \filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Check if provided port as string is right
     * 
     * Network ports are commomly int type, this function check if provided port as string is 
     * right to use
     * 
     * @param string $port 
     * @return int converted string port or 0 if could not parse port
    */
    private function sanitizeCheckPort(string $port): int
    {
        $parsedPort = \intval($port);

        return ($parsedPort < 0x10000 && \strlen($port) === \strlen((string) $parsedPort) ? 
            $parsedPort : 0);
    }

    /**
     * Auxiliary function to proxy
     * 
     * Replaces http URL to server url, 
     * 
     * @param string $data Page data to be parsed 
     * @return int Number of replaces done
    */
    private function replaceUrl(&$data): int
    {
        $replaces = 0;
        $last = 0;
        $dataClone = '';
        while (($start = \strpos($data, '"http', $last)) !== false) 
            if (($end = \strpos($data, '"', $start +1)) !== false) {
                
                $dataClone .= \substr($data, $last, $start - $last) . 'window.location.origin + ":' .
                    $this->sysConfig['http_server_port'] . '/?proxy=' . \substr($data, ++$start, $end - $start) . '"';
                        
                $replaces ++;
                $last = $end + 1;
            }
            
        $data = $dataClone . \substr($data, $last);
        
        return $replaces;
    }
}