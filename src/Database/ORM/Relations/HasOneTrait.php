<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Collection;


trait HasOneTrait
{
    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Mini\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function matchOne(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $value = $dictionary[$key];

                $model->setRelation($relation, reset($value));
            }
        }

        return $models;
    }
}
