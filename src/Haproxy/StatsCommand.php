<?php namespace Shift31\Haproxy;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


/**
 * Class StatsCommand
 *
 * @package Shift31\Haproxy
 * @todo
 */
class StatsCommand extends BaseCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'stats';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View clluster stats';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
//       $stats = $this->haproxyClient->getStatsFromCluster([
//       ]);
//
//       $this->line(print_r($stats, true));
    }


    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
//            ['fqdn|query', InputArgument::REQUIRED, 'A FQDN or query string.'],
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array
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