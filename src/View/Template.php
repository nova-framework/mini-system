<?php

namespace Mini\View;

use Mini\Filesystem\Filesystem;
use Mini\Support\Arr;
use Mini\Support\Str;


class Template
{
	/**
	 * All of the registered extensions.
	 *
	 * @var array
	 */
	protected $extensions = array();

	/**
	* The Filesystem instance.
	*
	* @var \Mini\Filesystem\Filesystem
	*/
	protected $files;

	/**
	 * The file currently being compiled.
	 *
	 * @var string
	 */
	protected $path;

	/**
	* Get the cache path for the compiled views.
	*
	* @var string
	*/
	protected $cachePath;

	/**
	 * All of the available compiler functions.
	 *
	 * @var array
	 */
	protected $compilers = array(
		'Extensions',
		'Statements',
		'Comments',
		'Echos'
	);

	/**
	 * Array of footer lines to be added to template.
	 *
	 * @var array
	 */
	protected $footer = array();

	/**
	 * Counter to keep track of nested forelse statements.
	 *
	 * @var int
	 */
	protected $forelseCounter = 0;


	/**
	* Create a new Template Engine instance.
	*
	* @param  \Mini\Filesystem\Filesystem  $files
	* @param  string  $cachePath
	* @return void
	*/
	public function __construct(Filesystem $files, $cachePath)
	{
		$this->files = $files;

		$this->cachePath = $cachePath;
	}

	/**
	* Determine if the view at the given path is expired.
	*
	* @param  string  $path
	* @return bool
	*/
	public function isExpired($path)
	{
		$compiled = $this->getCompiledPath($path);

		if (is_null($this->cachePath) || ! $this->files->exists($compiled)) {
			return true;
		}

		$lastModified = $this->files->lastModified($path);

		if ($lastModified >= $this->files->lastModified($compiled)) {
			return true;
		}

		return false;
	}

	/**
	* Get the path to the compiled version of a view.
	*
	* @param  string  $path
	* @return string
	*/
	public function getCompiledPath($path)
	{
		return $this->cachePath .DS .sha1($path) .'.php';
	}

	/**
	 * Get the path currently being compiled.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Set the path currently being compiled.
	 *
	 * @param  string  $path
	 * @return void
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * Compile the view at the given path.
	 *
	 * @param  string  $path
	 * @return void
	 */
	public function compile($path = null)
	{
		$this->footer = array();

		if (! is_null($path)) {
			$this->setPath($path);
		}

		$contents = $this->compileString($this->files->get($path));

		if ( ! is_null($this->cachePath)) {
			$compiled = $this->getCompiledPath($this->getPath());

			$this->files->put($compiled, $contents);
		}
	}

	/**
	 * Compile the given Template template contents.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function compileString($value)
	{
		$result = '';

		foreach (token_get_all($value) as $token) {
			$result .= is_array($token) ? $this->parseToken($token) : $token;
		}

		if (count($this->footer) > 0) {
			$footer = implode(PHP_EOL, array_reverse($this->footer));

			$result = ltrim($result, PHP_EOL) .PHP_EOL .$footer;
		}

		return $result;
	}

	/**
	 * Parse the tokens from the template.
	 *
	 * @param  array  $token
	 * @return string
	 */
	protected function parseToken($token)
	{
		list($id, $content) = $token;

		if ($id == T_INLINE_HTML) {
			foreach ($this->compilers as $type) {
				$method = 'compile' .$type;

				$content = call_user_func(array($this, $method), $content);
			}
		}

		return $content;
	}

