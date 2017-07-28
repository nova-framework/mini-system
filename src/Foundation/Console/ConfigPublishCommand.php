<?php

namespace Mini\Foundation\Console;

use Mini\Console\Command;
use Mini\Console\ConfirmableTrait;
use Mini\Foundation\Publishers\ConfigPublisher;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ConfigPublishCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Publish a package's configuration to the application";

    /**
     * The config publisher instance.
     *
     * @var \Mini\Foundation\ConfigPublisher
     */
    protected $publisher;


    /**
     * Create a new configuration publish command instance.
     *
     * @param  \Mini\Foundation\ConfigPublisher  $config
     * @return void
     */
    public function __construct(ConfigPublisher $publisher)
    {
        parent::__construct();

        $this->publisher = $publisher;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $package = $this->input->getArgument('package');

        $proceed = $this->confirmToProceed('Config Already Published!', function() use ($package)
        {
            return $this->publisher->alreadyPublished($package);
        });

        if (! $proceed) return;

        $this->publisher->publishPackage($package);

        $this->output->writeln('<info>Configuration published for package:</info> '.$package);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('package', InputArgument::REQUIRED, 'The configuration namespace of the package being published.'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run when the file already exists.'),
        );
    }

}
