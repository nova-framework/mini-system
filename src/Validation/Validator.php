<?php

namespace Mini\Validation;

use Mini\Config\Repository as Config;
use Mini\Container\Container;
use Mini\Support\Fluent;
use Mini\Support\MessageBag;
use Mini\Support\Contracts\MessageProviderInterface;
use Mini\Support\Str;
use Mini\Validation\Presence\PresenceVerifierInterface;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Closure;
use DateTime;
use DateTimeZone;


class Validator implements MessageProviderInterface
{
	/**
	 * The Config instance.
	 *
	 * @var \Mini\Config\Repository
	 */
	protected $config;

	/**
	 * The Presence Verifier implementation.
	 *
	 * @var \Validation\PresenceVerifierInterface
	 */
	protected $presenceVerifier;

	/**
	 * The failed validation rules.
	 *
	 * @var array
	 */
	protected $failedRules = array();

	/**
	 * The message bag instance.
	 *
	 * @var \Mini\Support\MessageBag
	 */
	protected $messages;

	/**
	 * The data under validation.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The files under validation.
	 *
	 * @var array
	 */
	protected $files = array();

	/**
	 * The rules to be applied to the data.
	 *
	 * @var array
	 */
	protected $rules;

	/**
	 * The array of custom error messages.
	 *
	 * @var array
	 */
	protected $customMessages = array();

	/**
	 * The array of fallback error messages.
	 *
	 * @var array
	 */
	protected $fallbackMessages = array();

	/**
	 * The array of custom attribute names.
	 *
	 * @var array
	 */
	protected $customAttributes = array();

	/**
	 * The array of custom displayabled values.
	 *
	 * @var array
	 */
	protected $customValues = array();

	/**
	 * All of the custom validator extensions.
	 *
	 * @var array
	 */
	protected $extensions = array();

	/**
	 * All of the custom replacer extensions.
	 *
	 * @var array
	 */
	protected $replacers = array();

	/**
	 * The size related validation rules.
	 *
	 * @var array
	 */
	protected $sizeRules = array('Size', 'Between', 'Min', 'Max');

	/**
	 * The numeric related validation rules.
	 *
	 * @var array
	 */
	protected $numericRules = array('Numeric', 'Integer');

	/**
	 * The validation rules that imply the field is required.
	 *
	 * @var array
	 */
	protected $implicitRules = array(
		'Required', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll', 'RequiredIf', 'Accepted'
	);


	/**
	 * Create a new Validator instance.
	 *
	 * @param  \Mini\Config\Repository  $config
	 * @param  array  $data
	 * @param  array  $rules
	 * @param  array  $messages
	 * @param  array  $customAttributes
	 * @return void
	 */
	public function __construct(Config $config, array $data, array $rules, array $messages = array(), array $customAttributes = array())
	{
		$this->config = $config;

		$this->customMessages = $messages;

		$this->data  = $this->parseData($data);
		$this->rules = $this->explodeRules($rules);

		$this->customAttributes = $customAttributes;
	}

	/**
	 * Parse the data and hydrate the files array.
	 *
	 * @param  array   $data
	 * @param  string  $arrayKey
	 * @return array
	 */
	protected function parseData(array $data, $arrayKey = null)
	{
		if (is_null($arrayKey)) {
			$this->files = array();
		}

		foreach ($data as $key => $value) {
			$key = ($arrayKey) ? "$arrayKey.$key" : $key;

			if ($value instanceof File) {
				$this->files[$key] = $value;

				unset($data[$key]);
			} else if (is_array($value)) {
				$this->parseData($value, $key);
			}
		}

		return $data;
	}

	/**
	 * Explode the rules into an array of rules.
	 *
	 * @param  string|array  $rules
	 * @return array
	 */
	protected function explodeRules($rules)
	{
		foreach ($rules as $key => &$rule) {
			$rule = (is_string($rule)) ? explode('|', $rule) : $rule;
		}

		return $rules;
	}

	/**
	 * Add conditions to a given field based on a Closure.
	 *
	 * @param  string  $attribute
	 * @param  string|array  $rules
	 * @param  callable  $callback
	 * @return void
	 */
	public function sometimes($attribute, $rules, callable $callback)
	{
		$payload = new Fluent(array_merge($this->data, $this->files));

		if (call_user_func($callback, $payload)) {
			foreach ((array) $attribute as $key) {
				$this->mergeRules($key, $rules);
			}
		}
	}

	/**
	 * Define a set of rules that apply to each element in an array attribute.
	 *
	 * @param  string  $attribute
	 * @param  string|array  $rules
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	public function each($attribute, $rules)
	{
		$data = array_get($this->data, $attribute);

		if (! is_array($data)) {
			if ($this->hasRule($attribute, 'Array')) return;

			throw new \InvalidArgumentException('Attribute for each() must be an array.');
		}

		foreach ($data as $dataKey => $dataValue) {
			foreach ($rules as $ruleValue) {
				$this->mergeRules("$attribute.$dataKey", $ruleValue);
			}
		}
	}

	/**
	 * Merge additional rules into a given attribute.
	 *
	 * @param  string  $attribute
	 * @param  string|array  $rules
	 * @return void
	 */
	public function mergeRules($attribute, $rules)
	{
		$current = isset($this->rules[$attribute]) ? $this->rules[$attribute] : [];

		$merge = head($this->explodeRules(array($rules)));

		$this->rules[$attribute] = array_merge($current, $merge);
	}

