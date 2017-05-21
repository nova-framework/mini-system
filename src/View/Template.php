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
	 * Placeholder to temporary mark the position of verbatim blocks.
	 *
	 * @var string
	 */
	protected $verbatimPlaceholder = '@__verbatim__@';

	/**
	 * Array to temporary store the verbatim blocks found in the template.
	 *
	 * @var array
	 */
	protected $verbatimBlocks = array();

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
	 * Compile the view at the given path.
	 *
	 * @param  string  $path
	 * @return void
	 */
	public function compile($path = null)
	{
		if (! is_null($path)) {
			$this->setPath($path);
		}

		$contents = $this->compileString($this->files->get($path));

		if ( ! is_null($this->cachePath)) {
			$this->files->put($this->getCompiledPath($this->getPath()), $contents);
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

		if (strpos($value, '@verbatim') !== false) {
			$value = $this->storeVerbatimBlocks($value);
		}

		$this->footer = array();

		foreach (token_get_all($value) as $token) {
			$result .= is_array($token) ? $this->parseToken($token) : $token;
		}

		if (! empty($this->verbatimBlocks)) {
			$result = $this->restoreVerbatimBlocks($result);
		}

		if (count($this->footer) > 0) {
			$result = ltrim($result, PHP_EOL) .PHP_EOL .implode(PHP_EOL, array_reverse($this->footer));
		}

		return $result;
	}

	/**
	 * Store the verbatim blocks and replace them with a temporary placeholder.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function storeVerbatimBlocks($value)
	{
		return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches)
		{
			$this->verbatimBlocks[] = $matches[1];

			return $this->verbatimPlaceholder;

		}, $value);
	}

	/**
	 * Replace the raw placeholders with the original code stored in the raw blocks.
	 *
	 * @param  string  $result
	 * @return string
	 */
	protected function restoreVerbatimBlocks($result)
	{
		$result = preg_replace_callback('/' .preg_quote($this->verbatimPlaceholder) .'/', function ()
		{
			return array_shift($this->verbatimBlocks);

		}, $result);

		$this->verbatimBlocks = array();

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
	 * Compile the escaped echo statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileEchos($value)
	{
		// Compile the escaped echoes.
		$value = preg_replace_callback('/{{{\s*(.+?)\s*}}}(\r?\n)?/s', function($matches)
		{
			$whitespace = empty($matches[2]) ? '' : $matches[2] .$matches[2];

			return '<?php echo e(' .$this->compileEchoDefaults($matches[1]) .'); ?>' .$whitespace;

		}, $value);

		// Compile the regular echoes.
		$value = preg_replace_callback('/(@)?{{\s*(.+?)\s*}}(\r?\n)?/s', function($matches)
		{
			$whitespace = empty($matches[3]) ? '' : $matches[3] .$matches[3];

			return ! empty($matches[1])
				? substr($matches[0], 1)
				: '<?php echo ' .$this->compileEchoDefaults($matches[2]) .'; ?>' .$whitespace;

		}, $value);

		return $value;
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
		return "<?php echo \$__env->renderEach{$expression}; ?>";
	}

	/**
	 * Compile the yield statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileYield($expression)
	{
		return "<?php echo \$__env->yieldContent{$expression}; ?>";
	}

	/**
	 * Compile the show statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileShow($expression)
	{
		return "<?php echo \$__env->yieldSection(); ?>";
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
	 * Compile the break statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileBreak($expression)
	{
		return $expression ? "<?php if{$expression} break; ?>" : '<?php break; ?>';
	}

	/**
	 * Compile the continue statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileContinue($expression)
	{
		return $expression ? "<?php if{$expression} continue; ?>" : '<?php continue; ?>';
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
	 * Compile the unset statements into valid PHP.
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileUnset($expression)
	{
		return "<?php unset{$expression}; ?>";
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

		$data = "<?php echo \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";

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

		return "<?php echo \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";
	}

	/**
	 * Compile the stack statements into the content
	 *
	 * @param  string  $expression
	 * @return string
	 */
	protected function compileStack($expression)
	{
		return "<?php echo \$__env->yieldContent{$expression}; ?>";
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
}
