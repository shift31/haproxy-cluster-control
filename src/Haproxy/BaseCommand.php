<?php namespace Shift31\Haproxy;

use Illuminate\Console\Command;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


/**
 * Class BaseCommand
 *
 * @package Shift31\Haproxy
 */
abstract class BaseCommand extends Command
{
    const CONFIG_FILE = 'haproxycc.config.php';


    /**
     * @var array
     */
    protected $config;


    /**
     * @var TestHandler
     */
    protected $logHandler;

    /**
     * @var Logger
     */
    protected $logger;


    public function __construct()
    {
        parent::__construct();

        try {
            $this->config = $this->getConfig();
        } catch (\Exception $e) {
            print $e->getMessage() . PHP_EOL;
            exit(1);
        }

        date_default_timezone_set('UTC');

        $this->logHandler = new TestHandler(Logger::INFO);
        $this->logger = new Logger('haproxycc', [$this->logHandler]);
    }


    /**
     * @return array
     * @throws \Exception
     */
    protected function getConfig()
    {
        $userConfigFile = getenv('HOME') . '/' . self::CONFIG_FILE;
        $systemConfigFile = '/etc/' . self::CONFIG_FILE;

        if (file_exists(self::CONFIG_FILE)) {
            $config = require(self::CONFIG_FILE);
        } elseif (file_exists($userConfigFile)) {
            $config = require($userConfigFile);
        } elseif (file_exists($systemConfigFile)) {
            $config = require($systemConfigFile);
        } else {
            throw new \Exception('No configuration file was found!');
        }

        return $config;
    }


    /**
     * @inheritdoc
     */
    protected function getArguments()
    {
        return[
            ['env', InputArgument::REQUIRED, 'The name of the cluster environment'],
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getOptions()
    {
        return [
            ['debug', null, InputOption::VALUE_NONE, 'Enable debug output'],
        ];
    }


    /**
     * @param $env
     * @param $key
     *
     * @return mixed
     */
    protected function getEnvironmentConfig($env, $key)
    {
        return array_get($this->config, "environments.$env.$key");
    }


    protected function outputLog()
    {
        if ($this->option('debug')) {
            $this->info('Log:');
            foreach ($this->logHandler->getRecords() as $record) {
                $this->line(str_replace(PHP_EOL, '', $record['formatted']));
            }
        }
    }
}