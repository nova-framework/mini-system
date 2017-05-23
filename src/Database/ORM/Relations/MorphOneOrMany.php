<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Model;
use Mini\Database\ORM\Builder;


abstract class MorphOneOrMany extends HasOneOrMany
{
	/**
	 * The foreign key type for the relationship.
	 *
	 * @var string
	 */
	protected $morphType;

	/**
	 * The class name of the parent model.
	 *
	 * @var string
	 */
	protected $morphClass;

	/**
	 * Create a new has many relationship instance.
	 *
	 * @param  \Mini\Database\ORM\Builder  $query
	 * @param  \Mini\Database\ORM\Model  $parent
	 * @param  string  $type
	 * @param  string  $id
	 * @param  string  $localKey
	 * @return void
	 */
	public function __construct(Builder $query, Model $parent, $type, $id, $localKey)
	{
		$this->morphType = $type;

		$this->morphClass = $parent->getMorphClass();

		parent::__construct($query, $parent, $id, $localKey);
	}

	/**
	 * Set the base constraints on the relation query.
	 *
	 * @return void
	 */
	public function addConstraints()
	{
		if (static::$constraints) {
			parent::addConstraints();

			$this->query->where($this->morphType, $this->morphClass);
		}
	}

	/**
	 * Get the relationship count query.
	 *
	 * @param  \Mini\Database\ORM\Builder  $query
	 * @param  \Mini\Database\ORM\Builder  $parent
	 * @return \Mini\Database\ORM\Builder
	 */
	public function getRelationCountQuery(Builder $query, Builder $parent)
	{
		$query = parent::getRelationCountQuery($query, $parent);

		return $query->where($this->morphType, $this->morphClass);
	}

	/**
	 * Set the constraints for an eager load of the relation.
	 *
	 * @param  array  $models
	 * @return void
	 */
	public function addEagerConstraints(array $models)
	{
		parent::addEagerConstraints($models);

		$this->query->where($this->morphType, $this->morphClass);
	}

	/**
	 * Attach a model instance to the parent model.
	 *
	 * @param  \Mini\Database\ORM\Model  $model
	 * @return \Mini\Database\ORM\Model
	 */
	public function save(Model $model)
	{
		$model->setAttribute($this->getPlainMorphType(), $this->morphClass);

		return parent::save($model);
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
	 * Get the plain morph type name without the table.
	 *
	 * @return string
	 */
	public function getPlainMorphType()
	{
		return last(explode('.', $this->morphType));
	}

	/**
	 * Get the class name of the parent model.
	 *
	 * @return string
	 */
	public function getMorphClass()
	{
		return $this->morphClass;
	}

}
