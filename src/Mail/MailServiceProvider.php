<?php

namespace Mini\Mail;

use Mini\Mail\LogTransport;
use Mini\Support\ServiceProvider;

use Swift_Mailer;
use Swift_SmtpTransport as SmtpTransport;
use Swift_MailTransport as MailTransport;
use Swift_SendmailTransport as SendmailTransport;

use Swift_FileSpool as FileSpool;
use Swift_SpoolTransport as SpoolTransport;


class MailServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('mailer', function ($app)
		{
			$this->registerSwiftMailers();

			$mailer = new Mailer(
				$app['view'], $app['swift.mailer'], $app['swift.spool.mailer'], $app['events']
			);

			$this->setMailerDependencies($mailer, $app);

			$from = $app['config']['mail.from'];

			if (is_array($from) && isset($from['address'])) {
				$mailer->alwaysFrom($from['address'], $from['name']);
			}

			$pretend = $app['config']->get('mail.pretend', false);

			$mailer->pretend($pretend);

			return $mailer;
		});

		$this->registerCommands();
	}

	public function registerCommands()
	{
		$this->app->bindShared('command.mailer.spool.flush', function($app)
		{
			$this->registerSwiftMailers();

			return new Console\FlushSpoolQueueCommand($app['swift.transport'], $app['swift.spool.transport']);
		});

		$this->commands('command.mailer.spool.flush');
	}

	/**
	 * Set a few dependencies on the mailer instance.
	 *
	 * @param  \Mini\Mail\Mailer  $mailer
	 * @param  \Mini\Foundation\Application  $app
	 * @return void
	 */
	protected function setMailerDependencies($mailer, $app)
	{
		$mailer->setContainer($app);

		if ($app->bound('log')) {
			$mailer->setLogger($app['log']);
		}
	}

	/**
	 * Register the Swift Mailer instance.
	 *
	 * @return void
	 */
	public function registerSwiftMailers()
	{
		$config = $this->app['config']['mail'];

		$this->registerSwiftTransport($config);

		$this->registerSpoolTransport($config);

		$this->app['swift.mailer'] = $this->app->share(function ($app)
		{
			return new Swift_Mailer($app['swift.transport']);
		});

		$this->app['swift.spool.mailer'] = $this->app->share(function ($app)
		{
			return new Swift_Mailer($app['swift.spool.transport']);
		});
	}

	/**
	 * Register the Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function registerSwiftTransport($config)
	{
		switch ($config['driver'])
		{
			case 'smtp':
				return $this->registerSmtpTransport($config);

			case 'sendmail':
				return $this->registerSendmailTransport($config);

			case 'mail':
				return $this->registerMailTransport($config);

			case 'log':
				return $this->registerLogTransport($config);

			default:
				throw new \InvalidArgumentException('Invalid mail driver.');
		}
	}

	/**
	 * Register the SMTP Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	protected function registerSmtpTransport($config)
	{
		$this->app['swift.transport'] = $this->app->share(function ($app) use ($config)
		{
			extract($config);

			$transport = SmtpTransport::newInstance($host, $port);

			if (isset($encryption)) {
				$transport->setEncryption($encryption);
			}

			if (isset($username)) {
				$transport->setUsername($username);

				$transport->setPassword($password);
			}

			return $transport;
		});
	}

	/**
	 * Register the Sendmail Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	protected function registerSendmailTransport($config)
	{
		$this->app['swift.transport'] = $this->app->share(function ($app) use ($config)
		{
			return SendmailTransport::newInstance($config['sendmail']);
		});
	}

	/**
	 * Register the Mail Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	protected function registerMailTransport($config)
	{
		$this->app['swift.transport'] = $this->app->share(function ()
		{
			return MailTransport::newInstance();
		});
	}

	/**
	 * Register the Spool Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	protected function registerSpoolTransport($config)
	{
		$config = isset($config['spool'])
			? $config['spool']
			: array('files' => STORAGE_PATH .'spool');

		$this->app['swift.spool.transport'] = $this->app->share(function () use ($config)
		{
			$spool = new FileSpool($config['files']);

			return SpoolTransport::newInstance($spool);
		});
	}

	/**
	 * Register the "Log" Swift Transport instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	protected function registerLogTransport($config)
	{
		$this->app->bindShared('swift.transport', function ($app)
		{
			return new LogTransport($app->make('Psr\Log\LoggerInterface'));
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('mailer', 'swift.mailer', 'swift.transport');
	}

}
