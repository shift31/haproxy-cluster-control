<?php namespace Shift31\Haproxy;

use Shift31\HAProxyClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


/**
 * Class SetCommand
 *
 * @package Shift31\Haproxy
 */
class SetCommand extends BaseCommand
{
    /**
     * @inheritdoc
     */
    protected $name = 'set';


    /**
     * @inheritdoc
     */
    protected $description = 'Set server state';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $env = $this->argument('env');

        $haproxyClient = new HAProxyClient(
            null,
            $this->getEnvironmentConfig($env, 'port'),
            $this->getEnvironmentConfig($env, 'baseUrl'),
            $this->getEnvironmentConfig($env, 'username'),
            $this->getEnvironmentConfig($env, 'password'),
            15,
            $this->logger
        );

        $loadBalancers = $this->getEnvironmentConfig($env, 'servers');

        $backend = $this->argument('backend');

        if (in_array($backend, array_keys($this->config['backend_nickname_map']))) {
            $backend = $this->config['backend_nickname_map'][$backend];
        }

        $server = $this->argument('server');

        $action = $this->argument('action');

        if ($action !== 'enable' and $action !== 'disable') {
            throw new \InvalidArgumentException("The 'action' argument must be either 'enable' or 'disable'");
        }

        $haproxyClient->setServerStatusInCluster($loadBalancers, $server, $action, $backend);

        $this->outputLog();

        $this->call('check', [
            'env' => $env, 'server' => $server, 'backend' => $backend, '--debug' => $this->option('debug')
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function getArguments()
    {
        return array_merge(
            parent::getArguments(),
            [
                ['server', InputArgument::REQUIRED, 'The name of the server as set in the HAProxy config'],
                ['action', InputArgument::REQUIRED, "'enable' or 'disable"],
                ['backend', InputArgument::OPTIONAL, 'The name of the backend as set in the HAProxy config', 'all']
            ]
        );
    }


    /**
     * @inheritdoc
     */
    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            [

            ]
        );
    }
}