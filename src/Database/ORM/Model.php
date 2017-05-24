<?php

namespace Mini\Database\ORM;

use Mini\Database\ORM\Relations\BelongsTo;
use Mini\Database\ORM\Relations\BelongsToMany;
use Mini\Database\ORM\Relations\HasMany;
use Mini\Database\ORM\Relations\HasManyThrough;
use Mini\Database\ORM\Relations\HasOne;
use Mini\Database\ORM\Relations\MorphMany;
use Mini\Database\ORM\Relations\MorphOne;
use Mini\Database\ORM\Relations\MorphTo;
use Mini\Database\ORM\Relations\MorphToMany;
use Mini\Database\ORM\Relations\Pivot;
use Mini\Database\ORM\Relations\Relation;
use Mini\Database\ORM\Builder;
use Mini\Database\ORM\Collection;
use Mini\Database\ORM\ModelNotFoundException;
use Mini\Database\Query\Builder as QueryBuilder;
use Mini\Database\Contracts\ConnectionResolverInterface as Resolver;
use Mini\Events\Dispatcher;
use Mini\Support\Contracts\ArrayableInterface;
use Mini\Support\Contracts\JsonableInterface;
use Mini\Support\Arr;
use Mini\Support\Str;

use Carbon\Carbon;

use ArrayAccess;
use DateTime;
use JsonSerializable;
use LogicException;


class MassAssignmentException extends \RuntimeException {}

class Model implements ArrayAccess, ArrayableInterface, JsonableInterface, JsonSerializable
{
	/**
	 * The Database Connection name.
	 *
	 * @var string
	 */
	protected $connection = null;

	/**
	 * The table associated with the Model.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * The primary key for the Model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'id';

	/**
	 * The number of Records to return for pagination.
	 *
	 * @var int
	 */
	protected $perPage = 15;

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = true;

	/**
	 * The Model's attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * The Model attribute's original state.
	 *
	 * @var array
	 */
	protected $original = array();

	/**
	 * The loaded relationships for the Model.
	 *
	 * @var array
	 */
	protected $relations = array();

	/**
	 * The attributes that should be hidden for arrays.
	 *
	 * @var array
	 */
	protected $hidden = array();

	/**
	 * The attributes that should be visible in arrays.
	 *
	 * @var array
	 */
	protected $visible = array();

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = array();

	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = array('*');

	/**
	 * The attributes that should be mutated to dates.
	 *
	 * @var array
	 */
	protected $dates = array();

	/**
	 * The relationships that should be touched on save.
	 *
	 * @var array
	 */
	protected $touches = array();

	/**
	 * The relations to eager load on every query.
	 *
	 * @var array
	 */
	protected $with = array();

	/**
	 * The class name to be used in polymorphic relations.
	 *
	 * @var string
	 */
	protected $morphClass;

	/**
	 * Indicates if the Model exists.
	 *
	 * @var bool
	 */
	public $exists = false;

	/**
	 * Indicates if the model was inserted during the current request lifecycle.
	 *
	 * @var bool
	 */
	public $wasRecentlyCreated = false;

	/**
	 * The connection resolver instance.
	 *
	 * @var \Mini\Database\Contracts\ConnectionResolverInterface
	 */
	protected static $resolver;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Mini\Events\Dispatcher
	 */
	protected static $dispatcher;

	/**
	 * The array of booted models.
	 *
	 * @var array
	 */
	protected static $booted = array();

	/**
	 * Indicates if all mass assignment is enabled.
	 *
	 * @var bool
	 */
	protected static $unguarded = false;

	/**
	 * The cache of the mutated attributes for each class.
	 *
	 * @var array
	 */
	protected static $mutatorCache = array();

	/**
	 * The many to many relationship methods.
	 *
	 * @var array
	 */
	public static $manyMethods = array('belongsToMany', 'morphToMany', 'morphedByMany');

	/**
	 * The array of observable events.
	 *
	 * @var array
	 */
	public static $observables = array(
			'creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved',
	);

	/**
	 * The name of the "created at" column.
	 *
	 * @var string
	 */
	const CREATED_AT = 'created_at';

	/**
	 * The name of the "updated at" column.
	 *
	 * @var string
	 */
	const UPDATED_AT = 'updated_at';


	/**
	 * Create a new generic User object.
	 *
	 * @param  array  $attributes
	 * @return void
	 */
	public function __construct($attributes = array())
	{
		$this->bootIfNotBooted();

		$this->syncOriginal();

		$this->fill($attributes);
	}

	/**
	 * Check if the model needs to be booted and if so, do it.
	 *
	 * @return void
	 */
	protected function bootIfNotBooted()
	{
		$class = get_class($this);

		if (! isset(static::$booted[$class])) {
			static::$booted[$class] = true;

			$this->fireModelEvent('booting', false);

			static::boot();

			$this->fireModelEvent('booted', false);
		}
	}

