<?php

namespace Mini\Foundation\Auth\Access;

use Mini\Auth\Contracts\Access\GateInterface as Gate;
use Mini\Auth\Access\AuthorizationException;
use Mini\Support\Facades\App;

use Symfony\Component\HttpKernel\Exception\HttpException;


trait AuthorizesRequestsTrait
{
	/**
	 * Authorize a given action against a set of arguments.
	 *
	 * @param  mixed  $ability
	 * @param  mixed|array  $arguments
	 * @return \Mini\Auth\Access\Response
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	public function authorize($ability, $arguments = array())
	{
		list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

		$gate = App::make(Gate::class);

		return $this->authorizeAtGate($gate, $ability, $arguments);
	}

	/**
	 * Authorize a given action for a user.
	 *
	 * @param  \Mini\Auth\UserInterface|mixed  $user
	 * @param  mixed  $ability
	 * @param  mixed|array  $arguments
	 * @return \Mini\Auth\Access\Response
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	public function authorizeForUser($user, $ability, $arguments = array())
	{
		list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

		$gate = App::make(Gate::class)->forUser($user);

		return $this->authorizeAtGate($gate, $ability, $arguments);
	}

	/**
	 * Authorize the request at the given gate.
	 *
	 * @param  \Mini\Auth\Access\GateInterface  $gate
	 * @param  mixed  $ability
	 * @param  mixed|array  $arguments
	 * @return \Mini\Auth\Access\Response
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	public function authorizeAtGate(Gate $gate, $ability, $arguments)
	{
		try {
			return $gate->authorize($ability, $arguments);
		}
		catch (AuthorizationException $e) {
			$exception = $this->createGateAuthorizationException($ability, $arguments, $e->getMessage(), $e);

			throw $exception;
		}
	}

	/**
	 * Guesses the ability's name if it wasn't provided.
	 *
	 * @param  mixed  $ability
	 * @param  mixed|array  $arguments
	 * @return array
	 */
	protected function parseAbilityAndArguments($ability, $arguments)
	{
		if (is_string($ability) && (strpos($ability, '\\') === false)) {
			return array($ability, $arguments);
		}

		list(,, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

		return array($this->normalizeGuessedAbilityName($caller['function']), $ability);
	}

	/**
	 * Normalize the ability name that has been guessed from the method name.
	 *
	 * @param  string  $ability
	 * @return string
	 */
	protected function normalizeGuessedAbilityName($ability)
	{
		$map = $this->resourceAbilityMap();

		return isset($map[$ability]) ? $map[$ability] : $ability;
	}

	/**
	 * Get the map of resource methods to ability names.
	 *
	 * @return array
	 */
	protected function resourceAbilityMap()
	{
		return array(
			'show'		=> 'view',
			'create'	=> 'create',
			'store'		=> 'create',
			'edit'		=> 'update',
			'update'	=> 'update',
			'destroy'	=> 'delete',
		);
	}

	/**
	 * Throw an unauthorized exception based on gate results.
	 *
	 * @param  string  $ability
	 * @param  mixed|array  $arguments
	 * @param  string  $message
	 * @param  \Exception  $previousException
	 * @return \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	protected function createGateAuthorizationException($ability, $arguments, $message = null, $previousException = null)
	{
		$message = $message ?: __d('nova', 'This action is unauthorized.');

		return new HttpException(403, $message, $previousException);
	}

}
