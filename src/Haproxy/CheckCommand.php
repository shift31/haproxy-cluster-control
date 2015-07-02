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
    const DEFAULT_SLEEP_TIME = 2;
    const DEFAULT_VERIFY_MAX_ATTEMPTS = 15;

    /**
     * @inheritdoc
     */
    protected $name = 'check';


    /**
     * @inheritdoc
     */
    protected $description = 'Check server state in backend';

    /**
     * @var HAProxyClient
     */
    protected $haproxyClient;


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $env = $this->argument('env');

        $this->haproxyClient = new HAProxyClient(
            null,
            $this->getEnvironmentConfig($env, 'port'),
            $this->getEnvironmentConfig($env, 'baseUrl'),
            $this->getEnvironmentConfig($env, 'username'),
            $this->getEnvironmentConfig($env, 'password'),
            15,
            $this->logger
        );

        $loadBalancers = $this->getEnvironmentConfig($env, 'servers');

        // set backend or determine using nickname map
        $backend = $this->argument('backend');

        if ($backend !== null && in_array($backend, array_keys($this->config['backend_nickname_map']))) {
            $backend = $this->config['backend_nickname_map'][$backend];
        }

        $server = $this->argument('server');


        // optionally verify status
        if ( ($this->option('up') || $this->option('down')) && $this->argument('backend') == 'all') {
            $this->error('Must specify [backend] when verifying status');
            exit(1);
        }

        if ($this->option('up')) {
            $statusVerified = $this->verifyStatus($loadBalancers, $server, $backend, 'UP');
            if ($statusVerified === false) exit(1);
        } elseif ($this->option('down')) {
            $statusVerified = $this->verifyStatus($loadBalancers, $server, $backend, 'DOWN');
            if ($statusVerified === false) exit(1);
        } else {

            // display table of statuses
            $headers = ['Load Balancer', 'Backend', 'Server', 'Status'];
            $statuses = $this->getStatuses($loadBalancers, $server, $backend);

            if ( ! empty($statuses)) {
                $this->table($headers, $statuses);
            } else {
                $this->comment("No status available for $server");
            }
        }

        $this->outputLog();
    }


    /**
     * @param array $loadBalancers
     * @param string $server
     * @param string $backend
     *
     * @return array[]
     */
    protected function getStatuses(array $loadBalancers, $server, $backend)
    {
        $clusterStats = $this->haproxyClient->getStatsFromCluster($loadBalancers);

        if (count($clusterStats) <= 0) {
            $this->error("Unable to retrieve stats from any of $server's load balancers.");
            exit(1);
        }

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
                    if ( ! isset($servers[$server]['status'])) {
                        continue;
                    }
                    $status = $servers[$server]['status'];
                    $statuses[] = [$loadBalancer, $proxyName, $server, $status];
                }
            }
        }

        return $statuses;
    }


    /**
     * @param array  $loadBalancers
     * @param string $server
     * @param string $backend
     * @param string $status
     *
     * @return bool
     */
    protected function verifyStatus(array $loadBalancers, $server, $backend, $status)
    {
        $status = strtoupper($status);

        $statusVerified = false;
        $noServiceInProxy = false;
        $lbTries = 0;
        $sleepTime = $this->option('sleep');

        while ($statusVerified === false && $lbTries < $this->option('verifyMaxAttempts')) {
            $lbTries++;

            $clusterStats = $this->haproxyClient->getStatsFromCluster($loadBalancers);

            if (count($clusterStats) <= 0) {
                $this->error("Unable to retrieve stats from any of $server's load balancers.");
                break; // break out of while loop
            }

            foreach ($clusterStats as $loadBalancer => $stats) {

                if (isset($stats[$backend][$server])) {

                    $proxyServiceStatus = $stats[$backend][$server]['status'];

                    $this->info("$loadBalancer: Status for service '$server' in proxy '$backend' is '$proxyServiceStatus'");

                    if ($status == 'UP') {
                        $statusVerified = ($proxyServiceStatus == 'UP') ? true : false;
                    } else {
                        // make sure status is 'DOWN' or 'MAINT' and has zero current connections
                        $statusVerified = ($proxyServiceStatus == 'DOWN' || $proxyServiceStatus == 'MAINT')
                        && ($stats[$backend][$server]['scur'] == '0') ? true : false;
                    }

                } else {
                    $this->error("Unable to find server named '$server' in backend named '$backend' on $loadBalancer.");
                    $noServiceInProxy = true;
                    break 2; // break out of while loop
                }
            }

            // wait between attempts
            sleep($sleepTime);
        }

        if ($statusVerified === true) {
            $this->info("HAProxy detected server '$server' of backend '$backend' is '$status'.  Retried $lbTries times ("
                . $lbTries * $sleepTime . ' seconds).'
            );
        } elseif ($noServiceInProxy === false) {
            $this->error("HAProxy did not detect server '$server' of backend '$backend' as '$status'.  Retried $lbTries times ("
                . $lbTries * $sleepTime . ' seconds).'
            );
        }

        return $statusVerified;
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
                ['verifyMaxAttempts', null, InputOption::VALUE_REQUIRED, 'Maximum number of attempts when verifying status', self::DEFAULT_VERIFY_MAX_ATTEMPTS],
                ['sleep', null, InputOption::VALUE_REQUIRED, 'Sleep time in seconds between status verification attempts', self::DEFAULT_SLEEP_TIME],
                ['up', null, InputOption::VALUE_NONE, 'Verify status is UP'],
                ['down', null, InputOption::VALUE_NONE, 'Verify status is DOWN']
            ]
        );
    }
}