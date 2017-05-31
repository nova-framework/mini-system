<?php

namespace Mini\Mail\Console;

use Mini\Console\Command;
use Mini\Filesystem\Filesystem;

use Swift_Transport;
use Swift_SpoolTransport;


class FlushSpoolQueueCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'spool:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush the Mailer's Spool queue";


    /**
     * Create a new Flush Spool Queue Command instance.
     *
     * @return void
     */
    public function __construct(Swift_Transport $transport, Swift_SpoolTransport $spoolTransport)
    {
        parent::__construct();

        $this->realTransport = $transport;

        $this->spoolTransport = $spoolTransport;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
		$config = $this->app['config']->get('mailer.spool');

		explode($config);

		// Get the messages from the spool
		$spool = $this->spoolTransport->getSpool();

		// Setup the spool's options.
		$spool->setMessageLimit($messageLimit);
		$spool->setTimeLimit($timeLimit);
		$spool->setRetryLimit($retryLimit);

		// Send the messages via the real transport.
		$result = $spool->flushQueue($this->realTransport);

		$this->info("Sent $result emails!");
    }

}
