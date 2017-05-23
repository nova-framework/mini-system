<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Relations\BelongsToMany;
use Mini\Database\ORM\Relations\MorphPivot;
use Mini\Database\ORM\Builder;
use Mini\Database\ORM\Model;


class MorphToMany extends BelongsToMany
{
	/**
	 * The type of the polymorphic relation.
	 *
	 * @var string
	 */
	protected $morphType;

	/**
	 * The class name of the morph type constraint.
	 *
	 * @var string
	 */
	protected $morphClass;

	/**
	 * Indicates if we are connecting the inverse of the relation.
	 *
	 * This primarily affects the morphClass constraint.
	 *
	 * @var bool
	 */
	protected $inverse;


	/**
	 * Create a new has many relationship instance.
	 *
	 * @param  \Mini\Database\ORM\Model  $related
	 * @param  \Mini\Database\ORM\Model  $parent
	 * @param  string  $name
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relationName
	 * @param  bool   $inverse
	 * @return void
	 */
	public function __construct(Model $related, Model $parent, $name, $table, $foreignKey, $otherKey, $relationName = null, $inverse = false)
	{
		$this->inverse = $inverse;

		$this->morphType = $name .'_type';

		$this->morphClass = $inverse ? $related->getMorphClass() : $parent->getMorphClass();

		parent::__construct($related, $parent, $table, $foreignKey, $otherKey, $relationName);
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

		$this->query->where($this->table.'.'.$this->morphType, $this->morphClass);
	}

	/**
	 * Set the where clause for the relation query.
	 *
	 * @return $this
	 */
	protected function setWhere()
	{
		parent::setWhere();

		$this->query->where($this->table.'.'.$this->morphType, $this->morphClass);

		return $this;
	}

	/**
	 * Add the constraints for a relationship count query.
	 *
	 * @param  \Mini\Database\ORM\Builder  $query
	 * @param  \Mini\Database\ORM\Builder  $parent
	 * @return \Mini\Database\ORM\Builder
	 */
	public function getRelationCountQuery(Builder $query, Builder $parent)
	{
		$query = parent::getRelationCountQuery($query, $parent);

		return $query->where($this->table.'.'.$this->morphType, $this->morphClass);
	}

	/**
	 * Create a new pivot attachment record.
	 *
	 * @param  int   $id
	 * @param  bool  $timed
	 * @return array
	 */
	protected function createAttachRecord($id, $timed)
	{
		$record = parent::createAttachRecord($id, $timed);

		return array_add($record, $this->morphType, $this->morphClass);
	}

	/**
	 * Create a new query builder for the pivot table.
	 *
	 * @return \Mini\Database\Query\Builder
	 */
	protected function newPivotQuery()
	{
		$query = parent::newPivotQuery();

		return $query->where($this->morphType, $this->morphClass);
	}

	/**
	 * Create a new pivot model instance.
	 *
	 * @param  array  $attributes
	 * @param  bool   $exists
	 * @return \Mini\Database\ORM\Relations\Pivot
	 */
	public function newPivot(array $attributes = array(), $exists = false)
	{
		$pivot = new MorphPivot($this->parent, $attributes, $this->table, $exists);

		$pivot->setPivotKeys($this->foreignKey, $this->otherKey)
			->setMorphType($this->morphType)
			->setMorphClass($this->morphClass);

		return $pivot;
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
	 * Get the class name of the parent model.
	 *
	 * @return string
	 */
	public function getMorphClass()
	{
		return $this->morphClass;
	}

}
