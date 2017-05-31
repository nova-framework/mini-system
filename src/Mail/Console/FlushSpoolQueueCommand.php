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
	protected $name = 'spool:queue:flush';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Flush the Mailer's Spool queue";

	/**
	 * The Swift Transport instance.
	 *
	 * @var \Swift_Transport
	 */
	protected $transport;

	/**
	 * The Swift Spool Transport instance.
	 *
	 * @var \Swift_SpoolTransport
	 */
	protected $spoolTransport;


	/**
	 * Create a new Flush Spool Queue Command instance.
	 *
	 * @return void
	 */
	public function __construct(Swift_Transport $transport, Swift_SpoolTransport $spoolTransport)
	{
		parent::__construct();

		//
		$this->transport = $transport;

		$this->spoolTransport = $spoolTransport;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$config = $this->container['config']->get('mail.spool');

		extract($config);

		// Get the messages from the spool.
		$spool = $this->spoolTransport->getSpool();

		// Setup the spool's options.
		$spool->setMessageLimit($messageLimit);
		$spool->setTimeLimit($timeLimit);
		$spool->setRetryLimit($retryLimit);

		// Send the messages via the real transport.
		$result = $spool->flushQueue($this->transport);

		$this->info("Sent $result emails!");
	}

}