	/**
	 * Execute the user defined extensions.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileExtensions($value)
	{
		foreach ($this->extensions as $compiler) {
			$value = call_user_func($compiler, $value, $this);
		}

		return $value;
	}

	/**
	 * Compile Template comments into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileComments($value)
	{
		return preg_replace('/{{--((.|\s)*?)--}}/', '<?php /*$1*/ ?>', $value);
	}

	/**
	 * Compile the echo statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileEchos($value)
	{
		// First, we will compile the escaped echo statements.
		$value = preg_replace_callback('/{{{\s*(.+?)\s*}}}(\r?\n)?/s', function($matches)
		{
			$whitespace = empty($matches[2]) ? '' : $matches[2] .$matches[2];

			return '<?= e(' .$this->compileEchoDefaults($matches[1]) .'); ?>' .$whitespace;

		}, $value);

		return $this->compileRegularEchos($value);
	}

	/**
	 * Compile Template Statements that start with "@"
	 *
	 * @param  string  $value
	 * @return mixed
	 */
	protected function compileStatements($value)
	{
		return preg_replace_callback('/\B@(\w+)([ \t]*)(\(((?>[^()]+)|(?3))*\))?/x', function($match)
		{
			if (method_exists($this, $method = 'compile' .ucfirst($match[1]))) {
				$match[0] = call_user_func(array($this, $method), Arr::get($match, 3));
			}

			return isset($match[3]) ? $match[0] : $match[0] .$match[2];

		}, $value);
	}

	/**
	 * Compile the "regular" echo statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileRegularEchos($value)
	{
		return preg_replace_callback('/(@)?{{\s*(.+?)\s*}}(\r?\n)?/s', function($matches)
		{
			$whitespace = empty($matches[3]) ? '' : $matches[3] .$matches[3];

			return ! empty($matches[1])
				? substr($matches[0], 1)
				: '<?= ' .$this->compileEchoDefaults($matches[2]) .'; ?>' .$whitespace;

		}, $value);
	}

	/**
	 * Compile the default values for the echo statement.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function compileEchoDefaults($value)
	{
		return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/is', 'isset($1) ? $1 : $2', $value);
	}

	/**
	 * Compile the each statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEach($expression)
	{
		return "<?= \$__env->renderEach{$expression}; ?>";
	}

	/**
	 * Compile the yield statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileYield($expression)
	{
		return "<?= \$__env->yieldContent{$expression}; ?>";
	}

	/**
	 * Compile the show statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileShow($expression)
	{
		return "<?= \$__env->yieldSection(); ?>";
	}

	/**
	 * Compile the section statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileSection($expression)
	{
		return "<?php \$__env->startSection{$expression}; ?>";
	}

	/**
	 * Compile the append statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileAppend($expression)
	{
		return "<?php \$__env->appendSection(); ?>";
	}

	/**
	 * Compile the end-section statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndsection($expression)
	{
		return "<?php \$__env->stopSection(); ?>";
	}

	/**
	 * Compile the stop statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileStop($expression)
	{
		return "<?php \$__env->stopSection(); ?>";
	}

	/**
	 * Compile the overwrite statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileOverwrite($expression)
	{
		return "<?php \$__env->stopSection(true); ?>";
	}

	/**
	 * Compile the unless statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileUnless($expression)
	{
		return "<?php if ( ! $expression): ?>";
	}

	/**
	 * Compile the end unless statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndunless($expression)
	{
		return "<?php endif; ?>";
	}

	/**
	 * Compile the else statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileElse($expression)
	{
		return "<?php else: ?>";
	}

	/**
	 * Compile the for statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileFor($expression)
	{
		return "<?php for{$expression}: ?>";
	}

	/**
	 * Compile the foreach statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileForeach($expression)
	{
		return "<?php foreach{$expression}: ?>";
	}

	/**
	 * Compile the forelse statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileForelse($expression)
	{
		$empty = '$__empty_' . ++$this->forelseCounter;

		return "<?php {$empty} = true; foreach{$expression}: {$empty} = false; ?>";
	}

	/**
	 * Compile the if statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileIf($expression)
	{
		return "<?php if{$expression}: ?>";
	}

	/**
	 * Compile the else-if statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileElseif($expression)
	{
		return "<?php elseif{$expression}: ?>";
	}

	/**
	 * Compile the forelse statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEmpty($expression)
	{
		$empty = '$__empty_' . $this->forelseCounter--;

		return "<?php endforeach; if ({$empty}): ?>";
	}

	/**
	 * Compile the while statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileWhile($expression)
	{
		return "<?php while{$expression}: ?>";
	}

	/**
	 * Compile the end-while statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndwhile($expression)
	{
		return "<?php endwhile; ?>";
	}

	/**
	 * Compile the end-for statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndfor($expression)
	{
		return "<?php endfor; ?>";
	}

	/**
	 * Compile the end-for-each statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndforeach($expression)
	{
		return "<?php endforeach; ?>";
	}

	/**
	 * Compile the end-if statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndif($expression)
	{
		return "<?php endif; ?>";
	}

	/**
	 * Compile the end-for-else statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndforelse($expression)
	{
		return "<?php endif; ?>";
	}

	/**
	 * Compile the raw PHP statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compilePhp($expression)
	{
		return ! empty($expression) ? "<?php {$expression}; ?>" : '<?php ';
	}

	/**
	 * Compile end-php statement into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndphp($expression)
	{
		return ' ?>';
	}

	/**
	 * Compile the can statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileCan($expression)
	{
		return "<?php if (app('gate')->check{$expression}): ?>";
	}

	/**
	 * Compile the end-can statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndcan($expression)
	{
		return '<?php endif; ?>';
	}

	/**
	 * Compile the cannot statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileCannot($expression)
	{
		return "<?php if (app('gate')->denies{$expression}): ?>";
	}

	/**
	 * Compile the end-cannot statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndcannot($expression)
	{
		return '<?php endif; ?>';
	}

	/**
	 * Compile the extends statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileExtends($expression)
	{
		$expression = $this->stripParentheses($expression);

		$data = "<?= \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";

		$this->footer[] = $data;

		return '';
	}

	/**
	 * Compile the include statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileInclude($expression)
	{
		$expression = $this->stripParentheses($expression);

		return "<?= \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";
	}

	/**
	 * Compile the stack statements into the content
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileStack($expression)
	{
		return "<?= \$__env->yieldContent{$expression}; ?>";
	}

	/**
	 * Compile the push statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compilePush($expression)
	{
		return "<?php \$__env->startSection{$expression}; ?>";
	}

	/**
	 * Compile the endpush statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileEndpush($expression)
	{
		return "<?php \$__env->appendSection(); ?>";
	}

	/**
	 * Strip the parentheses from the given expression.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	public function stripParentheses($expression)
	{
		if (Str::startsWith($expression, '(')) {
			$expression = substr($expression, 1, -1);
		}

		return $expression;
	}

	/**
	 * Register a custom Template compiler.
	 *
	 * @param  \Closure  $compiler
	 * @return void
	 */
	public function extend(Closure $compiler)
	{
		$this->extensions[] = $compiler;
	}

	/**
	 * Get the regular expression for a generic Template function.
	 *
	 * @param  string  $function
	 * @return string
	 */
	public function createMatcher($function)
	{
		return '/(?<!\w)(\s*)@' .$function .'(\s*\(.*\))/';
	}

	/**
	 * Get the regular expression for a generic Template function.
	 *
	 * @param  string  $function
	 * @return string
	 */
	public function createOpenMatcher($function)
	{
		return '/(?<!\w)(\s*)@' .$function .'(\s*\(.*)\)/';
	}

	/**
	 * Create a plain Template matcher.
	 *
	 * @param  string  $function
	 * @return string
	 */
	public function createPlainMatcher($function)
	{
		return '/(?<!\w)(\s*)@' .$function .'(\s*)/';
	}
}