	/**
	 * The "booting" method of the model.
	 *
	 * @return void
	 */
	protected static function boot()
	{
		$class = get_called_class();

		static::$mutatorCache[$class] = array();

		foreach (get_class_methods($class) as $method) {
			if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
				$attribute = Str::snake($matches[1]);

				static::$mutatorCache[$class][] = $attribute;
			}
		}
	}

	/**
	 * Register an observer with the Model.
	 *
	 * @param  object  $class
	 * @return void
	 */
	public static function observe($class)
	{
		$instance = new static;

		$className = get_class($class);

		foreach (static::$observables as $event) {
			if (method_exists($class, $event)) {
				static::registerModelEvent($event, $className .'@' .$event);
			}
		}
	}

	/**
	 * Fill the Model with an array of attributes.
	 *
	 * @param  array  $attributes
	 * @return Model
	 */
	public function fill(array $attributes)
	{
		$totallyGuarded = $this->totallyGuarded();

		foreach ($this->fillableFromArray($attributes) as $key => $value) {
			if ($this->isFillable($key)) {
				$this->setAttribute($key, $value);
			} else if ($totallyGuarded) {
				throw new MassAssignmentException($key);
			}
		}

		return $this;
	}

	/**
	 * Get the fillable attributes of a given array.
	 *
	 * @param  array  $attributes
	 * @return array
	 */
	protected function fillableFromArray(array $attributes)
	{
		if ((count($this->fillable) > 0) && ! static::$unguarded) {
			return array_intersect_key($attributes, array_flip($this->fillable));
		}

		return $attributes;
	}

	/**
	 * Create a new instance of the given Model.
	 *
	 * @param  array  $attributes
	 * @param  bool   $exists
	 * @return Model
	 */
	public function newInstance($attributes = array(), $exists = false)
	{
		$model = new static((array) $attributes);

		$model->exists = $exists;

		return $model;
	}

	/**
	 * Create a new Model instance that is existing.
	 *
	 * @param  array  $attributes
	 * @return \Database\ORM\Model|static
	 */
	public function newFromBuilder($attributes = array())
	{
		$instance = $this->newInstance(array(), true);

		$instance->setRawAttributes((array) $attributes, true);

		return $instance;
	}

	/**
	 * Create a new Model instance, save it, then return the instance.
	 *
	 * @param  array  $attributes
	 * @return static
	 */
	public static function create(array $attributes)
	{
		$model = new static($attributes);

		$model->save();

		return $model;
	}

	/**
	 * Get the first record matching the attributes or create it.
	 *
	 * @param  array  $attributes
	 * @return static
	 */
	public static function firstOrCreate(array $attributes)
	{
		if (! is_null($model = static::where($attributes)->first())) {
			return $model;
		}

		return static::create($attributes);
	}

	/**
	 * Get the first record matching the attributes or instantiate it.
	 *
	 * @param  array  $attributes
	 * @return static
	 */
	public static function firstOrNew(array $attributes)
	{
		if (! is_null($model = static::where($attributes)->first())) {
			return $model;
		}

		return new static($attributes);
	}

	/**
	 * Create or update a record matching the attributes, and fill it with values.
	 *
	 * @param  array  $attributes
	 * @param  array  $values
	 * @return static
	 */
	public static function updateOrCreate(array $attributes, array $values = array())
	{
		$model = static::firstOrNew($attributes);

		$model->fill($values)->save();

		return $model;
	}

	/**
	 * Begin querying the model.
	 *
	 * @return \Mini\Database\Builder
	 */
	public static function query()
	{
		return (new static)->newQuery();
	}

	/**
	 * Begin querying the model on a given connection.
	 *
	 * @param  string  $connection
	 * @return \Mini\Database\Builder
	 */
	public static function on($connection = null)
	{
		$model = new static;

		$model->setConnection($connection);

		return $model->newQuery();
	}


	/**
	 * Get all of the models from the database.
	 *
	 * @param  array  $columns
	 * @return array
	 */
	public static function all($columns = array('*'))
	{
		$instance = new static();

		return $instance->newQuery()->get($columns);
	}

	/**
	 * Find a Model by its primary key.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return Model
	 */
	public static function find($id, $columns = array('*'))
	{
		$instance = new static();

		return $instance->newQuery()->find($id, $columns);
	}

	/**
	 * Find a Model by its primary key or return new static.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \Database\ORM\Model|static
	 */
	public static function findOrNew($id, $columns = array('*'))
	{
		if (! is_null($model = static::find($id, $columns))) return $model;

		return new static($columns);
	}

	/**
	 * Find a Model by its primary key or throw an exception.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \Database\ORM\Model|static
	 *
	 * @throws \Exception
	 */
	public static function findOrFail($id, $columns = array('*'))
	{
		if (! is_null($model = static::find($id, $columns))) {
			return $model;
		}

		throw (new ModelNotFoundException)->setModel(get_called_class());
	}

	/**
	 * Increment a Column's value by a given amount.
	 *
	 * @param  string  $column
	 * @param  int	 $amount
	 * @return int
	 */
	protected function increment($column, $amount = 1)
	{
		return $this->incrementOrDecrement($column, $amount, 'increment');
	}

	/**
	 * Decrement a Column's value by a given amount.
	 *
	 * @param  string  $column
	 * @param  int	 $amount
	 * @return int
	 */
	protected function decrement($column, $amount = 1)
	{
		return $this->incrementOrDecrement($column, $amount, 'decrement');
	}

	/**
	 * Run the increment or decrement method on the Model.
	 *
	 * @param  string  $column
	 * @param  int	 $amount
	 * @param  string  $method
	 * @return int
	 */
	protected function incrementOrDecrement($column, $amount, $method)
	{
		$query = $this->newQuery();

		if ( ! $this->exists) {
			return $query->{$method}($column, $amount);
		}

		$this->{$column} = $this->{$column} + ($method == 'increment' ? $amount : $amount * -1);

		$this->syncOriginalAttribute($column);

		return $query->where($this->getKeyName(), $this->getKey())->{$method}($column, $amount);
	}

	/**
	 * Update the Model in the database.
	 *
	 * @param  array  $attributes
	 * @return mixed
	 */
	public function update(array $attributes = array())
	{
		if (! $this->exists) {
			return $this->newQuery()->update($attributes);
		}

		return $this->fill($attributes)->save();
	}

	/**
	 * Save the Model to the database.
	 *
	 * @param  array  $options
	 * @return bool
	 */
	public function save(array $options = array())
	{
		if ($this->fireModelEvent('saving') === false) {
			return false;
		}

		$query = $this->newQuery();

		if ($this->exists) {
			$saved = $this->performUpdate($query);
		} else {
			$saved = $this->performInsert($query);
		}

		if ($saved) {
			$this->fireModelEvent('saved', false);

			$this->syncOriginal();
		}

		if (Arr::get($options, 'touch', true)) {
			$this->touchOwners();
		}

		return $saved;
	}

	/**
	 * Perform a Model update operation.
	 *
	 * @param  \Database\ORM\Builder  $query
	 * @return bool
	 */
	protected function performUpdate(Builder $query)
	{
		$dirty = $this->getDirty();

		if (count($dirty) > 0) {
			if ($this->fireModelEvent('updating') === false) {
				return false;
			}

			if ($this->timestamps) {
				$this->updateTimestamps();
			}

			$dirty = $this->getDirty();

			if (count($dirty) > 0) {
				$this->setKeysForSaveQuery($query)->update($dirty);

				$this->fireModelEvent('updated', false);
			}
		}

		return true;
	}

	/**
	 * Perform a model insert operation.
	 *
	 * @param  \Database\ORM\Builder  $query
	 * @return bool
	 */
	protected function performInsert(Builder $query)
	{
		if ($this->fireModelEvent('creating') === false) {
			return false;
		}

		if ($this->timestamps) {
			$this->updateTimestamps();
		}

		$attributes = $this->attributes;

		$keyName = $this->getKeyName();

		//
		$id = $query->insertGetId($attributes);

		$this->setAttribute($keyName, $id);

		//
		$this->exists = true;

		$this->wasRecentlyCreated = true;

		$this->fireModelEvent('created', false);

		return true;
	}

	/**
	 * Update the model's update timestamp.
	 *
	 * @return bool
	 */
	public function touch()
	{
		$this->updateTimestamps();

		return $this->save();
	}

	/**
	 * Update the creation and update timestamps.
	 *
	 * @return void
	 */
	protected function updateTimestamps()
	{
		$time = $this->freshTimestamp();

		//
		$column = static::UPDATED_AT;

		if (! $this->isDirty($column)) {
			$this->{$column} = $time;
		}

		$column = static::CREATED_AT;

		if (! $this->exists && ! $this->isDirty($column)) {
			$this->{$column} = $time;
		}
	}

	/**
	 * Destroy the models for the given IDs.
	 *
	 * @param  array|int  $ids
	 * @return int
	 */
	public static function destroy($ids)
	{
		$count = 0;

		$ids = is_array($ids) ? $ids : func_get_args();

		$instance = new static;

		$key = $instance->getKeyName();

		foreach ($instance->whereIn($key, $ids)->get() as $model) {
			if ($model->delete()) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Delete the Model from the database.
	 *
	 * @return bool|null
	 */
	public function delete()
	{
		if (is_null($this->primaryKey)) {
			throw new \Exception("No primary key defined on model.");
		}

		if ($this->exists) {
			if ($this->fireModelEvent('deleting') === false) {
				return false;
			}

			$this->touchOwners();

			$this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();

			$this->exists = false;

			//
			$this->fireModelEvent('deleted', false);

			return true;
		}
	}

	/**
	 * Eager load relations on the model.
	 *
	 * @param  array|string  $relations
	 * @return $this
	 */
	public function load($relations)
	{
		if (is_string($relations)) {
			$relations = func_get_args();
		}

		$query = $this->newQuery()->with($relations);

		$query->eagerLoadRelations(array($this));

		return $this;
	}

	/**
	 * Being querying a model with eager loading.
	 *
	 * @param  array|string  $relations
	 * @return \Mini\Database\ORM\Builder|static
	 */
	public static function with($relations)
	{
		if (is_string($relations)) {
			$relations = func_get_args();
		}

		$instance = new static;

		return $instance->newQuery()->with($relations);
	}

	/**
	 * Define a one-to-one relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Mini\Database\ORM\Relations\HasOne
	 */
	public function hasOne($related, $foreignKey = null, $localKey = null)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$localKey = $localKey ?: $this->getKeyName();

		//
		$related = new $related;

		return new HasOne($related, $this, $foreignKey, $localKey);
	}

	/**
	 * Define a polymorphic one-to-one relationship.
	 *
	 * @param  string  $related
	 * @param  string  $name
	 * @param  string  $type
	 * @param  string  $id
	 * @param  string  $localKey
	 * @return \Mini\Database\ORM\Relations\MorphOne
	 */
	public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
	{
		$related = new $related;

		list($type, $id) = $this->getMorphs($name, $type, $id);

		$table = $related->getTable();

		$localKey = $localKey ?: $this->getKeyName();

		return new MorphOne($related, $this, $table .'.' .$type, $table .'.' .$id, $localKey);
	}

	/**
	 * Define an inverse one-to-one or many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return \Mini\Database\ORM\Relations\BelongsTo
	 */
	public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
	{
		if (is_null($relation)) {
			list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

			$relation = $caller['function'];
		}

		if (is_null($foreignKey)) {
			$foreignKey = Str::snake($relation) .'_id';
		}

		$related = new $related;

		$otherKey = $otherKey ?: $related->getKeyName();

		return new BelongsTo($related, $this, $foreignKey, $otherKey, $relation);
	}

	/**
	 * Define a polymorphic, inverse one-to-one or many relationship.
	 *
	 * @param  string  $name
	 * @param  string  $type
	 * @param  string  $id
	 * @return \Mini\Database\ORM\Relations\MorphTo
	 */
	public function morphTo($name = null, $type = null, $id = null)
	{
		if (is_null($name)) {
			list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

			$name = Str::snake($caller['function']);
		}

		list($type, $id) = $this->getMorphs($name, $type, $id);

		if (is_null($class = $this->$type)) {
			return new MorphTo($this, $this, $id, null, $type, $name);
		} else {
			$related = new $class;

			return new MorphTo(
				$related, $this, $id, $related->getKeyName(), $type, $name
			);
		}
	}

	/**
	 * Define a one-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Mini\Database\ORM\Relations\HasMany
	 */
	public function hasMany($related, $foreignKey = null, $localKey = null)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$localKey = $localKey ?: $this->getKeyName();

		//
		$related = new $related;

		return new HasMany($related, $this, $foreignKey, $localKey);
	}

	/**
	 * Define a has-many-through relationship.
	 *
	 * @param  string  $related
	 * @param  string  $through
	 * @param  string|null  $firstKey
	 * @param  string|null  $secondKey
	 * @return \Mini\Database\ORM\Relations\HasManyThrough
	 */
	public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null)
	{
		$through = new $through;

		$firstKey = $firstKey ?: $this->getForeignKey();

		$secondKey = $secondKey ?: $through->getForeignKey();

		//
		$related = new $related;

		return new HasManyThrough($related, $this, $through, $firstKey, $secondKey);
	}

	/**
	 * Define a polymorphic one-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $name
	 * @param  string  $type
	 * @param  string  $id
	 * @param  string  $localKey
	 * @return \Mini\Database\ORM\Relations\MorphMany
	 */
	public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
	{
		$related = new $related;

		list($type, $id) = $this->getMorphs($name, $type, $id);

		$table = $related->getTable();

		$localKey = $localKey ?: $this->getKeyName();

		return new MorphMany($related, $this, $table .'.' .$type, $table .'.' .$id, $localKey);
	}

	/**
	 * Define a many-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return \Mini\Database\ORM\Relations\BelongsToMany
	 */
	public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
	{
		if (is_null($relation)) {
			$relation = $this->getBelongsToManyCaller();
		}

		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$related = new $related;

		$otherKey = $otherKey ?: $related->getForeignKey();

		if (is_null($table)) {
			$table = $this->joiningTable($related);
		}

		return new BelongsToMany($related, $this, $table, $foreignKey, $otherKey, $relation);
	}

	/**
	 * Define a polymorphic many-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $name
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  bool	$inverse
	 * @return \Mini\Database\ORM\Relations\MorphToMany
	 */
	public function morphToMany($related, $name, $table = null, $foreignKey = null, $otherKey = null, $inverse = false)
	{
		$caller = $this->getBelongsToManyCaller();

		$foreignKey = $foreignKey ?: $name .'_id';

		$related = new $related;

		$otherKey = $otherKey ?: $related->getForeignKey();

		$table = $table ?: Str::plural($name);

		return new MorphToMany(
			$related, $this, $name, $table, $foreignKey, $otherKey, $caller, $inverse
		);
	}

	/**
	 * Define a polymorphic, inverse many-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $name
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @return \Mini\Database\ORM\Relations\MorphToMany
	 */
	public function morphedByMany($related, $name, $table = null, $foreignKey = null, $otherKey = null)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$otherKey = $otherKey ?: $name .'_id';

		return $this->morphToMany($related, $name, $table, $foreignKey, $otherKey, true);
	}

	/**
	 * Get the relationship name of the belongs to many.
	 *
	 * @return  string
	 */
	protected function getBelongsToManyCaller()
	{
		$self = __FUNCTION__;

		$caller = array_first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function($key, $trace) use ($self)
		{
			$caller = $trace['function'];

			return (! in_array($caller, Model::$manyMethods) && $caller != $self);
		});

		return ! is_null($caller) ? $caller['function'] : null;
	}

	/**
	 * Get the joining table name for a many-to-many relation.
	 *
	 * @param  string  $related
	 * @return string
	 */
	public function joiningTable($related)
	{
		$base = Str::snake(class_basename($this));

		$related = Str::snake(class_basename($related));

		$models = array($related, $base);

		//
		sort($models);

		return strtolower(implode('_', $models));
	}

	/**
	 * Touch the owning relations of the model.
	 *
	 * @return void
	 */
	public function touchOwners()
	{
		foreach ($this->touches as $relation) {
			$this->$relation()->touch();

			if (! is_null($this->$relation)) {
				$this->$relation->touchOwners();
			}
		}
	}

	/**
	 * Determine if the model touches a given relation.
	 *
	 * @param  string  $relation
	 * @return bool
	 */
	public function touches($relation)
	{
		return in_array($relation, $this->touches);
	}

	/**
	 * Fire the given event for the model.
	 *
	 * @param  string  $event
	 * @param  bool	$halt
	 * @return mixed
	 */
	protected function fireModelEvent($event, $halt = true)
	{
		if (! isset(static::$dispatcher)) return true;

		$event = "entity.{$event}: ".get_class($this);

		$method = $halt ? 'until' : 'fire';

		return static::$dispatcher->$method($event, $this);
	}

	/**
	 * Set the keys for a save update query.
	 *
	 * @param  \Database\ORM\Builder  $query
	 * @return \Database\ORM\Builder
	 */
	protected function setKeysForSaveQuery(Builder $query)
	{
		$query->where($this->getKeyName(), $this->getKeyForSaveQuery());

		return $query;
	}

	/**
	 * Get the primary key value for a save query.
	 *
	 * @return mixed
	 */
	protected function getKeyForSaveQuery()
	{
		$key = $this->getKeyName();

		if (isset($this->original[$key])) {
			return $this->original[$key];
		}

		return $this->getAttribute($key);
	}

	/**
	 * Determine if the given attribute may be mass assigned.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function isFillable($key)
	{
		if (static::$unguarded) {
			return true;
		} else if (in_array($key, $this->fillable)) {
			return true;
		} else if ($this->isGuarded($key)) {
			return false;
		}

		return (empty($this->fillable) && ! Str::startsWith($key, '_'));
	}

	/**
	 * Set the array of model attributes. No checking is done.
	 *
	 * @param  array  $attributes
	 * @param  bool   $sync
	 * @return void
	 */
	public function setRawAttributes(array $attributes, $sync = false)
	{
		$this->attributes = $attributes;

		if ($sync) {
			$this->syncOriginal();
		}
	}

	/**
	 * Set a given attribute on the model.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function setAttribute($key, $value)
	{
		if ($this->hasSetMutator($key)) {
			$method = 'set' .Str::studly($key) .'Attribute';

			return call_user_func(array($this, $method), $value);
		} else if (in_array($key, $this->getDates()) && ! is_null($value)) {
			$value = $this->fromDateTime($value);
		}

		$this->attributes[$key] = $value;
	}

	/**
	 * Get an attribute from the Model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function getAttribute($key)
	{
		$inAttributes = array_key_exists($key, $this->attributes);

		if ($inAttributes || $this->hasGetMutator($key)) {
			return $this->getAttributeValue($key);
		}

		if (array_key_exists($key, $this->relations)) {
			return $this->relations[$key];
		}

		$camelKey = Str::camel($key);

		if (method_exists($this, $camelKey)) {
			return $this->getRelationshipFromMethod($key, $camelKey);
		}
	}

	/**
	 * Get a plain attribute.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	protected function getAttributeValue($key)
	{
		$value = $this->getAttributeFromArray($key);

		if ($this->hasGetMutator($key)) {
			return $this->mutateAttribute($key, $value);
		} else if (in_array($key, $this->getDates())) {
			if ($value) return $this->asDateTime($value);
		}

		return $value;
	}

	/**
	 * Get an attribute from the $attributes array.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	protected function getAttributeFromArray($key)
	{
		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key];
		}
	}

	/**
	 * Get a relationship value from a method.
	 *
	 * @param  string  $key
	 * @param  string  $method
	 * @return mixed
	 *
	 * @throws \LogicException
	 */
	protected function getRelationshipFromMethod($key, $method)
	{
		$relation = call_user_func(array($this, $method));

		if (! $relation instanceof Relation) {
			throw new LogicException("Relationship method must return an object of type Mini\Database\ORM\Relations\Relation");
		}

		return $this->relations[$key] = $relation->getResults();
	}

	/**
	 * Get the model's original attribute values.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return array
	 */
	public function getOriginal($key = null, $default = null)
	{
		return Arr::get($this->original, $key, $default);
	}

	/**
	 * Sync the original attributes with the current.
	 *
	 * @return Model
	 */
	public function syncOriginal()
	{
		$this->original = $this->attributes;

		return $this;
	}

	/**
	 * Sync a single original attribute with its current value.
	 *
	 * @param  string  $attribute
	 * @return $this
	 */
	public function syncOriginalAttribute($attribute)
	{
		$this->original[$attribute] = $this->attributes[$attribute];

		return $this;
	}

	/**
	 * Determine if the model or given attribute(s) have been modified.
	 *
	 * @param  array|string|null  $attributes
	 * @return bool
	 */
	public function isDirty($attributes = null)
	{
		$dirty = $this->getDirty();

		if (is_null($attributes)) {
			return ! empty($dirty);
		}

		if (! is_array($attributes)) {
			$attributes = func_get_args();
		}

		foreach ($attributes as $attribute) {
			if (array_key_exists($attribute, $dirty)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the attributes that have been changed since last sync.
	 *
	 * @return array
	 */
	public function getDirty()
	{
		$dirty = array();

		foreach ($this->attributes as $key => $value) {
			if (! array_key_exists($key, $this->original)) {
				$dirty[$key] = $value;
			} else if (($value !== $this->original[$key]) && ! $this->originalIsNumericallyEquivalent($key)) {
				$dirty[$key] = $value;
			}
		}

		return $dirty;
	}

	/**
	 * Determine if the new and old values for a given key are numerically equivalent.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	protected function originalIsNumericallyEquivalent($key)
	{
		$current = $this->attributes[$key];

		$original = $this->original[$key];

		return is_numeric($current) && is_numeric($original) && (strcmp((string) $current, (string) $original) === 0);
	}

	/**
	 * Get the value of an attribute using its mutator.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return mixed
	 */
	protected function mutateAttribute($key, $value)
	{
		$method = 'get' .Str::studly($key) .'Attribute';

		return call_user_func(array($this, $method), $value);
	}

	/**
	 * Get the value of an attribute using its mutator for array conversion.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return mixed
	 */
	protected function mutateAttributeForArray($key, $value)
	{
		$value = $this->mutateAttribute($key, $value);

		return ($value instanceof ArrayableInterface) ? $value->toArray() : $value;
	}

	/**
	 * Determine if a set mutator exists for an attribute.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function hasSetMutator($key)
	{
		return method_exists($this, 'set' .Str::studly($key) .'Attribute');
	}

	/**
	 * Determine if a get mutator exists for an attribute.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function hasGetMutator($key)
	{
		return method_exists($this, 'get' .Str::studly($key) .'Attribute');
	}

	/**
	 * Convert a DateTime to a storable string.
	 *
	 * @param  \DateTime|int  $value
	 * @return string
	 */
	public function fromDateTime($value)
	{
		$format = $this->getDateFormat();

		if ($value instanceof DateTime) {
			//
		} else if (is_numeric($value)) {
			$value = Carbon::createFromTimestamp($value);
		} else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
			$value = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
		} else {
			$value = Carbon::createFromFormat($format, $value);
		}

		return $value->format($format);
	}

	/**
	 * Return a timestamp as DateTime object.
	 *
	 * @param  mixed  $value
	 * @return \Carbon\Carbon
	 */
	protected function asDateTime($value)
	{
		if (is_numeric($value)) {
			return Carbon::createFromTimestamp($value);
		} else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
			return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
		} else if (! $value instanceof DateTime) {
			$format = $this->getDateFormat();

			return Carbon::createFromFormat($format, $value);
		}

		return Carbon::instance($value);
	}

	/**
	 * Register a model event with the dispatcher.
	 *
	 * @param  string  $event
	 * @param  \Closure|string  $callback
	 * @return void
	 */
	protected static function registerModelEvent($event, $callback)
	{
		if (isset(static::$dispatcher)) {
			$name = get_called_class();

			static::$dispatcher->listen("entity.{$event}: {$name}", $callback);
		}
	}

	/**
	 * Set the specific relationship in the Model.
	 *
	 * @param  string  $relation
	 * @param  mixed   $value
	 * @return $this
	 */
	public function setRelation($relation, $value)
	{
		$this->relations[$relation] = $value;

		return $this;
	}

	/**
	 * Determine if the model uses timestamps.
	 *
	 * @return bool
	 */
	public function usesTimestamps()
	{
		return $this->timestamps;
	}

	/**
	 * Get the Table for the Model.
	 *
	 * @return string
	 */
	public function getTable()
	{
		if (isset($this->table)) return $this->table;

		return str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));
	}

	/**
	 * Get the value of the model's primary key.
	 *
	 * @return mixed
	 */
	public function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}

	/**
	 * Get the Primary Key for the Model.
	 *
	 * @return string
	 */
	public function getKeyName()
	{
		return $this->primaryKey;
	}

	/**
	 * Get the table qualified key name.
	 *
	 * @return string
	 */
	public function getQualifiedKeyName()
	{
		return $this->getTable() .'.' .$this->getKeyName();
	}

	/**
	 * Get the default foreign key name for the Model.
	 *
	 * @return string
	 */
	public function getForeignKey()
	{
		return Str::snake(class_basename($this)) .'_id';
	}

	/**
	 * Get the polymorphic relationship columns.
	 *
	 * @param  string  $name
	 * @param  string  $type
	 * @param  string  $id
	 * @return array
	 */
	protected function getMorphs($name, $type, $id)
	{
		$type = $type ?: $name .'_type';

		$id = $id ?: $name .'_id';

		return array($type, $id);
	}

	/**
	 * Get the class name for polymorphic relations.
	 *
	 * @return string
	 */
	public function getMorphClass()
	{
		return $this->morphClass ?: get_class($this);
	}

	/**
	 * Get the number of models to return per page.
	 *
	 * @return int
	 */
	public function getPerPage()
	{
		return $this->perPage;
	}

	/**
	 * Set the number of models to return per page.
	 *
	 * @param  int   $perPage
	 * @return void
	 */
	public function setPerPage($perPage)
	{
		$this->perPage = $perPage;
	}

	/**
	 * Get the database Connection instance.
	 *
	 * @return \Mini\Database\Connection
	 */
	public function getConnection()
	{
		return $this->resolveConnection($this->connection);
	}

	/**
	 * Get the current Connection name for the Model.
	 *
	 * @return string
	 */
	public function getConnectionName()
	{
		return $this->connection;
	}

	/**
	 * Set the Connection associated with the Model.
	 *
	 * @param  string  $name
	 * @return \Mini\Database\Model
	 */
	public function setConnection($name)
	{
		$this->connection = $name;

		return $this;
	}

	/**
	 * Resolve a connection instance.
	 *
	 * @param  string  $connection
	 * @return \Mini\Database\Connection
	 */
	public function resolveConnection($connection = null)
	{
		return static::$resolver->connection($connection);
	}

	/**
	 * Get the connection resolver instance.
	 *
	 * @return \Mini\Database\Contracts\ConnectionResolverInterface
	 */
	public static function getConnectionResolver()
	{
		return static::$resolver;
	}

	/**
	 * Set the connection resolver instance.
	 *
	 * @param  \Mini\Database\Contracts\ConnectionResolverInterface  $resolver
	 * @return void
	 */
	public static function setConnectionResolver(Resolver $resolver)
	{
		static::$resolver = $resolver;
	}

	/**
	 * Unset the connection resolver for models.
	 *
	 * @return void
	 */
	public static function unsetConnectionResolver()
	{
		static::$resolver = null;
	}

	/**
	 * Get the event dispatcher instance.
	 *
	 * @return \Mini\Events\Dispatcher
	 */
	public static function getEventDispatcher()
	{
		return static::$dispatcher;
	}

	/**
	 * Set the event dispatcher instance.
	 *
	 * @param  \Mini\Events\Dispatcher  $dispatcher
	 * @return void
	 */
	public static function setEventDispatcher(Dispatcher $dispatcher)
	{
		static::$dispatcher = $dispatcher;
	}

	/**
	 * Unset the event dispatcher for models.
	 *
	 * @return void
	 */
	public static function unsetEventDispatcher()
	{
		static::$dispatcher = null;
	}

	/**
	 * Get the name of the "created at" column.
	 *
	 * @return string
	 */
	public function getCreatedAtColumn()
	{
		return static::CREATED_AT;
	}

	/**
	 * Get the name of the "updated at" column.
	 *
	 * @return string
	 */
	public function getUpdatedAtColumn()
	{
		return static::UPDATED_AT;
	}

	/**
	 * Get a fresh timestamp for the model.
	 *
	 * @return \Carbon\Carbon
	 */
	public function freshTimestamp()
	{
		return new Carbon();
	}

	/**
	 * Get a fresh timestamp for the model.
	 *
	 * @return string
	 */
	public function freshTimestampString()
	{
		return $this->fromDateTime($this->freshTimestamp());
	}

	/**
	 * Get the attributes that should be converted to dates.
	 *
	 * @return array
	 */
	public function getDates()
	{
		$defaults = array(static::CREATED_AT, static::UPDATED_AT);

		return array_merge($this->dates, $defaults);
	}

	/**
	 * Get the format for database stored dates.
	 *
	 * @return string
	 */
	protected function getDateFormat()
	{
		return $this->getConnection()->getQueryGrammar()->getDateFormat();
	}

	/**
	 * Get a new Query for the Model's table.
	 *
	 * @return \Mini\Database\Query
	 */
	public function newQuery()
	{
		$query = $this->newBaseQueryBuilder();

		$builder = $this->newBuilder($query);

		return $builder->setModel($this)->with($this->with);
	}

	/**
	 * Get a new query builder instance for the connection.
	 *
	 * @return \Mini\Database\Query\Builder
	 */
	protected function newBaseQueryBuilder()
	{
		$connection = $this->getConnection();

		$grammar = $connection->getQueryGrammar();

		return new QueryBuilder($connection, $grammar);
	}

	/**
	 * Create a new ORM query builder for the Model.
	 *
	 * @param  \Mini\Database\Query\Builder $query
	 * @return \Mini\Database\Query|static
	 */
	public function newBuilder($query)
	{
		return new Builder($query);
	}

	/**
	 * Create a new ORM Collection instance.
	 *
	 * @param  array  $models
	 * @return \Mini\Database\ORM\Collection
	 */
	public function newCollection(array $models = array())
	{
		return new Collection($models);
	}

	/**
	 * Create a new pivot model instance.
	 *
	 * @param  \Mini\Database\ORM\Model  $parent
	 * @param  array   $attributes
	 * @param  string  $table
	 * @param  bool	$exists
	 * @return \Mini\Database\ORM\Relations\Pivot
	 */
	public function newPivot(Model $parent, array $attributes, $table, $exists)
	{
		return new Pivot($parent, $attributes, $table, $exists);
	}

	/**
	 * Convert the model instance to JSON.
	 *
	 * @param  int  $options
	 * @return string
	 */
	public function toJson($options = 0)
	{
		return json_encode($this->toArray(), $options);
	}

	/**
	 * Convert the object into something JSON serializable.
	 *
	 * @return array
	 */
	public function jsonSerialize()
	{
		return $this->toArray();
	}

	/**
	 * Convert the Model instance to an array.
	 *
	 * @return array
	 */
	public function toArray()
	{
		$attributes = $this->getArrayableAttributes();

		foreach ($this->getDates() as $key) {
			if (isset($attributes[$key])) {
				$attributes[$key] = (string) $this->asDateTime($attributes[$key]);
			}
		}

		foreach ($this->getMutatedAttributes() as $key) {
			if (array_key_exists($key, $attributes)) {
				$attributes[$key] = $this->mutateAttributeForArray($key, $attributes[$key]);
			}
		}

		return array_merge($attributes, $this->relationsToArray());

		return $attributes;
	}

	/**
	 * Get an attribute array of all arrayable attributes.
	 *
	 * @return array
	 */
	protected function getArrayableAttributes()
	{
		return $this->getArrayableItems($this->attributes);
	}

	/**
	 * Get the model's relationships in array form.
	 *
	 * @return array
	 */
	public function relationsToArray()
	{
		$attributes = array();

		foreach ($this->getArrayableRelations() as $key => $value) {
			if (in_array($key, $this->hidden)) continue;

			if ($value instanceof ArrayableInterface) {
				$relation = $value->toArray();
			} else if (is_null($value)) {
				$relation = $value;
			}

			$key = Str::snake($key);

			if (isset($relation) || is_null($value)) {
				$attributes[$key] = $relation;
			}

			unset($relation);
		}

		return $attributes;
	}

	/**
	 * Get an attribute array of all arrayable relations.
	 *
	 * @return array
	 */
	protected function getArrayableRelations()
	{
		return $this->getArrayableItems($this->relations);
	}

	/**
	 * Get an attribute array of all arrayable values.
	 *
	 * @param  array  $values
	 * @return array
	 */
	protected function getArrayableItems(array $values)
	{
		if (count($this->visible) > 0) {
			return array_intersect_key($values, array_flip($this->visible));
		}

		return array_diff_key($values, array_flip($this->hidden));
	}

	/**
	 * Get all of the current attributes on the Model.
	 *
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * Get the hidden attributes for the model.
	 *
	 * @return array
	 */
	public function getHidden()
	{
		return $this->hidden;
	}

	/**
	 * Get the fillable attributes for the model.
	 *
	 * @return array
	 */
	public function getFillable()
	{
		return $this->fillable;
	}

	/**
	 * get the guarded attributes for the model.
	 *
	 * @return array
	 */
	public function getGuarded()
	{
		return $this->guarded;
	}

	/**
	 * Disable all mass assignable restrictions.
	 *
	 * @return void
	 */
	public static function unguard()
	{
		static::$unguarded = true;
	}

	/**
	 * Enable the mass assignment restrictions.
	 *
	 * @return void
	 */
	public static function reguard()
	{
		static::$unguarded = false;
	}

	/**
	 * Determine if the given key is guarded.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function isGuarded($key)
	{
		return (in_array($key, $this->guarded) || ($this->guarded == array('*')));
	}

	/**
	 * Determine if the Model is totally guarded.
	 *
	 * @return bool
	 */
	public function totallyGuarded()
	{
		return ((count($this->fillable) == 0) && ($this->guarded == array('*')));
	}

	/**
	 * Get the mutated attributes for a given instance.
	 *
	 * @return array
	 */
	public function getMutatedAttributes()
	{
		$key = get_class($this);

		return isset(static::$mutatorCache[$key]) ? static::$mutatorCache[$key] : array();
	}

	/**
	 * Determine if the given attribute exists.
	 *
	 * @param  mixed  $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->$offset);
	}

	/**
	 * Get the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->$offset;
	}

	/**
	 * Set the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->$offset = $value;
	}

	/**
	 * Unset the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}

	/**
	 * Dynamically access the user's attributes.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->getAttribute($key);
	}

	/**
	 * Dynamically set an attribute on the user.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set($key, $value)
	{
		$this->setAttribute($key, $value);
	}

	/**
	 * Dynamically check if a value is set on the user.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function __isset($key)
	{
		return isset($this->attributes[$key]);
	}

	/**
	 * Dynamically unset a value on the user.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __unset($key)
	{
		unset($this->attributes[$key]);
	}

	/**
	 * Convert the model to its string representation.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->toJson();
	}

	/**
	 * Handle dynamic method calls into the method.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		$query = $this->newQuery();

		return call_user_func_array(array($query, $method), $parameters);
	}

	/**
	 * Handle dynamic static method calls into the Method.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public static function __callStatic($method, $parameters)
	{
		if (in_array($method, static::$observables)) {
			$callback = array_shift($parameters);

			return static::registerModelEvent($method, $callback);
		}

		$instance = new static();

		return call_user_func_array(array($instance, $method), $parameters);
	}
}