	/**
	 * Determine if the data passes the validation rules.
	 *
	 * @return bool
	 */
	public function passes()
	{
		$this->messages = new MessageBag;

		foreach ($this->rules as $attribute => $rules) {
			foreach ($rules as $rule) {
				$this->validate($attribute, $rule);
			}
		}

		return count($this->messages->all()) === 0;
	}

	/**
	 * Determine if the data fails the validation rules.
	 *
	 * @return bool
	 */
	public function fails()
	{
		return ! $this->passes();
	}

	/**
	 * Validate a given attribute against a rule.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @return void
	 */
	protected function validate($attribute, $rule)
	{
		list($rule, $parameters) = $this->parseRule($rule);

		if ($rule == '') return;

		$value = $this->getValue($attribute);

		$validatable = $this->isValidatable($rule, $attribute, $value);

		$method = "validate{$rule}";

		if ($validatable && ! $this->$method($attribute, $value, $parameters, $this)) {
			$this->addFailure($attribute, $rule, $parameters);
		}
	}

	/**
	 * Returns the data which was valid.
	 *
	 * @return array
	 */
	public function valid()
	{
		if (! $this->messages) $this->passes();

		return array_diff_key($this->data, $this->messages()->toArray());
	}

	/**
	 * Returns the data which was invalid.
	 *
	 * @return array
	 */
	public function invalid()
	{
		if (! $this->messages) $this->passes();

		return array_intersect_key($this->data, $this->messages()->toArray());
	}

	/**
	 * Get the value of a given attribute.
	 *
	 * @param  string  $attribute
	 * @return mixed
	 */
	protected function getValue($attribute)
	{
		if (! is_null($value = array_get($this->data, $attribute))) {
			return $value;
		} else if (! is_null($value = array_get($this->files, $attribute))) {
			return $value;
		}
	}

	/**
	 * Determine if the attribute is validatable.
	 *
	 * @param  string  $rule
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function isValidatable($rule, $attribute, $value)
	{
		return $this->presentOrRuleIsImplicit($rule, $attribute, $value) &&
			   $this->passesOptionalCheck($attribute) &&
			   $this->hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute);
	}

	/**
	 * Determine if the field is present, or the rule implies required.
	 *
	 * @param  string  $rule
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function presentOrRuleIsImplicit($rule, $attribute, $value)
	{
		return $this->validateRequired($attribute, $value) || $this->isImplicit($rule);
	}

	/**
	 * Determine if the attribute passes any optional check.
	 *
	 * @param  string  $attribute
	 * @return bool
	 */
	protected function passesOptionalCheck($attribute)
	{
		if ($this->hasRule($attribute, array('Sometimes'))) {
			return array_key_exists($attribute, array_dot($this->data))
				|| in_array($attribute, array_keys($this->data))
				|| array_key_exists($attribute, $this->files);
		}

		return true;
	}

	/**
	 * Determine if a given rule implies the attribute is required.
	 *
	 * @param  string  $rule
	 * @return bool
	 */
	protected function isImplicit($rule)
	{
		return in_array($rule, $this->implicitRules);
	}

