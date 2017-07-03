<?php

namespace Mini\Http;

use Mini\Http\ResponseTrait;
use Mini\Support\Contracts\JsonableInterface;
use Mini\Support\Contracts\RenderableInterface;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use ArrayObject;
use Exception;


class Response extends SymfonyResponse
{
	use ResponseTrait;

	/**
	 * The original content of the response.
	 *
	 * @var mixed
	 */
	public $original;

	/**
	 * The exception that triggered the error response (if applicable).
	 *
	 * @var \Exception|null
	 */
	public $exception;


	/**
	 * Set the content on the response.
	 *
	 * @param  mixed  $content
	 * @return $this
	 */
	public function setContent($content)
	{
		$this->original = $content;

		// If the content is "JSONable" we will set the appropriate header and convert
		// the content to JSON. This is useful when returning something like models
		// from routes that will be automatically transformed to their JSON form.
		if ($this->shouldBeJson($content)) {
			$this->headers->set('Content-Type', 'application/json');

			$content = $this->morphToJson($content);
		}

		// If this content implements the "RenderableInterface", then we will call the
		// render method on the object so we will avoid any "__toString" exceptions
		// that might be thrown and have their errors obscured by PHP's handling.
		else if ($content instanceof RenderableInterface) {
			$content = $content->render();
		}

		return parent::setContent($content);
	}

	/**
	 * Morph the given content into JSON.
	 *
	 * @param  mixed   $content
	 * @return string
	 */
	protected function morphToJson($content)
	{
		if ($content instanceof JsonableInterface) return $content->toJson();

		return json_encode($content);
	}

	/**
	 * Determine if the given content should be turned into JSON.
	 *
	 * @param  mixed  $content
	 * @return bool
	 */
	protected function shouldBeJson($content)
	{
		return ($content instanceof JsonableInterface) ||
			   ($content instanceof ArrayObject) ||
			   is_array($content);
	}

	/**
	 * Get the original response content.
	 *
	 * @return mixed
	 */
	public function getOriginalContent()
	{
		return $this->original;
	}

	/**
	 * Set the exception to attach to the response.
	 *
	 * @param  \Exception  $e
	 * @return $this
	 */
	public function withException(Exception $e)
	{
		$this->exception = $e;

		return $this;
	}

}
