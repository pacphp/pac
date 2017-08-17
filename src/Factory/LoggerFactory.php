<?php
declare(strict_types=1);

namespace Pac\Factory;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerFactory
{
    public static function buildLogger(array $config)
    {
        $logFile = $config['logs_dir'] . '/' . $config['file_name'];
        $logger = new Logger($config['log_name']);
        $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        return $logger;
    }
}
