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
    protected $name = 'mailer:queue:flush';

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
        $config = $this->container['config'];

        // Get the Swift Spool instance.
        $spool = $this->getSwiftSpool();

        // Execute a recovery if for any reason a process is sending for too long.
        $timeout = $config->get('mail.spool.timeout', 900);

        if (is_integer($timeout) && ($timeout > 0)) {
            $spool->recover($timeout);
        }

        // Sends messages using the given transport instance.
        $result = $spool->flushQueue($this->transport);

        $this->info("Sent $result email(s) ...");
    }

    /**
     * Get the messages from the Mailer's Spool instance.
     *
     * @return \Swift_Spool
     */
    protected function getSwiftSpool()
    {
        return $this->spoolTransport->getSpool();
    }
}
