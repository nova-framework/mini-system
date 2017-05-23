<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Relations\BelongsTo;
use Mini\Database\ORM\Builder;
use Mini\Database\ORM\Collection;
use Mini\Database\ORM\Model;
use Mini\Support\Collection as BaseCollection;


class MorphTo extends BelongsTo
{
	/**
	 * The type of the polymorphic relation.
	 *
	 * @var string
	 */
	protected $morphType;

	/**
	 * The models whose relations are being eager loaded.
	 *
	 * @var \Mini\Database\ORM\Collection
	 */
	protected $models;

	/**
	 * All of the models keyed by ID.
	 *
	 * @var array
	 */
	protected $dictionary = array();


	/**
	 * Create a new belongs to relationship instance.
	 *
	 * @param  \Mini\Database\ORM\Model  $related
	 * @param  \Mini\Database\ORM\Model  $parent
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $type
	 * @param  string  $relation
	 * @return void
	 */
	public function __construct(Model $related, Model $parent, $foreignKey, $otherKey, $type, $relation)
	{
		$this->morphType = $type;

		parent::__construct($related, $parent, $foreignKey, $otherKey, $relation);
	}

	/**
	 * Set the constraints for an eager load of the relation.
	 *
	 * @param  array  $models
	 * @return void
	 */
	public function addEagerConstraints(array $models)
	{
		$this->buildDictionary($this->models = Collection::make($models));
	}

	/**
	 * Build a dictionary with the models.
	 *
	 * @param  \Mini\Database\ORM\Collection  $models
	 * @return void
	 */
	protected function buildDictionary(Collection $models)
	{
		foreach ($models as $model) {
			if ($model->{$this->morphType}) {
				$type = $model->{$this->morphType};

				$key = $model->{$this->foreignKey};

				$this->dictionary[$type][$key][] = $model;
			}
		}
	}

	/**
	 * Match the eagerly loaded results to their parents.
	 *
	 * @param  array   $models
	 * @param  \Mini\Database\ORM\Collection  $results
	 * @param  string  $relation
	 * @return array
	 */
	public function match(array $models, Collection $results, $relation)
	{
		return $models;
	}

	/**
	 * Associate the model instance to the given parent.
	 *
	 * @param  \Mini\Database\ORM\Model  $model
	 * @return \Mini\Database\ORM\Model
	 */
	public function associate(Model $model)
	{
		$this->parent->setAttribute($this->foreignKey, $model->getKey());

		$this->parent->setAttribute($this->morphType, $model->getMorphClass());

		return $this->parent->setRelation($this->relation, $model);
	}

	/**
	 * Get the results of the relationship.
	 *
	 * Called via eager load method of ORM query builder.
	 *
	 * @return mixed
	 */
	public function getEager()
	{
		foreach (array_keys($this->dictionary) as $type) {
			$this->matchToMorphParents($type, $this->getResultsByType($type));
		}

		return $this->models;
	}

	/**
	 * Match the results for a given type to their parents.
	 *
	 * @param  string  $type
	 * @param  \Mini\Database\ORM\Collection  $results
	 * @return void
	 */
	protected function matchToMorphParents($type, Collection $results)
	{
		foreach ($results as $result) {
			$key = $result->getKey();

			if (isset($this->dictionary[$type][$key])) {
				foreach ($this->dictionary[$type][$key] as $model) {
					$model->setRelation($this->relation, $result);
				}
			}
		}
	}

	/**
	 * Get all of the relation results for a type.
	 *
	 * @param  string  $type
	 * @return \Mini\Database\ORM\Collection
	 */
	protected function getResultsByType($type)
	{
		$instance = $this->createModelByType($type);

		$key = $instance->getKeyName();

		$query = $instance->newQuery();

		$query = $this->useWithTrashed($query);

		return $query->whereIn($key, $this->gatherKeysByType($type)->all())->get();
	}

	/**
	 * Gather all of the foreign keys for a given type.
	 *
	 * @param  string  $type
	 * @return array
	 */
	protected function gatherKeysByType($type)
	{
		$foreign = $this->foreignKey;

		return BaseCollection::make($this->dictionary[$type])->map(function($models) use ($foreign)
		{
			return head($models)->{$foreign};

		})->unique();
	}

	/**
	 * Create a new model instance by type.
	 *
	 * @param  string  $type
	 * @return \Mini\Database\ORM\Model
	 */
	public function createModelByType($type)
	{
		return new $type;
	}

	/**
	 * Get the foreign key "type" name.
	 *
	 * @return string
	 */
	public function getMorphType()
	{
		return $this->morphType;
	}

	/**
	 * Get the dictionary used by the relationship.
	 *
	 * @return array
	 */
	public function getDictionary()
	{
		return $this->dictionary;
	}
}
