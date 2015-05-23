<?php namespace Shift31\Haproxy;

use Shift31\HAProxyClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


/**
 * Class CheckCommand
 *
 * @package Shift31\Haproxy
 */
class CheckCommand extends BaseCommand
{
    /**
     * @inheritdoc
     */
    protected $name = 'check';


    /**
     * @inheritdoc
     */
    protected $description = 'Check server state in backend';


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

        if ($backend !== null && in_array($backend, array_keys($this->config['backend_nickname_map']))) {
            $backend = $this->config['backend_nickname_map'][$backend];
        }

        $server = $this->argument('server');

        $clusterStats = $haproxyClient->getStatsFromCluster($loadBalancers);

        if (count($clusterStats) <= 0) {
            $this->error("Unable to retrieve stats from any of $server's load balancers.");
            exit(1);
        }

        $headers = ['Load Balancer', 'Backend', 'Server', 'Status'];
        $statuses = [];

        foreach ($clusterStats as $loadBalancer => $stats) {

            if ($backend !== 'all') {
                if ( ! isset($stats[$backend][$server])) {
                    $this->error(
                        "Unable to find server named '$server' in backend named '$backend' on $loadBalancer."
                    );
                    exit(1);
                }

                $status = $stats[$backend][$server]['status'];

                $statuses[] = [$loadBalancer, $backend, $server, $status];
            } else {
                foreach ($stats as $proxyName => $servers) {
                    if ( ! isset($servers[$server]['status'])) continue;
                    $status = $servers[$server]['status'];
                    $statuses[] = [$loadBalancer, $proxyName, $server, $status];
                }
            }
        }

        if ( ! empty($statuses)) {
            $this->table($headers, $statuses);
        } else {
            $this->comment("No status available for $server");
        }

        $this->outputLog();
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
                ['backend', InputArgument::OPTIONAL, 'The name of the backend as set in the HAProxy config', 'all'],
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