	/**
	 * Determine if it's a necessary presence validation.
	 *
	 * This is to avoid possible database type comparison errors.
	 *
	 * @param  string  $rule
	 * @param  string  $attribute
	 * @return bool
	 */
	protected function hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute)
	{
		return in_array($rule, ['Unique', 'Exists'])
						? ! $this->messages->has($attribute): true;
	}

	/**
	 * Add a failed rule and error message to the collection.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return void
	 */
	protected function addFailure($attribute, $rule, $parameters)
	{
		$this->addError($attribute, $rule, $parameters);

		$this->failedRules[$attribute][$rule] = $parameters;
	}

	/**
	 * Add an error message to the validator's collection of messages.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return void
	 */
	protected function addError($attribute, $rule, $parameters)
	{
		$message = $this->getMessage($attribute, $rule);

		$message = $this->doReplacements($message, $attribute, $rule, $parameters);

		$this->messages->add($attribute, $message);
	}

	/**
	 * "Validate" optional attributes.
	 *
	 * Always returns true, just lets us put sometimes in rules.
	 *
	 * @return bool
	 */
	protected function validateSometimes()
	{
		return true;
	}

	/**
	 * Validate that a required attribute exists.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateRequired($attribute, $value)
	{
		if (is_null($value)) {
			return false;
		}  else if (is_string($value) && trim($value) === '') {
			return false;
		} else if ((is_array($value) || $value instanceof \Countable) && count($value) < 1) {
			return false;
		} else if ($value instanceof File) {
			return (string) $value->getPath() != '';
		}

		return true;
	}

	/**
	 * Validate the given attribute is filled if it is present.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateFilled($attribute, $value)
	{
		if (array_key_exists($attribute, $this->data) || array_key_exists($attribute, $this->files)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Determine if any of the given attributes fail the required test.
	 *
	 * @param  array  $attributes
	 * @return bool
	 */
	protected function anyFailingRequired(array $attributes)
	{
		foreach ($attributes as $key) {
			if (! $this->validateRequired($key, $this->getValue($key))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if all of the given attributes fail the required test.
	 *
	 * @param  array  $attributes
	 * @return bool
	 */
	protected function allFailingRequired(array $attributes)
	{
		foreach ($attributes as $key) {
			if ($this->validateRequired($key, $this->getValue($key))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate that an attribute exists when any other attribute exists.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  mixed   $parameters
	 * @return bool
	 */
	protected function validateRequiredWith($attribute, $value, $parameters)
	{
		if (! $this->allFailingRequired($parameters)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Validate that an attribute exists when all other attributes exists.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  mixed   $parameters
	 * @return bool
	 */
	protected function validateRequiredWithAll($attribute, $value, $parameters)
	{
		if (! $this->anyFailingRequired($parameters)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Validate that an attribute exists when another attribute does not.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  mixed   $parameters
	 * @return bool
	 */
	protected function validateRequiredWithout($attribute, $value, $parameters)
	{
		if ($this->anyFailingRequired($parameters)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Validate that an attribute exists when all other attributes do not.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  mixed   $parameters
	 * @return bool
	 */
	protected function validateRequiredWithoutAll($attribute, $value, $parameters)
	{
		if ($this->allFailingRequired($parameters)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Validate that an attribute exists when another attribute has a given value.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  mixed   $parameters
	 * @return bool
	 */
	protected function validateRequiredIf($attribute, $value, $parameters)
	{
		$this->requireParameterCount(2, $parameters, 'required_if');

		$data = array_get($this->data, $parameters[0]);

		$values = array_slice($parameters, 1);

		if (in_array($data, $values)) {
			return $this->validateRequired($attribute, $value);
		}

		return true;
	}

	/**
	 * Get the number of attributes in a list that are present.
	 *
	 * @param  array  $attributes
	 * @return int
	 */
	protected function getPresentCount($attributes)
	{
		$count = 0;

		foreach ($attributes as $key) {
			if (array_get($this->data, $key) || array_get($this->files, $key)) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Validate that an attribute has a matching confirmation.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateConfirmed($attribute, $value)
	{
		return $this->validateSame($attribute, $value, array($attribute.'_confirmation'));
	}

	/**
	 * Validate that two attributes match.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateSame($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'same');

		$other = array_get($this->data, $parameters[0]);

		return (isset($other) && $value == $other);
	}

	/**
	 * Validate that an attribute is different from another attribute.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateDifferent($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'different');

		$other = $parameters[0];

		return isset($this->data[$other]) && $value != $this->data[$other];
	}

	/**
	 * Validate that an attribute was "accepted".
	 *
	 * This validation rule implies the attribute is "required".
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateAccepted($attribute, $value)
	{
		$acceptable = array('yes', 'on', '1', 1, true, 'true');

		return ($this->validateRequired($attribute, $value) && in_array($value, $acceptable, true));
	}

	/**
	 * Validate that an attribute is an array.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateArray($attribute, $value)
	{
		return is_array($value);
	}

	/**
	 * Validate that an attribute is a boolean.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateBoolean($attribute, $value)
	{
		$acceptable = array(true, false, 0, 1, '0', '1');

		return in_array($value, $acceptable, true);
	}

	/**
	 * Validate that an attribute is an integer.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateInteger($attribute, $value)
	{
		return filter_var($value, FILTER_VALIDATE_INT) !== false;
	}

	/**
	 * Validate that an attribute is numeric.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateNumeric($attribute, $value)
	{
		return is_numeric($value);
	}

	/**
	 * Validate that an attribute is a string.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateString($attribute, $value)
	{
		return is_string($value);
	}

	/**
	 * Validate that an attribute has a given number of digits.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateDigits($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'digits');

		return $this->validateNumeric($attribute, $value)
			&& strlen((string) $value) == $parameters[0];
	}

	/**
	 * Validate that an attribute is between a given number of digits.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateDigitsBetween($attribute, $value, $parameters)
	{
		$this->requireParameterCount(2, $parameters, 'digits_between');

		$length = strlen((string) $value);

		return $this->validateNumeric($attribute, $value)
		  && $length >= $parameters[0] && $length <= $parameters[1];
	}

	/**
	 * Validate the size of an attribute.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateSize($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'size');

		return $this->getSize($attribute, $value) == $parameters[0];
	}

	/**
	 * Validate the size of an attribute is between a set of values.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateBetween($attribute, $value, $parameters)
	{
		$this->requireParameterCount(2, $parameters, 'between');

		$size = $this->getSize($attribute, $value);

		return $size >= $parameters[0] && $size <= $parameters[1];
	}

	/**
	 * Validate the size of an attribute is greater than a minimum value.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateMin($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'min');

		return $this->getSize($attribute, $value) >= $parameters[0];
	}

	/**
	 * Validate the size of an attribute is less than a maximum value.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateMax($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'max');

		if ($value instanceof UploadedFile && ! $value->isValid()) return false;

		return $this->getSize($attribute, $value) <= $parameters[0];
	}

	/**
	 * Get the size of an attribute.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return mixed
	 */
	protected function getSize($attribute, $value)
	{
		$hasNumeric = $this->hasRule($attribute, $this->numericRules);

		if (is_numeric($value) && $hasNumeric) {
			return array_get($this->data, $attribute);
		} else if (is_array($value)) {
			return count($value);
		} else if ($value instanceof File) {
			return $value->getSize() / 1024;
		}

		return $this->getStringSize($value);
	}

	/**
	 * Get the size of a string.
	 *
	 * @param  string  $value
	 * @return int
	 */
	protected function getStringSize($value)
	{
		if (function_exists('mb_strlen')) return mb_strlen($value);

		return strlen($value);
	}

	/**
	 * Validate an attribute is contained within a list of values.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateIn($attribute, $value, $parameters)
	{
		return in_array((string) $value, $parameters);
	}

	/**
	 * Validate an attribute is not contained within a list of values.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateNotIn($attribute, $value, $parameters)
	{
		return ! $this->validateIn($attribute, $value, $parameters);
	}

	/**
	 * Validate the uniqueness of an attribute value on a given database table.
	 *
	 * If a database column is not specified, the attribute will be used.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateUnique($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'unique');

		$table = $parameters[0];

		$column = isset($parameters[1]) ? $parameters[1] : $attribute;

		list($idColumn, $id) = array(null, null);

		if (isset($parameters[2])) {
			list($idColumn, $id) = $this->getUniqueIds($parameters);

			if (strtolower($id) == 'null') $id = null;
		}

		$verifier = $this->getPresenceVerifier();

		$extra = $this->getUniqueExtra($parameters);

		return $verifier->getCount(

			$table, $column, $value, $id, $idColumn, $extra

		) == 0;
	}

	/**
	 * Get the excluded ID column and value for the unique rule.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getUniqueIds($parameters)
	{
		$idColumn = isset($parameters[3]) ? $parameters[3] : 'id';

		return array($idColumn, $parameters[2]);
	}

	/**
	 * Get the extra conditions for a unique rule.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getUniqueExtra($parameters)
	{
		if (isset($parameters[4])) {
			return $this->getExtraConditions(array_slice($parameters, 4));
		}

		return array();
	}

	/**
	 * Validate the existence of an attribute value in a database table.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateExists($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'exists');

		$table = $parameters[0];

		$column = isset($parameters[1]) ? $parameters[1] : $attribute;

		$expected = (is_array($value)) ? count($value) : 1;

		return $this->getExistCount($table, $column, $value, $parameters) >= $expected;
	}

	/**
	 * Get the number of records that exist in storage.
	 *
	 * @param  string  $table
	 * @param  string  $column
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return int
	 */
	protected function getExistCount($table, $column, $value, $parameters)
	{
		$verifier = $this->getPresenceVerifier();

		$extra = $this->getExtraExistConditions($parameters);

		if (is_array($value)) {
			return $verifier->getMultiCount($table, $column, $value, $extra);
		}

		return $verifier->getCount($table, $column, $value, null, null, $extra);
	}

	/**
	 * Get the extra exist conditions.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getExtraExistConditions(array $parameters)
	{
		return $this->getExtraConditions(array_values(array_slice($parameters, 2)));
	}

	/**
	 * Get the extra conditions for a unique / exists rule.
	 *
	 * @param  array  $segments
	 * @return array
	 */
	protected function getExtraConditions(array $segments)
	{
		$extra = array();

		$count = count($segments);

		for ($i = 0; $i < $count; $i = $i + 2) {
			$extra[$segments[$i]] = $segments[$i + 1];
		}

		return $extra;
	}

	/**
	 * Validate that an attribute is a valid IP.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateIp($attribute, $value)
	{
		return filter_var($value, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Validate that an attribute is a valid e-mail address.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateEmail($attribute, $value)
	{
		return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * Validate that an attribute is a valid URL.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateUrl($attribute, $value)
	{
		return filter_var($value, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * Validate that an attribute is an active URL.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateActiveUrl($attribute, $value)
	{
		$url = str_replace(array('http://', 'https://', 'ftp://'), '', strtolower($value));

		return checkdnsrr($url);
	}

	/**
	 * Validate the MIME type of a file is an image MIME type.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateImage($attribute, $value)
	{
		return $this->validateMimes($attribute, $value, array('jpeg', 'png', 'gif', 'bmp'));
	}

	/**
	 * Validate the MIME type of a file upload attribute is in a set of MIME types.
	 *
	 * @param  string  $attribute
	 * @param  mixed  $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateMimes($attribute, $value, $parameters)
	{
		if (! $this->isAValidFileInstance($value))
		{
			return false;
		}

		return $value->getPath() != '' && in_array($value->guessExtension(), $parameters);
	}

	/**
	 * Check that the given value is a valid file instance.
	 *
	 * @param  mixed  $value
	 * @return bool
	 */
	protected function isAValidFileInstance($value)
	{
		if ($value instanceof UploadedFile && ! $value->isValid()) return false;

		return $value instanceof File;
	}

	/**
	 * Validate that an attribute contains only alphabetic characters.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateAlpha($attribute, $value)
	{
		return preg_match('/^[\pL\pM]+$/u', $value);
	}

	/**
	 * Validate that an attribute contains only alpha-numeric characters.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateAlphaNum($attribute, $value)
	{
		return preg_match('/^[\pL\pM\pN]+$/u', $value);
	}

	/**
	 * Validate that an attribute contains only alpha-numeric characters, dashes, and underscores.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateAlphaDash($attribute, $value)
	{
		return preg_match('/^[\pL\pM\pN_-]+$/u', $value);
	}

	/**
	 * Validate that an attribute passes a regular expression check.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateRegex($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'regex');

		return preg_match($parameters[0], $value);
	}

	/**
	 * Validate that an attribute is a valid date.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateDate($attribute, $value)
	{
		if ($value instanceof DateTime) return true;

		if (strtotime($value) === false) return false;

		$date = date_parse($value);

		return checkdate($date['month'], $date['day'], $date['year']);
	}

	/**
	 * Validate that an attribute matches a date format.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateDateFormat($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'date_format');

		$parsed = date_parse_from_format($parameters[0], $value);

		return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
	}

	/**
	 * Validate the date is before a given date.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateBefore($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'before');

		if ($format = $this->getDateFormat($attribute)) {
			return $this->validateBeforeWithFormat($format, $value, $parameters);
		}

		if (! ($date = strtotime($parameters[0]))) {
			return strtotime($value) < strtotime($this->getValue($parameters[0]));
		}

		return strtotime($value) < $date;
	}

	/**
	 * Validate the date is before a given date with a given format.
	 *
	 * @param  string  $format
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateBeforeWithFormat($format, $value, $parameters)
	{
		$param = $this->getValue($parameters[0]) ?: $parameters[0];

		return $this->checkDateTimeOrder($format, $value, $param);
	}

	/**
	 * Validate the date is after a given date.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateAfter($attribute, $value, $parameters)
	{
		$this->requireParameterCount(1, $parameters, 'after');

		if ($format = $this->getDateFormat($attribute)) {
			return $this->validateAfterWithFormat($format, $value, $parameters);
		}

		if (! ($date = strtotime($parameters[0]))) {
			return strtotime($value) > strtotime($this->getValue($parameters[0]));
		}

		return strtotime($value) > $date;
	}

	/**
	 * Validate the date is after a given date with a given format.
	 *
	 * @param  string  $format
	 * @param  mixed   $value
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function validateAfterWithFormat($format, $value, $parameters)
	{
		$param = $this->getValue($parameters[0]) ?: $parameters[0];

		return $this->checkDateTimeOrder($format, $param, $value);
	}

	/**
	 * Given two date/time strings, check that one is after the other.
	 *
	 * @param  string  $format
	 * @param  string  $before
	 * @param  string  $after
	 * @return bool
	 */
	protected function checkDateTimeOrder($format, $before, $after)
	{
		$before = $this->getDateTimeWithOptionalFormat($format, $before);

		$after = $this->getDateTimeWithOptionalFormat($format, $after);

		return ($before && $after) && ($after > $before);
	}

	/**
	 * Get a DateTime instance from a string.
	 *
	 * @param  string  $format
	 * @param  string  $value
	 * @return \DateTime|null
	 */
	protected function getDateTimeWithOptionalFormat($format, $value)
	{
		$date = DateTime::createFromFormat($format, $value);

		if ($date) return $date;

		try
		{
			return new DateTime($value);
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	/**
	 * Validate that an attribute is a valid timezone.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return bool
	 */
	protected function validateTimezone($attribute, $value)
	{
		try
		{
			new DateTimeZone($value);
		}
		catch (\Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Get the date format for an attribute if it has one.
	 *
	 * @param  string  $attribute
	 * @return string|null
	 */
	protected function getDateFormat($attribute)
	{
		if ($result = $this->getRule($attribute, 'DateFormat')) {
			return $result[1][0];
		}
	}

	/**
	 * Get the validation message for an attribute and rule.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @return string
	 */
	protected function getMessage($attribute, $rule)
	{
		$lowerRule = Str::camel($rule);

		if (! is_null($inlineMessage = $this->getInlineMessage($attribute, $lowerRule))) {
			return $inlineMessage;
		}

		$customKey = "validation.custom.{$attribute}.{$lowerRule}";

		if (! is_null($customMessage = $this->config->get($customKey))) {
			return $customMessage;
		} else if (in_array($rule, $this->sizeRules)) {
			return $this->getSizeMessage($attribute, $rule);
		}

		$key = "validation.{$lowerRule}";

		if (! is_null($value = $this->config->get($key))) {
			return $value;
		}

		return $this->getInlineMessage(
			$attribute, $lowerRule, $this->fallbackMessages

		) ?: $key;
	}

	/**
	 * Get the inline message for a rule if it exists.
	 *
	 * @param  string  $attribute
	 * @param  string  $lowerRule
	 * @param  array   $source
	 * @return string
	 */
	protected function getInlineMessage($attribute, $lowerRule, $source = null)
	{
		$source = $source ?: $this->customMessages;

		$keys = array("{$attribute}.{$lowerRule}", $lowerRule);

		foreach ($keys as $key) {
			if (isset($source[$key])) {
				return $source[$key];
			}
		}
	}

	/**
	 * Get the proper error message for an attribute and size rule.
	 *
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @return string
	 */
	protected function getSizeMessage($attribute, $rule)
	{
		$lowerRule = Str::camel($rule);

		$type = $this->getAttributeType($attribute);

		$key = "validation.{$lowerRule}.{$type}";

		return $this->config->get($key, $key);
	}

	/**
	 * Get the data type of the given attribute.
	 *
	 * @param  string  $attribute
	 * @return string
	 */
	protected function getAttributeType($attribute)
	{
		if ($this->hasRule($attribute, $this->numericRules)) {
			return 'numeric';
		} else if ($this->hasRule($attribute, array('Array'))) {
			return 'array';
		} else if (array_key_exists($attribute, $this->files)) {
			return 'file';
		}

		return 'string';
	}

	/**
	 * Replace all error message place-holders with actual values.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function doReplacements($message, $attribute, $rule, $parameters)
	{
		$message = str_replace(':attribute', $this->getAttribute($attribute), $message);

		if (isset($this->replacers[Str::snake($rule)])) {
			$message = $this->callReplacer($message, $attribute, Str::snake($rule), $parameters);
		} else if (method_exists($this, $replacer = "replace{$rule}")) {
			$message = $this->$replacer($message, $attribute, $rule, $parameters);
		}

		return $message;
	}

	/**
	 * Transform an array of attributes to their displayable form.
	 *
	 * @param  array  $values
	 * @return array
	 */
	protected function getAttributeList(array $values)
	{
		$attributes = array();

		foreach ($values as $key => $value) {
			$attributes[$key] = $this->getAttribute($value);
		}

		return $attributes;
	}

	/**
	 * Get the displayable name of the attribute.
	 *
	 * @param  string  $attribute
	 * @return string
	 */
	protected function getAttribute($attribute)
	{
		if (isset($this->customAttributes[$attribute])) {
			return $this->customAttributes[$attribute];
		}

		$key = "validation.attributes.{$attribute}";

		if (! is_null($line = $this->config->get($key))) {
			return $line;
		}

		return str_replace('_', ' ', Str::snake($attribute));
	}

	/**
	 * Get the displayable name of the value.
	 *
	 * @param  string  $attribute
	 * @param  mixed   $value
	 * @return string
	 */
	public function getDisplayableValue($attribute, $value)
	{
		if (isset($this->customValues[$attribute][$value])) {
			return $this->customValues[$attribute][$value];
		}

		$key = "validation.values.{$attribute}.{$value}";

		if (! is_null($line = $this->config->get($key))) {
			return $line;
		}

		return $value;
	}

	/**
	 * Replace all place-holders for the between rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceBetween($message, $attribute, $rule, $parameters)
	{
		return str_replace(array(':min', ':max'), $parameters, $message);
	}

	/**
	 * Replace all place-holders for the digits rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceDigits($message, $attribute, $rule, $parameters)
	{
		return str_replace(':digits', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the digits (between) rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceDigitsBetween($message, $attribute, $rule, $parameters)
	{
		return $this->replaceBetween($message, $attribute, $rule, $parameters);
	}

	/**
	 * Replace all place-holders for the size rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceSize($message, $attribute, $rule, $parameters)
	{
		return str_replace(':size', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the min rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceMin($message, $attribute, $rule, $parameters)
	{
		return str_replace(':min', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the max rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceMax($message, $attribute, $rule, $parameters)
	{
		return str_replace(':max', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the in rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceIn($message, $attribute, $rule, $parameters)
	{
		foreach ($parameters as &$parameter) {
			$parameter = $this->getDisplayableValue($attribute, $parameter);
		}

		return str_replace(':values', implode(', ', $parameters), $message);
	}

	/**
	 * Replace all place-holders for the not_in rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceNotIn($message, $attribute, $rule, $parameters)
	{
		return $this->replaceIn($message, $attribute, $rule, $parameters);
	}

	/**
	 * Replace all place-holders for the mimes rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceMimes($message, $attribute, $rule, $parameters)
	{
		return str_replace(':values', implode(', ', $parameters), $message);
	}

	/**
	 * Replace all place-holders for the required_with rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceRequiredWith($message, $attribute, $rule, $parameters)
	{
		$parameters = $this->getAttributeList($parameters);

		return str_replace(':values', implode(' / ', $parameters), $message);
	}

	/**
	 * Replace all place-holders for the required_without rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceRequiredWithout($message, $attribute, $rule, $parameters)
	{
		return $this->replaceRequiredWith($message, $attribute, $rule, $parameters);
	}

	/**
	 * Replace all place-holders for the required_without_all rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceRequiredWithoutAll($message, $attribute, $rule, $parameters)
	{
		return $this->replaceRequiredWith($message, $attribute, $rule, $parameters);
	}

	/**
	 * Replace all place-holders for the required_if rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceRequiredIf($message, $attribute, $rule, $parameters)
	{
		$parameters[1] = $this->getDisplayableValue($parameters[0], array_get($this->data, $parameters[0]));

		$parameters[0] = $this->getAttribute($parameters[0]);

		return str_replace(array(':other', ':value'), $parameters, $message);
	}

	/**
	 * Replace all place-holders for the same rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceSame($message, $attribute, $rule, $parameters)
	{
		return str_replace(':other', $this->getAttribute($parameters[0]), $message);
	}

	/**
	 * Replace all place-holders for the different rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceDifferent($message, $attribute, $rule, $parameters)
	{
		return $this->replaceSame($message, $attribute, $rule, $parameters);
	}

	/**
	 * Replace all place-holders for the date_format rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceDateFormat($message, $attribute, $rule, $parameters)
	{
		return str_replace(':format', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the before rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceBefore($message, $attribute, $rule, $parameters)
	{
		if (! (strtotime($parameters[0]))) {
			return str_replace(':date', $this->getAttribute($parameters[0]), $message);
		}

		return str_replace(':date', $parameters[0], $message);
	}

	/**
	 * Replace all place-holders for the after rule.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function replaceAfter($message, $attribute, $rule, $parameters)
	{
		return $this->replaceBefore($message, $attribute, $rule, $parameters);
	}

	/**
	 * Determine if the given attribute has a rule in the given set.
	 *
	 * @param  string  $attribute
	 * @param  string|array  $rules
	 * @return bool
	 */
	protected function hasRule($attribute, $rules)
	{
		return ! is_null($this->getRule($attribute, $rules));
	}

	/**
	 * Get a rule and its parameters for a given attribute.
	 *
	 * @param  string  $attribute
	 * @param  string|array  $rules
	 * @return array|null
	 */
	protected function getRule($attribute, $rules)
	{
		if (! array_key_exists($attribute, $this->rules)) {
			return;
		}

		$rules = (array) $rules;

		foreach ($this->rules[$attribute] as $rule) {
			list($rule, $parameters) = $this->parseRule($rule);

			if (in_array($rule, $rules)) return [$rule, $parameters];
		}
	}

	/**
	 * Extract the rule name and parameters from a rule.
	 *
	 * @param  array|string  $rules
	 * @return array
	 */
	protected function parseRule($rules)
	{
		if (is_array($rules)) {
			return $this->parseArrayRule($rules);
		}

		return $this->parseStringRule($rules);
	}

	/**
	 * Parse an array based rule.
	 *
	 * @param  array  $rules
	 * @return array
	 */
	protected function parseArrayRule(array $rules)
	{
		return array(Str::studly(trim(array_get($rules, 0))), array_slice($rules, 1));
	}

	/**
	 * Parse a string based rule.
	 *
	 * @param  string  $rules
	 * @return array
	 */
	protected function parseStringRule($rules)
	{
		$parameters = array();

		if (strpos($rules, ':') !== false) {
			list($rules, $parameter) = explode(':', $rules, 2);

			$parameters = $this->parseParameters($rules, $parameter);
		}

		return array(Str::studly(trim($rules)), $parameters);
	}

	/**
	 * Parse a parameter list.
	 *
	 * @param  string  $rule
	 * @param  string  $parameter
	 * @return array
	 */
	protected function parseParameters($rule, $parameter)
	{
		if (strtolower($rule) == 'regex') return array($parameter);

		return str_getcsv($parameter);
	}

	/**
	 * Get the array of custom validator extensions.
	 *
	 * @return array
	 */
	public function getExtensions()
	{
		return $this->extensions;
	}

	/**
	 * Register an array of custom validator extensions.
	 *
	 * @param  array  $extensions
	 * @return void
	 */
	public function addExtensions(array $extensions)
	{
		if ($extensions) {
			$keys = array_map('Str::snake', array_keys($extensions));

			$extensions = array_combine($keys, array_values($extensions));
		}

		$this->extensions = array_merge($this->extensions, $extensions);
	}

	/**
	 * Register an array of custom implicit validator extensions.
	 *
	 * @param  array  $extensions
	 * @return void
	 */
	public function addImplicitExtensions(array $extensions)
	{
		$this->addExtensions($extensions);

		foreach ($extensions as $rule => $extension) {
			$this->implicitRules[] = Str::studly($rule);
		}
	}

	/**
	 * Register a custom validator extension.
	 *
	 * @param  string  $rule
	 * @param  \Closure|string  $extension
	 * @return void
	 */
	public function addExtension($rule, $extension)
	{
		$this->extensions[Str::snake($rule)] = $extension;
	}

	/**
	 * Register a custom implicit validator extension.
	 *
	 * @param  string   $rule
	 * @param  \Closure|string  $extension
	 * @return void
	 */
	public function addImplicitExtension($rule, $extension)
	{
		$this->addExtension($rule, $extension);

		$this->implicitRules[] = Str::studly($rule);
	}

	/**
	 * Get the array of custom validator message replacers.
	 *
	 * @return array
	 */
	public function getReplacers()
	{
		return $this->replacers;
	}

	/**
	 * Register an array of custom validator message replacers.
	 *
	 * @param  array  $replacers
	 * @return void
	 */
	public function addReplacers(array $replacers)
	{
		if ($replacers) {
			$keys = array_map('Str::snake', array_keys($replacers));

			$replacers = array_combine($keys, array_values($replacers));
		}

		$this->replacers = array_merge($this->replacers, $replacers);
	}

	/**
	 * Register a custom validator message replacer.
	 *
	 * @param  string  $rule
	 * @param  \Closure|string  $replacer
	 * @return void
	 */
	public function addReplacer($rule, $replacer)
	{
		$this->replacers[Str::snake($rule)] = $replacer;
	}

	/**
	 * Get the data under validation.
	 *
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * Set the data under validation.
	 *
	 * @param  array  $data
	 * @return void
	 */
	public function setData(array $data)
	{
		$this->data = $this->parseData($data);
	}

	/**
	 * Get the validation rules.
	 *
	 * @return array
	 */
	public function getRules()
	{
		return $this->rules;
	}

	/**
	 * Set the validation rules.
	 *
	 * @param  array  $rules
	 * @return $this
	 */
	public function setRules(array $rules)
	{
		$this->rules = $this->explodeRules($rules);

		return $this;
	}

	/**
	 * Set the custom attributes on the validator.
	 *
	 * @param  array  $attributes
	 * @return $this
	 */
	public function setAttributeNames(array $attributes)
	{
		$this->customAttributes = $attributes;

		return $this;
	}

	/**
	 * Set the custom values on the validator.
	 *
	 * @param  array  $values
	 * @return $this
	 */
	public function setValueNames(array $values)
	{
		$this->customValues = $values;

		return $this;
	}

	/**
	 * Get the files under validation.
	 *
	 * @return array
	 */
	public function getFiles()
	{
		return $this->files;
	}

	/**
	 * Set the files under validation.
	 *
	 * @param  array  $files
	 * @return $this
	 */
	public function setFiles(array $files)
	{
		$this->files = $files;

		return $this;
	}

	/**
	 * Get the Presence Verifier implementation.
	 *
	 * @return \Validation\PresenceVerifierInterface
	 *
	 * @throws \RuntimeException
	 */
	public function getPresenceVerifier()
	{
		if (! isset($this->presenceVerifier)) {
			throw new \RuntimeException("Presence verifier has not been set.");
		}

		return $this->presenceVerifier;
	}

	/**
	 * Set the Presence Verifier implementation.
	 *
	 * @param  \Validation\PresenceVerifierInterface  $presenceVerifier
	 * @return void
	 */
	public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier)
	{
		$this->presenceVerifier = $presenceVerifier;
	}

	/**
	 * Get the Translator implementation.
	 *
	 * @return \Symfony\Component\Translation\TranslatorInterface
	 */
	public function getTranslator()
	{
		return $this->translator;
	}

	/**
	 * Set the Translator implementation.
	 *
	 * @param  \Symfony\Component\Translation\TranslatorInterface  $translator
	 * @return void
	 */
	public function setTranslator(TranslatorInterface $translator)
	{
		$this->translator = $translator;
	}

	/**
	 * Get the custom messages for the validator
	 *
	 * @return array
	 */
	public function getCustomMessages()
	{
		return $this->customMessages;
	}

	/**
	 * Set the custom messages for the validator
	 *
	 * @param  array  $messages
	 * @return void
	 */
	public function setCustomMessages(array $messages)
	{
		$this->customMessages = array_merge($this->customMessages, $messages);
	}

	/**
	 * Get the custom attributes used by the validator.
	 *
	 * @return array
	 */
	public function getCustomAttributes()
	{
		return $this->customAttributes;
	}

	/**
	 * Add custom attributes to the validator.
	 *
	 * @param  array  $customAttributes
	 * @return $this
	 */
	public function addCustomAttributes(array $customAttributes)
	{
		$this->customAttributes = array_merge($this->customAttributes, $customAttributes);

		return $this;
	}

	/**
	 * Get the custom values for the validator.
	 *
	 * @return array
	 */
	public function getCustomValues()
	{
		return $this->customValues;
	}

	/**
	 * Add the custom values for the validator.
	 *
	 * @param  array  $customValues
	 * @return $this
	 */
	public function addCustomValues(array $customValues)
	{
		$this->customValues = array_merge($this->customValues, $customValues);

		return $this;
	}

	/**
	 * Get the fallback messages for the validator.
	 *
	 * @return array
	 */
	public function getFallbackMessages()
	{
		return $this->fallbackMessages;
	}

	/**
	 * Set the fallback messages for the validator.
	 *
	 * @param  array  $messages
	 * @return void
	 */
	public function setFallbackMessages(array $messages)
	{
		$this->fallbackMessages = $messages;
	}

	/**
	 * Get the failed validation rules.
	 *
	 * @return array
	 */
	public function failed()
	{
		return $this->failedRules;
	}

	/**
	 * Get the message container for the validator.
	 *
	 * @return \Mini\Support\MessageBag
	 */
	public function messages()
	{
		if (! $this->messages) $this->passes();

		return $this->messages;
	}

	/**
	 * An alternative more semantic shortcut to the message container.
	 *
	 * @return \Mini\Support\MessageBag
	 */
	public function errors()
	{
		return $this->messages();
	}

	/**
	 * Get the messages for the instance.
	 *
	 * @return \Mini\Support\MessageBag
	 */
	public function getMessageBag()
	{
		return $this->messages();
	}

	/**
	 * Set the IoC container instance.
	 *
	 * @param  \Mini\Container\Container  $container
	 * @return void
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Call a custom validator extension.
	 *
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function callExtension($rule, $parameters)
	{
		$callback = $this->extensions[$rule];

		if ($callback instanceof Closure) {
			return call_user_func_array($callback, $parameters);
		} else if (is_string($callback)) {
			return $this->callClassBasedExtension($callback, $parameters);
		}
	}

	/**
	 * Call a class based validator extension.
	 *
	 * @param  string  $callback
	 * @param  array   $parameters
	 * @return bool
	 */
	protected function callClassBasedExtension($callback, $parameters)
	{
		list($class, $method) = explode('@', $callback);

		return call_user_func_array(array($this->container->make($class), $method), $parameters);
	}

	/**
	 * Call a custom validator message replacer.
	 *
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function callReplacer($message, $attribute, $rule, $parameters)
	{
		$callback = $this->replacers[$rule];

		if ($callback instanceof Closure) {
			return call_user_func_array($callback, func_get_args());
		} else if (is_string($callback)) {
			return $this->callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters);
		}
	}

	/**
	 * Call a class based validator message replacer.
	 *
	 * @param  string  $callback
	 * @param  string  $message
	 * @param  string  $attribute
	 * @param  string  $rule
	 * @param  array   $parameters
	 * @return string
	 */
	protected function callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters)
	{
		list($class, $method) = explode('@', $callback);

		return call_user_func_array(array($this->container->make($class), $method), array_slice(func_get_args(), 1));
	}

	/**
	 * Require a certain number of parameters to be present.
	 *
	 * @param  int	$count
	 * @param  array  $parameters
	 * @param  string  $rule
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	protected function requireParameterCount($count, $parameters, $rule)
	{
		if (count($parameters) < $count) {
			throw new \InvalidArgumentException("Validation rule $rule requires at least $count parameters.");
		}
	}

	/**
	 * Handle dynamic calls to class methods.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 *
	 * @throws \BadMethodCallException
	 */
	public function __call($method, $parameters)
	{
		$rule = Str::snake(substr($method, 8));

		if (isset($this->extensions[$rule])) {
			return $this->callExtension($rule, $parameters);
		}

		throw new \BadMethodCallException("Method [$method] does not exist.");
	}

}
